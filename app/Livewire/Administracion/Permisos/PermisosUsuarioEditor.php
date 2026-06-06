<?php

namespace App\Livewire\Administracion\Permisos;

use App\Models\PermisoIa;
use App\Models\Profesor;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Panel de edición de permisos de un solo usuario (componente hijo; se remonta al cambiar profesorId).
 */
class PermisosUsuarioEditor extends Component
{
    public int $profesorId;

    public string $permisosCadena = '';

    public function mount(int $profesorId): void
    {
        abort_unless(tienePermiso(0), 403, 'Sin permiso para administrar permisos.');
        $this->profesorId = $profesorId;
        $this->recargarCadenaDesdeBd();
    }

    public function togglePermiso(int $orden): void
    {
        abort_unless(tienePermiso(0), 403);

        $key = 'permisos:toggle:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->js('window.seSwalAviso(' . json_encode('Demasiados cambios seguidos. Espere un momento e intente nuevamente.', JSON_UNESCAPED_UNICODE) . ')');

            return;
        }
        RateLimiter::hit($key, 60);

        $cadena = $this->normalizarCadenaPermisos($this->permisosCadena);
        $chars = str_split($cadena);
        $chars[$orden] = (isset($cadena[$orden]) && $cadena[$orden] === '1') ? '0' : '1';
        $this->permisosCadena = implode('', $chars);

        $this->persistirPermisosCadena();
    }

    private function recargarCadenaDesdeBd(): void
    {
        $profesor = Profesor::query()
            ->where('nivel', (int) (schoolCtx()->idNivel ?? 0))
            ->whereKey($this->profesorId)
            ->firstOrFail(['id', 'permisos_ia']);

        $this->permisosCadena = $this->normalizarCadenaPermisos((string) ($profesor->permisos_ia ?? ''));
    }

    private function persistirPermisosCadena(): void
    {
        $cadena = $this->normalizarCadenaPermisos($this->permisosCadena);

        $actualizado = Profesor::query()
            ->where('nivel', (int) (schoolCtx()->idNivel ?? 0))
            ->whereKey($this->profesorId)
            ->update(['permisos_ia' => $cadena]);

        abort_unless($actualizado === 1, 404);

        if ($this->profesorId === (int) (schoolCtx()->idProfesor ?? 0)) {
            schoolCtx()->refreshProfesor();
        }
    }

    private function maxOrdenPermisos(): int
    {
        return max(
            (int) (PermisoIa::query()->max('orden') ?? 0),
            PermisosIaCatalog::maxOrden(),
        );
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
     * @return array<string, Collection<int, PermisoIa>>
     */
    private function permisosPorTema(): array
    {
        $catalogo = PermisoIa::query()
            ->orderBy('orden')
            ->get(['id', 'orden', 'tema', 'descripcion']);

        return $catalogo
            ->groupBy(function (PermisoIa $p) {
                $tema = trim((string) ($p->tema ?? ''));

                return $tema !== '' ? $tema : 'OTROS';
            })
            ->map(fn (Collection $items) => $items->sortBy('orden')->values())
            ->sortKeysUsing(fn (string $a, string $b) => strcasecmp($a, $b))
            ->all();
    }

    public function render()
    {
        return view('livewire.administracion.permisos.usuario-editor', [
            'porTema' => $this->permisosPorTema(),
        ]);
    }
}
