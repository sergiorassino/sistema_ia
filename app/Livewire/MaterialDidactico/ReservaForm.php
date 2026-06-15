<?php

namespace App\Livewire\MaterialDidactico;

use App\Models\Curso;
use App\Models\Nivel;
use App\Models\RrdGrupo;
use App\Models\RrdPedido;
use App\Models\RrdRecurso;
use App\Models\RrdReserva;
use App\Support\MaterialDidactico\RrdReservaException;
use App\Support\MaterialDidactico\RrdReservaService;
use Livewire\Component;

class ReservaForm extends Component
{
    public string $fecha      = '';
    public string $horaInicio = '';
    public string $horaFin    = '';

    public string $grupoId           = '';
    public string $recursoIdAgregar = '';
    /** @var list<int> */
    public array $recursosSeleccionados = [];
    public string $salaCursoGrado        = '';
    public string $auxiliar              = '';
    public string $observaciones         = '';
    public bool   $esEntregaDirect       = false; // solo admin: préstamo espontáneo entregado al instante
    public string $entregadoA            = '';

    // Estado calculado
    public ?int $pedidoId = null; // modo edición

    public function mount(?int $id = null): void
    {
        $rol = rrdRol();
        abort_unless($rol === 'admin' || $rol === 'profesor', 403);

        if ($id !== null) {
            $pedido = RrdPedido::queryEnContexto()->findOrFail($id);

            if ($rol === 'profesor') {
                abort_unless(
                    $pedido->id_profesor === (int) (schoolCtx()->idProfesor ?? 0),
                    403
                );
            }

            // Verificar que el pedido sea modificable
            $tieneEntregado = $pedido->reservas()
                ->whereIn('estado', [RrdReserva::ESTADO_ENTREGADO, RrdReserva::ESTADO_DEVUELTO])
                ->exists();
            if ($tieneEntregado) {
                session()->flash('error', 'No se puede editar un pedido con recursos ya entregados.');
                $this->redirect(route('material-didactico.index'), navigate: true);
                return;
            }

            $this->pedidoId        = $pedido->id;
            $this->fecha           = $pedido->fecha->format('Y-m-d');
            $this->horaInicio      = substr((string) $pedido->hora_inicio, 0, 5);
            $this->horaFin         = substr((string) $pedido->hora_fin, 0, 5);
            $this->salaCursoGrado  = $pedido->sala_curso_grado;
            $this->auxiliar        = $pedido->auxiliar;
            $this->observaciones   = $pedido->observaciones ?? '';

            $this->recursosSeleccionados = $pedido->reservas()
                ->pluck('id_recurso')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $primerRecurso = RrdRecurso::find($this->recursosSeleccionados[0] ?? 0);
            if ($primerRecurso) {
                $this->grupoId = (string) $primerRecurso->id_grupo;
            }
        } else {
            $this->fecha = now()->format('Y-m-d');
        }
    }

    public function updatedGrupoId(): void
    {
        $this->recursoIdAgregar = '';
    }

    public function agregarRecursoAlPedido(): void
    {
        $this->validate([
            'grupoId'           => 'required|integer|min:1',
            'recursoIdAgregar'  => 'required|integer|min:1',
        ], [
            'grupoId.required'          => 'Seleccione un grupo.',
            'recursoIdAgregar.required' => 'Seleccione un recurso.',
        ]);

        $idRecurso = (int) $this->recursoIdAgregar;

        $idsActuales = array_map('intval', $this->recursosSeleccionados);
        if (in_array($idRecurso, $idsActuales, true)) {
            $this->dispatch('se-swal-aviso', mensaje: 'Ese recurso ya está en el pedido.');

            return;
        }

        $existe = RrdRecurso::query()
            ->enContexto()
            ->activos()
            ->where('id_grupo', (int) $this->grupoId)
            ->where('id', $idRecurso)
            ->exists();

        if (! $existe) {
            $this->dispatch('se-swal-error', mensaje: 'El recurso no pertenece al grupo seleccionado.');

            return;
        }

        $this->recursosSeleccionados[] = $idRecurso;
        $this->recursoIdAgregar        = '';
    }

    public function quitarRecursoDelPedido(int $idRecurso): void
    {
        $this->recursosSeleccionados = array_values(array_filter(
            $this->recursosSeleccionados,
            fn ($id): bool => (int) $id !== $idRecurso
        ));
    }

    // ---------------------------------------------------------------
    // Guardar
    // ---------------------------------------------------------------

    public function guardar(): void
    {
        $rol = rrdRol();
        abort_unless($rol === 'admin' || $rol === 'profesor', 403);

        $rules = [
            'fecha'                   => 'required|date',
            'horaInicio'              => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'horaFin'                 => ['required', 'regex:/^\d{2}:\d{2}$/', 'after:horaInicio'],
            'recursosSeleccionados'   => 'required|array|min:1',
            'recursosSeleccionados.*' => 'integer|min:1',
            'salaCursoGrado'          => 'nullable|string|max:120',
            'auxiliar'                => 'nullable|string|max:100',
            'observaciones'           => 'nullable|string|max:2000',
        ];

        if ($rol !== 'admin') {
            $rules['fecha'] .= '|after_or_equal:today';
        }

        if ($rol === 'admin' && $this->esEntregaDirect) {
            $rules['entregadoA'] = 'required|string|max:100';
        }

        $this->validate($rules, [
            'fecha.after_or_equal'           => 'La fecha debe ser hoy o posterior.',
            'recursosSeleccionados.required' => 'Agregue al menos un recurso al pedido.',
            'recursosSeleccionados.min'      => 'Agregue al menos un recurso al pedido.',
            'horaFin.after'                  => 'La hora de fin debe ser posterior a la hora de inicio.',
            'entregadoA.required'            => 'Indique quién retira el material.',
        ]);

        $datos = [
            'fecha'            => $this->fecha,
            'hora_inicio'      => $this->horaInicio,
            'hora_fin'         => $this->horaFin,
            'sala_curso_grado' => trim($this->salaCursoGrado),
            'auxiliar'         => trim($this->auxiliar),
            'observaciones'    => trim($this->observaciones),
            'entregado_directo' => $rol === 'admin' && $this->esEntregaDirect,
            'entregado_a'      => $this->entregadoA,
        ];

        try {
            if ($this->pedidoId) {
                $pedido = RrdPedido::queryEnContexto()->findOrFail($this->pedidoId);
                RrdReservaService::editarPedido(
                    $pedido,
                    $datos,
                    $this->recursosSeleccionados,
                    $rol === 'admin'
                );
            } else {
                RrdReservaService::crearPedido(
                    $datos,
                    $this->recursosSeleccionados,
                    $rol === 'admin'
                );
            }

            $this->redirect(route('material-didactico.index'), navigate: true);

        } catch (RrdReservaException $e) {
            $this->dispatch('se-swal-error', mensaje: $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Render
    // ---------------------------------------------------------------

    public function render()
    {
        $ctx = schoolCtx();

        $grupos = RrdGrupo::query()
            ->enContexto()
            ->activos()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $recursosDelGrupo = collect();
        if ($this->grupoId !== '' && (int) $this->grupoId > 0) {
            $recursosDelGrupo = RrdRecurso::paraGrupo((int) $this->grupoId);
        }

        $recursosEnPedido = collect();
        if ($this->recursosSeleccionados !== []) {
            $porId = RrdRecurso::query()
                ->whereIn('id', $this->recursosSeleccionados)
                ->with(['grupo', 'disponibilidades'])
                ->get()
                ->keyBy('id');

            foreach ($this->recursosSeleccionados as $id) {
                if ($porId->has($id)) {
                    $recursosEnPedido->push($porId->get($id));
                }
            }
        }

        // Cursos del año lectivo activo para el select Sala/Curso/Grado
        $cursos = Curso::query()
            ->where('idNivel', (int) ($ctx->idNivel ?? 0))
            ->where('idTerlec', (int) ($ctx->idTerlec ?? 0))
            ->with(['curplan', 'turnoClase', 'nivel'])
            ->orderBy('orden')
            ->get();

        // Nivel abreviatura para etiqueta
        $nivelAbrev = $cursos->first()?->nivel?->abrev
            ?? Nivel::find((int) ($ctx->idNivel ?? 0))?->abrev
            ?? '';

        $titulo = $this->pedidoId ? 'Editar reserva' : 'Nueva reserva';

        return view('livewire.material-didactico.reserva-form', [
            'grupos'           => $grupos,
            'recursosDelGrupo' => $recursosDelGrupo,
            'recursosEnPedido' => $recursosEnPedido,
            'cursos'           => $cursos,
            'nivelAbrev'       => $nivelAbrev,
            'titulo'           => $titulo,
            'rol'              => rrdRol(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Material Didáctico — {$titulo}"]);
    }
}
