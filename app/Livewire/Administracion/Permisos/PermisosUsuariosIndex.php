<?php

namespace App\Livewire\Administracion\Permisos;

use App\Models\Profesor;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PermisosUsuariosIndex extends Component
{
    public string $q = '';

    public ?int $profesorId = null;

    public bool $showModalCopiar = false;

    public ?int $copiarOrigenId = null;

    public ?int $copiarDestinoId = null;

    public function mount(): void
    {
        abort_unless(tienePermiso(0), 403, 'Sin permiso para administrar permisos.');
    }

    public function seleccionarProfesor(int $id): void
    {
        $this->profesorId = $id > 0 ? $id : null;
    }

    public function abrirModalCopiar(): void
    {
        abort_unless(tienePermiso(0), 403);

        $this->resetValidation();
        $this->reset('copiarOrigenId', 'copiarDestinoId');
        if ($this->profesorId) {
            $this->copiarOrigenId = $this->profesorId;
        }
        $this->showModalCopiar = true;
    }

    public function cerrarModalCopiar(): void
    {
        $this->showModalCopiar = false;
        $this->reset('copiarOrigenId', 'copiarDestinoId');
        $this->resetValidation();
    }

    public function copiarPermisos(): void
    {
        abort_unless(tienePermiso(0), 403);

        $nivel = (int) (schoolCtx()->idNivel ?? 0);
        $idsValidos = $this->usuariosDelNivel()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->validate([
            'copiarOrigenId' => ['required', 'integer', Rule::in($idsValidos)],
            'copiarDestinoId' => [
                'required',
                'integer',
                'different:copiarOrigenId',
                Rule::in($idsValidos),
            ],
        ], [
            'copiarOrigenId.required' => 'Seleccione el usuario origen.',
            'copiarOrigenId.in' => 'El usuario origen no pertenece al nivel actual.',
            'copiarDestinoId.required' => 'Seleccione el usuario destino.',
            'copiarDestinoId.different' => 'El usuario destino debe ser distinto del origen.',
            'copiarDestinoId.in' => 'El usuario destino no pertenece al nivel actual.',
        ]);

        $key = 'permisos:copiar:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->js('window.seSwalError(' . json_encode('Demasiados intentos seguidos. Espere un momento e intente nuevamente.', JSON_UNESCAPED_UNICODE) . ')');

            return;
        }
        RateLimiter::hit($key, 60);

        $origen = Profesor::query()
            ->where('nivel', $nivel)
            ->elegiblesParaPermisosIa()
            ->whereKey($this->copiarOrigenId)
            ->firstOrFail(['id', 'apellido', 'nombre', 'permisos_ia']);

        $destino = Profesor::query()
            ->where('nivel', $nivel)
            ->elegiblesParaPermisosIa()
            ->whereKey($this->copiarDestinoId)
            ->firstOrFail(['id', 'permisos_ia']);

        $cadena = $this->normalizarCadenaPermisos((string) ($origen->permisos_ia ?? ''));

        Profesor::query()
            ->where('nivel', $nivel)
            ->elegiblesParaPermisosIa()
            ->whereKey($destino->id)
            ->update(['permisos_ia' => $cadena]);

        if ((int) $destino->id === (int) (schoolCtx()->idProfesor ?? 0)) {
            schoolCtx()->refreshProfesor();
        }

        if ((int) $destino->id === (int) $this->profesorId) {
            $idActual = $this->profesorId;
            $this->profesorId = null;
            $this->profesorId = $idActual;
        }

        $nombreOrigen = trim($origen->apellido . ', ' . $origen->nombre);
        $this->cerrarModalCopiar();
        $this->js('window.seSwalExito(' . json_encode("Permisos copiados desde {$nombreOrigen} al usuario seleccionado.", JSON_UNESCAPED_UNICODE) . ')');
    }

    private function maxOrdenPermisos(): int
    {
        return PermisosIaCatalog::maxOrden();
    }

    private function normalizarCadenaPermisos(string $cadena): string
    {
        $maxOrden = $this->maxOrdenPermisos();
        $cadena = trim($cadena);
        if ($cadena === '') {
            return str_repeat('0', $maxOrden + 1);
        }
        if (strlen($cadena) <= $maxOrden) {
            return str_pad($cadena, $maxOrden + 1, '0', STR_PAD_RIGHT);
        }

        return $cadena;
    }

    /**
     * @return Collection<int, Profesor>
     */
    private function usuariosDelNivel(?string $busqueda = null): Collection
    {
        $nivel = (int) (schoolCtx()->idNivel ?? 0);
        $term = trim((string) ($busqueda ?? ''));

        return Profesor::query()
            ->where('nivel', $nivel)
            ->elegiblesParaPermisosIa()
            ->when($term !== '', function ($q) use ($term) {
                $like = '%' . $term . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('apellido', 'like', $like)
                        ->orWhere('nombre', 'like', $like)
                        ->orWhere('dni', 'like', $like);
                });
            })
            ->with(['tipo:id,tipo'])
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'dni', 'nombre', 'apellido', 'IdTipoProf']);
    }

    public function render()
    {
        $nivel = (int) (schoolCtx()->idNivel ?? 0);
        $usuarios = $this->usuariosDelNivel($this->q)->take(200)->values();

        $profesorSeleccionado = null;
        if ($this->profesorId) {
            $profesorSeleccionado = Profesor::query()
                ->where('nivel', $nivel)
                ->elegiblesParaPermisosIa()
                ->whereKey($this->profesorId)
                ->with(['tipo:id,tipo'])
                ->first(['id', 'dni', 'nombre', 'apellido', 'IdTipoProf']);
            if ($profesorSeleccionado === null) {
                $this->profesorId = null;
            }
        }

        return view('livewire.administracion.permisos.usuarios-index', [
            'usuarios' => $usuarios,
            'usuariosModal' => $this->showModalCopiar ? $this->usuariosDelNivel() : collect(),
            'profesorSeleccionado' => $profesorSeleccionado,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Permisos de Usuarios']);
    }
}
