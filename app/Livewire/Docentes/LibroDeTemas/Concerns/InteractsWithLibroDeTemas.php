<?php

namespace App\Livewire\Docentes\LibroDeTemas\Concerns;

use App\Support\LibroDeTemas\LibroDeTemasService;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\PortalDocenteContext;

trait InteractsWithLibroDeTemas
{
    public bool $modoPortalDocente = false;

    protected function inicializarLibroDeTemas(): void
    {
        abort_unless(tenantLibroDeTemasHabilitado(), 404);

        $this->modoPortalDocente = LibroDeTemasService::esPortalDocente();

        if (LibroDeTemasService::esPortalDocente()) {
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
        return LibroDeTemasService::esPortalDocente();
    }

    public function layoutLibroDeTemas(): string
    {
        return LibroDeTemasService::esPortalDocente() ? 'layouts.docente' : layoutMenuStaff();
    }

    public function rutaIndiceLibroDeTemas(): string
    {
        return LibroDeTemasService::esPortalDocente()
            ? 'portalDocente.libroDeTemas'
            : 'docentes.libro-de-temas';
    }

    public function rutaClasesLibroDeTemas(): string
    {
        return LibroDeTemasService::esPortalDocente()
            ? 'portalDocente.libroDeTemas.clases'
            : 'docentes.libro-de-temas.clases';
    }
}
