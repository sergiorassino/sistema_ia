<?php

namespace App\Livewire\Parametrizacion;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Listados\CamposLegajoSync;
use App\Models\CampoLegajo;
use App\Models\SolapaLegajo;
use Livewire\Component;

class CamposLegajoIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::CAMPOS_LEGAJO_ESTUDIANTE;
    }

    /** '' = todas las columnas; '__sin__' = solo sin solapa; caso contrario id numérico de `solapas_legajo`. */
    public string $filtroSolapa = '';

    public function sincronizarDesdeLegajos(CamposLegajoSync $sync): void
    {
        $r = $sync->sincronizarDesdeSchema();
        $i = $r['insertados'];
        $e = $r['eliminados'];

        if ($i === 0 && $e === 0) {
            session()->flash('status', 'La lista ya está alineada con la tabla legajos: no hay columnas nuevas ni obsoletas.');
        } elseif ($i > 0 && $e > 0) {
            session()->flash('status', "Se agregaron {$i} columna(s) nueva(s) y se quitaron {$e} que ya no existen en legajos.");
        } elseif ($i > 0) {
            session()->flash('status', "Se agregaron {$i} columna(s) nueva(s) desde la tabla legajos.");
        } else {
            session()->flash('status', "Se quitaron {$e} campo(s) que ya no existen en la tabla legajos.");
        }
    }

    /**
     * Asigna (o quita) una solapa al campo.
     * Al asignar → marca visible_listado=true para compatibilidad con el PDF.
     * Al quitar  → marca visible_listado=false.
     */
    public function setSolapa(int $id, mixed $solapaId): void
    {
        $c = CampoLegajo::query()->whereKey($id)->firstOrFail();
        if (in_array($c->columna, CampoLegajo::COLUMNAS_FIJAS_ALUMNO, true)) {
            return;
        }
        $c->solapa_legajo_id = ($solapaId !== '' && $solapaId !== null && (string) $solapaId !== '0')
            ? (int) $solapaId
            : null;
        $c->visible_listado = $c->solapa_legajo_id !== null;
        $c->save();
    }

    /**
     * Actualiza el orden del campo dentro de su solapa.
     */
    public function setOrden(int $id, mixed $orden): void
    {
        $c = CampoLegajo::query()->whereKey($id)->firstOrFail();
        if (in_array($c->columna, CampoLegajo::COLUMNAS_FIJAS_ALUMNO, true)) {
            return;
        }
        $c->orden_en_solapa = max(0, (int) $orden);
        $c->save();
    }

    /**
     * Texto del label en el formulario de legajo (y fallback coherente en PDF si aplica).
     * Vacío → se usa la etiqueta por defecto del catálogo de columnas.
     */
    public function setEtiqueta(int $id, mixed $valor): void
    {
        $c = CampoLegajo::query()->whereKey($id)->firstOrFail();
        if (in_array($c->columna, CampoLegajo::COLUMNAS_FIJAS_ALUMNO, true)) {
            return;
        }
        $t = is_string($valor) ? trim($valor) : '';
        $c->etiqueta = $t === '' ? null : mb_substr($t, 0, 100);
        $c->save();
    }

    public function render()
    {
        $q = CampoLegajo::query()
            ->whereNotIn('columna', CampoLegajo::COLUMNAS_FIJAS_ALUMNO)
            ->with('solapa');

        if ($this->filtroSolapa === '__sin__') {
            $q->whereNull('solapa_legajo_id')
                ->orderBy('orden')
                ->orderBy('columna');
        } elseif ($this->filtroSolapa !== '' && ctype_digit($this->filtroSolapa)) {
            $q->where('solapa_legajo_id', (int) $this->filtroSolapa)
                ->orderBy('orden_en_solapa')
                ->orderBy('columna');
        } else {
            $q->orderBy('orden')
                ->orderBy('columna');
        }

        $campos = $q->get();

        $solapas = SolapaLegajo::query()
            ->orderBy('orden')
            ->get(['id', 'nombre']);

        return view('listados::parametrizacion.campos-listado-alumnos-index', compact('campos', 'solapas'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Campos activos (Legajo del estudiante)']);
    }
}
