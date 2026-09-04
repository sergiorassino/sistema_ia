<?php

namespace App\Livewire\CalificacionesInicial\Sfq;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\CalificacionesInicial\CalificacionesInicialModulos;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqCatalogo;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqDatos;
use App\Support\NivelSistema;
use App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * SFQ — Informes inicial: diagnóstico, etapas pedagógicas y Bellas Artes.
 */
class BoletinInicialSfqIndex extends Component
{
    public bool $modoPortalDocente = false;

    public ?int $cursoId = null;

    /** diagnostico | etapa1 | etapa2 | bellas_artes — vacío hasta que el usuario elija. */
    public string $tipoInforme = '';

    /** @var list<string> */
    public array $matriculasSeleccionadas = [];

    public function mount(): void
    {
        CalificacionesInicialModulos::abortSiImplementacionInactiva(
            CalificacionesInicialModulos::BOLETIN,
            CalificacionesInicialSfqCatalogo::IMPLEMENTACION,
        );

        $this->modoPortalDocente = CalificacionesInicialSfqPortalDocente::esPortalDocente();

        if ($this->modoPortalDocente) {
            CalificacionesInicialSfqPortalDocente::abortSiMenuBoletinInactivo();
        }

        abort_unless(
            NivelSistema::esInicial((int) schoolCtx()->idNivel),
            403,
            'Este módulo corresponde al nivel inicial.',
        );

        $tipoQuery = trim((string) request()->query('tipo', ''));
        if ($tipoQuery !== '' && CalificacionesInicialSfqCatalogo::esTipoInformeValido($tipoQuery)) {
            $this->tipoInforme = $tipoQuery;
        }
    }

    public function updatedCursoId(mixed $value): void
    {
        $id = ((int) $value) > 0 ? (int) $value : null;
        if ($id !== null && $this->modoPortalDocente) {
            CalificacionesInicialSfqPortalDocente::abortSiProfesorSinCurso($id);
        }
        $this->cursoId = $id;
        $this->matriculasSeleccionadas = [];
    }

    public function updatedTipoInforme(mixed $value): void
    {
        $tipo = trim((string) $value);
        $this->tipoInforme = CalificacionesInicialSfqCatalogo::esTipoInformeValido($tipo) ? $tipo : '';
        $this->matriculasSeleccionadas = [];
    }

    public function tieneTipoInformeSeleccionado(): bool
    {
        return CalificacionesInicialSfqCatalogo::esTipoInformeValido($this->tipoInforme);
    }

    public function updatedMatriculasSeleccionadas(): void
    {
        $this->normalizarMatriculasSeleccionadas();
    }

    public function seleccionarTodasMatriculas(): void
    {
        $this->matriculasSeleccionadas = $this->matriculasDelCurso()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function quitarTodasMatriculas(): void
    {
        $this->matriculasSeleccionadas = [];
    }

    public function puedeGenerarPdfLote(): bool
    {
        return $this->tieneTipoInformeSeleccionado()
            && collect($this->matriculasSeleccionadas)
                ->filter(fn ($v) => (int) $v > 0)
                ->isNotEmpty();
    }

    protected function normalizarMatriculasSeleccionadas(): void
    {
        $allowed = $this->matriculasDelCurso()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->matriculasSeleccionadas = collect($this->matriculasSeleccionadas)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0 && in_array($id, $allowed, true))
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @return Collection<int, Matricula>
     */
    public function matriculasDelCurso(): Collection
    {
        if (! $this->cursoId) {
            return collect();
        }

        return CalificacionesInicialSfqDatos::matriculasRegularesCurso((int) $this->cursoId)
            ->loadMissing(['legajo', 'condicion'])
            ->when($this->modoPortalDocente, function (Collection $c) {
                $permitidos = CalificacionesInicialSfqPortalDocente::idsCursosAsignados();

                return $c->filter(fn (Matricula $m) => in_array((int) $m->idCursos, $permitidos, true));
            })
            ->values();
    }

    public function cursos(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->when($this->modoPortalDocente, fn ($q) => $q->whereIn('Id', CalificacionesInicialSfqPortalDocente::idsCursosAsignados()))
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    public function render()
    {
        $matriculas = $this->matriculasDelCurso();
        $hayTipoInforme = $this->tieneTipoInformeSeleccionado();
        $tipo = $hayTipoInforme ? $this->tipoInforme : '';
        $meta = $hayTipoInforme ? CalificacionesInicialSfqCatalogo::metaTipoInforme($tipo) : null;

        $idsPdfLote = [];
        $puedePdfLote = false;

        if ($this->puedeGenerarPdfLote()) {
            $ids = collect($this->matriculasSeleccionadas)
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            if ($ids->count() <= BoletinSecundarioLoteParams::MAX_MATRICULAS) {
                $idsPdfLote = $ids->all();
                $puedePdfLote = $idsPdfLote !== [];
            }
        }

        $tiposInforme = [];
        foreach (CalificacionesInicialSfqCatalogo::TIPOS_INFORME as $clave) {
            $m = CalificacionesInicialSfqCatalogo::metaTipoInforme($clave);
            if ($m !== null) {
                $tiposInforme[] = ['clave' => $clave, 'etiqueta' => $m['etiqueta']];
            }
        }

        return view('livewire.calificaciones-inicial.sfq.boletin-index', [
            'cursos' => $this->cursos(),
            'matriculas' => $matriculas,
            'idsPdfLote' => $idsPdfLote,
            'puedePdfLote' => $puedePdfLote,
            'tipoPdf' => $tipo,
            'hayTipoInforme' => $hayTipoInforme,
            'etiquetaInforme' => (string) ($meta['etiqueta'] ?? ''),
            'tiposInforme' => $tiposInforme,
        ])->layout(CalificacionesInicialSfqPortalDocente::layout(), ['pageTitle' => 'Informes (Inicial SFQ)']);
    }
}
