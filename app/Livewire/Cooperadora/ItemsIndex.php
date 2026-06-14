<?php

namespace App\Livewire\Cooperadora;

use App\Models\CoopItemIngreso;
use App\Models\CoopRubroIngreso;
use App\Support\Cooperadora\CooperadoraConfig;
use App\Support\Cooperadora\PermisosCooperadora;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ItemsIndex extends Component
{
    public int|string $idRubro = '';

    public bool $mostrarHistorial = false;

    public bool $showModal = false;

    public ?int $editId = null;

    public string $nombre = '';

    public string $anio = '';

    public string $precio = '0';

    public string $orden = '0';

    public bool $activo = true;

    public function mount(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);
        $primer = CoopRubroIngreso::query()->orderBy('orden')->value('id');
        if ($primer) {
            $this->idRubro = (int) $primer;
        }
    }

    public function abrirNuevo(): void
    {
        abort_unless((int) $this->idRubro > 0, 422);
        $this->resetForm();
        $rubro = CoopRubroIngreso::query()->findOrFail((int) $this->idRubro);
        if ($rubro->es_anual) {
            $this->anio = (string) CooperadoraConfig::anioVigente();
        }
        $this->showModal = true;
    }

    public function abrirEditar(int $id): void
    {
        $item = CoopItemIngreso::query()->findOrFail($id);
        $this->editId = $id;
        $this->idRubro = (int) $item->id_rubro;
        $this->nombre = (string) $item->nombre;
        $this->anio = $item->anio !== null ? (string) $item->anio : '';
        $this->precio = number_format((float) $item->precio, 2, '.', '');
        $this->orden = (string) $item->orden;
        $this->activo = (bool) $item->activo;
        $this->showModal = true;
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function generarItemsAnio(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);
        abort_unless((int) $this->idRubro > 0, 422);

        $rubro = CoopRubroIngreso::query()->findOrFail((int) $this->idRubro);
        abort_unless($rubro->es_anual, 422);

        $anioNuevo = CooperadoraConfig::anioVigente();
        $anioAnterior = $anioNuevo - 1;

        $existentes = CoopItemIngreso::query()
            ->where('id_rubro', $rubro->id)
            ->where('anio', $anioNuevo)
            ->count();

        if ($existentes > 0) {
            return;
        }

        $base = CoopItemIngreso::query()
            ->where('id_rubro', $rubro->id)
            ->where('anio', $anioAnterior)
            ->orderBy('orden')
            ->get();

        if ($base->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($base, $rubro, $anioNuevo) {
            foreach ($base as $item) {
                CoopItemIngreso::query()->create([
                    'id_rubro' => $rubro->id,
                    'nombre' => $item->nombre,
                    'anio' => $anioNuevo,
                    'precio' => $item->precio,
                    'orden' => $item->orden,
                    'activo' => true,
                ]);
            }
        });
    }

    public function guardar(): void
    {
        abort_unless(PermisosCooperadora::puedeParametrizacion(), 403);

        $key = 'coop:items:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return;
        }
        RateLimiter::hit($key, 60);

        abort_unless((int) $this->idRubro > 0, 422);
        $rubro = CoopRubroIngreso::query()->findOrFail((int) $this->idRubro);

        $rules = [
            'nombre' => ['required', 'string', 'max:120'],
            'precio' => ['required', 'numeric', 'min:0'],
            'orden' => ['required', 'integer', 'min:0', 'max:999'],
            'activo' => ['boolean'],
        ];

        if ($rubro->es_anual) {
            $rules['anio'] = ['required', 'integer', 'min:2000', 'max:2100'];
        } else {
            $rules['anio'] = ['nullable'];
        }

        $validated = $this->validate($rules);

        $data = [
            'id_rubro' => $rubro->id,
            'nombre' => trim($validated['nombre']),
            'anio' => $rubro->es_anual ? (int) $validated['anio'] : null,
            'precio' => round((float) $validated['precio'], 2),
            'orden' => (int) $validated['orden'],
            'activo' => (bool) $validated['activo'],
        ];

        if ($this->editId) {
            CoopItemIngreso::query()->whereKey($this->editId)->update($data);
        } else {
            CoopItemIngreso::query()->create($data);
        }

        $this->cerrarModal();
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->nombre = '';
        $this->anio = '';
        $this->precio = '0';
        $this->orden = '0';
        $this->activo = true;
    }

    public function render()
    {
        $rubros = CoopRubroIngreso::query()->orderBy('orden')->orderBy('nombre')->get();
        $rubroSel = (int) $this->idRubro > 0
            ? CoopRubroIngreso::query()->find((int) $this->idRubro)
            : null;

        $query = CoopItemIngreso::query()->orderBy('orden')->orderBy('nombre');
        if ($rubroSel) {
            $query->where('id_rubro', $rubroSel->id);
            if ($rubroSel->es_anual && ! $this->mostrarHistorial) {
                $query->where('anio', CooperadoraConfig::anioVigente());
            }
        } else {
            $query->whereRaw('1 = 0');
        }

        $items = $query->get();

        return view('livewire.cooperadora.items-index', [
            'rubros' => $rubros,
            'rubroSel' => $rubroSel,
            'items' => $items,
            'anioVigente' => CooperadoraConfig::anioVigente(),
        ])->layout(ProfesorMenuPortal::layoutStaff(), ['pageTitle' => 'Cooperadora — Ítems de ingreso']);
    }
}
