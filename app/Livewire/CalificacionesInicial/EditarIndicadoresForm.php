<?php

namespace App\Livewire\CalificacionesInicial;

use App\Support\CalificacionesInicial\CalificacionesInicialIndicadoresCatalogo;
use App\Support\CalificacionesInicial\CalificacionesInicialIndicadoresDatos;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\CalificacionesInicialPortalDocente;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Edición de indicadores de un espacio curricular: ambas etapas en pantalla (tabla `indicadores`).
 */
class EditarIndicadoresForm extends Component
{
    public bool $modoPortalDocente = false;

    public int $idMateria;

    public string $materiaNombre = '';

    public string $cursoLabel = '';

    /**
     * Texto por etapa (clave = 1, 2, …).
     *
     * @var array<int, string>
     */
    public array $textosPorEtapa = [];

    public function mount(int $materia): void
    {
        $this->modoPortalDocente = CalificacionesInicialPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiMenuInactivo(CalificacionesInicialPortalDocente::MENU_INDICADORES);
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(
                PermisosIaCatalog::CALIF_CARGA,
                'Sin permiso para calificaciones.',
            );
        }

        CalificacionesInicialPortalDocente::abortSiNoEsInicial();
        CalificacionesInicialIndicadoresCatalogo::abortSiTablaInexistente();

        $ctx = schoolCtx();
        $mat = CalificacionesInicialIndicadoresDatos::materiaEnContexto(
            $materia,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );
        abort_if($mat === null, 404);

        if ($this->modoPortalDocente) {
            CalificacionesInicialPortalDocente::abortSiProfesorSinMateria(
                (int) $mat->id,
                (int) $mat->idCursos,
            );
        }

        $this->idMateria = (int) $mat->id;
        $this->materiaNombre = (string) $mat->materia;
        $this->cursoLabel = (string) $mat->cursoLabel;
        $this->cargarDesdeBd();
    }

    protected function cargarDesdeBd(): void
    {
        $this->textosPorEtapa = CalificacionesInicialIndicadoresDatos::textosPorEtapa($this->idMateria);
    }

    public function guardar(): void
    {
        if (! $this->modoPortalDocente) {
            PortalDocenteContext::abortSiStaffSinPermisoIa(PermisosIaCatalog::CALIF_CARGA);
        }

        $key = 'calif-inicial-indicadores:save:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $rules = [];
        foreach (CalificacionesInicialIndicadoresCatalogo::etapasDisponibles() as $etapa) {
            $rules["textosPorEtapa.{$etapa}"] = ['nullable', 'string'];
        }
        $this->validate($rules);

        try {
            CalificacionesInicialIndicadoresDatos::guardarTextos($this->idMateria, $this->textosPorEtapa);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('se-swal-error', mensaje: 'No se pudieron guardar los indicadores. Verifique los datos e intente nuevamente.');

            return;
        }

        session()->flash('success', 'Indicadores guardados correctamente.');

        $this->redirect(
            CalificacionesInicialPortalDocente::route('indicadores'),
            navigate: true,
        );
    }

    public function render()
    {
        return view('livewire.calificaciones-inicial.editar-indicadores-form', [
            'etapas' => CalificacionesInicialIndicadoresCatalogo::etapasDisponibles(),
        ])->layout(CalificacionesInicialPortalDocente::layout(), ['pageTitle' => 'Indicadores — espacio curricular']);
    }
}
