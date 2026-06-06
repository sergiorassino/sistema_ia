<?php

namespace App\Livewire\Parametrizacion;

use App\Livewire\Concerns\RequiresPermisoConfiguracion;
use App\Models\CampoAspirante;
use App\Models\CampoAspiranteNivel;
use App\Models\Nivel;
use App\Support\Aspirantes\CampoAspiranteOpciones;
use App\Support\Aspirantes\CamposAspirantesSync;
use App\Support\PermisosConfiguracion;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class CamposAspirantesIndex extends Component
{
    use RequiresPermisoConfiguracion;

    protected function permisoConfigOrden(): int
    {
        return PermisosConfiguracion::ASPIRANTES_CAMPOS;
    }

    /** '' = todas; 'visibles' = solo visibles; 'ocultas' = solo no visibles. */
    public string $filtro = '';

    public ?int $idNivel = null;

    public function mount(): void
    {
        $this->idNivel = (int) (schoolCtx()->idNivel ?? 0) ?: null;
    }

    public function sincronizarDesdeAspirantes(CamposAspirantesSync $sync): void
    {
        $r = $sync->sincronizarDesdeSchema();
        $i = $r['insertados'];
        $e = $r['eliminados'];

        if ($i === 0 && $e === 0) {
            session()->flash('status', 'La lista ya está alineada con la tabla aspirantes: no hay columnas nuevas ni obsoletas.');
        } elseif ($i > 0 && $e > 0) {
            session()->flash('status', "Se agregaron {$i} columna(s) nueva(s) y se quitaron {$e} que ya no existen en aspirantes.");
        } elseif ($i > 0) {
            session()->flash('status', "Se agregaron {$i} columna(s) nueva(s) desde la tabla aspirantes.");
        } else {
            session()->flash('status', "Se quitaron {$e} campo(s) que ya no existen en la tabla aspirantes.");
        }
    }

    public function setVisible(int $id, mixed $valor): void
    {
        $c = CampoAspirante::query()->whereKey($id)->firstOrFail();
        $idNivel = (int) ($this->idNivel ?? 0);
        if ($idNivel <= 0 || ! Schema::hasTable('campos_aspirantes_nivel')) {
            return;
        }

        $r = CampoAspiranteNivel::query()->firstOrNew([
            'campo_aspirante_id' => (int) $c->getKey(),
            'idNivel'            => $idNivel,
        ]);
        $r->visible = (bool) $valor;
        if (! $r->visible) {
            $r->obligatorio = false;
        }
        $r->save();
    }

    public function setObligatorio(int $id, mixed $valor): void
    {
        $c = CampoAspirante::query()->whereKey($id)->firstOrFail();
        $idNivel = (int) ($this->idNivel ?? 0);
        if ($idNivel <= 0 || ! Schema::hasTable('campos_aspirantes_nivel')) {
            return;
        }

        $r = CampoAspiranteNivel::query()->firstOrNew([
            'campo_aspirante_id' => (int) $c->getKey(),
            'idNivel'            => $idNivel,
        ]);
        if (! $r->visible) {
            return;
        }
        $r->obligatorio = (bool) $valor;
        $r->save();
    }

    public function setEtiqueta(int $id, mixed $valor): void
    {
        if (! Schema::hasColumn('campos_aspirantes_nivel', 'etiqueta')) {
            return;
        }

        $r = $this->registroNivelParaCampo($id);
        if (! $r) {
            return;
        }

        $t = is_string($valor) ? trim($valor) : '';
        $r->etiqueta = $t === '' ? null : mb_substr($t, 0, 100);
        $r->save();
    }

    public function setOpciones(int $id, mixed $valor): void
    {
        if (! Schema::hasColumn('campos_aspirantes_nivel', 'opciones')) {
            return;
        }

        $r = $this->registroNivelParaCampo($id);
        if (! $r) {
            return;
        }

        $r->opciones = CampoAspiranteOpciones::normalizarEntrada(is_string($valor) ? $valor : null);
        $r->save();
    }

    public function setAyuda(int $id, mixed $valor): void
    {
        if (! Schema::hasColumn('campos_aspirantes_nivel', 'ayuda')) {
            return;
        }

        $r = $this->registroNivelParaCampo($id);
        if (! $r) {
            return;
        }

        $t = is_string($valor) ? trim($valor) : '';
        $r->ayuda = $t === '' ? null : mb_substr($t, 0, 500);
        $r->save();
    }

    private function registroNivelParaCampo(int $campoId): ?CampoAspiranteNivel
    {
        CampoAspirante::query()->whereKey($campoId)->firstOrFail();

        $idNivel = (int) ($this->idNivel ?? 0);
        if ($idNivel <= 0 || ! Schema::hasTable('campos_aspirantes_nivel')) {
            return null;
        }

        return CampoAspiranteNivel::query()->firstOrNew([
            'campo_aspirante_id' => $campoId,
            'idNivel'            => $idNivel,
        ]);
    }

    public function setOrden(int $id, mixed $orden): void
    {
        $c = CampoAspirante::query()->whereKey($id)->firstOrFail();
        $c->orden = max(0, (int) $orden);
        $c->save();
    }

    public function render()
    {
        $q = CampoAspirante::query();
        if ($this->filtro === 'visibles') {
            // filtrado se resuelve luego (puede depender del nivel)
        } elseif ($this->filtro === 'ocultas') {
            // filtrado se resuelve luego (puede depender del nivel)
        }

        $campos = $q->orderBy('orden')->orderBy('columna')->get();

        $niveles = Nivel::orderBy('id')->get();

        $idNivel = (int) ($this->idNivel ?? 0);
        $porNivel = collect();
        if ($idNivel > 0 && Schema::hasTable('campos_aspirantes_nivel')) {
            $porNivel = CampoAspiranteNivel::query()
                ->where('idNivel', $idNivel)
                ->get()
                ->keyBy('campo_aspirante_id');
        }

        $campos = $campos->map(function (CampoAspirante $c) use ($porNivel) {
            $r = $porNivel->get((int) $c->getKey());
            $c->visible = $r ? (bool) $r->visible : false;
            $c->obligatorio = $r ? (bool) $r->obligatorio : false;
            $c->etiqueta = $r?->etiqueta;
            $c->opciones = $r?->opciones;
            $c->ayuda = $r?->ayuda;
            return $c;
        });

        if ($this->filtro === 'visibles') {
            $campos = $campos->filter(fn (CampoAspirante $c) => (bool) $c->visible)->values();
        } elseif ($this->filtro === 'ocultas') {
            $campos = $campos->filter(fn (CampoAspirante $c) => ! (bool) $c->visible)->values();
        }

        return view('livewire.parametrizacion.campos-aspirantes-index', compact('campos', 'niveles', 'idNivel'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Campos activos (Aspirantes)']);
    }
}
