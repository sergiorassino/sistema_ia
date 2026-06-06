<?php

namespace App\Livewire\Cuotas;

use App\Support\Cuotas\GestionAranceles;
use App\Support\Cuotas\SolicitudAyudaFamiliarService;
use App\Support\PermisosCuotas;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Búsqueda de estudiante e impresión de Solicitud de Ayuda Familiar — Administración / Becas.
 */
class SolicitudAyudaFamiliarIndex extends Component
{
    use WithPagination;

    public string $search = '';

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'search' => ['except' => '', 'as' => 'buscar'],
    ];

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeSolicitudAyudaFamiliar(), 403, 'Sin permiso para solicitud de ayuda familiar.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function imprimirSolicitud(int $idLegajo): void
    {
        abort_unless(PermisosCuotas::puedeSolicitudAyudaFamiliar(), 403);

        if (GestionAranceles::legajoParaGestion($idLegajo) === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el estudiante solicitado.');

            return;
        }

        $rateKey = 'cuotas:solicitud-ayuda-familiar:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        try {
            $nro = SolicitudAyudaFamiliarService::reservarNumero($idLegajo);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('se-swal-error', mensaje: 'No se pudo generar el número de solicitud. Verifique la configuración del sistema.');

            return;
        }

        $url = se_route_url('cuotas.solicitud-ayuda-familiar.pdf', [
            'ref' => OpaqueRouteToken::forSolicitudAyudaFamiliar($nro, $idLegajo),
        ]);

        $this->dispatch('cuotas-solicitud-ayuda-familiar-abrir-pdf', url: $url);
    }

    public function render()
    {
        $legajos = trim($this->search) !== ''
            ? GestionAranceles::buscarLegajos($this->search)
            : null;

        return view('livewire.cuotas.solicitud-ayuda-familiar', [
            'legajos' => $legajos,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Solicitud de Ayuda Familiar']);
    }
}
