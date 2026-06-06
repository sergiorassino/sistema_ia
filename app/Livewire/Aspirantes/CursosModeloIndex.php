<?php

namespace App\Livewire\Aspirantes;

use App\Models\AspiCursoModelo;
use App\Models\Aspicurso;
use App\Models\Aspirante;
use App\Support\PermisosIaCatalog;
use Livewire\Component;

class CursosModeloIndex extends Component
{
    public string $nuevoNombre = '';

    public int $nuevoOrden = 0;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASPIRANTES_GESTION), 403);

        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            abort(403, 'Contexto incompleto.');
        }
    }

    public function agregar(): void
    {
        $this->validate([
            'nuevoNombre' => ['required', 'string', 'max:80'],
            'nuevoOrden'  => ['integer', 'min:0', 'max:999'],
        ], [
            'nuevoNombre.required' => 'Indicá el nombre del curso modelo.',
        ]);

        AspiCursoModelo::create([
            'idNivel' => schoolCtx()->idNivel,
            'nombre'  => trim($this->nuevoNombre),
            'orden'   => max(0, (int) $this->nuevoOrden),
            'activo'  => true,
        ]);

        $this->reset(['nuevoNombre', 'nuevoOrden']);
        session()->flash('status', 'Curso modelo agregado.');
    }

    public function setNombre(int $id, mixed $valor): void
    {
        $m = $this->encontrarPropio($id);
        $t = is_string($valor) ? trim($valor) : '';
        if ($t === '') {
            return;
        }
        $m->nombre = mb_substr($t, 0, 80);
        $m->save();
    }

    public function setOrden(int $id, mixed $orden): void
    {
        $m = $this->encontrarPropio($id);
        $m->orden = max(0, (int) $orden);
        $m->save();
    }

    public function setActivo(int $id, mixed $valor): void
    {
        $m = $this->encontrarPropio($id);
        $m->activo = (bool) $valor;
        $m->save();
    }

    public function eliminar(int $id): void
    {
        $m = $this->encontrarPropio($id);

        if (Aspirante::query()->where('idCursoModelo', $id)->exists()) {
            session()->flash('status', 'No se puede borrar: hay aspirantes registrados con este curso modelo.');

            return;
        }

        // Limpia referencias en aspicursos antes de borrar (deja el aspiento intacto).
        Aspicurso::query()->where('idCursoModelo', $id)->delete();
        $m->delete();
        session()->flash('status', 'Curso modelo eliminado.');
    }

    protected function encontrarPropio(int $id): AspiCursoModelo
    {
        $m = AspiCursoModelo::query()->whereKey($id)->firstOrFail();
        abort_unless((int) $m->idNivel === (int) schoolCtx()->idNivel, 403);

        return $m;
    }

    public function render()
    {
        $cursos = AspiCursoModelo::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('livewire.aspirantes.cursos-modelo-index', compact('cursos'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Aspirantes — Cursos modelo']);
    }
}
