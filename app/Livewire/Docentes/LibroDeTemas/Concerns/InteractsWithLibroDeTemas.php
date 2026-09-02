<?php

namespace App\Livewire\Docentes\LibroDeTemas\Concerns;

use App\Support\LibroDeTemas\LibroDeTemasService;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\PortalDocenteContext;
use Livewire\Attributes\Locked;

trait InteractsWithLibroDeTemas
{
    /**
     * Fijado en mount() desde la ruta HTTP inicial.
     * En peticiones Livewire (`wire:model` / `wire:click`) `request()->routeIs()` no es fiable.
     */
    #[Locked]
    public bool $modoPortalDocente = false;

    protected function inicializarLibroDeTemas(): void
    {
        abort_unless(tenantLibroDeTemasHabilitado(), 404);

        $this->modoPortalDocente = request()->routeIs('portalDocente.*');

        if ($this->modoPortalDocente) {
            abort_unless(tenantPortalDocenteLibroDeTemas(), 404);
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::LIBRO_DE_TEMAS,
                'Sin permiso para el libro de temas.',
            );
        }

        abort_unless(
            LibroDeTemasService::tablaDisponible(),
            404,
            LibroDeTemasService::mensajeTablaFaltante(),
        );
    }

    protected function soloPpcDelProfesor(): bool
    {
        return $this->modoPortalDocente;
    }

    public function layoutLibroDeTemas(): string
    {
        return $this->modoPortalDocente ? 'layouts.docente' : layoutMenuStaff();
    }

    public function rutaIndiceLibroDeTemas(): string
    {
        return $this->modoPortalDocente
            ? 'portalDocente.libroDeTemas'
            : 'docentes.libro-de-temas';
    }

    public function rutaClasesLibroDeTemas(): string
    {
        return $this->modoPortalDocente
            ? 'portalDocente.libroDeTemas.clases'
            : 'docentes.libro-de-temas.clases';
    }
}
