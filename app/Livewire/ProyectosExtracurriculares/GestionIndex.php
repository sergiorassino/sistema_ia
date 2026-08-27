<?php

namespace App\Livewire\ProyectosExtracurriculares;

use App\Support\PermisosIaCatalog;
use App\Support\ProyectosExtracurriculares\ExtActividadesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class GestionIndex extends Component
{
    use WithPagination;

    public const POR_PAGINA = 50;

    public string $buscar = '';

    public string $filtroEstado = '';

    public ?int $detalleId = null;

    public function mount(): void
    {
        abort_unless(
            tienePermiso(PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_APROBAR),
            403,
            'Sin permiso para gestionar proyectos extracurriculares.',
        );
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function verDetalle(int $id): void
    {
        $act = ExtActividadesService::scopedQuery()->whereKey($id)->first();
        if ($act === null) {
            $this->detalleId = null;
            $this->js('window.seSwalError('.json_encode('No se encontró el proyecto en el ciclo y nivel actuales.', JSON_UNESCAPED_UNICODE).')');

            return;
        }

        $this->detalleId = (int) $act->id;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    public function aprobar(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_APROBAR), 403);

        if (! RateLimiter::attempt('ext-proy-apr-'.(auth()->id() ?? 0), 30, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        ExtActividadesService::aprobar($id, (int) Auth::id());
        $this->dispatch('se-swal-exito', mensaje: 'Proyecto aprobado. Ya figura en el calendario escolar.');
    }

    public function volverAPendiente(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_APROBAR), 403);

        if (! RateLimiter::attempt('ext-proy-pend-'.(auth()->id() ?? 0), 30, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        ExtActividadesService::volverAPendiente($id);
        $this->dispatch('se-swal-exito', mensaje: 'El proyecto volvió a pendiente y salió del calendario.');
    }

    public function confirmarComunicar(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_APROBAR), 403);

        $act = ExtActividadesService::scopedQuery()->whereKey($id)->first();
        if ($act === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el proyecto en el ciclo y nivel actuales.');

            return;
        }
        if (! $act->estaAprobada()) {
            $this->dispatch('se-swal-error', mensaje: 'Solo se comunica un proyecto aprobado.');

            return;
        }

        $html = ExtActividadesService::htmlConfirmarComunicar($id, (int) Auth::id());
        if ($html === '') {
            $this->dispatch('se-swal-error', mensaje: 'No hay destinatarios distintos del remitente para este proyecto.');

            return;
        }

        $this->dispatch('ext-confirmar-comunicar', html: $html, id: $id);
    }

    public function comunicar(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_APROBAR), 403);

        if (! RateLimiter::attempt('ext-proy-com-'.(auth()->id() ?? 0), 15, fn () => true)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }

        $r = ExtActividadesService::comunicarInvolucrados($id);
        if (! $r['ok']) {
            $this->dispatch('se-swal-error', mensaje: $r['mensaje']);

            return;
        }

        if ($r['refuerzo_mail_desarrollo'] ?? false) {
            $this->dispatch(
                'se-swal-aviso',
                mensaje: $r['mensaje'],
                titulo: 'Correo desactivado (desarrollo)',
            );

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: $r['mensaje']);
    }

    public function render()
    {
        $tablasOk = ExtActividadesService::tablasDisponibles();
        $registros = null;
        $detalle = null;

        if ($tablasOk) {
            $q = ExtActividadesService::scopedQuery()
                ->with(['fechas', 'proponente', 'tipoRegistro'])
                ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")
                ->orderByDesc('id');
            $t = trim($this->buscar);
            if ($t !== '') {
                $q->where('nombre', 'like', '%'.$t.'%');
            }
            if (in_array($this->filtroEstado, ['pendiente', 'aprobado'], true)) {
                $q->where('estado', $this->filtroEstado);
            }
            $registros = $q->paginate(self::POR_PAGINA);

            if ($this->detalleId !== null) {
                try {
                    $detalle = ExtActividadesService::cargarCompleta($this->detalleId);
                } catch (\Throwable $e) {
                    report($e);
                    $this->detalleId = null;
                    $detalle = null;
                }
            }
        }

        return view('livewire.proyectos-extracurriculares.gestion-index', [
            'tablasOk' => $tablasOk,
            'mensajeTabla' => $tablasOk ? '' : ExtActividadesService::mensajeTablasFaltantes(),
            'registros' => $registros,
            'detalle' => $detalle,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Proyectos extracurriculares']);
    }
}
