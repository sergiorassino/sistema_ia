<?php

namespace App\Livewire\Cuotas;

use App\Models\Cuota;
use App\Support\Cuotas\CuotasImportesCatalog;
use App\Support\Navegacion\ContextoCuotasImportesSesion;
use App\Support\PermisosCuotas;
use Livewire\Component;

/**
 * Listado de plantillas de cuota del ciclo activo — acceso a importes por curso.
 */
class CuotasImportesIndex extends Component
{
    public string $search = '';

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);
    }

    public function abrirEditor(int $idCuotas): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);

        CuotasImportesCatalog::cuotaDelCicloOrFail($idCuotas);
        ContextoCuotasImportesSesion::fijar($idCuotas);

        $this->redirectRoute('cuotas.importes.editar', navigate: true);
    }

    public function render()
    {
        $idTerlec = CuotasImportesCatalog::idTerlecActivo();
        $ano = (int) schoolCtx()->terlecAno();

        $query = Cuota::query()
            ->where('idTerlec', $idTerlec)
            ->orderBy('orden')
            ->orderBy('id');

        $q = mb_strtolower(trim($this->search));
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->whereRaw('LOWER(nombre) LIKE ?', ['%'.$q.'%']);
            });
        }

        return view('livewire.cuotas.importes-index', [
            'cuotas' => $query->get(),
            'ano' => $ano,
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Importes por curso — {$ano}"]);
    }
}
