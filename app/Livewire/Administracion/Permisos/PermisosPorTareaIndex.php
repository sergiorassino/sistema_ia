<?php

namespace App\Livewire\Administracion\Permisos;

use App\Models\PermisoIa;
use App\Models\Profesor;
use App\Support\PermisosConfiguracion;
use Illuminate\Support\Collection;
use Livewire\Component;

class PermisosPorTareaIndex extends Component
{
    /** IdTipoProf que identifica «Sin Rol» en la tabla profesortipo. */
    private const ID_TIPO_SIN_ROL = 1;

    public string $q = '';

    public string $tema = '';

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosConfiguracion::PERMISOS_POR_TAREA), 403, 'Sin permiso para consultar permisos por tarea.');
    }

    /**
     * @return Collection<int, PermisoIa>
     */
    private function catalogo(): Collection
    {
        return PermisoIa::query()
            ->orderBy('orden')
            ->get(['orden', 'tema', 'descripcion']);
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
     * @return Collection<int, Profesor>
     */
    private function usuariosDelNivel(): Collection
    {
        $nivel = (int) (schoolCtx()->idNivel ?? 0);

        return Profesor::query()
            ->where('nivel', $nivel)
            ->where(function ($w) {
                $w->whereNull('IdTipoProf')
                    ->orWhere('IdTipoProf', '<>', self::ID_TIPO_SIN_ROL);
            })
            ->with(['tipo:id,tipo'])
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get(['id', 'dni', 'nombre', 'apellido', 'IdTipoProf', 'permisos_ia']);
    }

    /**
     * @return Collection<int, array{orden: int, tema: string, descripcion: string, usuarios: list<Profesor>}>
     */
    private function filasAsignadas(): Collection
    {
        $catalogo = $this->catalogo();
        $maxOrden = $catalogo->max('orden');
        $maxOrden = $maxOrden !== null ? (int) $maxOrden : 0;
        $usuarios = $this->usuariosDelNivel();

        $cadenas = $usuarios->mapWithKeys(function (Profesor $profesor) use ($maxOrden) {
            return [(int) $profesor->id => $this->cadenaNormalizada($profesor->permisos_ia, $maxOrden)];
        });

        return $catalogo
            ->map(function (PermisoIa $perm) use ($usuarios, $cadenas) {
                $orden = (int) $perm->orden;
                $tema = trim((string) ($perm->tema ?? ''));
                $desc = trim((string) ($perm->descripcion ?? ''));

                $conPermiso = $usuarios
                    ->filter(function (Profesor $profesor) use ($cadenas, $orden) {
                        $cadena = $cadenas[(int) $profesor->id] ?? '';

                        return isset($cadena[$orden]) && $cadena[$orden] === '1';
                    })
                    ->values()
                    ->all();

                return [
                    'orden' => $orden,
                    'tema' => $tema !== '' ? $tema : 'OTROS',
                    'descripcion' => $desc !== '' ? $desc : ('Orden ' . $orden),
                    'usuarios' => $conPermiso,
                ];
            })
            ->filter(fn (array $row) => $row['usuarios'] !== [])
            ->values();
    }

    /**
     * @param  Collection<int, array{orden: int, tema: string, descripcion: string, usuarios: list<Profesor>}>  $filas
     * @return Collection<int, array{orden: int, tema: string, descripcion: string, usuarios: list<Profesor>}>
     */
    private function aplicarFiltros(Collection $filas): Collection
    {
        $term = trim($this->q);
        $temaFiltro = trim($this->tema);

        return $filas
            ->filter(function (array $row) use ($term, $temaFiltro) {
                if ($temaFiltro !== '' && $row['tema'] !== $temaFiltro) {
                    return false;
                }
                if ($term === '') {
                    return true;
                }
                if ($this->textoContiene($row['tema'], $term) || $this->textoContiene($row['descripcion'], $term)) {
                    return true;
                }
                foreach ($row['usuarios'] as $usuario) {
                    $haystack = trim($usuario->apellido . ' ' . $usuario->nombre . ' ' . $usuario->dni);
                    if ($this->textoContiene($haystack, $term)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    private function textoContiene(string $haystack, string $needle): bool
    {
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }

    /**
     * @param  Collection<int, array{orden: int, tema: string, descripcion: string, usuarios: list<Profesor>}>  $filas
     * @return list<string>
     */
    private function temasDe(Collection $filas): array
    {
        return $filas
            ->pluck('tema')
            ->unique()
            ->sort(fn (string $a, string $b) => strcasecmp($a, $b))
            ->values()
            ->all();
    }

    public function render()
    {
        $asignadas = $this->filasAsignadas();
        $filas = $this->aplicarFiltros($asignadas);
        $porTema = $filas
            ->groupBy(fn (array $row) => $row['tema'])
            ->sortKeysUsing(fn (string $a, string $b) => strcasecmp($a, $b));

        return view('livewire.administracion.permisos.por-tarea-index', [
            'porTema' => $porTema,
            'temas' => $this->temasDe($asignadas),
            'totalTareas' => $filas->count(),
            'totalAsignadas' => $asignadas->count(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Permisos por Tarea']);
    }
}
