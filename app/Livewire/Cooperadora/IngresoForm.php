<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopItemIngreso;
use App\Models\CoopRubroIngreso;
use App\Support\Cooperadora\BusquedaEstudianteCooperadora;
use App\Support\Cooperadora\CooperadoraConfig;
use App\Support\Cooperadora\DescuentoHermanos;
use App\Support\Cooperadora\EnvioReciboCooperadora;
use App\Support\Cooperadora\MedioPagoCooperadora;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\Cooperadora\ReciboIngresosGrupo;
use App\Support\Cooperadora\RegistroIngresoService;
use App\Support\Cooperadora\ResponsablesLegajoCooperadora;
use App\Support\Cooperadora\ValidacionFormularioCooperadora;
use App\Support\ProfesorMenuPortal;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class IngresoForm extends Component
{
    public string $modo = 'origen_estudiantes';

    /** @var list<array{idLegajo: string, idRubro: string, idItem: string, importeBruto: string, descuentoPct: string, importe: string, concepto: string}> */
    public array $lineas = [];

    public ?int $idLegajo = null;

    public string $pagadorNombre = '';

    public string $pagadorVinculo = '';

    /** @var array{padre: array{apellido: string, nombre: string, dni: string, email: string}, madre: array{apellido: string, nombre: string, dni: string, email: string}, tutor: array{apellido: string, nombre: string, dni: string, email: string}} */
    public array $pagadorResponsables = [];

    public bool $modalPagadorAbierto = false;

    public string $fecha = '';

    public int|string $idMedioPago = '';

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);
        $tipo = request()->query('tipo');
        $this->modo = in_array($tipo, CoopRubroIngreso::tiposValidos(), true) ? (string) $tipo : 'origen_estudiantes';
        $this->fecha = now()->format('Y-m-d');
        $this->lineas = [$this->nuevaLineaVacia()];
        $this->pagadorResponsables = ResponsablesLegajoCooperadora::estructuraVacia();

        if (! $this->esOrigenEstudiantes()) {
            $this->idLegajo = null;
            $this->pagadorNombre = '';
            $this->pagadorVinculo = '';
        }
    }

    public function hydrate(): void
    {
        $this->sincronizarLineasConLegajoActivo();
    }

    public function esOrigenEstudiantes(): bool
    {
        return $this->modo === 'origen_estudiantes';
    }

    public function agregarLinea(): void
    {
        $linea = $this->nuevaLineaVacia();
        if ($this->idLegajo) {
            $linea['idLegajo'] = (string) $this->idLegajo;
        }
        $this->lineas[] = $linea;
    }

    public function quitarLinea(int $index): void
    {
        if (count($this->lineas) <= 1) {
            return;
        }
        unset($this->lineas[$index]);
        $this->lineas = array_values($this->lineas);
    }

    public function updated($property): void
    {
        if (preg_match('/^lineas\.(\d+)\.(idRubro|idItem|idLegajo)$/', (string) $property, $matches)) {
            $this->recalcularLinea((int) $matches[1], false);
        } elseif (! $this->esOrigenEstudiantes() && preg_match('/^lineas\.(\d+)\.importe$/', (string) $property, $matches)) {
            $this->sincronizarImporteOtrosOrigenes((int) $matches[1]);
        }
    }

    public function seleccionarLegajo(int $id): void
    {
        $legajo = BusquedaEstudianteCooperadora::legajo($id);
        if ($legajo === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el estudiante seleccionado.');

            return;
        }

        $this->idLegajo = $id;
        $this->sincronizarLineasConLegajoActivo();

        foreach (array_keys($this->lineas) as $index) {
            $this->recalcularLinea($index);
        }

        $this->pagadorResponsables = ResponsablesLegajoCooperadora::desdeLegajo($legajo);

        if ($this->pagadorNombre === '' || $this->pagadorVinculo === '') {
            $vinculo = ResponsablesLegajoCooperadora::vinculoPredeterminado($this->pagadorResponsables);
            if ($vinculo !== null) {
                $this->pagadorVinculo = $vinculo;
                if ($this->pagadorNombre === '') {
                    $this->pagadorNombre = ResponsablesLegajoCooperadora::nombrePagador($this->pagadorResponsables, $vinculo);
                }
            }
        }
    }

    #[On('coop-alumno-elegido')]
    public function onCoopAlumnoElegido(int $id): void
    {
        $this->seleccionarLegajo($id);
    }

    public function confirmarAlumnoActual(): void
    {
        if (! $this->idLegajo) {
            return;
        }

        $idActual = $this->idLegajo;
        $tieneItems = false;
        foreach ($this->lineas as $linea) {
            if ((int) ($linea['idLegajo'] ?? 0) !== $idActual) {
                continue;
            }
            if (($linea['idRubro'] ?? '') !== '' || ($linea['idItem'] ?? '') !== '') {
                $tieneItems = true;
                break;
            }
        }

        if (! $tieneItems) {
            $this->dispatch('se-swal-aviso', mensaje: 'Agregue al menos un ítem para este alumno antes de buscar otro.');

            return;
        }

        $this->idLegajo = null;
        $this->lineas[] = $this->nuevaLineaVacia();
    }

    public function abrirModalPagador(): void
    {
        if (! $this->esOrigenEstudiantes()) {
            return;
        }

        $idsLegajo = $this->legajosEnRecibo();
        if ($idsLegajo === []) {
            $this->dispatch('se-swal-aviso', mensaje: 'Seleccione al menos un alumno antes de designar el pagador.');

            return;
        }

        $this->pagadorResponsables = ResponsablesLegajoCooperadora::cargarDatosDesdeLegajo($idsLegajo[0]);
        if ($this->pagadorVinculo === '' || ! in_array($this->pagadorVinculo, ResponsablesLegajoCooperadora::VINCULOS, true)) {
            $this->pagadorVinculo = ResponsablesLegajoCooperadora::vinculoPredeterminado($this->pagadorResponsables) ?? 'padre';
        }
        $this->modalPagadorAbierto = true;
    }

    public function cerrarModalPagador(): void
    {
        $this->modalPagadorAbierto = false;
        $this->resetValidation(['pagadorResponsables', 'pagadorVinculo']);
    }

    public function guardarModalPagador(): void
    {
        if (! $this->esOrigenEstudiantes()) {
            return;
        }

        $idsLegajo = $this->legajosEnRecibo();
        if ($idsLegajo === []) {
            $this->dispatch('se-swal-aviso', mensaje: 'Seleccione al menos un alumno antes de designar el pagador.');

            return;
        }

        $rules = [
            'pagadorVinculo' => ['required', Rule::in(ResponsablesLegajoCooperadora::VINCULOS)],
        ];
        foreach (ResponsablesLegajoCooperadora::VINCULOS as $vinculo) {
            $rules['pagadorResponsables.'.$vinculo.'.apellido'] = ['nullable', 'string', 'max:80'];
            $rules['pagadorResponsables.'.$vinculo.'.nombre'] = ['nullable', 'string', 'max:80'];
            $rules['pagadorResponsables.'.$vinculo.'.dni'] = ['nullable', 'string', 'max:20'];
            $rules['pagadorResponsables.'.$vinculo.'.email'] = ['nullable', 'email', 'max:120'];
        }

        $this->validate($rules, [
            'pagadorVinculo.required' => 'Indique quién es el pagador del recibo.',
            'pagadorVinculo.in' => 'El pagador seleccionado no es válido.',
            'pagadorResponsables.*.email.email' => 'El email no es válido.',
        ]);

        $nombrePagador = ResponsablesLegajoCooperadora::nombrePagador($this->pagadorResponsables, $this->pagadorVinculo);
        if ($nombrePagador === '') {
            $this->addError('pagadorVinculo', 'El pagador elegido debe tener apellido o nombre.');

            return;
        }

        ResponsablesLegajoCooperadora::guardarEnLegajos($this->pagadorResponsables, $idsLegajo);

        $this->pagadorNombre = $nombrePagador;
        $this->modalPagadorAbierto = false;
        $this->resetValidation(['pagadorResponsables', 'pagadorVinculo']);
    }

    public function aplicarImporteBruto(int $index, string $valor): void
    {
        if (! isset($this->lineas[$index])) {
            return;
        }

        $valor = trim(str_replace(',', '.', $valor));
        if ($valor === '') {
            $this->lineas[$index]['importeBruto'] = '';
            $this->recalcularLinea($index, false);

            return;
        }

        $this->lineas[$index]['importeBruto'] = $valor;
        $this->recalcularLinea($index, true, false);
    }

    public function aplicarDescuentoPct(int $index, string $valor): void
    {
        if (! isset($this->lineas[$index]) || ! $this->esOrigenEstudiantes()) {
            return;
        }

        $valor = trim(str_replace(['%', ','], ['', '.'], $valor));
        $pct = $valor === '' ? 0.0 : max(0, min(100, (float) $valor));

        $linea = &$this->lineas[$index];
        $linea['descuentoPct'] = number_format($pct, 2, '.', '');

        $bruto = $this->resolverImporteBrutoLinea($linea, true);
        if ($bruto <= 0) {
            $bruto = $this->resolverImporteBrutoLinea($linea, false);
        }

        $this->aplicarImporteNetoEnLinea($index, $bruto);
    }

    public function recalcularLinea(int $index, bool $desdeImporteBruto = false, bool $resetDescuento = true): void
    {
        if (! isset($this->lineas[$index])) {
            return;
        }

        $linea = &$this->lineas[$index];
        if ($linea['idRubro'] !== '' && $linea['idRubro'] !== '0') {
            $rubro = CoopRubroIngreso::query()->find((int) $linea['idRubro']);
            if ($rubro !== null && (int) $linea['idItem'] > 0) {
                $q = CoopItemIngreso::query()
                    ->where('id_rubro', $rubro->id)
                    ->where('activo', true);
                if ($rubro->es_anual) {
                    $q->where('anio', CooperadoraConfig::anioVigente());
                }
                $item = $q->find((int) $linea['idItem']);
                if ($item === null) {
                    $linea['idItem'] = '';
                }
            }
        }

        $bruto = $this->resolverImporteBrutoLinea($linea, $desdeImporteBruto);
        $bruto = max(0, $bruto);

        if ($this->esOrigenEstudiantes()) {
            $descuentoPct = null;
            if ($resetDescuento) {
                $idLegajoLinea = (int) ($linea['idLegajo'] ?? 0);
                $idRubroLinea = (int) ($linea['idRubro'] ?? 0);
                $descuentoPct = ($idLegajoLinea > 0 && $idRubroLinea > 0)
                    ? DescuentoHermanos::porcentajeParaLinea($idLegajoLinea, $idRubroLinea)
                    : 0.0;
            }
            $this->aplicarImporteNetoEnLinea($index, $bruto, $descuentoPct);
        } else {
            $linea['descuentoPct'] = '0';
            $linea['importeBruto'] = number_format($bruto, 2, '.', '');
            $linea['importe'] = number_format($bruto, 2, '.', '');
        }
    }

    private function aplicarImporteNetoEnLinea(int $index, float $bruto, ?float $descuentoPct = null): void
    {
        if (! isset($this->lineas[$index])) {
            return;
        }

        $linea = &$this->lineas[$index];
        $bruto = max(0, $bruto);
        $linea['importeBruto'] = number_format($bruto, 2, '.', '');

        if ($descuentoPct !== null) {
            $descuentoPct = max(0, min(100, $descuentoPct));
            $linea['descuentoPct'] = number_format($descuentoPct, 2, '.', '');
        }

        $pct = (float) str_replace(',', '.', (string) ($linea['descuentoPct'] ?? '0'));
        $pct = max(0, min(100, $pct));
        $linea['importe'] = number_format(DescuentoHermanos::importeConDescuento($bruto, $pct), 2, '.', '');
    }

    /**
     * @param  array{idLegajo: string, idRubro: string, idItem: string, importeBruto: string, descuentoPct: string, importe: string, concepto: string}  $linea
     */
    private function resolverImporteBrutoLinea(array $linea, bool $desdeImporteBruto): float
    {
        if ($desdeImporteBruto && $linea['importeBruto'] !== '') {
            return (float) str_replace(',', '.', $linea['importeBruto']);
        }

        if ($linea['idItem'] !== '' && $linea['idItem'] !== '0') {
            $item = CoopItemIngreso::query()->find((int) $linea['idItem']);

            return (float) ($item?->precio ?? 0);
        }

        if ($linea['importeBruto'] !== '') {
            return (float) str_replace(',', '.', $linea['importeBruto']);
        }

        return 0.0;
    }

    private function sincronizarImporteOtrosOrigenes(int $index): void
    {
        if (! isset($this->lineas[$index])) {
            return;
        }

        $importe = (float) str_replace(',', '.', (string) ($this->lineas[$index]['importe'] ?? '0'));
        $importe = max(0, $importe);
        $formateado = number_format($importe, 2, '.', '');

        $this->lineas[$index]['importe'] = $formateado;
        $this->lineas[$index]['importeBruto'] = $formateado;
        $this->lineas[$index]['descuentoPct'] = '0';
    }

    public function guardar(): void
    {
        abort_unless(PermisosCooperadora::puedeIngresos(), 403);

        $key = 'coop:ingreso:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 15)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $rubros = $this->rubrosActivos()->keyBy('id');
        $itemsMap = $this->itemsActivosPorRubro();

        $rules = [
            'fecha' => ['required', 'date'],
            'pagadorNombre' => ['required', 'string', 'max:200'],
            'idMedioPago' => ['required', 'integer', Rule::in(MedioPagoCooperadora::idsActivos())],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.idRubro' => ['required', 'integer'],
            'lineas.*.idItem' => ['required', 'integer'],
            'lineas.*.importe' => ['required', 'numeric', 'min:0.01'],
            'lineas.*.concepto' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->esOrigenEstudiantes()) {
            $rules['pagadorVinculo'] = ['required', Rule::in(ResponsablesLegajoCooperadora::VINCULOS)];
        }

        foreach ($this->lineas as $i => $linea) {
            $rubroId = (int) ($linea['idRubro'] ?? 0);
            $rubro = $rubros->get($rubroId);
            if ($rubro === null) {
                $rules['lineas.'.$i.'.idRubro'][] = Rule::in([]);
            } else {
                $rules['lineas.'.$i.'.idRubro'][] = Rule::in([$rubro->id]);
                $itemsIds = ($itemsMap->get($rubro->id) ?? collect())->pluck('id')->map(fn ($id) => (int) $id)->all();
                $rules['lineas.'.$i.'.idItem'][] = Rule::in($itemsIds);
                if ($rubro->requiereAlumno() && $this->esOrigenEstudiantes()) {
                    $rules['lineas.'.$i.'.idLegajo'] = ['required', 'integer', 'min:1'];
                }
            }
        }

        $validated = ValidacionFormularioCooperadora::validar($this, $rules, [
            'lineas.*.idLegajo' => 'Alumno',
            'pagadorNombre' => $this->esOrigenEstudiantes() ? 'Señor / pagador' : 'Pagador / entidad',
            'pagadorVinculo' => 'Pagador del recibo',
            'fecha' => 'Fecha',
            'idMedioPago' => 'Medio de pago',
            'lineas.*.idRubro' => 'Rubro',
            'lineas.*.idItem' => 'Ítem',
            'lineas.*.importe' => 'Importe a cobrar',
        ]);

        $lineasRegistro = [];
        foreach ($validated['lineas'] as $i => $linea) {
            $rubro = $rubros->get((int) $linea['idRubro']);
            abort_unless($rubro !== null, 422);

            $idLegajoLinea = null;
            $idMatriculaLinea = null;
            if ($this->esOrigenEstudiantes()) {
                $idLegajoLinea = (int) ($this->lineas[$i]['idLegajo'] ?? 0);
                abort_unless($idLegajoLinea > 0, 422);
                abort_unless(BusquedaEstudianteCooperadora::legajo($idLegajoLinea) !== null, 422);
                $idMatriculaLinea = BusquedaEstudianteCooperadora::matriculaActiva($idLegajoLinea)?->id;
            }

            $lineasRegistro[] = [
                'tipo' => $rubro->tipo,
                'id_rubro' => (int) $linea['idRubro'],
                'id_item' => (int) $linea['idItem'],
                'id_legajo' => $idLegajoLinea,
                'id_matricula' => $idMatriculaLinea,
                'concepto' => $linea['concepto'] ?? null,
                'importe_bruto' => (float) ($this->lineas[$i]['importeBruto'] ?? 0),
                'descuento_pct' => (float) ($this->lineas[$i]['descuentoPct'] ?? 0),
                'importe' => (float) $linea['importe'],
            ];
        }

        $resultado = RegistroIngresoService::registrarLote($lineasRegistro, [
            'pagador_nombre' => $validated['pagadorNombre'],
            'pagador_vinculo' => $this->esOrigenEstudiantes() ? ($this->pagadorVinculo ?: null) : null,
            'pagador_email' => $this->esOrigenEstudiantes()
                ? ResponsablesLegajoCooperadora::emailPagador($this->pagadorResponsables, $this->pagadorVinculo)
                : null,
            'fecha' => $validated['fecha'],
            'id_medio_pago' => (int) $validated['idMedioPago'],
        ]);

        $lider = $resultado['lider'];
        $idRef = ReciboIngresosGrupo::idReferenciaPdf($lider);
        $emailEnviado = EnvioReciboCooperadora::enviar($idRef);
        $ref = OpaqueRouteToken::forCoopRecibo($idRef);
        $urlPdf = route('cooperadora.recibo.pdf', ['ref' => $ref]);

        $cantidad = count($lineasRegistro);
        $mensaje = $cantidad > 1
            ? 'Ingresos registrados. Recibo Nº '.$lider->recibo_numero.' ('.$cantidad.' ítems)'
            : 'Ingreso registrado. Recibo Nº '.$lider->recibo_numero;

        if ($this->esOrigenEstudiantes()) {
            if ($emailEnviado && EnvioReciboCooperadora::RECIBO_EMAIL_SIMULADO) {
                $mensaje .= '. Email al pagador registrado (modo simulado, no se envió correo real).';
            } elseif (! $emailEnviado && ResponsablesLegajoCooperadora::emailPagador($this->pagadorResponsables, $this->pagadorVinculo) === '') {
                $mensaje .= '. Sin email del pagador: podrá reenviarlo desde el listado cuando lo cargue.';
            }
        }

        session()->flash('success', $mensaje);
        $this->dispatch('cooperadora-abrir-pdf', url: $urlPdf);
        $this->redirectRoute('cooperadora.ingresos', navigate: true);
    }

    private function sincronizarLineasConLegajoActivo(): void
    {
        if (! $this->esOrigenEstudiantes() || ! $this->idLegajo) {
            return;
        }

        $id = (string) $this->idLegajo;
        foreach (array_keys($this->lineas) as $index) {
            if (($this->lineas[$index]['idLegajo'] ?? '') === '') {
                $this->lineas[$index]['idLegajo'] = $id;
            }
        }
    }

    /** @return list<int> */
    private function legajosEnRecibo(): array
    {
        $ids = [];
        foreach ($this->lineas as $linea) {
            $id = (int) ($linea['idLegajo'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array{idLegajo: string, idRubro: string, idItem: string, importeBruto: string, descuentoPct: string, importe: string, concepto: string}
     */
    private function nuevaLineaVacia(): array
    {
        return [
            'idLegajo' => '',
            'idRubro' => '',
            'idItem' => '',
            'importeBruto' => '',
            'descuentoPct' => '0',
            'importe' => '',
            'concepto' => '',
        ];
    }

    /** @return Collection<int, CoopRubroIngreso> */
    private function rubrosActivos(): Collection
    {
        return CoopRubroIngreso::query()
            ->where('activo', true)
            ->where('tipo', $this->modo)
            ->orderBy('orden')
            ->get();
    }

    /** @return Collection<int, Collection<int, CoopItemIngreso>> */
    private function itemsActivosPorRubro(): Collection
    {
        $anio = CooperadoraConfig::anioVigente();
        $rubros = $this->rubrosActivos();
        $items = CoopItemIngreso::query()
            ->where('activo', true)
            ->whereIn('id_rubro', $rubros->pluck('id'))
            ->orderBy('orden')
            ->get();

        return $items->groupBy('id_rubro')->map(function (Collection $grupo, int $idRubro) use ($rubros, $anio) {
            $rubro = $rubros->firstWhere('id', $idRubro);
            if ($rubro?->es_anual) {
                return $grupo->where('anio', $anio)->values();
            }

            return $grupo->values();
        });
    }

    public function render()
    {
        $rubros = $this->rubrosActivos();
        $itemsPorRubro = $this->itemsActivosPorRubro();

        $legajoSel = $this->idLegajo ? BusquedaEstudianteCooperadora::legajo($this->idLegajo) : null;
        $matricula = $this->idLegajo ? BusquedaEstudianteCooperadora::matriculaActiva($this->idLegajo) : null;

        $etiquetasLegajo = [];
        $alumnosEnRecibo = [];
        foreach ($this->lineas as $linea) {
            $lid = (int) ($linea['idLegajo'] ?? 0);
            if ($lid <= 0 || isset($etiquetasLegajo[$lid])) {
                continue;
            }
            $leg = BusquedaEstudianteCooperadora::legajo($lid);
            if ($leg === null) {
                continue;
            }
            $etiquetasLegajo[$lid] = $leg->apellido.', '.$leg->nombre;
            $mat = BusquedaEstudianteCooperadora::matriculaActiva($lid);
            $alumnosEnRecibo[] = [
                'id' => $lid,
                'nombre' => $etiquetasLegajo[$lid],
                'curso' => BusquedaEstudianteCooperadora::etiquetaCurso($mat),
            ];
        }

        $totalImporte = 0.0;
        foreach ($this->lineas as $linea) {
            $totalImporte += (float) ($linea['importe'] !== '' ? $linea['importe'] : 0);
        }

        $titulo = $this->esOrigenEstudiantes()
            ? 'Cooperadora — Ingreso (origen estudiantes)'
            : 'Cooperadora — Ingreso (otros orígenes)';

        return view('livewire.cooperadora.ingreso-form', [
            'rubros' => $rubros,
            'itemsPorRubro' => $itemsPorRubro,
            'legajoSel' => $legajoSel,
            'matricula' => $matricula,
            'etiquetaCurso' => BusquedaEstudianteCooperadora::etiquetaCurso($matricula),
            'etiquetasLegajo' => $etiquetasLegajo,
            'alumnosEnRecibo' => $alumnosEnRecibo,
            'mediosPago' => MedioPagoCooperadora::paraSelector(),
            'totalImporte' => round($totalImporte, 2),
        ])->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => $titulo]);
    }
}
