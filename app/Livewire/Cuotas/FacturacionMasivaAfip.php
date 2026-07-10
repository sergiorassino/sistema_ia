<?php

namespace App\Livewire\Cuotas;

use App\Models\Cuota;
use App\Models\Familia;
use App\Models\Legajo;
use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Support\Cuotas\ConsultaAfipComprobanteService;
use App\Support\Cuotas\CuotasPlantillaCatalog;
use App\Support\Cuotas\FacturacionAfipComun;
use App\Support\Cuotas\FacturacionMasivaAfipService;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\Cuotas\GestionAranceles;
use App\Support\Database\PersistenciaColumnas;
use App\Support\DniInput;
use App\Support\PermisosCuotas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

/**
 * Facturación masiva AFIP por devengamiento (manual).
 */
class FacturacionMasivaAfip extends Component
{
    /** 1 = cuotas + tipo, 2 = alumnos + vista previa, 3 = resultado */
    public int $paso = 1;

    public string $tipoOperacion = '';

    /** @var list<string> */
    public array $cursosSeleccionados = [];

    public string $filtroCursos = '';

    public string $buscarAlumno = '';

    /** @var list<array{id: int, label: string}> */
    public array $alumnosSeleccionados = [];

    /** @var list<string> */
    public array $cuotasSeleccionadas = [];

    /** @var array<string, mixed> */
    public array $vistaPrevia = [];

    /** @var array<string, mixed> */
    public array $resultado = [];

    public bool $modalRespAdmiAbierto = false;

    public ?int $respAdmiIdLegajo = null;

    public ?int $respAdmiIdFamilia = null;

    public string $respAdmiApellido = '';

    public string $respAdmiNombre = '';

    public string $respAdmiDni = '';

    public string $respAdmiEmail = '';

    public string $respAdmiVinculo = '';

    /** @var array<string, array{nombre: string, dni: string}> */
    public array $respAdmiVinculos = [];

    public string $respAdmiEstudianteEtiqueta = '';

    /** True cuando se acaba de crear la familia al abrir el modal (sin familia previa). */
    public bool $respAdmiFamiliaNueva = false;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeFacturacionMasivaAfip(), 403);
    }

    public function continuarAAlumnos(): void
    {
        $this->validarTipoOperacion();
        $this->validarCuotasSeleccionadas();
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->paso = 2;
        $this->cursosSeleccionados = [];
        $this->alumnosSeleccionados = [];
        $this->vistaPrevia = [];
        $this->resultado = [];
        $this->resetErrorBag();
    }

    public function nuevaOperacion(): void
    {
        $this->paso = 1;
        $this->tipoOperacion = '';
        $this->cuotasSeleccionadas = [];
        $this->cursosSeleccionados = [];
        $this->alumnosSeleccionados = [];
        $this->filtroCursos = '';
        $this->buscarAlumno = '';
        $this->vistaPrevia = [];
        $this->resultado = [];
        $this->resetErrorBag();
    }

    public function volverACuotas(): void
    {
        $this->paso = 1;
        $this->cursosSeleccionados = [];
        $this->alumnosSeleccionados = [];
        $this->vistaPrevia = [];
        $this->resultado = [];
        $this->resetErrorBag();
    }

    public function updatedCuotasSeleccionadas(): void
    {
        if ($this->tipoOperacion === '') {
            $this->cuotasSeleccionadas = [];
        }

        $this->vistaPrevia = [];
        $this->resultado = [];
    }

    public function updatedTipoOperacion(): void
    {
        $this->vistaPrevia = [];
        $this->resultado = [];
    }

    public function updatedCursosSeleccionados(): void
    {
        $this->invalidarVistaPreviaPorCambioAlcance();
    }

    /**
     * Si ya había vista previa, la oculta al cambiar cursos o alumnos individuales.
     */
    private function invalidarVistaPreviaPorCambioAlcance(): void
    {
        if ($this->vistaPrevia === [] && $this->resultado === []) {
            return;
        }

        $this->vistaPrevia = [];
        $this->resultado = [];
    }

    public function armarVistaPrevia(): void
    {
        abort_unless(PermisosCuotas::puedeFacturacionMasivaAfip(), 403);

        $this->validarTipoOperacion();
        $this->validarAlcanceEstudiantes();
        $this->validarCuotasSeleccionadas();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $cursoIds = $this->idsCursosValidados();
        $cuotaIds = $this->idsCuotasValidadas();
        $this->vistaPrevia = FacturacionMasivaAfipService::vistaPrevia(
            $cursoIds,
            $cuotaIds,
            $this->idsLegajosValidados(),
            $this->tipoOperacion,
        );
        $this->resultado = [];
    }

    public function facturar(): void
    {
        abort_unless(PermisosCuotas::puedeFacturacionMasivaAfip(), 403);

        $rateKey = 'cuotas:facturacion-masiva-afip:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 120);

        $this->validarAlcanceEstudiantes();
        $this->validarCuotasSeleccionadas();
        $this->validarTipoOperacion();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if (($this->vistaPrevia['total'] ?? 0) < 1) {
            $mensaje = $this->esNotaCredito()
                ? 'No hay estudiantes para anular con nota de crédito. Revise la vista previa.'
                : 'No hay estudiantes para facturar. Revise la vista previa.';
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        }

        $cursoIds = $this->idsCursosValidados();
        $cuotaIds = $this->idsCuotasValidadas();
        $this->resultado = FacturacionMasivaAfipService::procesarEnCursos(
            $cursoIds,
            $cuotaIds,
            $this->idsLegajosValidados(),
            $this->tipoOperacion,
        );
        $this->paso = 3;
        $this->vistaPrevia = [];
    }

    public function abrirModalRespAdmi(int $idLegajo): void
    {
        abort_unless(PermisosCuotas::puedeFacturacionMasivaAfip(), 403);

        if (! $this->legajoEnAlcance($idLegajo)) {
            $this->dispatch('se-swal-error', mensaje: 'El estudiante no pertenece al alcance seleccionado.');

            return;
        }

        $legajo = GestionAranceles::legajoParaFacturacionAfip($idLegajo);
        if ($legajo === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el estudiante.');

            return;
        }

        $asegurado = ['creada' => false, 'error' => '', 'vinculo' => ''];
        $cargaManualSinFamilia = false;

        if (! LegajoFamilia::tieneFamiliaAsignada($legajo)) {
            $vinculoDisponible = FacturacionAfipComun::primerVinculoResponsableEconomico($legajo);
            if ($vinculoDisponible === null) {
                // Sin padre/madre/tutor: abrir modal para carga manual (no bloquear).
                $cargaManualSinFamilia = true;
            } else {
                $asegurado = FacturacionAfipComun::asegurarFamiliaDesdeVinculosLegajo($legajo);
                if ($asegurado['error'] !== '') {
                    // Si falló la creación automática, igual permitir carga manual.
                    $cargaManualSinFamilia = true;
                } else {
                    $legajo = GestionAranceles::legajoParaFacturacionAfip($idLegajo);
                    if ($legajo === null) {
                        $this->dispatch('se-swal-error', mensaje: 'No se encontró el estudiante.');

                        return;
                    }
                }
            }
        }

        if ($cargaManualSinFamilia) {
            $this->respAdmiIdLegajo = $idLegajo;
            $this->respAdmiIdFamilia = null;
            $this->respAdmiFamiliaNueva = true;
            $this->respAdmiApellido = trim((string) ($legajo->apellido ?? ''));
            $this->respAdmiNombre = '';
            $this->respAdmiDni = '';
            $this->respAdmiEmail = '';
            $this->respAdmiVinculos = FacturacionAfipComun::vinculosResponsableEconomico($legajo);
            $this->respAdmiVinculo = '';
            $this->respAdmiEstudianteEtiqueta = trim(($legajo->apellido ?? '').', '.($legajo->nombre ?? ''));
            $this->resetValidation(['respAdmiApellido', 'respAdmiNombre', 'respAdmiDni', 'respAdmiEmail', 'respAdmiVinculo']);
            $this->modalRespAdmiAbierto = true;

            return;
        }

        $destinatario = FacturacionAfipComun::destinatarioFacturaDesdeLegajo($legajo);
        $idFamilia = (int) $destinatario['idFamilia'];
        if ($idFamilia <= 0 || $idFamilia === LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR) {
            $this->respAdmiIdLegajo = $idLegajo;
            $this->respAdmiIdFamilia = null;
            $this->respAdmiFamiliaNueva = true;
            $this->respAdmiApellido = trim((string) ($legajo->apellido ?? ''));
            $this->respAdmiNombre = '';
            $this->respAdmiDni = '';
            $this->respAdmiEmail = '';
            $this->respAdmiVinculos = FacturacionAfipComun::vinculosResponsableEconomico($legajo);
            $this->respAdmiVinculo = '';
            $this->respAdmiEstudianteEtiqueta = trim(($legajo->apellido ?? '').', '.($legajo->nombre ?? ''));
            $this->resetValidation(['respAdmiApellido', 'respAdmiNombre', 'respAdmiDni', 'respAdmiEmail', 'respAdmiVinculo']);
            $this->modalRespAdmiAbierto = true;

            return;
        }

        $this->respAdmiIdLegajo = $idLegajo;
        $this->respAdmiIdFamilia = $idFamilia;
        $this->respAdmiFamiliaNueva = (bool) ($asegurado['creada'] ?? false);
        $this->respAdmiApellido = trim((string) ($legajo->familia?->apellido ?? ''));
        $this->respAdmiNombre = (string) $destinatario['responsable'];
        $this->respAdmiDni = (string) $destinatario['dniResp'];
        // Solo email de familias.email (o del vínculo email* del legajo); nunca teléfono u otros campos.
        $this->respAdmiEmail = FacturacionAfipComun::emailFehaciente($legajo->familia?->email ?? '');
        $this->respAdmiVinculos = FacturacionAfipComun::vinculosResponsableEconomico($legajo);
        $vinculoPreseleccionado = (string) ($asegurado['vinculo'] ?? '');
        if ($vinculoPreseleccionado === '' && $this->respAdmiVinculos !== []) {
            $primer = FacturacionAfipComun::primerVinculoResponsableEconomico($legajo);
            $vinculoPreseleccionado = (string) ($primer['vinculo'] ?? '');
        }
        $this->respAdmiVinculo = in_array($vinculoPreseleccionado, ['padre', 'madre', 'tutor'], true)
            ? $vinculoPreseleccionado
            : '';
        // Si el apellido de familia está vacío, completar con apellido y nombre del vínculo elegido.
        if ($this->respAdmiApellido === '' && $this->respAdmiVinculo !== '') {
            $filaPre = $this->respAdmiVinculos[$this->respAdmiVinculo] ?? [];
            $this->respAdmiApellido = FacturacionAfipComun::apellidoFamiliaDesdeVinculo($filaPre);
        }
        $this->respAdmiEstudianteEtiqueta = trim(($legajo->apellido ?? '').', '.($legajo->nombre ?? ''));
        $this->resetValidation(['respAdmiApellido', 'respAdmiNombre', 'respAdmiDni', 'respAdmiEmail', 'respAdmiVinculo']);
        $this->modalRespAdmiAbierto = true;
    }

    public function cerrarModalRespAdmi(): void
    {
        $this->modalRespAdmiAbierto = false;
        $this->respAdmiIdLegajo = null;
        $this->respAdmiIdFamilia = null;
        $this->respAdmiApellido = '';
        $this->respAdmiNombre = '';
        $this->respAdmiDni = '';
        $this->respAdmiEmail = '';
        $this->respAdmiVinculo = '';
        $this->respAdmiVinculos = [];
        $this->respAdmiEstudianteEtiqueta = '';
        $this->respAdmiFamiliaNueva = false;
        $this->resetValidation(['respAdmiApellido', 'respAdmiNombre', 'respAdmiDni', 'respAdmiEmail', 'respAdmiVinculo']);
    }

    public function seleccionarRespAdmiVinculo(string $vinculo): void
    {
        if (! in_array($vinculo, ['padre', 'madre', 'tutor'], true)) {
            return;
        }

        $fila = $this->respAdmiVinculos[$vinculo] ?? ['nombre' => '', 'dni' => '', 'email' => '', 'apellido' => '', 'nombrePila' => ''];
        $this->respAdmiVinculo = $vinculo;
        // Solo completar apellido de familia si está vacío (apellido y nombre del vínculo).
        if (trim($this->respAdmiApellido) === '') {
            $this->respAdmiApellido = FacturacionAfipComun::apellidoFamiliaDesdeVinculo($fila);
        }
        $this->respAdmiNombre = trim((string) ($fila['nombre'] ?? ''));
        $this->respAdmiDni = (string) ($fila['dni'] ?? '');
        $this->respAdmiEmail = FacturacionAfipComun::emailFehaciente($fila['email'] ?? '');
        $this->resetValidation(['respAdmiApellido', 'respAdmiNombre', 'respAdmiDni', 'respAdmiEmail']);
    }

    public function guardarRespAdmi(): void
    {
        abort_unless(PermisosCuotas::puedeFacturacionMasivaAfip(), 403);

        $idLegajo = (int) ($this->respAdmiIdLegajo ?? 0);
        $idFamilia = (int) ($this->respAdmiIdFamilia ?? 0);
        $crearFamilia = $this->respAdmiFamiliaNueva && ($idFamilia < 1 || $idFamilia === LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR);

        if ($idLegajo < 1 || ! $this->legajoEnAlcance($idLegajo)) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo validar el estudiante seleccionado.');

            return;
        }

        if (! $crearFamilia && $idFamilia < 1) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo validar el estudiante seleccionado.');

            return;
        }

        $rateKey = 'cuotas:facturacion-afip:resp-admi:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $this->respAdmiDni = DniInput::digitsOnly($this->respAdmiDni);
        $this->respAdmiEmail = FacturacionAfipComun::emailFehaciente($this->respAdmiEmail);
        $this->validate([
            'respAdmiApellido' => ['required', 'string', 'max:50'],
            'respAdmiNombre' => ['required', 'string', 'max:50'],
            'respAdmiDni' => ['required', 'digits_between:7,11'],
            'respAdmiEmail' => ['nullable', 'email', 'max:100'],
        ], [
            'respAdmiApellido.required' => 'Indique el apellido de la familia.',
            'respAdmiApellido.max' => 'El apellido no puede superar los 50 caracteres.',
            'respAdmiNombre.required' => 'Indique el nombre del responsable económico.',
            'respAdmiNombre.max' => 'El responsable no puede superar los 50 caracteres.',
            'respAdmiDni.required' => 'Indique el DNI del responsable económico.',
            'respAdmiDni.digits_between' => 'El DNI del responsable debe tener entre 7 y 11 dígitos.',
            'respAdmiEmail.email' => 'El email no es válido.',
            'respAdmiEmail.max' => 'El email no puede superar los 100 caracteres.',
        ]);

        $legajo = GestionAranceles::legajoParaFacturacionAfip($idLegajo);
        if ($legajo === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el estudiante.');

            return;
        }

        $apellido = trim($this->respAdmiApellido);
        $responsable = trim($this->respAdmiNombre);
        $email = $this->respAdmiEmail;
        $payload = [
            'apellido' => $apellido,
            'responsable' => $responsable,
            'dniResp' => $this->respAdmiDni,
            'email' => $email !== '' ? $email : '',
        ];

        $preparado = PersistenciaColumnas::prepararPayload('familias', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $this->dispatch(
                'se-swal-error',
                mensaje: PersistenciaColumnas::mensajeColumnasInexistentes('familias', $preparado['columnas_con_valor_sin_columna']),
            );

            return;
        }

        try {
            if ($crearFamilia) {
                if (LegajoFamilia::tieneFamiliaAsignada($legajo)) {
                    $this->dispatch('se-swal-error', mensaje: 'La familia del estudiante cambió. Cierre el modal y vuelva a intentar.');

                    return;
                }

                DB::transaction(function () use ($idLegajo, $preparado): void {
                    $familia = Familia::query()->create($preparado['payload']);
                    Legajo::query()->whereKey($idLegajo)->update(['idFamilias' => $familia->id]);
                });
            } else {
                if ((int) ($legajo->idFamilias ?? 0) !== $idFamilia) {
                    $this->dispatch('se-swal-error', mensaje: 'La familia del estudiante cambió. Cierre el modal y vuelva a intentar.');

                    return;
                }

                $familia = Familia::query()->find($idFamilia);
                if ($familia === null) {
                    $this->dispatch('se-swal-error', mensaje: 'No se encontró la familia del estudiante.');

                    return;
                }

                $familia->update($preparado['payload']);
            }
        } catch (QueryException $e) {
            $this->dispatch(
                'se-swal-error',
                mensaje: PersistenciaColumnas::mensajeDesdeQueryException($e)
                    ?? 'No se pudo guardar el responsable económico. Intente nuevamente.',
            );

            return;
        } catch (Throwable) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo guardar el responsable económico. Intente nuevamente.');

            return;
        }

        $this->cerrarModalRespAdmi();

        if ($this->vistaPrevia !== []) {
            $this->armarVistaPrevia();
        }

        $this->dispatch('se-swal-exito', mensaje: $crearFamilia
            ? 'Familia creada y responsable económico guardado.'
            : 'Responsable económico actualizado.');
    }

    public function quitarAlumno(int $idLegajo): void
    {
        $this->alumnosSeleccionados = array_values(array_filter(
            $this->alumnosSeleccionados,
            fn (array $alumno) => (int) ($alumno['id'] ?? 0) !== $idLegajo,
        ));
        $this->invalidarVistaPreviaPorCambioAlcance();
    }

    public function agregarAlumno(int $idLegajo): void
    {
        if ($idLegajo < 1) {
            return;
        }

        foreach ($this->alumnosSeleccionados as $alumno) {
            if ((int) ($alumno['id'] ?? 0) === $idLegajo) {
                return;
            }
        }

        $fila = GeneracionMasivaCuotasConsulta::filaAlumnoDesdeLegajo($idLegajo);
        if ($fila === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el estudiante.');

            return;
        }

        $label = GeneracionMasivaCuotasConsulta::etiquetaAlumno($fila);
        $curso = trim((string) ($fila->curso_nombre ?? ''));
        if ($curso !== '') {
            $label .= ' · '.$curso;
        }

        $this->alumnosSeleccionados[] = [
            'id' => $idLegajo,
            'label' => $label,
        ];
        $this->resetErrorBag('alcanceEstudiantes');
        $this->invalidarVistaPreviaPorCambioAlcance();
    }

    public function quitarCurso(int $idCurso): void
    {
        $key = (string) $idCurso;
        $this->cursosSeleccionados = array_values(array_filter(
            $this->cursosSeleccionados,
            fn (string $id) => $id !== $key,
        ));
        $this->invalidarVistaPreviaPorCambioAlcance();
    }

    public function seleccionarTodosCursos(): void
    {
        $this->cursosSeleccionados = $this->idsCursosPermitidosComoString()->keys()->all();
        $this->resetErrorBag('cursosSeleccionados');
        $this->invalidarVistaPreviaPorCambioAlcance();
    }

    public function quitarTodosCursos(): void
    {
        $this->cursosSeleccionados = [];
        $this->invalidarVistaPreviaPorCambioAlcance();
    }

    public function marcarNivel(int $idNivel): void
    {
        $ids = $this->idsCursosDelNivel($idNivel);
        $this->cursosSeleccionados = array_values(array_unique(array_merge(
            $this->cursosSeleccionados,
            $ids,
        )));
        $this->resetErrorBag('cursosSeleccionados');
        $this->invalidarVistaPreviaPorCambioAlcance();
    }

    public function quitarNivel(int $idNivel): void
    {
        $quitar = array_flip($this->idsCursosDelNivel($idNivel));
        $this->cursosSeleccionados = array_values(array_filter(
            $this->cursosSeleccionados,
            fn (string $id) => ! isset($quitar[$id]),
        ));
        $this->invalidarVistaPreviaPorCambioAlcance();
    }

    public function seleccionarTodasCuotas(): void
    {
        if ($this->tipoOperacion === '') {
            return;
        }

        $this->cuotasSeleccionadas = $this->idsCuotasPermitidasComoString()->keys()->all();
        $this->resetErrorBag('cuotasSeleccionadas');
    }

    public function quitarTodasCuotas(): void
    {
        $this->cuotasSeleccionadas = [];
    }

    private function esNotaCredito(): bool
    {
        return $this->tipoOperacion === ConsultaAfipComprobanteService::TIPO_NOTA_CREDITO;
    }

    private function validarTipoOperacion(): void
    {
        $this->validate([
            'tipoOperacion' => [
                'required',
                Rule::in([
                    ConsultaAfipComprobanteService::TIPO_FACTURA,
                    ConsultaAfipComprobanteService::TIPO_NOTA_CREDITO,
                ]),
            ],
        ], [
            'tipoOperacion.required' => 'Seleccione si desea emitir factura o nota de crédito.',
        ]);
    }

    /** @return \Illuminate\Support\Collection<string, int> */
    private function idsCursosPermitidosComoString(): \Illuminate\Support\Collection
    {
        return GeneracionMasivaCuotasConsulta::cursosEnContexto()
            ->pluck('Id')
            ->mapWithKeys(fn ($id) => [(string) (int) $id => (int) $id]);
    }

    /** @return list<string> */
    private function idsCursosDelNivel(int $idNivel): array
    {
        if ($idNivel < 1) {
            return [];
        }

        return GeneracionMasivaCuotasConsulta::cursosEnContexto()
            ->filter(fn ($c) => (int) ($c->idNivel ?? 0) === $idNivel)
            ->map(fn ($c) => (string) (int) $c->Id)
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function idsCursosValidados(): array
    {
        $permitidos = $this->idsCursosPermitidosComoString();

        return collect($this->cursosSeleccionados)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $permitidos->has((string) $id))
            ->unique()
            ->values()
            ->all();
    }

    /** @return \Illuminate\Support\Collection<string, int> */
    private function idsCuotasPermitidasComoString(): \Illuminate\Support\Collection
    {
        return Cuota::query()
            ->where('idTerlec', CuotasPlantillaCatalog::idTerlecActivo())
            ->orderBy('orden')
            ->orderBy('id')
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(string) (int) $id => (int) $id]);
    }

    /** @return list<int> */
    private function idsCuotasValidadas(): array
    {
        $permitidos = $this->idsCuotasPermitidasComoString();

        return collect($this->cuotasSeleccionadas)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $permitidos->has((string) $id))
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function idsLegajosValidados(): array
    {
        return collect($this->alumnosSeleccionados)
            ->map(fn (array $alumno) => (int) ($alumno['id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function legajoEnAlcance(int $idLegajo): bool
    {
        if ($idLegajo < 1) {
            return false;
        }

        if (in_array($idLegajo, $this->idsLegajosValidados(), true)) {
            return true;
        }

        $cursoIds = $this->idsCursosValidados();
        if ($cursoIds === []) {
            return false;
        }

        foreach (GeneracionMasivaCuotasConsulta::alumnosRegularesPorCursos($cursoIds) as $alumno) {
            if ((int) ($alumno->id_legajo ?? 0) === $idLegajo) {
                return true;
            }
        }

        return false;
    }

    private function validarAlcanceEstudiantes(): void
    {
        $this->validate([
            'cursosSeleccionados' => ['array'],
            'cursosSeleccionados.*' => ['integer', 'min:1'],
            'alumnosSeleccionados' => ['array'],
            'alumnosSeleccionados.*.id' => ['integer', 'min:1'],
        ]);

        $permitidos = $this->idsCursosPermitidosComoString();

        $this->cursosSeleccionados = collect($this->cursosSeleccionados)
            ->map(fn ($id) => (string) (int) $id)
            ->filter(fn (string $id) => $permitidos->has($id))
            ->unique()
            ->values()
            ->all();

        $this->alumnosSeleccionados = collect($this->alumnosSeleccionados)
            ->filter(fn ($alumno) => is_array($alumno) && (int) ($alumno['id'] ?? 0) > 0)
            ->unique('id')
            ->values()
            ->all();

        if ($this->cursosSeleccionados === [] && $this->alumnosSeleccionados === []) {
            $this->addError(
                'alcanceEstudiantes',
                'Seleccione al menos un curso o un estudiante individual.',
            );
        }
    }

    private function validarCuotasSeleccionadas(): void
    {
        $this->validate([
            'cuotasSeleccionadas' => ['required', 'array', 'min:1'],
            'cuotasSeleccionadas.*' => ['integer', 'min:1'],
        ], [
            'cuotasSeleccionadas.required' => 'Seleccione al menos una cuota.',
            'cuotasSeleccionadas.min' => 'Seleccione al menos una cuota.',
        ]);

        $permitidos = $this->idsCuotasPermitidasComoString();

        $this->cuotasSeleccionadas = collect($this->cuotasSeleccionadas)
            ->map(fn ($id) => (string) (int) $id)
            ->filter(fn (string $id) => $permitidos->has($id))
            ->unique()
            ->values()
            ->all();

        if ($this->cuotasSeleccionadas === []) {
            $this->addError('cuotasSeleccionadas', 'Seleccione al menos una cuota válida.');
        }
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();
        $cursos = GeneracionMasivaCuotasConsulta::cursosEnContexto();

        $filtro = mb_strtolower(trim($this->filtroCursos));
        $seleccionadosFlip = array_flip($this->cursosSeleccionados);
        $cantidadSeleccionados = count($this->cursosSeleccionados);

        $cursosPorNivel = [];
        foreach ($cursos as $curso) {
            $etiqueta = GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($curso);
            if ($filtro !== '' && ! str_contains(mb_strtolower($etiqueta), $filtro)) {
                continue;
            }

            $idNivel = (int) ($curso->idNivel ?? 0);
            $key = (string) $idNivel;
            if (! isset($cursosPorNivel[$key])) {
                $cursosPorNivel[$key] = [
                    'idNivel' => $idNivel,
                    'nivelNombre' => trim((string) ($curso->nivel?->nivel ?? 'Sin nivel')),
                    'cursos' => [],
                    'total' => 0,
                    'seleccionados' => 0,
                ];
            }

            $idCursoStr = (string) (int) $curso->Id;
            $marcado = isset($seleccionadosFlip[$idCursoStr]);
            $cursosPorNivel[$key]['cursos'][] = [
                'id' => (int) $curso->Id,
                'etiqueta' => $etiqueta,
                'seleccionado' => $marcado,
            ];
            $cursosPorNivel[$key]['total']++;
            if ($marcado) {
                $cursosPorNivel[$key]['seleccionados']++;
            }
        }

        $etiquetasPorId = $cursos->mapWithKeys(fn ($c) => [
            (string) (int) $c->Id => GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($c),
        ]);

        $cursosSeleccionadosResumen = collect($this->cursosSeleccionados)
            ->map(fn (string $id) => [
                'id' => (int) $id,
                'label' => (string) ($etiquetasPorId[$id] ?? ''),
            ])
            ->filter(fn (array $r) => $r['label'] !== '')
            ->values()
            ->all();

        $plantillas = Cuota::query()
            ->where('idTerlec', CuotasPlantillaCatalog::idTerlecActivo())
            ->with(['cuotasTipo:id,nombre', 'cuotasMes:id,mes'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $legajosBusqueda = null;
        if ($this->paso === 2 && trim($this->buscarAlumno) !== '') {
            $cuotaIds = $this->idsCuotasValidadas();
            $legajosBusqueda = $cuotaIds === []
                ? collect()
                : GestionAranceles::buscarLegajosConCuotasPlantilla($this->buscarAlumno, $cuotaIds, 15);
        }

        $idsAlumnosSeleccionados = array_flip(
            collect($this->alumnosSeleccionados)->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        return view('livewire.cuotas.facturacion-masiva-afip', [
            'cursos' => $cursos,
            'cursosPorNivel' => array_values($cursosPorNivel),
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'cursosSeleccionadosResumen' => $cursosSeleccionadosResumen,
            'plantillas' => $plantillas,
            'cantidadCuotasSeleccionadas' => count($this->cuotasSeleccionadas),
            'cantidadAlumnosSeleccionados' => count($this->alumnosSeleccionados),
            'puedeContinuarCuotas' => $this->tipoOperacion !== '' && count($this->cuotasSeleccionadas) > 0,
            'puedeSeleccionarCuotas' => $this->tipoOperacion !== '',
            'puedeContinuarAlumnos' => $cantidadSeleccionados > 0 || count($this->alumnosSeleccionados) > 0,
            'legajosBusqueda' => $legajosBusqueda,
            'idsAlumnosSeleccionados' => $idsAlumnosSeleccionados,
            'esNotaCredito' => $this->esNotaCredito(),
            'ano' => $ano,
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Facturación masiva AFIP — {$ano}"]);
    }
}
