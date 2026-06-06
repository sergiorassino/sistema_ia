<?php

namespace App\Livewire\Seguimiento\Disciplinario;

use App\Livewire\Seguimiento\Disciplinario\Concerns\RequiresPermisoSeguimientoDisciplinario;
use App\Models\Matricula;
use App\Models\Sancion;
use App\Models\SancionTipo;
use App\Support\Navegacion\ContextoEstudianteSesion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class SancionForm extends Component
{
    use RequiresPermisoSeguimientoDisciplinario;

    public ?int $id = null; // id sancion (edit)
    public int|string $idMatricula = '';

    public int|string $idTipoSancion = '';
    public string $fecha = '';
    public int|string $cantidad = '';
    public string $motivo = '';
    public string $solipor = '';

    public function mount(?int $id = null): void
    {
        $this->id = $id;

        if ($id) {
            $s = Sancion::query()
                ->with(['matricula.legajo', 'matricula.curso'])
                ->findOrFail($id);

            // Seguridad: la sanción debe pertenecer al contexto (año/nivel via matrícula)
            if ((int) ($s->matricula?->idNivel ?? 0) !== (int) schoolCtx()->idNivel
                || (int) ($s->matricula?->idTerlec ?? 0) !== (int) schoolCtx()->idTerlec) {
                abort(404);
            }

            $this->idMatricula = (string) $s->idMatricula;
            $this->idTipoSancion = (string) ($s->idTipoSancion ?? '');
            $this->fecha = $s->fecha ? $s->fecha->format('Y-m-d') : '';
            $this->cantidad = $s->cantidad ?? '';
            $this->motivo = (string) ($s->motivo ?? '');
            $this->solipor = (string) ($s->solipor ?? '');

            return;
        }

        $id = ContextoEstudianteSesion::matricula(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO);
        abort_if($id === null, 404);
        $this->idMatricula = (string) $id;
        $this->fecha = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'idMatricula' => ['required', 'integer', 'min:1'],
            'idTipoSancion' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'cantidad' => ['nullable', 'integer', 'min:0', 'max:99'],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'solipor' => ['nullable', 'string', 'max:150'],
        ];
    }

    protected function messages(): array
    {
        return [
            'idTipoSancion.required' => 'Seleccione el tipo de registro.',
            'fecha.required' => 'Indique la fecha.',
            'fecha.date' => 'Fecha inválida.',
            'cantidad.integer' => 'Cantidad inválida.',
        ];
    }

    private function matriculaDelContexto(int $id): Matricula
    {
        /** @var Matricula $m */
        $m = Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->findOrFail($id);

        return $m;
    }

    /** @return Collection<int, SancionTipo> */
    private function tipos(): Collection
    {
        return SancionTipo::query()
            ->orderBy('tipo')
            ->get(['id', 'tipo']);
    }

    public function save(): mixed
    {
        $key = 'sanciones:save:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('fecha', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();

        $m = $this->matriculaDelContexto((int) $this->idMatricula);

        $payload = [
            'idMatricula' => (int) $m->id,
            'idTipoSancion' => (int) $this->idTipoSancion,
            'idProfesores' => (int) (schoolCtx()->idProfesor ?? 0),
            'fecha' => $this->fecha ?: null,
            'cantidad' => ($this->cantidad === '' || $this->cantidad === null) ? null : (int) $this->cantidad,
            'motivo' => trim($this->motivo) !== '' ? trim($this->motivo) : null,
            'solipor' => trim($this->solipor) !== '' ? trim($this->solipor) : null,
        ];

        if ($this->id) {
            $s = Sancion::findOrFail($this->id);
            // Revalidar alcance de la sanción
            if ((int) ($s->matricula?->idNivel ?? 0) !== (int) schoolCtx()->idNivel
                || (int) ($s->matricula?->idTerlec ?? 0) !== (int) schoolCtx()->idTerlec) {
                abort(404);
            }
            $s->update($payload);
            session()->flash('success', 'Sanción actualizada.');
        } else {
            Sancion::create($payload);
            session()->flash('success', 'Sanción creada.');
        }

        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO, [
            'curso' => (int) $m->idCursos,
            'matricula' => (int) $m->id,
        ]);

        return redirect()->route('seguimiento.disciplinario');
    }

    public function render()
    {
        $m = null;
        $idMat = (int) $this->idMatricula;
        if ($idMat > 0) {
            // si no existe / fuera de contexto, 404 para evitar leaks
            $m = $this->matriculaDelContexto($idMat);
        }

        $tipos = $this->tipos();

        return view('livewire.seguimiento.disciplinario.form', compact('m', 'tipos'))
            ->layout(layoutMenuStaff(), ['pageTitle' => $this->id ? 'Editar sanción' : 'Nueva sanción']);
    }
}

