<?php

namespace App\Livewire\Abm\Legajos;

use App\Models\Familia;
use App\Models\Legajo;
use Illuminate\Support\Collection;
use Livewire\Component;

class LegajoBuscarFamilias extends Component
{
    public string $filtroFamilias = '';

    public ?int $familiaSeleccionadaId = null;

    private const MIN_CHARS_BUSQUEDA = 2;

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'familiaSeleccionadaId' => ['except' => null, 'as' => 'familia'],
    ];

    public function mount(): void
    {
        if ($this->familiaSeleccionadaId !== null && $this->familiaSeleccionadaId > 0) {
            $this->validarFamiliaSeleccionada();
        }
    }

    public function updatedFiltroFamilias(): void
    {
        // Mantener la familia elegida si sigue en resultados; si no, limpiar.
        if ($this->familiaSeleccionadaId === null) {
            return;
        }

        $filtro = trim($this->filtroFamilias);
        if (mb_strlen($filtro) < self::MIN_CHARS_BUSQUEDA) {
            return;
        }

        $sigueVisible = $this->familiasCoincidentesQuery()
            ->whereKey($this->familiaSeleccionadaId)
            ->exists();

        if (! $sigueVisible) {
            $this->familiaSeleccionadaId = null;
        }
    }

    public function seleccionarFamilia(int $id): void
    {
        abort_unless($id > 0 && $id !== LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR, 422);
        Familia::query()->findOrFail($id);
        $this->familiaSeleccionadaId = $id;
    }

    public function limpiarSeleccion(): void
    {
        $this->familiaSeleccionadaId = null;
    }

    private function validarFamiliaSeleccionada(): void
    {
        $id = (int) $this->familiaSeleccionadaId;
        if ($id <= 0 || $id === LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR) {
            $this->familiaSeleccionadaId = null;

            return;
        }

        if (! Familia::query()->whereKey($id)->exists()) {
            $this->familiaSeleccionadaId = null;
        }
    }

    private function familiasCoincidentesQuery()
    {
        $filtro = trim($this->filtroFamilias);

        return Familia::query()
            ->whereKeyNot(LegajoFamilia::ID_FAMILIA_SIN_ASIGNAR)
            ->when($filtro !== '', function ($q) use ($filtro) {
                $q->where(function ($sub) use ($filtro) {
                    $sub->where('apellido', 'like', "%{$filtro}%")
                        ->orWhere('responsable', 'like', "%{$filtro}%")
                        ->orWhere('email', 'like', "%{$filtro}%");

                    if (ctype_digit($filtro)) {
                        $sub->orWhere('id', (int) $filtro);
                    }
                });
            });
    }

    /** @return Collection<int, Familia> */
    private function familiasCoincidentes(): Collection
    {
        $filtro = trim($this->filtroFamilias);
        if (mb_strlen($filtro) < self::MIN_CHARS_BUSQUEDA) {
            return collect();
        }

        return $this->familiasCoincidentesQuery()
            ->orderBy('apellido')
            ->orderBy('id')
            ->limit(50)
            ->get();
    }

    /** @return array<string, string> Etiquetas para columnas de `familias`. */
    public static function etiquetasColumnasFamilia(): array
    {
        return [
            'id' => 'ID',
            'apellido' => 'Apellido',
            'responsable' => 'Responsable',
            'email' => 'Email',
        ];
    }

    public function render()
    {
        $familia = null;
        $hijos = collect();

        if ($this->familiaSeleccionadaId) {
            $familia = Familia::query()->find($this->familiaSeleccionadaId);

            if ($familia) {
                $hijos = Legajo::query()
                    ->where('idFamilias', $familia->id)
                    ->with([
                        'matriculas' => function ($q) {
                            $q->with(['terlec', 'curso', 'condicion', 'nivel'])
                                ->leftJoin('terlec', 'terlec.id', '=', 'matricula.idTerlec')
                                ->orderBy('terlec.ano')
                                ->orderBy('matricula.id')
                                ->select('matricula.*');
                        },
                    ])
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->get(['id', 'apellido', 'nombre', 'dni', 'legajo', 'idFamilias']);
            }
        }

        return view('livewire.abm.legajos.buscar-familias', [
            'familiasCoincidentes' => $this->familiasCoincidentes(),
            'familia' => $familia,
            'hijos' => $hijos,
            'minCharsBusqueda' => self::MIN_CHARS_BUSQUEDA,
            'etiquetasFamilia' => self::etiquetasColumnasFamilia(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Buscar familias']);
    }
}
