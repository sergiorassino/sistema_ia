<?php

namespace App\Livewire\CalificacionesPrimario;

use App\Models\Curso;
use App\Support\CalificacionesPrimario\PlanillaCalificacionesPrimarioDatos;
use App\Support\NivelSistema;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Selección de cursos y etapa (1ª / 2ª / apreciación final) para imprimir la planilla de calificaciones (PDF).
 */
class PlanillaCalificacionesPrimario extends Component
{
    /** IDs de curso marcados (`cursos.Id` como string). */
    public array $cursosSeleccionados = [];

    /** 1 = primera etapa, 2 = segunda, 9 = apreciación final (legacy). */
    public int $etapa = 1;

    public function mount(): void
    {
        abort_unless(
            NivelSistema::esPrimario((int) schoolCtx()->idNivel),
            403,
            'Esta planilla corresponde al nivel primario.'
        );

        $this->cursosSeleccionados = [];
        $this->etapa = 1;
    }

    public function updatedCursosSeleccionados(): void
    {
        $this->normalizarCursosSeleccionados();
    }

    public function updatedEtapa($value): void
    {
        $this->etapa = PlanillaCalificacionesPrimarioDatos::normalizarEtapa((int) $value);
    }

    public function etiquetaEtapaSeleccionada(): string
    {
        return match ($this->etapa) {
            2 => 'Segunda etapa',
            PlanillaCalificacionesPrimarioDatos::ETAPA_APRECIACION_FINAL => 'Apreciación final',
            default => 'Primera etapa',
        };
    }

    public function seleccionarTodosCursos(): void
    {
        $this->cursosSeleccionados = $this->cursos()
            ->pluck('Id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function quitarTodosCursos(): void
    {
        $this->cursosSeleccionados = [];
    }

    protected function normalizarCursosSeleccionados(): void
    {
        $allowed = $this->cursos()->pluck('Id')->map(fn ($id) => (int) $id)->all();

        $this->cursosSeleccionados = collect($this->cursosSeleccionados)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && in_array($id, $allowed, true))
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function puedeGenerarPdf(): bool
    {
        return collect($this->cursosSeleccionados)
            ->filter(fn ($v) => $v !== '' && $v !== null)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->isNotEmpty();
    }

    /**
     * @return Collection<int, mixed>
     */
    public function cursos(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    public function etiquetaCursosSeleccionados(): string
    {
        if (! $this->puedeGenerarPdf()) {
            return '';
        }

        $ids = collect($this->cursosSeleccionados)->map(fn ($v) => (int) $v)->flip();

        return $this->cursos()
            ->filter(fn ($c) => $ids->has((int) $c->Id))
            ->map(fn ($c) => $c->nombreParaListado())
            ->implode(' · ');
    }

    public function render()
    {
        $cursos = $this->cursos();
        $etiquetaCursos = $this->etiquetaCursosSeleccionados();
        $cantidadSeleccionados = collect($this->cursosSeleccionados)
            ->filter(fn ($v) => (int) $v > 0)
            ->count();

        $pdfUrl = null;
        if ($this->puedeGenerarPdf()) {
            $ids = collect($this->cursosSeleccionados)
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $pdfUrl = route('calificacionesPrimario.planilla.pdf', [
                'cursos' => $ids->implode(','),
                'etapa' => $this->etapa,
            ]);
        }

        $etiquetaEtapa = $this->etiquetaEtapaSeleccionada();

        return view('livewire.calificaciones-primario.planilla-calificaciones-primario', compact(
            'cursos',
            'etiquetaCursos',
            'cantidadSeleccionados',
            'pdfUrl',
            'etiquetaEtapa',
        ))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Planilla de calificaciones']);
    }
}
