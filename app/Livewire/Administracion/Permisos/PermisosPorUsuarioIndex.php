<?php

namespace App\Livewire\Administracion\Permisos;

use App\Models\PermisoIa;
use App\Support\PermisosConfiguracion;
use App\Support\PermisosIaCatalog;
use App\Models\Profesor;
use Illuminate\Support\Collection;
use Livewire\Component;

class PermisosPorUsuarioIndex extends Component
{
    /** IdTipoProf que identifica «Sin Rol» en la tabla profesortipo. */
    private const ID_TIPO_SIN_ROL = 1;

    public string $q = '';

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosConfiguracion::PERMISOS_POR_USUARIO), 403, 'Sin permiso para consultar permisos por usuario.');
    }

    /**
     * @return array<int, string>
     */
    private function catalogoPorOrden(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        PermisoIa::query()
            ->orderBy('orden')
            ->get(['orden', 'descripcion'])
            ->each(function (PermisoIa $perm) use (&$cache) {
                $orden = (int) $perm->orden;
                $desc = trim((string) ($perm->descripcion ?? ''));
                $aviso = PermisosIaCatalog::AVISO_NO_OTORGAR_ADMIN;
                $reservado = PermisosIaCatalog::esReservadoAdministrador($orden);
                $base = $reservado ? trim(str_replace($aviso, '', $desc), " \t.") : $desc;
                if ($base === '') {
                    $base = 'Orden '.$orden;
                }
                if ($reservado) {
                    $base = 'NO OTORGAR · '.$base;
                }
                if (strlen($base) > 52) {
                    $base = substr($base, 0, 49).'…';
                }
                $cache[$orden] = $base;
            });

        return $cache;
    }

    /**
     * @return list<string>
     */
    private function permisosConcedidosDeCadena(string $cadena, array $catalogoPorOrden): array
    {
        $concedidos = [];
        $maxOrden = $catalogoPorOrden !== [] ? max(array_keys($catalogoPorOrden)) : strlen($cadena) - 1;

        foreach (range(0, max(0, $maxOrden)) as $orden) {
            if (isset($cadena[$orden]) && $cadena[$orden] === '1') {
                $concedidos[] = $catalogoPorOrden[$orden] ?? ('Orden ' . $orden);
            }
        }

        return $concedidos;
    }

    private function cadenaNormalizada(?string $cadena, int $maxOrden): string
    {
        $cadena = trim((string) $cadena);
        if ($cadena === '') {
            return str_repeat('0', $maxOrden + 1);
        }
        if (strlen($cadena) <= $maxOrden) {
            return str_pad($cadena, $maxOrden + 1, '0', STR_PAD_RIGHT);
        }

        return $cadena;
    }

    /**
     * @return Collection<int, array{profesor: Profesor, permisos: list<string>}>
     */
    private function filas(): Collection
    {
        $nivel = (int) (schoolCtx()->idNivel ?? 0);
        $catalogoPorOrden = $this->catalogoPorOrden();
        $maxOrden = $catalogoPorOrden !== [] ? max(array_keys($catalogoPorOrden)) : 0;

        $usuarios = Profesor::query()
            ->where('nivel', $nivel)
            ->where(function ($w) {
                $w->whereNull('IdTipoProf')
                    ->orWhere('IdTipoProf', '<>', self::ID_TIPO_SIN_ROL);
            })
            ->when(trim($this->q) !== '', function ($q) {
                $term = '%' . trim($this->q) . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('apellido', 'like', $term)
                        ->orWhere('nombre', 'like', $term)
                        ->orWhere('dni', 'like', $term);
                });
            })
            ->with(['tipo:id,tipo'])
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get(['id', 'dni', 'nombre', 'apellido', 'IdTipoProf', 'permisos_ia']);

        return $usuarios
            ->map(function (Profesor $profesor) use ($catalogoPorOrden, $maxOrden) {
                $cadena = $this->cadenaNormalizada($profesor->permisos_ia, $maxOrden);
                $permisos = $this->permisosConcedidosDeCadena($cadena, $catalogoPorOrden);

                return [
                    'profesor' => $profesor,
                    'permisos' => $permisos,
                ];
            })
            ->filter(fn (array $row) => $row['permisos'] !== [])
            ->values();
    }

    public function render()
    {
        $filas = $this->filas();

        return view('livewire.administracion.permisos.por-usuario-index', [
            'filas' => $filas,
            'totalUsuarios' => $filas->count(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Permisos por Usuario']);
    }
}
