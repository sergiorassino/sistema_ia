<?php

namespace App\Livewire\Seguimiento\Inasistencias;

use App\Livewire\Seguimiento\Inasistencias\Concerns\RequiresPermisoInasistenciasEstudiantesGestion;
use App\Models\Curso;
use App\Models\Inasistencia;
use App\Models\Matricula;
use App\Support\InformeInasistencias;
use App\Support\InasistenciasResumen;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\Tea\TeaInstanciasPendientes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class InasistenciasIndex extends Component
{
    use RequiresPermisoInasistenciasEstudiantesGestion;

    public int|string $idCurso = '';

    public int|string $idMatricula = '';

    /** ID de {@see InasistenciaValor}; vacío = todos los tipos. */
    public string $idTipoFiltro = '';

    /** Filtro de fechas (Y-m-d); vacío = todo el año lectivo. */
    public string $fechaDesdeFiltro = '';

    public string $fechaHastaFiltro = '';

    public bool $showDeleteConfirm = false;
    public ?int $deleteId = null;

    public string $deleteInfo = '';

    public function mount(): void
    {
        $ctx = ContextoEstudianteSesion::leer(ContextoEstudianteSesion::SEGUIMIENTO_INASISTENCIAS);
        $this->idCurso = (string) ($ctx['curso'] ?? '');
        $this->idMatricula = (string) ($ctx['matricula'] ?? '');
        $this->idTipoFiltro = (string) ($ctx['tipo'] ?? '');
        $this->fechaDesdeFiltro = $this->normalizarFechaFiltro((string) ($ctx['desde'] ?? ''));
        $this->fechaHastaFiltro = $this->normalizarFechaFiltro((string) ($ctx['hasta'] ?? ''));
    }

    private function persistirContextoEnSesion(): void
    {
        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_INASISTENCIAS, [
            'curso' => (int) $this->idCurso ?: null,
            'matricula' => (int) $this->idMatricula ?: null,
            'tipo' => $this->idTipoFiltro,
            'desde' => $this->fechaDesdeFiltro,
            'hasta' => $this->fechaHastaFiltro,
        ]);
    }
    public function updatedIdCurso(mixed $value): void
    {
        $this->idCurso = is_scalar($value) ? (string) $value : '';
        $this->idMatricula = '';
        $this->idTipoFiltro = '';
        $this->resetFiltrosFecha();
        $this->persistirContextoEnSesion();
    }
    public function updatedIdMatricula(mixed $value): void
    {
        $this->idMatricula = is_scalar($value) ? (string) $value : '';
        $this->idTipoFiltro = '';
        $this->resetFiltrosFecha();
        $this->persistirContextoEnSesion();
    }

    public function updatedIdTipoFiltro(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->idTipoFiltro = '';

            return;
        }

        $this->idTipoFiltro = is_scalar($value) ? (string) $value : '';
        $this->persistirContextoEnSesion();
    }

    public function updatedFechaDesdeFiltro(mixed $value): void
    {
        $this->fechaDesdeFiltro = $this->normalizarFechaFiltro(is_scalar($value) ? (string) $value : '');
        $this->persistirContextoEnSesion();
    }

    public function updatedFechaHastaFiltro(mixed $value): void
    {
        $this->fechaHastaFiltro = $this->normalizarFechaFiltro(is_scalar($value) ? (string) $value : '');
        $this->persistirContextoEnSesion();
    }

    private function normalizarFechaFiltro(string $value): string
    {
        $parsed = InformeInasistencias::parseFechaFiltro($value, InformeInasistencias::anoLectivo());

        return $parsed?->toDateString() ?? '';
    }

    private function resetFiltrosFecha(): void
    {
        $this->fechaDesdeFiltro = '';
        $this->fechaHastaFiltro = '';
    }

    private function idTipoFiltroActivo(): ?int
    {
        return InformeInasistencias::tipoFiltroValido((int) $this->idTipoFiltro ?: null);
    }
    /** @return Collection<int, Curso> */
    private function cursosDelContexto(): Collection
    {
        return Curso::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idTurnoClase', 'c', 's']);
    }

    /** @return Collection<int, object> */
    private function alumnosDelCurso(int $idCurso): Collection
    {
        $idsCondicionesRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );

        return Matricula::query()
            ->where('matricula.idNivel', schoolCtx()->idNivel)
            ->where('matricula.idTerlec', schoolCtx()->idTerlec)
            ->where('matricula.idCursos', $idCurso)
            ->whereIn('matricula.idCondiciones', $idsCondicionesRegulares)
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('legajos.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('legajos.nombre'))
            ->select([
                'matricula.id',
                'matricula.idLegajos',
                'legajos.apellido',
                'legajos.nombre',
                'legajos.dni',
            ])
            ->get();
    }

    private function matriculaSeleccionada(): ?Matricula
    {
        $id = (int) $this->idMatricula;
        if ($id <= 0) {
            return null;
        }

        $idsCondicionesRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );

        return Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->whereIn('idCondiciones', $idsCondicionesRegulares)
            ->find($id);
    }

    /** @return Collection<int, Inasistencia> */
    private function inasistenciasDeMatricula(int $idMatricula): Collection
    {
        return InformeInasistencias::inasistenciasDelAno(
            $idMatricula,
            $this->idTipoFiltroActivo(),
            null,
            $this->fechaDesdeFiltro !== '' ? $this->fechaDesdeFiltro : null,
            $this->fechaHastaFiltro !== '' ? $this->fechaHastaFiltro : null,
        )
            ->sortByDesc(fn (Inasistencia $i) => $i->fecha?->format('Y-m-d').'-'.$i->id)
            ->values();
    }
    public function confirmDelete(int $id): void
    {
        $m = $this->matriculaSeleccionada();
        if (! $m) {
            abort(404);
        }

        $i = Inasistencia::query()
            ->where('idMatricula', (int) $m->id)
            ->with('valorTipo')
            ->findOrFail($id);

        $fecha = $i->fecha ? $i->fecha->format('d/m/Y') : '—';
        $tipo = $i->etiquetaTipo();

        $this->deleteId = (int) $i->id;
        $this->deleteInfo = "¿Confirma borrar la inasistencia \"{$tipo}\" ({$fecha})?";
        $this->showDeleteConfirm = true;
    }

    public function delete(): void
    {
        $m = $this->matriculaSeleccionada();
        if (! $m) {
            abort(404);
        }

        $key = 'inasistencias:delete:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('success', 'Demasiados intentos. Espere un momento e intente nuevamente.');
            $this->showDeleteConfirm = false;
            $this->reset('deleteId', 'deleteInfo');

            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->deleteId) {
            Inasistencia::query()
                ->where('idMatricula', (int) $m->id)
                ->findOrFail((int) $this->deleteId)
                ->delete();

            session()->flash('success', 'Inasistencia borrada.');
        }

        $this->showDeleteConfirm = false;
        $this->reset('deleteId', 'deleteInfo');
    }

    public function render()
    {
        $cursos = $this->cursosDelContexto();

        $alumnos = collect();
        $cursoId = (int) $this->idCurso;
        if ($cursoId > 0) {
            $alumnos = $this->alumnosDelCurso($cursoId);
        }

        $matricula = $this->matriculaSeleccionada();
        $inasistencias = collect();
        $resumen = null;
        $teaPendientes = [];
        $teaPendientesPorMatricula = [];

        if ($matricula) {
            $inasistencias = $this->inasistenciasDeMatricula((int) $matricula->id);
            $resumen = InasistenciasResumen::desdeColeccion($inasistencias);
            $teaPendientes = TeaInstanciasPendientes::deMatricula((int) $matricula->id);
        } elseif ((int) $this->idMatricula > 0) {
            $this->idMatricula = '';
        }

        if ($cursoId > 0 && $alumnos->isNotEmpty()) {
            $teaPendientesPorMatricula = TeaInstanciasPendientes::porMatriculas(
                $alumnos->pluck('id')->map(static fn ($id) => (int) $id)->all(),
            );
        }

        $tiposInasistencia = InformeInasistencias::tiposDisponibles();
        $tipoFiltroActivo = $this->idTipoFiltroActivo();
        $etiquetaTipoFiltro = InformeInasistencias::etiquetaFiltroTipos($tipoFiltroActivo);

        $anoLectivo = InformeInasistencias::anoLectivo();
        $rangoFechas = InformeInasistencias::rangoFechasConFiltro(
            $this->fechaDesdeFiltro !== '' ? $this->fechaDesdeFiltro : null,
            $this->fechaHastaFiltro !== '' ? $this->fechaHastaFiltro : null,
            $anoLectivo,
        );
        $filtroFechasActivo = $this->fechaDesdeFiltro !== '' || $this->fechaHastaFiltro !== '';
        $etiquetaPeriodoFiltro = $rangoFechas['desde']->format('d/m/Y')
            .' — '.$rangoFechas['hasta']->format('d/m/Y');
        $fechaMinimaFiltro = InformeInasistencias::fechaMinimaAno($anoLectivo);
        $fechaMaximaFiltro = InformeInasistencias::fechaMaximaAno($anoLectivo);

        return view('livewire.seguimiento.inasistencias.index', compact(
            'cursos',
            'alumnos',
            'matricula',
            'inasistencias',
            'resumen',
            'tiposInasistencia',
            'tipoFiltroActivo',
            'etiquetaTipoFiltro',
            'filtroFechasActivo',
            'etiquetaPeriodoFiltro',
            'fechaMinimaFiltro',
            'fechaMaximaFiltro',
            'teaPendientes',
            'teaPendientesPorMatricula',
        ))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Gestión de Inasistencias del Estudiante']);
    }
}
