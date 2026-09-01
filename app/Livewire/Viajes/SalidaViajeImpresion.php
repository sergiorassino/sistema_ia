<?php

namespace App\Livewire\Viajes;

use App\Models\Curso;
use App\Models\Matricula;
use App\Models\SalidaViaje;
use App\Support\BoletinSecundarioLoteParams;
use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\OrdenAlfabeticoEstudiante;
use App\Support\PermisosIaCatalog;
use App\Support\Viajes\SalidaViajeDatos;
use Illuminate\Support\Collection;
use Livewire\Component;

class SalidaViajeImpresion extends Component
{
    public int $viajeId;

    public string $viajeTitulo = '';

    public ?int $cursoId = null;

    /** @var list<string> */
    public array $matriculasSeleccionadas = [];

    public function mount(int $id): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::VIAJES_SALIDAS_EDUCATIVAS), 403);
        MenuSecretariaPerfil::abortSiNoViajesSalidasEducativas();

        $ctx = schoolCtx();
        if ($ctx->idNivel < 1 || $ctx->idTerlec < 1) {
            abort(403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
        }

        $viaje = SalidaViaje::queryEnContexto()->findOrFail($id);
        $this->viajeId = (int) $viaje->id;
        $this->viajeTitulo = (string) ($viaje->titulo ?? '');
    }

    public function updatedCursoId(mixed $value): void
    {
        $this->cursoId = ((int) $value) > 0 ? (int) $value : null;
        $this->matriculasSeleccionadas = [];
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

    public function toggleSeleccionTodas(): void
    {
        if ($this->todasLasMatriculasMarcadas()) {
            $this->quitarTodasMatriculas();
        } else {
            $this->seleccionarTodasMatriculas();
        }
    }

    public function todasLasMatriculasMarcadas(): bool
    {
        $permitidos = $this->matriculasDelCurso()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values();

        if ($permitidos->isEmpty()) {
            return false;
        }

        $marcados = collect($this->matriculasSeleccionadas)
            ->map(fn ($v) => (string) $v)
            ->filter(fn ($v) => $v !== '')
            ->sort()
            ->values();

        return $marcados->all() === $permitidos->all();
    }

    public function puedeGenerarPdf(): bool
    {
        return $this->cursoId > 0
            && collect($this->matriculasSeleccionadas)->filter(fn ($v) => (int) $v > 0)->isNotEmpty();
    }

    /** @return list<int> */
    public function idsMatriculasPdf(): array
    {
        if (! $this->puedeGenerarPdf() || ! $this->cursoId) {
            return [];
        }

        $ids = BoletinSecundarioLoteParams::resolverIdsMatriculasDesdeLista(
            collect($this->matriculasSeleccionadas)->map(fn ($v) => (int) $v)->all(),
            (int) $this->cursoId,
        );

        return $ids;
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

        $ctx = schoolCtx();

        $cursoOk = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', $this->cursoId)
            ->exists();

        if (! $cursoOk) {
            return collect();
        }

        return Matricula::query()
            ->with('legajo')
            ->where('idCursos', $this->cursoId)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereNull('fechaBaja')
            ->get()
            ->pipe(fn ($c) => OrdenAlfabeticoEstudiante::ordenarMatriculas($c));
    }

    public function render()
    {
        $matriculas = $this->matriculasDelCurso();
        $cantidadSeleccionados = count($this->idsMatriculasPdf());

        return view('livewire.viajes.salida-viaje-impresion', [
            'cursos' => SalidaViajeDatos::cursosEnContexto(),
            'matriculas' => $matriculas,
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'idsPdfLote' => $this->idsMatriculasPdf(),
            'puedePdf' => $this->puedeGenerarPdf() && $cantidadSeleccionados > 0,
            'todasMarcadas' => $this->todasLasMatriculasMarcadas(),
            'hayMatriculas' => $matriculas->isNotEmpty(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Imprimir autorización']);
    }
}
