<?php

namespace App\Livewire\Cuotas;

use App\Models\Cuota;
use App\Support\Cuotas\CuotasPlantillaCatalog;
use App\Support\Cuotas\FacturacionMasivaAfipService;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\Cuotas\GestionAranceles;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Facturación masiva AFIP por devengamiento (manual).
 */
class FacturacionMasivaAfip extends Component
{
    /** 1 = cursos, 2 = cuotas + vista previa, 3 = resultado */
    public int $paso = 1;

    /** @var list<string> */
    public array $cursosSeleccionados = [];

    public string $filtroCursos = '';

    public string $buscarAlumno = '';

    /** @var list<array{id: int, label: string}> */
    public array $alumnosSeleccionados = [];

    /** @var list<string> */
    public array $cuotasSeleccionadas = [];

    /** @var array<string, mixed> */
    public array $vistaPrevia = [];

    /** @var array<string, mixed> */
    public array $resultado = [];

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeFacturacionMasivaAfip(), 403);
    }

    public function continuarACuotas(): void
    {
        $this->validarAlcanceEstudiantes();
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
        $this->cuotasSeleccionadas = [];
        $this->vistaPrevia = [];
        $this->resultado = [];
        $this->resetErrorBag();
    }

    public function updatedCuotasSeleccionadas(): void
    {
        $this->vistaPrevia = [];
        $this->resultado = [];
    }

    public function armarVistaPrevia(): void
    {
        abort_unless(PermisosCuotas::puedeFacturacionMasivaAfip(), 403);

        $this->validarAlcanceEstudiantes();
        $this->validarCuotasSeleccionadas();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $cursoIds = $this->idsCursosValidados();
        $cuotaIds = $this->idsCuotasValidadas();
        $this->vistaPrevia = FacturacionMasivaAfipService::vistaPrevia(
            $cursoIds,
            $cuotaIds,
            $this->idsLegajosValidados(),
        );
        $this->resultado = [];
    }

    public function facturar(): void
    {
        abort_unless(PermisosCuotas::puedeFacturacionMasivaAfip(), 403);

        $rateKey = 'cuotas:facturacion-masiva-afip:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 120);

        $this->validarAlcanceEstudiantes();
        $this->validarCuotasSeleccionadas();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if (($this->vistaPrevia['total'] ?? 0) < 1) {
            $this->dispatch('se-swal-error', mensaje: 'No hay estudiantes para facturar. Revise la vista previa.');

            return;
        }

        $cursoIds = $this->idsCursosValidados();
        $cuotaIds = $this->idsCuotasValidadas();
        $this->resultado = FacturacionMasivaAfipService::facturarEnCursos(
            $cursoIds,
            $cuotaIds,
            $this->idsLegajosValidados(),
        );
        $this->paso = 3;
        $this->vistaPrevia = [];
    }

    public function quitarAlumno(int $idLegajo): void
    {
        $this->alumnosSeleccionados = array_values(array_filter(
            $this->alumnosSeleccionados,
            fn (array $alumno) => (int) ($alumno['id'] ?? 0) !== $idLegajo,
        ));
    }

    public function agregarAlumno(int $idLegajo): void
    {
        if ($idLegajo < 1) {
            return;
        }

        foreach ($this->alumnosSeleccionados as $alumno) {
            if ((int) ($alumno['id'] ?? 0) === $idLegajo) {
                return;
            }
        }

        $fila = GeneracionMasivaCuotasConsulta::filaAlumnoDesdeLegajo($idLegajo);
        if ($fila === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró el estudiante.');

            return;
        }

        $label = GeneracionMasivaCuotasConsulta::etiquetaAlumno($fila);
        $curso = trim((string) ($fila->curso_nombre ?? ''));
        if ($curso !== '') {
            $label .= ' · '.$curso;
        }

        $this->alumnosSeleccionados[] = [
            'id' => $idLegajo,
            'label' => $label,
        ];
        $this->resetErrorBag('alcanceEstudiantes');
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

    public function seleccionarTodasCuotas(): void
    {
        $this->cuotasSeleccionadas = $this->idsCuotasPermitidasComoString()->keys()->all();
        $this->resetErrorBag('cuotasSeleccionadas');
    }

    public function quitarTodasCuotas(): void
    {
        $this->cuotasSeleccionadas = [];
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

    /** @return \Illuminate\Support\Collection<string, int> */
    private function idsCuotasPermitidasComoString(): \Illuminate\Support\Collection
    {
        return Cuota::query()
            ->where('idTerlec', CuotasPlantillaCatalog::idTerlecActivo())
            ->orderBy('orden')
            ->orderBy('id')
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(string) (int) $id => (int) $id]);
    }

    /** @return list<int> */
    private function idsCuotasValidadas(): array
    {
        $permitidos = $this->idsCuotasPermitidasComoString();

        return collect($this->cuotasSeleccionadas)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $permitidos->has((string) $id))
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function idsLegajosValidados(): array
    {
        return collect($this->alumnosSeleccionados)
            ->map(fn (array $alumno) => (int) ($alumno['id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function validarAlcanceEstudiantes(): void
    {
        $this->validate([
            'cursosSeleccionados' => ['array'],
            'cursosSeleccionados.*' => ['integer', 'min:1'],
            'alumnosSeleccionados' => ['array'],
            'alumnosSeleccionados.*.id' => ['integer', 'min:1'],
        ]);

        $permitidos = $this->idsCursosPermitidosComoString();

        $this->cursosSeleccionados = collect($this->cursosSeleccionados)
            ->map(fn ($id) => (string) (int) $id)
            ->filter(fn (string $id) => $permitidos->has($id))
            ->unique()
            ->values()
            ->all();

        $this->alumnosSeleccionados = collect($this->alumnosSeleccionados)
            ->filter(fn ($alumno) => is_array($alumno) && (int) ($alumno['id'] ?? 0) > 0)
            ->unique('id')
            ->values()
            ->all();

        if ($this->cursosSeleccionados === [] && $this->alumnosSeleccionados === []) {
            $this->addError(
                'alcanceEstudiantes',
                'Seleccione al menos un curso o un estudiante individual.',
            );
        }
    }

    private function validarCuotasSeleccionadas(): void
    {
        $this->validate([
            'cuotasSeleccionadas' => ['required', 'array', 'min:1'],
            'cuotasSeleccionadas.*' => ['integer', 'min:1'],
        ], [
            'cuotasSeleccionadas.required' => 'Seleccione al menos una cuota a facturar.',
            'cuotasSeleccionadas.min' => 'Seleccione al menos una cuota a facturar.',
        ]);

        $permitidos = $this->idsCuotasPermitidasComoString();

        $this->cuotasSeleccionadas = collect($this->cuotasSeleccionadas)
            ->map(fn ($id) => (string) (int) $id)
            ->filter(fn (string $id) => $permitidos->has($id))
            ->unique()
            ->values()
            ->all();

        if ($this->cuotasSeleccionadas === []) {
            $this->addError('cuotasSeleccionadas', 'Seleccione al menos una cuota válida.');
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

        $legajosBusqueda = null;
        if (trim($this->buscarAlumno) !== '') {
            $legajosBusqueda = GestionAranceles::buscarLegajos($this->buscarAlumno, 15);
        }

        $idsAlumnosSeleccionados = array_flip(
            collect($this->alumnosSeleccionados)->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        return view('livewire.cuotas.facturacion-masiva-afip', [
            'cursos' => $cursos,
            'cursosPorNivel' => array_values($cursosPorNivel),
            'cantidadSeleccionados' => $cantidadSeleccionados,
            'cursosSeleccionadosResumen' => $cursosSeleccionadosResumen,
            'plantillas' => $plantillas,
            'cantidadCuotasSeleccionadas' => count($this->cuotasSeleccionadas),
            'cantidadAlumnosSeleccionados' => count($this->alumnosSeleccionados),
            'puedeContinuar' => $cantidadSeleccionados > 0 || count($this->alumnosSeleccionados) > 0,
            'legajosBusqueda' => $legajosBusqueda,
            'idsAlumnosSeleccionados' => $idsAlumnosSeleccionados,
            'ano' => $ano,
        ])->layout(layoutMenuStaff(), ['pageTitle' => "Facturación masiva AFIP — {$ano}"]);
    }
}
