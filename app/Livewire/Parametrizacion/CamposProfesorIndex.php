<?php

namespace App\Livewire\Parametrizacion;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Support\PermisosConfiguracion;
use App\Listados\CamposProfesorSync;
use App\Models\CampoProfesor;
use App\Models\SolapaLegajoProfesor;
use Livewire\Component;

class CamposProfesorIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::CAMPOS_LEGAJO_DOCENTE;
    }

    public string $filtroSolapa = '';

    public function sincronizarDesdeProfesores(CamposProfesorSync $sync): void
    {
        $r = $sync->sincronizarDesdeSchema();
        $i = $r['insertados'];
        $e = $r['eliminados'];

        if ($i === 0 && $e === 0) {
            session()->flash('status', 'La lista ya está alineada con la tabla profesores: no hay columnas nuevas ni obsoletas.');
        } elseif ($i > 0 && $e > 0) {
            session()->flash('status', "Se agregaron {$i} columna(s) nueva(s) y se quitaron {$e} que ya no existen en profesores.");
        } elseif ($i > 0) {
            session()->flash('status', "Se agregaron {$i} columna(s) nueva(s) desde la tabla profesores.");
        } else {
            session()->flash('status', "Se quitaron {$e} campo(s) que ya no existen en la tabla profesores.");
        }
    }

    public function setSolapa(int $id, mixed $solapaId): void
    {
        $c = CampoProfesor::query()->whereKey($id)->firstOrFail();
        if (in_array($c->columna, CampoProfesor::COLUMNAS_FIJAS_DOCENTE, true)) {
            return;
        }
        $c->solapa_legajo_profesor_id = ($solapaId !== '' && $solapaId !== null && (string) $solapaId !== '0')
            ? (int) $solapaId
            : null;
        $c->visible_listado = $c->solapa_legajo_profesor_id !== null;
        $c->save();
    }

    public function setOrden(int $id, mixed $orden): void
    {
        $c = CampoProfesor::query()->whereKey($id)->firstOrFail();
        if (in_array($c->columna, CampoProfesor::COLUMNAS_FIJAS_DOCENTE, true)) {
            return;
        }
        $c->orden_en_solapa = max(0, (int) $orden);
        $c->save();
    }

    public function setEtiqueta(int $id, mixed $valor): void
    {
        $c = CampoProfesor::query()->whereKey($id)->firstOrFail();
        if (in_array($c->columna, CampoProfesor::COLUMNAS_FIJAS_DOCENTE, true)) {
            return;
        }
        $t = is_string($valor) ? trim($valor) : '';
        $c->etiqueta = $t === '' ? null : mb_substr($t, 0, 100);
        $c->save();
    }

    public function render()
    {
        $q = CampoProfesor::query()
            ->whereNotIn('columna', CampoProfesor::COLUMNAS_FIJAS_DOCENTE)
            ->with('solapa');

        if ($this->filtroSolapa === '__sin__') {
            $q->whereNull('solapa_legajo_profesor_id')
                ->orderBy('orden')
                ->orderBy('columna');
        } elseif ($this->filtroSolapa !== '' && ctype_digit($this->filtroSolapa)) {
            $q->where('solapa_legajo_profesor_id', (int) $this->filtroSolapa)
                ->orderBy('orden_en_solapa')
                ->orderBy('columna');
        } else {
            $q->orderBy('orden')->orderBy('columna');
        }

        $campos = $q->get();

        $solapas = SolapaLegajoProfesor::query()
            ->orderBy('orden')
            ->get(['id', 'nombre']);

        return view('listados::parametrizacion.campos-legajo-profesor-index', compact('campos', 'solapas'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Campos activos (Legajo del docente)']);
    }
}
