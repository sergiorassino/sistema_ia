<?php

namespace App\Livewire\Docentes\CertificacionServicios;

use App\Support\CertificacionServicios\CertificacionServicios;
use App\Support\PermisosIaCatalog;
use Livewire\Component;
use Livewire\WithPagination;

class CertificacionServiciosIndex extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    public string $buscar = '';

    /** @var array<string, array{except?: mixed}> */
    protected $queryString = [
        'buscar' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CERTIFICACION_SERVICIOS), 403, 'Sin permiso para certificación de servicios.');
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tablasOk = CertificacionServicios::tablasDisponibles();

        return view('livewire.docentes.certificacion-servicios.index', [
            'profesores' => $tablasOk
                ? CertificacionServicios::paginarProfesores($this->buscar, self::POR_PAGINA)
                : null,
            'tablasOk' => $tablasOk,
            'mensajeTablas' => $tablasOk ? '' : CertificacionServicios::mensajeTablasFaltantes(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Certificación de servicios']);
    }
}
