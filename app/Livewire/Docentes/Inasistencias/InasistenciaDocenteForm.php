<?php

namespace App\Livewire\Docentes\Inasistencias;

use App\Models\Profesor;
use App\Support\InasistenciasDocentes;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class InasistenciaDocenteForm extends Component
{
    public int $idProfesor;

    public ?int $id = null;

    public string $retorno = 'profesor';

    public int $retornoBimestre = 0;

    public int $retornoAnio = 0;

    public int $inaLic = 0;

    public int|string $idTipoInaDoc = '';

    public int|string $idCargosXProfesor = '';

    public int $idNivel = 0;

    public string $fecha = '';

    public string $hasta = '';

    public int $cantOblig = 0;

    public string $cantObligIna = '0';

    public bool $justif = false;

    public string $obs = '';

    /** @var array<int, string> valor "idMaterias_idCursos" */
    public array $detalleMateriaCurso = [];

    /** @var array<int, string> */
    public array $detalleCantidad = [];

    public function mount(int $idProfesor, ?int $id = null): void
    {
        abort_unless(tienePermiso(InasistenciasDocentes::PERMISO_ORDEN), 403);
        abort_unless(InasistenciasDocentes::moduloDisponible(), 503);

        $this->idProfesor = $idProfesor;
        $profesor = InasistenciasDocentes::profesorDelContexto($idProfesor);
        $this->idNivel = (int) ($profesor->nivel ?? schoolCtx()->idNivel);
        $this->retorno = in_array(request()->query('retorno'), ['profesor', 'informe'], true)
            ? request()->query('retorno')
            : 'profesor';
        $this->retornoBimestre = (int) request()->query('bimestre', 0);
        $this->retornoAnio = (int) request()->query('anio', InasistenciasDocentes::anoLectivo());

        $this->id = $id;

        if ($id) {
            $reg = InasistenciasDocentes::registroDelProfesor($id, $idProfesor);
            $this->inaLic = (int) ($reg->inaLic ?? 0);
            $this->idTipoInaDoc = (string) (int) $reg->idTipoInaDoc;
            $this->idCargosXProfesor = (string) (int) ($reg->idCargosXProfesor ?? 0);
            $this->fecha = $reg->fecha ? $reg->fecha->format('Y-m-d') : '';
            $this->hasta = $reg->hasta ? $reg->hasta->format('Y-m-d') : '';
            $this->cantOblig = (int) ($reg->cantOblig ?? 0);
            $this->cantObligIna = number_format((float) ($reg->cantObligIna ?? 0), 1, '.', '');
            $this->justif = (int) ($reg->justif ?? 0) === 1;
            $this->obs = trim((string) ($reg->obs ?? ''));

            foreach (InasistenciasDocentes::detalleDeInasistencia($id) as $d) {
                $rowId = $this->nuevaFilaDetalleId();
                $this->detalleMateriaCurso[$rowId] = $d->idMaterias.'_'.$d->idCursos;
                $this->detalleCantidad[$rowId] = (string) $d->cantidad;
            }

            if ($this->filasDetalleIds() === [] && InasistenciasDocentes::opcionesMateriaCurso($this->idNivel)->isNotEmpty()) {
                $this->addDetalleFila();
            }

            return;
        }

        $this->fecha = now()->format('Y-m-d');

        if (InasistenciasDocentes::opcionesMateriaCurso($this->idNivel)->isNotEmpty()) {
            $this->addDetalleFila();
        }
    }

    /** @return array<int, int> */
    public function filasDetalleIds(): array
    {
        $ids = array_unique(array_merge(
            array_keys($this->detalleMateriaCurso),
            array_keys($this->detalleCantidad),
        ));
        sort($ids, SORT_NUMERIC);

        return array_values($ids);
    }

    private function nuevaFilaDetalleId(): int
    {
        $ids = $this->filasDetalleIds();

        return $ids === [] ? 0 : (max($ids) + 1);
    }

    public function addDetalleFila(): void
    {
        $rowId = $this->nuevaFilaDetalleId();
        $this->detalleMateriaCurso[$rowId] = '';
        $this->detalleCantidad[$rowId] = '';
    }

    public function removeDetalleFila(int $rowId): void
    {
        unset($this->detalleMateriaCurso[$rowId], $this->detalleCantidad[$rowId]);
    }

    protected function rules(): array
    {
        $rules = [
            'idTipoInaDoc' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'cantObligIna' => ['required', 'numeric', 'min:0.1', 'max:999'],
            'obs' => ['nullable', 'string'],
            'detalleMateriaCurso' => ['array'],
            'detalleMateriaCurso.*' => ['nullable', 'string', 'max:64'],
            'detalleCantidad' => ['array'],
            'detalleCantidad.*' => ['nullable', 'numeric', 'min:0'],
        ];

        if ((int) $this->inaLic === 1) {
            $rules['hasta'] = ['required', 'date', 'after_or_equal:fecha'];
        }

        if (InasistenciasDocentes::tieneCargos() && InasistenciasDocentes::cargosSelectProfesor($this->idProfesor)->isNotEmpty()) {
            $rules['idCargosXProfesor'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'idTipoInaDoc.required' => 'Seleccione el motivo.',
            'idCargosXProfesor.required' => 'Seleccione el cargo.',
            'fecha.required' => 'Indique la fecha.',
            'hasta.required' => 'Indique la fecha hasta (licencia).',
            'hasta.after_or_equal' => 'La fecha hasta debe ser posterior o igual a la fecha de inicio.',
            'cantObligIna.required' => 'Ingrese la cantidad de obligaciones inasistidas.',
            'cantObligIna.min' => 'La cantidad debe ser mayor a cero.',
        ];
    }

    public function save(): mixed
    {
        $key = 'inasdocentes:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('fecha', 'Demasiados intentos. Espere un momento.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $profesor = InasistenciasDocentes::profesorDelContexto($this->idProfesor);

        $obligaciones = InasistenciasDocentes::calcularObligacionesEsperadas(
            $this->idProfesor,
            (int) $this->idCargosXProfesor,
            $this->fecha,
            (int) $this->inaLic === 1 ? $this->hasta : null,
            (int) $this->inaLic === 1,
        );
        $this->cantOblig = $obligaciones !== null ? (int) round($obligaciones['total']) : 0;

        InasistenciasDocentes::guardarInasistencia([
            'idNivel' => $this->idNivel,
            'inaLic' => $this->inaLic,
            'idTipoInaDoc' => (int) $this->idTipoInaDoc,
            'idCargosXProfesor' => (int) $this->idCargosXProfesor,
            'fecha' => $this->fecha,
            'hasta' => (int) $this->inaLic === 1 ? $this->hasta : null,
            'cantOblig' => $this->cantOblig,
            'cantObligIna' => $this->cantObligIna,
            'justif' => $this->justif,
            'obs' => $this->obs,
            'detalle' => InasistenciasDocentes::normalizarDetalleDesdeListas(
                $this->detalleMateriaCurso,
                $this->detalleCantidad,
            ),
            'detalleMateriaCurso' => $this->detalleMateriaCurso,
            'detalleCantidad' => $this->detalleCantidad,
        ], $profesor, $this->id);

        session()->flash('success', $this->id ? 'Inasistencia actualizada.' : 'Inasistencia registrada.');

        return redirect($this->urlVolver());
    }

    public function delete(): mixed
    {
        abort_unless($this->id, 404);

        $key = 'inasdocentes:delete:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('fecha', 'Demasiados intentos. Espere un momento.');

            return null;
        }
        RateLimiter::hit($key, 60);

        InasistenciasDocentes::eliminarInasistencia($this->id, $this->idProfesor);
        session()->flash('success', 'Inasistencia eliminada.');

        return redirect($this->urlVolver());
    }

    private function urlVolver(): string
    {
        if ($this->retorno === 'informe' && $this->retornoBimestre >= 1 && $this->retornoBimestre <= 6) {
            return route('docentes.inasistencias.informe', [
                'idProfesor' => $this->idProfesor,
                'bimestre' => $this->retornoBimestre,
                'anio' => $this->retornoAnio ?: InasistenciasDocentes::anoLectivo(),
            ]);
        }

        return route('docentes.inasistencias.show', $this->idProfesor);
    }

    public function render()
    {
        $profesor = InasistenciasDocentes::profesorDelContexto($this->idProfesor);

        $obligacionesEsperadas = InasistenciasDocentes::resumenObligacionesEsperadasForm(
            $this->idProfesor,
            (int) $this->idCargosXProfesor,
            $this->fecha,
            $this->hasta,
            $this->inaLic,
        );

        return view('livewire.docentes.inasistencias.form', [
            'profesor' => $profesor,
            'tipos' => InasistenciasDocentes::tiposMotivo(),
            'cargos' => InasistenciasDocentes::cargosSelectProfesor($this->idProfesor),
            'opcionesMateriaCurso' => InasistenciasDocentes::opcionesMateriaCurso($this->idNivel),
            'filasDetalle' => $this->filasDetalleIds(),
            'nivelNombre' => schoolCtx()->nivelNombre(),
            'urlVolver' => $this->urlVolver(),
            'obligacionesEsperadas' => $obligacionesEsperadas,
        ])->layout(layoutMenuStaff(), ['pageTitle' => $this->id ? 'Editar inasistencia docente' : 'Nueva inasistencia docente']);
    }
}
