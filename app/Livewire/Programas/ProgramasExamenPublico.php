<?php

namespace App\Livewire\Programas;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Descarga pública de programas de examen (sin login).
 *
 * El alumno elige un año del menú (configurado por tenant en
 * `config/tenants/{slug}.php` → `programas_examen.anios`) y ve la grilla de la
 * tabla legacy `pp{año}` con un enlace al PDF alojado en el servidor de archivos
 * externo. No hay sesión: el acceso es público y la pestaña se cierra al terminar.
 */
#[Layout('layouts.programas-examen')]
class ProgramasExamenPublico extends Component
{
    public ?int $anio = null;

    public function mount(): void
    {
        abort_unless(tenantProgramasExamenHabilitado(), 404);
    }

    /**
     * Años ofrecidos, del más reciente al más antiguo.
     *
     * @return list<int>
     */
    public function aniosDisponibles(): array
    {
        $anios = (array) config('tenant.programas_examen.anios', []);
        $anios = array_values(array_unique(array_filter(array_map('intval', $anios))));
        rsort($anios);

        return $anios;
    }

    public function elegirAnio(int $anio): void
    {
        if (in_array($anio, $this->aniosDisponibles(), true)) {
            $this->anio = $anio;
        }
    }

    public function volver(): void
    {
        $this->anio = null;
    }

    /**
     * Filas de la tabla `pp{año}` para el año elegido, con enlace ya resuelto.
     *
     * @return Collection<int, object>
     */
    public function programas(): Collection
    {
        if ($this->anio === null || ! in_array($this->anio, $this->aniosDisponibles(), true)) {
            return collect();
        }

        $tabla = 'pp'.$this->anio;
        if (! Schema::hasTable($tabla)) {
            return collect();
        }

        $filas = DB::table($tabla)
            ->select('id', 'nombreMateria', 'curso', 'seccion', 'programa', 'progr_nom')
            ->orderByRaw('CAST(curso AS UNSIGNED)')
            ->orderBy('curso')
            ->orderBy('seccion')
            ->orderBy('id')
            ->get();

        $prefijo = $this->prefijoArchivos();

        return $filas->map(function (object $r) use ($prefijo) {
            $nombreArchivo = trim((string) ($r->progr_nom ?? ''));
            $tiene = (int) ($r->programa ?? 0) === 1 && $nombreArchivo !== '';

            $r->tiene_programa = $tiene;
            $r->texto_programa = $nombreArchivo !== ''
                ? $nombreArchivo
                : (string) $r->nombreMateria;

            if ($tiene) {
                $archivoPdf = $nombreArchivo;
                if (! str_ends_with(strtolower($archivoPdf), '.pdf')) {
                    $archivoPdf .= '.pdf';
                }
                $r->url_programa = $prefijo.rawurlencode($archivoPdf);
            } else {
                $r->url_programa = null;
            }

            return $r;
        });
    }

    /**
     * Prefijo de la carpeta de programas en el servidor de archivos externo:
     * `{base_url}/{glo_codcol}/{nivel}/{año}/programas/`.
     */
    private function prefijoArchivos(): string
    {
        $base = rtrim((string) config('tenant.programas_examen.base_url', 'https://sistesco.site/archivos'), '/');
        $codcol = (string) (config('tenant.programas_examen.glo_codcol') ?: config('tenant.slug', ''));
        $nivel = (string) config('tenant.programas_examen.nivel', 'secu');

        return $base
            .'/'.rawurlencode($codcol)
            .'/'.rawurlencode($nivel)
            .'/'.$this->anio
            .'/programas/';
    }

    public function render()
    {
        return view('livewire.programas.programas-examen-publico', [
            'anios' => $this->aniosDisponibles(),
            'programas' => $this->programas(),
        ]);
    }
}
