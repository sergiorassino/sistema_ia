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
use App\Support\NivelSistema;
use App\Support\PortalDocente\PortalDocenteContext;
use Carbon\Carbon;
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
    public string $nivelId             = '';
    public string $salaCursoGrado        = '';
    public string $observaciones         = '';
    public bool   $esEntregaDirect       = false; // solo admin: préstamo espontáneo entregado al instante
    public string $entregadoA            = '';

    // Estado calculado
    public ?int $pedidoId = null; // modo edición

    public function mount(?int $id = null): void
    {
        $rol = $this->rolMaterialDidactico();
        abort_unless($rol === 'admin' || $rol === 'profesor', 403);

        if ($this->esPortalDocente()) {
            abort_unless(tenantPortalDocenteRecursosDidacticosNuevaReserva(), 404);
        }

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
                $this->redirect($this->rutaListadoMaterialDidactico(), navigate: true);

                return;
            }

            $this->pedidoId        = $pedido->id;
            $this->fecha           = $pedido->fecha->format('Y-m-d');
            $this->horaInicio      = substr((string) $pedido->hora_inicio, 0, 5);
            $this->horaFin         = substr((string) $pedido->hora_fin, 0, 5);
            $this->salaCursoGrado  = $pedido->sala_curso_grado;
            $this->observaciones   = $pedido->observaciones ?? '';
            $this->inferirNivelDesdeSalaCursoGrado($this->salaCursoGrado);

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
            $idNivelCtx  = (int) (schoolCtx()->idNivel ?? 0);
            if ($idNivelCtx > 0 && $idNivelCtx !== NivelSistema::ADMINISTRACION) {
                $this->nivelId = (string) $idNivelCtx;
            }
        }
    }

    public function updatedNivelId(): void
    {
        $this->salaCursoGrado = '';
    }

    public function updatedGrupoId(): void
    {
        $this->recursoIdAgregar = '';
    }

    public function updatedFecha(): void
    {
        $this->sincronizarRecursosConHorario();
    }

    public function updatedHoraInicio(): void
    {
        $this->sincronizarRecursosConHorario();
    }

    public function updatedHoraFin(): void
    {
        $this->sincronizarRecursosConHorario();
    }

    public function updatedEsEntregaDirect(): void
    {
        $this->sincronizarRecursosConHorario();
    }

    public function agregarRecursoAlPedido(): void
    {
        if (! $this->horarioReservaCompleto()) {
            $this->dispatch('se-swal-aviso', mensaje: 'Indique primero la fecha y el horario de la reserva.');

            return;
        }

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

        $recurso = RrdRecurso::query()
            ->enContexto()
            ->activos()
            ->where('id_grupo', (int) $this->grupoId)
            ->where('id', $idRecurso)
            ->with('disponibilidades')
            ->first();

        if (! $recurso) {
            $this->dispatch('se-swal-error', mensaje: 'El recurso no pertenece al grupo seleccionado.');

            return;
        }

        if (! RrdReservaService::esReservableEnHorario(
            $recurso,
            $this->fecha,
            $this->horaInicio,
            $this->horaFin,
            $this->omitirAntelacionEnFormulario(),
            $this->pedidoId
        )) {
            $this->dispatch('se-swal-error', mensaje: 'El recurso no está disponible para el horario seleccionado.');

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
        $rol = $this->rolMaterialDidactico();
        abort_unless($rol === 'admin' || $rol === 'profesor', 403);

        $rules = [
            'fecha'                   => 'required|date',
            'horaInicio'              => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'horaFin'                 => ['required', 'regex:/^\d{2}:\d{2}$/', 'after:horaInicio'],
            'recursosSeleccionados'   => 'required|array|min:1',
            'recursosSeleccionados.*' => 'integer|min:1',
            'nivelId'                 => 'nullable|integer|min:1',
            'salaCursoGrado'          => 'required|string|max:120',
            'observaciones'           => 'nullable|string|max:2000',
        ];

        if ($rol !== 'admin') {
            $rules['fecha'] .= '|after_or_equal:today';
        }

        if ($rol === 'admin' && $this->esEntregaDirect && ! $this->esPortalDocente()) {
            $rules['entregadoA'] = 'required|string|max:100';
        }

        $this->validate($rules, [
            'fecha.after_or_equal'           => 'La fecha debe ser hoy o posterior.',
            'recursosSeleccionados.required' => 'Agregue al menos un recurso al pedido.',
            'recursosSeleccionados.min'      => 'Agregue al menos un recurso al pedido.',
            'salaCursoGrado.required'        => 'Indique sala, grado o curso.',
            'horaFin.after'                  => 'La hora de fin debe ser posterior a la hora de inicio.',
            'entregadoA.required'            => 'Indique quién retira el material.',
        ]);

        $datos = [
            'fecha'            => $this->fecha,
            'hora_inicio'      => $this->horaInicio,
            'hora_fin'         => $this->horaFin,
            'sala_curso_grado' => trim($this->salaCursoGrado),
            'observaciones'    => trim($this->observaciones),
            'entregado_directo' => $rol === 'admin' && $this->esEntregaDirect && ! $this->esPortalDocente(),
            'entregado_a'      => $this->entregadoA,
        ];

        try {
            if ($this->pedidoId) {
                $pedido = RrdPedido::queryEnContexto()->findOrFail($this->pedidoId);

                if ($rol === 'profesor') {
                    abort_unless(
                        $pedido->id_profesor === (int) (schoolCtx()->idProfesor ?? 0),
                        403
                    );
                }

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

            $this->redirect($this->rutaListadoMaterialDidactico(), navigate: true);

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
        $horarioCompleto  = $this->horarioReservaCompleto();

        if ($horarioCompleto && $this->grupoId !== '' && (int) $this->grupoId > 0) {
            $recursosDelGrupo = RrdRecurso::paraGrupoReservablesEnHorario(
                (int) $this->grupoId,
                $this->fecha,
                $this->horaInicio,
                $this->horaFin,
                $this->omitirAntelacionEnFormulario(),
                $this->pedidoId
            );
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

        // Cursos del nivel elegido (cualquier nivel del colegio; ciclo lectivo del contexto activo)
        $niveles = Nivel::query()
            ->where('id', '!=', NivelSistema::ADMINISTRACION)
            ->orderBy('id')
            ->get(['id', 'nivel', 'abrev']);

        $cursos = collect();
        $nivelAbrev = '';

        if ($this->nivelId !== '' && (int) $this->nivelId > 0) {
            $idNivelSel = (int) $this->nivelId;
            $nivelAbrev = $niveles->firstWhere('id', $idNivelSel)?->abrev
                ?? Nivel::find($idNivelSel)?->abrev
                ?? '';

            $cursos = Curso::query()
                ->where('idNivel', $idNivelSel)
                ->where('idTerlec', (int) ($ctx->idTerlec ?? 0))
                ->with(['curplan', 'turnoClase', 'nivel'])
                ->orderBy('orden')
                ->get();
        }

        $titulo = $this->pedidoId ? 'Editar reserva' : 'Nueva reserva';
        $rol = $this->rolMaterialDidactico();

        return view('livewire.material-didactico.reserva-form', [
            'grupos'           => $grupos,
            'recursosDelGrupo' => $recursosDelGrupo,
            'recursosEnPedido' => $recursosEnPedido,
            'horarioCompleto'  => $horarioCompleto,
            'niveles'          => $niveles,
            'cursos'           => $cursos,
            'nivelAbrev'       => $nivelAbrev,
            'titulo'           => $titulo,
            'rol'              => $rol,
            'rutaVolver'       => $this->rutaListadoMaterialDidactico(),
            'mostrarPrestamoEspontaneo' => $rol === 'admin' && ! $this->esPortalDocente(),
        ])->layout($this->layoutMaterialDidactico(), ['pageTitle' => "Material Didáctico — {$titulo}"]);
    }

    private function esPortalDocente(): bool
    {
        return PortalDocenteContext::esActivo();
    }

    private function rolMaterialDidactico(): ?string
    {
        if ($this->esPortalDocente()) {
            return tenantPortalDocenteRecursosDidacticosNuevaReserva() ? 'profesor' : null;
        }

        return rrdRol();
    }

    private function layoutMaterialDidactico(): string
    {
        return $this->esPortalDocente() ? 'layouts.docente' : layoutMenuStaff();
    }

    private function rutaListadoMaterialDidactico(): string
    {
        if ($this->esPortalDocente()) {
            return tenantPortalDocenteRecursosDidacticosListado()
                ? route('portalDocente.materialDidactico.index')
                : route('portalDocente.home');
        }

        return route('material-didactico.index');
    }

    private function horarioReservaCompleto(): bool
    {
        if ($this->fecha === '' || $this->horaInicio === '' || $this->horaFin === '') {
            return false;
        }

        if (! preg_match('/^\d{2}:\d{2}$/', $this->horaInicio) || ! preg_match('/^\d{2}:\d{2}$/', $this->horaFin)) {
            return false;
        }

        $tz = config('app.timezone');

        try {
            $inicio = Carbon::parse("{$this->fecha} {$this->horaInicio}", $tz);
            $fin    = Carbon::parse("{$this->fecha} {$this->horaFin}", $tz);
        } catch (\Throwable) {
            return false;
        }

        return $fin->gt($inicio);
    }

    private function omitirAntelacionEnFormulario(): bool
    {
        return $this->rolMaterialDidactico() === 'admin'
            && $this->esEntregaDirect
            && ! $this->esPortalDocente();
    }

    private function sincronizarRecursosConHorario(): void
    {
        $this->recursoIdAgregar = '';

        if (! $this->horarioReservaCompleto()) {
            if (! $this->pedidoId) {
                $this->recursosSeleccionados = [];
            }

            return;
        }

        $omitirAntelacion = $this->omitirAntelacionEnFormulario();

        $this->recursosSeleccionados = array_values(array_filter(
            $this->recursosSeleccionados,
            function ($id) use ($omitirAntelacion): bool {
                $recurso = RrdRecurso::query()
                    ->enContexto()
                    ->activos()
                    ->with('disponibilidades')
                    ->find((int) $id);

                if (! $recurso) {
                    return false;
                }

                return RrdReservaService::esReservableEnHorario(
                    $recurso,
                    $this->fecha,
                    $this->horaInicio,
                    $this->horaFin,
                    $omitirAntelacion,
                    $this->pedidoId
                );
            }
        ));
    }

    private function inferirNivelDesdeSalaCursoGrado(string $salaCursoGrado): void
    {
        if (trim($salaCursoGrado) === '') {
            return;
        }

        $idTerlec = (int) (schoolCtx()->idTerlec ?? 0);
        if ($idTerlec <= 0) {
            return;
        }

        $cursos = Curso::query()
            ->where('idTerlec', $idTerlec)
            ->with(['curplan', 'turnoClase', 'nivel'])
            ->get();

        foreach ($cursos as $curso) {
            $abrev = trim((string) ($curso->nivel?->abrev ?? ''));
            if ($this->etiquetaSalaCursoGrado($curso, $abrev) === $salaCursoGrado) {
                $this->nivelId = (string) (int) $curso->idNivel;

                return;
            }
        }
    }

    private function etiquetaSalaCursoGrado(Curso $curso, string $nivelAbrev): string
    {
        $label = $curso->nombreParaListado();
        if ($nivelAbrev !== '') {
            $label .= ' ('.$nivelAbrev.')';
        }

        return $label;
    }
}
