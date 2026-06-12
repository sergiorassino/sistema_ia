<?php

namespace App\Livewire\CalificacionesInicial;

use App\Support\CalificacionesInicial\CalificacionesInicialIndicadoresCatalogo;
use App\Support\CalificacionesInicial\CalificacionesInicialIndicadoresDatos;
use App\Support\NivelSistema;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Edición de indicadores de un espacio curricular: ambas etapas en pantalla (tabla `indicadores`).
 */
class EditarIndicadoresForm extends Component
{
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
        abort_unless(tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA), 403, 'Sin permiso para calificaciones.');
        abort_unless(
            NivelSistema::esInicial((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel inicial.'
        );
        CalificacionesInicialIndicadoresCatalogo::abortSiTablaInexistente();

        $ctx = schoolCtx();
        $mat = CalificacionesInicialIndicadoresDatos::materiaEnContexto(
            $materia,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
        );
        abort_if($mat === null, 404);

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
        abort_unless(tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA), 403);

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

        $this->redirect(route('calificacionesInicial.indicadores'), navigate: true);
    }

    public function render()
    {
        return view('livewire.calificaciones-inicial.editar-indicadores-form', [
            'etapas' => CalificacionesInicialIndicadoresCatalogo::etapasDisponibles(),
        ])->layout('layouts.app', ['pageTitle' => 'Indicadores — espacio curricular']);
    }
}
