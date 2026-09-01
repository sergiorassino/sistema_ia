<?php

namespace App\Livewire\Cuotas;

use App\Models\Cuota;
use App\Support\Cuotas\CuotasPlantillaCatalog;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\Cuotas\GeneracionMasivaCuotasService;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Generación masiva de cuotas por cursos (estudiantes regulares).
 */
class GeneracionMasivaCuotas extends Component
{
    /** 1 = cursos, 2 = cuota + vista previa, 3 = resultado */
    public int $paso = 1;

    /** @var list<string> */
    public array $cursosSeleccionados = [];

    public string $filtroCursos = '';

    public int $idCuota = 0;

    /** @var array<string, mixed> */
    public array $vistaPrevia = [];

    /** @var array<string, mixed> */
    public array $resultado = [];

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeGeneracionMasiva(), 403);
    }

    public function continuarACuota(): void
    {
        $this->validarCursosSeleccionados();
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->paso = 2;
        $this->vistaPrevia = [];
        $this->resultado = [];
        $this->resetErrorBag();
    }

    public function volverACursos(): void
    {
        $this->paso = 1;
        $this->idCuota = 0;
        $this->vistaPrevia = [];
        $this->resultado = [];
        $this->resetErrorBag();
    }

    public function volverAlInicio(): void
    {
        $this->volverACursos();
        $this->cursosSeleccionados = [];
        $this->filtroCursos = '';
    }

    public function updatedIdCuota(): void
    {
        $this->vistaPrevia = [];
        $this->resultado = [];
    }

    public function armarVistaPrevia(): void
    {
        abort_unless(PermisosCuotas::puedeGeneracionMasiva(), 403);

        $this->validarCursosSeleccionados();
        $this->validate([
            'idCuota' => ['required', 'integer', 'min:1'],
        ], [
            'idCuota.required' => 'Seleccione la cuota que desea generar.',
            'idCuota.min' => 'Seleccione la cuota que desea generar.',
        ]);

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $cursoIds = $this->idsCursosValidados();
        $this->vistaPrevia = GeneracionMasivaCuotasService::vistaPrevia($cursoIds, $this->idCuota);
        $this->resultado = [];
    }

    public function generar(): void
    {
        abort_unless(PermisosCuotas::puedeGeneracionMasiva(), 403);

        $rateKey = 'cuotas:generar-masiva:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 120);

        $this->validarCursosSeleccionados();
        $this->validate([
            'idCuota' => ['required', 'integer', 'min:1'],
        ], [
            'idCuota.required' => 'Seleccione la cuota que desea generar.',
            'idCuota.min' => 'Seleccione la cuota que desea generar.',
        ]);

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if (($this->vistaPrevia['total'] ?? 0) < 1) {
            $this->dispatch('se-swal-error', mensaje: 'No hay estudiantes para generar. Revise la vista previa.');

            return;
        }

        $cursoIds = $this->idsCursosValidados();
        $this->resultado = GeneracionMasivaCuotasService::generarEnCursos($cursoIds, $this->idCuota);
        $this->paso = 3;
        $this->vistaPrevia = [];
    }

    public function quitarCurso(int $idCurso): void
    {
        $key = (string) $idCurso;
        $this->cursosSeleccionados = array_values(array_filter(
            $this->cursosSeleccionados,
            fn (string $id) => $id !== $key,
        ));
    }

    public function seleccionarTodosCursos(): void
    {
        $this->cursosSeleccionados = $this->idsCursosPermitidosComoString()->keys()->all();
        $this->resetErrorBag('cursosSeleccionados');
    }

    public function quitarTodosCursos(): void
    {
        $this->cursosSeleccionados = [];
    }

    public function marcarNivel(int $idNivel): void
    {
        $ids = $this->idsCursosDelNivel($idNivel);
        $this->cursosSeleccionados = array_values(array_unique(array_merge(
            $this->cursosSeleccionados,
            $ids,
        )));
        $this->resetErrorBag('cursosSeleccionados');
    }

    public function quitarNivel(int $idNivel): void
    {
        $quitar = array_flip($this->idsCursosDelNivel($idNivel));
        $this->cursosSeleccionados = array_values(array_filter(
            $this->cursosSeleccionados,
            fn (string $id) => ! isset($quitar[$id]),
        ));
    }

    /** @return \Illuminate\Support\Collection<string, int> */
    private function idsCursosPermitidosComoString(): \Illuminate\Support\Collection
    {
        return GeneracionMasivaCuotasConsulta::cursosEnContexto()
            ->pluck('Id')
            ->mapWithKeys(fn ($id) => [(string) (int) $id => (int) $id]);
    }

    /** @return list<string> */
    private function idsCursosDelNivel(int $idNivel): array
    {
        if ($idNivel < 1) {
            return [];
        }

        return GeneracionMasivaCuotasConsulta::cursosEnContexto()
            ->filter(fn ($c) => (int) ($c->idNivel ?? 0) === $idNivel)
            ->map(fn ($c) => (string) (int) $c->Id)
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function idsCursosValidados(): array
    {
        $permitidos = $this->idsCursosPermitidosComoString();

        return collect($this->cursosSeleccionados)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $permitidos->has((string) $id))
            ->unique()
            ->values()
            ->all();
    }

    private function validarCursosSeleccionados(): void
    {
        $this->validate([
            'cursosSeleccionados' => ['required', 'array', 'min:1'],
            'cursosSeleccionados.*' => ['integer', 'min:1'],
        ], [
            'cursosSeleccionados.required' => 'Seleccione al menos un curso.',
            'cursosSeleccionados.min' => 'Seleccione al menos un curso.',
        ]);

        $permitidos = $this->idsCursosPermitidosComoString();

        $this->cursosSeleccionados = collect($this->cursosSeleccionados)
            ->map(fn ($id) => (string) (int) $id)
            ->filter(fn (string $id) => $permitidos->has($id))
            ->unique()
            ->values()
            ->all();

        if ($this->cursosSeleccionados === []) {
            $this->addError('cursosSeleccionados', 'Seleccione al menos un curso válido.');
        }
    }

    public function render()
    {
        $ano = (int) schoolCtx()->terlecAno();
        $cursos = GeneracionMasivaCuotasConsulta::cursosEnContexto();

        $filtro = mb_strtolower(trim($this->filtroCursos));
        $seleccionadosFlip = array_flip($this->cursosSeleccionados);
        $cantidadSeleccionados = count($this->cursosSeleccionados);

        $cursosPorNivel = [];
        foreach ($cursos as $curso) {
            $etiqueta = GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($curso);
            if ($filtro !== '' && ! str_contains(mb_strtolower($etiqueta), $filtro)) {
                continue;
            }

            $idNivel = (int) ($curso->idNivel ?? 0);
            $key = (string) $idNivel;
            if (! isset($cursosPorNivel[$key])) {
                $cursosPorNivel[$key] = [
                    'idNivel' => $idNivel,
                    'nivelNombre' => trim((string) ($curso->nivel?->nivel ?? 'Sin nivel')),
                    'cursos' => [],
                    'total' => 0,
                    'seleccionados' => 0,
                ];
            }

            $idCursoStr = (string) (int) $curso->Id;
            $marcado = isset($seleccionadosFlip[$idCursoStr]);
            $cursosPorNivel[$key]['cursos'][] = [
                'id' => (int) $curso->Id,
                'etiqueta' => $etiqueta,
                'etiquetaCorta' => $curso->nombreParaListado(),
                'seleccionado' => $marcado,
            ];
            $cursosPorNivel[$key]['total']++;
            if ($marcado) {
                $cursosPorNivel[$key]['seleccionados']++;
            }
        }

        $etiquetasPorId = $cursos->mapWithKeys(fn ($c) => [
            (string) (int) $c->Id => GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($c),
        ]);

        $cursosSeleccionadosResumen = collect($this->cursosSeleccionados)
            ->map(fn (string $id) => [
                'id' => (int) $id,
                'label' => (string) ($etiquetasPorId[$id] ?? ''),
            ])
            ->filter(fn (array $r) => $r['label'] !== '')
            ->values()
            ->all();

        $plantillas = collect();
        if ($this->paso >= 2) {
            $plantillas = Cuota::query()
                ->where('idTerlec', CuotasPlantillaCatalog::idTerlecActivo())
                ->with(['cuotasTipo:id,nombre', 'cuotasMes:id,mes'])
                ->orderBy('orden')
                ->orderBy('id')
                ->get();
        }

        return view('livewire.cuotas.generacion-masiva', [
            'cursos' => $cursos,
            'cursosPorNivel' => array_values($cursosPorNivel),
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'cursosSeleccionadosResumen' => $cursosSeleccionadosResumen,
            'plantillas' => $plantillas,
            'ano' => $ano,
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Generación masiva de cuotas — {$ano}"]);
    }
}
