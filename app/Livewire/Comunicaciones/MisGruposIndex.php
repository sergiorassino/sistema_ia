<?php

namespace App\Livewire\Comunicaciones;

use App\Comunicaciones\ComGruposRepository;
use App\Comunicaciones\ComunicacionesRepository;
use App\Models\ComGrupoMiembro;
use App\Push\DestinatariosRepository;
use App\Support\ComunicacionesRutasGestion;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class MisGruposIndex extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    public string $buscar = '';

    public bool $modalFormAbierto = false;

    public ?int $editId = null;

    public string $nombre = '';

    /** @var list<array{tipo:string,id:int,label:string,dni:?string,rol_label:?string,clave:string}> */
    public array $miembrosSeleccionados = [];

    public bool $modalMiembrosAbierto = false;

    public string $modalMiembrosFiltro = '';

    /** todos | estudiantes | personal */
    public string $modalMiembrosVista = 'todos';

    /** @var list<array{tipo:string,id:int,label:string,dni:?string,rol_label:?string,clave:string}> */
    public array $modalMiembrosLista = [];

    /** @var list<string> claves `legajo:id` / `profesor:id` */
    public array $modalMiembrosMarcados = [];

    public function mount(): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoMisGrupos(), 403, 'Sin permiso para administrar grupos de destinatarios.');
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function abrirNuevo(): void
    {
        $this->resetValidation();
        $this->editId = null;
        $this->nombre = '';
        $this->miembrosSeleccionados = [];
        $this->cerrarModalMiembros();
        $this->modalFormAbierto = true;
    }

    public function abrirEditar(int $id): void
    {
        $ctx = schoolCtx();
        $grupo = ComGruposRepository::scopedOrFail($id, (int) $ctx->idProfesor, (int) $ctx->idNivel);
        $this->resetValidation();
        $this->editId = (int) $grupo->id;
        $this->nombre = (string) $grupo->nombre;
        $this->miembrosSeleccionados = array_map(
            [self::class, 'conClave'],
            ComGruposRepository::miembrosParaEdicion($grupo)
        );
        $this->cerrarModalMiembros();
        $this->modalFormAbierto = true;
    }

    public function cerrarModalForm(): void
    {
        $this->modalFormAbierto = false;
        $this->cerrarModalMiembros();
        $this->resetFormulario();
    }

    public function abrirModalMiembros(): void
    {
        $this->modalMiembrosAbierto = true;
        $this->modalMiembrosFiltro = '';
        $this->modalMiembrosVista = 'todos';
        $this->modalMiembrosMarcados = array_values(array_filter(array_map(
            static fn ($m) => (string) ($m['clave'] ?? ''),
            $this->miembrosSeleccionados
        )));
        $this->recargarModalMiembrosLista();
    }

    public function cerrarModalMiembros(): void
    {
        $this->modalMiembrosAbierto = false;
    }

    public function updatedModalMiembrosFiltro(): void
    {
        if ($this->modalMiembrosAbierto) {
            $this->recargarModalMiembrosLista();
        }
    }

    public function updatedModalMiembrosVista(): void
    {
        if (! in_array($this->modalMiembrosVista, ['todos', 'estudiantes', 'personal'], true)) {
            $this->modalMiembrosVista = 'todos';
        }
        if ($this->modalMiembrosAbierto) {
            $this->recargarModalMiembrosLista();
        }
    }

    public function cambiarVistaMiembros(string $vista): void
    {
        $this->modalMiembrosVista = in_array($vista, ['todos', 'estudiantes', 'personal'], true) ? $vista : 'todos';
        if ($this->modalMiembrosAbierto) {
            $this->recargarModalMiembrosLista();
        }
    }

    public function aplicarModalMiembros(): void
    {
        $labelsPorClave = collect($this->modalMiembrosLista)->keyBy('clave');
        $prev = collect($this->miembrosSeleccionados)->keyBy('clave');
        $out = [];
        foreach (array_unique(array_map('strval', $this->modalMiembrosMarcados)) as $clave) {
            if ($clave === '') {
                continue;
            }
            $fromLista = $labelsPorClave->get($clave);
            if (is_array($fromLista)) {
                $out[] = self::conClave($fromLista);

                continue;
            }
            $fromPrev = $prev->get($clave);
            if (is_array($fromPrev)) {
                $out[] = self::conClave($fromPrev);
            }
        }
        $this->miembrosSeleccionados = $out;
        $this->modalMiembrosAbierto = false;
    }

    public function modalMiembrosSeleccionarTodosVisibles(): void
    {
        $claves = array_map(static fn ($r) => (string) ($r['clave'] ?? ''), $this->modalMiembrosLista);
        $this->modalMiembrosMarcados = array_values(array_unique(array_merge(
            array_map('strval', $this->modalMiembrosMarcados),
            $claves
        )));
    }

    public function modalMiembrosQuitarVisibles(): void
    {
        $vis = array_flip(array_map(static fn ($r) => (string) ($r['clave'] ?? ''), $this->modalMiembrosLista));
        $this->modalMiembrosMarcados = array_values(array_filter(
            array_map('strval', $this->modalMiembrosMarcados),
            static fn (string $clave) => $clave !== '' && ! isset($vis[$clave])
        ));
    }

    public function removeMiembro(string $tipo, int $id): void
    {
        $clave = self::claveMiembro($tipo, $id);
        $this->miembrosSeleccionados = array_values(
            array_filter($this->miembrosSeleccionados, static fn ($m) => (string) ($m['clave'] ?? '') !== $clave)
        );
    }

    public function guardar(): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoMisGrupos(), 403);

        if (! RateLimiter::attempt('com:grupos-guardar-'.(auth()->id() ?? 0), 40, static fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        if (! ComGruposRepository::tablasDisponibles()) {
            $this->dispatch('se-swal-error', mensaje: ComGruposRepository::mensajeTablasFaltantes());

            return;
        }

        $ctx = schoolCtx();
        $idProf = (int) $ctx->idProfesor;
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;
        if ($idProf <= 0 || $idNivel <= 0) {
            $this->dispatch('se-swal-error', mensaje: 'No hay usuario o nivel activo en el contexto.');

            return;
        }

        $this->nombre = trim($this->nombre);
        $this->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'miembrosSeleccionados' => ['required', 'array', 'min:1'],
        ], [
            'nombre.required' => 'Indique el nombre del grupo.',
            'miembrosSeleccionados.required' => 'Agregue al menos un integrante.',
            'miembrosSeleccionados.min' => 'Agregue al menos un integrante.',
        ]);

        $resultado = ComGruposRepository::guardar(
            $this->editId,
            $idProf,
            $idNivel,
            $idTerlec,
            $this->nombre,
            $this->miembrosSeleccionados
        );

        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['error'] ?? 'No se pudo guardar el grupo.');

            return;
        }

        $this->cerrarModalForm();
        $this->resetPage();
        $this->dispatch('se-swal-exito', mensaje: 'Grupo guardado.');
    }

    public function eliminar(int $id): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoMisGrupos(), 403);

        if (! RateLimiter::attempt('com:grupos-eliminar-'.(auth()->id() ?? 0), 40, static fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        $ctx = schoolCtx();
        $resultado = ComGruposRepository::eliminar($id, (int) $ctx->idProfesor, (int) $ctx->idNivel);
        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['error'] ?? 'No se pudo eliminar el grupo.');

            return;
        }

        $this->resetPage();
        $this->dispatch('se-swal-exito', mensaje: 'Grupo eliminado.');
    }

    private function resetFormulario(): void
    {
        $this->editId = null;
        $this->nombre = '';
        $this->miembrosSeleccionados = [];
        $this->resetValidation();
    }

    private function recargarModalMiembrosLista(): void
    {
        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;
        $idProf = (int) $ctx->idProfesor;
        $filtro = $this->modalMiembrosFiltro;
        $lista = [];

        $incluirEst = $this->modalMiembrosVista !== 'personal';
        $incluirPers = $this->modalMiembrosVista !== 'estudiantes';

        if ($incluirEst && $idNivel > 0 && $idTerlec > 0) {
            foreach (DestinatariosRepository::alumnosMatriculadosParaSelector($idNivel, $idTerlec, $filtro, 2500) as $al) {
                $lista[] = self::conClave([
                    'tipo'      => ComGrupoMiembro::TIPO_LEGAJO,
                    'id'        => (int) $al['id'],
                    'label'     => (string) $al['label'],
                    'dni'       => $al['dni'] ?? null,
                    'rol_label' => 'Estudiante',
                ]);
            }
        }

        if ($incluirPers && $idNivel > 0) {
            foreach (ComunicacionesRepository::profesoresDelNivelParaSelectorTodos($idNivel, $filtro, 800, $idProf) as $d) {
                $lista[] = self::conClave([
                    'tipo'      => ComGrupoMiembro::TIPO_PROFESOR,
                    'id'        => (int) $d['id'],
                    'label'     => (string) $d['label'],
                    'dni'       => $d['dni'] ?? null,
                    'rol_label' => (string) ($d['rol_label'] ?? 'Personal'),
                ]);
            }
        }

        usort(
            $lista,
            static fn (array $a, array $b): int => strcasecmp((string) $a['label'], (string) $b['label'])
        );

        $this->modalMiembrosLista = $lista;
    }

    /**
     * @param  array{tipo?:string,id?:int,label?:string,dni?:?string,rol_label?:?string,clave?:string}  $m
     * @return array{tipo:string,id:int,label:string,dni:?string,rol_label:?string,clave:string}
     */
    private static function conClave(array $m): array
    {
        $tipo = (string) ($m['tipo'] ?? '');
        $id = (int) ($m['id'] ?? 0);

        return [
            'tipo'      => $tipo,
            'id'        => $id,
            'label'     => (string) ($m['label'] ?? ''),
            'dni'       => $m['dni'] ?? null,
            'rol_label' => $m['rol_label'] ?? null,
            'clave'     => (string) ($m['clave'] ?? self::claveMiembro($tipo, $id)),
        ];
    }

    private static function claveMiembro(string $tipo, int $id): string
    {
        return $tipo.':'.$id;
    }

    public function render()
    {
        $ctx = schoolCtx();
        $tablasOk = ComGruposRepository::tablasDisponibles();
        $registros = null;
        $nombresPorGrupo = [];

        if ($tablasOk && (int) $ctx->idProfesor > 0 && (int) $ctx->idNivel > 0) {
            $q = ComGruposRepository::queryDelDueno((int) $ctx->idProfesor, (int) $ctx->idNivel)
                ->with('miembros')
                ->withCount('miembros')
                ->orderBy('nombre');
            $buscar = trim($this->buscar);
            if ($buscar !== '') {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $buscar).'%';
                $q->where('nombre', 'like', $like);
            }
            $registros = $q->paginate(self::POR_PAGINA);
            $nombresPorGrupo = ComGruposRepository::etiquetasMiembrosPorGrupos($registros);
        }

        return view('comunicaciones::livewire.comunicaciones.mis-grupos-index', [
            'tablasOk'         => $tablasOk,
            'mensajeTabla'     => ComGruposRepository::mensajeTablasFaltantes(),
            'registros'        => $registros,
            'nombresPorGrupo'  => $nombresPorGrupo,
        ])->layout(ComunicacionesRutasGestion::layout(), ['pageTitle' => 'Mis grupos']);
    }
}
