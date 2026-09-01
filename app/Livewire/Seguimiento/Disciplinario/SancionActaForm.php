<?php

namespace App\Livewire\Seguimiento\Disciplinario;

use App\Livewire\Seguimiento\Disciplinario\Concerns\RequiresPermisoSeguimientoDisciplinario;
use App\Models\Matricula;
use App\Models\Sancion;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\Seguimiento\SancionActaHtmlSanitizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class SancionActaForm extends Component
{
    use RequiresPermisoSeguimientoDisciplinario;

    public int $id;

    public string $formActa = '';

    public string $alumnoLabel = '';

    public string $sancionLabel = '';

    public function mount(int $id): void
    {
        $s = Sancion::query()
            ->with(['tipo', 'matricula.legajo', 'matricula.curso'])
            ->findOrFail($id);

        if ((int) ($s->matricula?->idNivel ?? 0) !== (int) schoolCtx()->idNivel
            || (int) ($s->matricula?->idTerlec ?? 0) !== (int) schoolCtx()->idTerlec
            || ! in_array(
                (int) ($s->matricula?->idCondiciones ?? 0),
                ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::TODOS),
                true
            )) {
            abort(404);
        }

        if (! Schema::hasColumn('sanciones', 'acta')) {
            session()->flash('error', PersistenciaColumnas::mensajeColumnasInexistentes('sanciones', ['acta']));

            $this->redirect(route('seguimiento.disciplinario'), navigate: true);

            return;
        }

        $this->id = (int) $s->id;
        $this->formActa = (string) ($s->acta ?? '');

        $legajo = $s->matricula?->legajo;
        $this->alumnoLabel = trim(($legajo?->apellido ?? '').', '.($legajo?->nombre ?? ''));
        if ($this->alumnoLabel === ',') {
            $this->alumnoLabel = 'Alumno/a';
        }

        $fecha = $s->fecha ? $s->fecha->format('d/m/Y') : '—';
        $tipo = $s->tipo?->tipo ?? ('#'.$s->idTipoSancion);
        $this->sancionLabel = "{$tipo} · {$fecha}";

        if ($s->matricula) {
            ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO, [
                'curso' => (int) $s->matricula->idCursos,
                'matricula' => (int) $s->matricula->id,
            ]);
        }
    }

    protected function rules(): array
    {
        return [
            'formActa' => ['nullable', 'string', 'max:65000'],
        ];
    }

    public function save(): void
    {
        $key = 'sanciones:acta:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('formActa', 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->formActa = SancionActaHtmlSanitizer::limpiar($this->formActa);
        if (SancionActaHtmlSanitizer::estaVacio($this->formActa)) {
            $this->formActa = '';
        }

        $this->validate();

        $s = Sancion::query()
            ->with('matricula')
            ->findOrFail($this->id);

        if ((int) ($s->matricula?->idNivel ?? 0) !== (int) schoolCtx()->idNivel
            || (int) ($s->matricula?->idTerlec ?? 0) !== (int) schoolCtx()->idTerlec
            || ! in_array(
                (int) ($s->matricula?->idCondiciones ?? 0),
                ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::TODOS),
                true
            )) {
            abort(404);
        }

        $payload = [
            'acta' => $this->formActa !== '' ? $this->formActa : null,
        ];

        $preparado = PersistenciaColumnas::prepararPayload('sanciones', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            $mensaje = PersistenciaColumnas::mensajeColumnasInexistentes(
                'sanciones',
                $preparado['columnas_con_valor_sin_columna']
            );
            $this->addError('formActa', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        }

        try {
            $s->update($preparado['payload']);
        } catch (QueryException $e) {
            Log::warning('sancion-acta: error al guardar', [
                'id' => $this->id,
                'code' => $e->getCode(),
            ]);
            $mensaje = PersistenciaColumnas::mensajeDesdeQueryException($e)
                ?? 'No se pudo guardar el acta. Intente nuevamente.';
            $this->addError('formActa', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        }

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'sanciones',
            ['id' => (int) $s->id],
            $preparado['payload']
        );
        if ($noPersistidas !== []) {
            $mensaje = PersistenciaColumnas::mensajeColumnasNoPersistidas('sanciones', $noPersistidas);
            $this->addError('formActa', $mensaje);
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        }

        if ($s->matricula instanceof Matricula) {
            ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO, [
                'curso' => (int) $s->matricula->idCursos,
                'matricula' => (int) $s->matricula->id,
            ]);
        }

        session()->flash('success', 'Acta guardada.');

        $this->redirect(route('seguimiento.disciplinario'), navigate: true);
    }

    public function render()
    {
        return view('livewire.seguimiento.disciplinario.acta-form')
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Acta de sanción']);
    }
}
