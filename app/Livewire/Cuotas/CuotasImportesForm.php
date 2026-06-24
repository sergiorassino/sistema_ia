<?php

namespace App\Livewire\Cuotas;

use App\Models\CuotasImporte;
use App\Models\Curso;
use App\Support\Cuotas\CuotasFormato;
use App\Support\Cuotas\CuotasImportesCatalog;
use App\Support\Navegacion\ContextoCuotasImportesSesion;
use App\Support\PermisosCuotas;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Edición de importes y fórmulas por curso (`cuotasimportes`) para una plantilla de cuota.
 */
class CuotasImportesForm extends Component
{
    public int $idCuotas = 0;

    public string $search = '';

    /** @var array<string|int, array<string, mixed>> */
    public array $draft = [];

    public bool $persistiendo = false;

    /** @var array<string, string> */
    public array $ultimoGuardadoHashes = [];

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);

        $idCuotas = ContextoCuotasImportesSesion::idCuotas();
        abort_if($idCuotas === null, 404);

        $this->idCuotas = $idCuotas;
        CuotasImportesCatalog::cuotaDelCicloOrFail($idCuotas);
        $this->cargarFilas();
    }

    public function updatedSearch(): void
    {
        // Solo filtra en la vista.
    }

    public function updated($property): void
    {
        if ($this->persistiendo || $property === 'search') {
            return;
        }

        if (! preg_match('/^draft\.([^.]+)\.([^.]+)$/', (string) $property, $coincidencias)) {
            return;
        }

        $this->saveRowField($coincidencias[1]);
    }

    /**
     * Persiste un campo de texto (importe / valor) desde el DOM (focusout o navegación con teclado).
     * Los selects usan wire:model.live; los inputs no usan wire:model para evitar perder el valor al mover el foco.
     */
    public function commitDraftCell(string $key, string $field, string $value): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);

        $campos = ['importe', 'valor1v', 'valor2v', 'valor3v', 'valor4v'];
        if (! in_array($field, $campos, true) || ! isset($this->draft[$key])) {
            return;
        }

        $this->draft[$key][$field] = trim($value);
        $this->saveRowField($key);
    }

    public function saveRowField(string $key): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);

        if ($this->persistiendo || ! isset($this->draft[$key])) {
            return;
        }

        $hash = $this->hashFila($key);
        if (($this->ultimoGuardadoHashes[$key] ?? '') === $hash) {
            return;
        }

        $rateKey = 'cuotas-importes:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 120)) {
            $this->addError("draft.{$key}.importe", 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $this->persistiendo = true;

        try {
            $this->validate(CuotasImportesCatalog::reglasFila($key, $this->draft[$key]));
            CuotasImportesCatalog::validarMontos($this->draft[$key], "draft.{$key}.");
        } catch (\Illuminate\Validation\ValidationException) {
            $this->persistiendo = false;

            return;
        }

        $payload = CuotasImportesCatalog::payloadDesdeDraft($this->draft[$key]);

        try {
            $registro = CuotasImportesCatalog::importeDelCicloOrFail((int) $key, $this->idCuotas);
            $registro->update($payload);
            $clave = (string) $registro->id;
            $this->draft[$clave] = $this->filaDesdeModelo($registro->fresh(['curso.curplan', 'curso.turnoClase', 'curso.nivel']));
            $this->ultimoGuardadoHashes[$clave] = $this->hashFila($clave);
            $this->resetValidation();
        } finally {
            $this->persistiendo = false;
        }
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function filasVisibles(): array
    {
        $q = mb_strtolower(trim($this->search));
        if ($q === '') {
            return $this->draft;
        }

        return array_filter(
            $this->draft,
            fn (array $row): bool => str_contains(
                mb_strtolower((string) ($row['cursoLabel'] ?? '')),
                $q,
            ),
        );
    }

    public function render()
    {
        $cuota = CuotasImportesCatalog::cuotaDelCicloOrFail($this->idCuotas);
        $ano = (int) schoolCtx()->terlecAno();

        return view('livewire.cuotas.importes-form', [
            'filas' => $this->filasVisibles(),
            'cuota' => $cuota,
            'opcionesSigno' => CuotasImportesCatalog::opcionesSigno(),
            'opcionesPorcan' => CuotasImportesCatalog::opcionesPorcan(),
            'leyendasPorcan' => CuotasImportesCatalog::leyendasPorcan(),
            'ano' => $ano,
        ])->layout(layoutMenuStaff(), [
            'pageTitle' => 'Importes — '.trim((string) $cuota->nombre),
        ]);
    }

    private function cargarFilas(): void
    {
        $this->draft = [];

        $registros = CuotasImporte::query()
            ->where('idCuotas', $this->idCuotas)
            ->with(['curso.curplan', 'curso.turnoClase', 'curso.nivel'])
            ->get()
            ->sortBy(fn (CuotasImporte $r) => [
                (int) ($r->curso?->idNivel ?? 0),
                (int) ($r->curso?->orden ?? 0),
                mb_strtolower((string) ($r->curso?->cursec ?? '')),
                (int) $r->id,
            ])
            ->values();

        foreach ($registros as $registro) {
            $clave = (string) $registro->id;
            $this->draft[$clave] = $this->filaDesdeModelo($registro);
            $this->ultimoGuardadoHashes[$clave] = $this->hashFila($clave);
        }
    }

    private function hashFila(string $key): string
    {
        if (! isset($this->draft[$key])) {
            return '';
        }

        return hash('xxh128', json_encode(
            CuotasImportesCatalog::payloadDesdeDraft($this->draft[$key]),
            JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function filaDesdeModelo(CuotasImporte $registro): array
    {
        $curso = $registro->curso;

        return [
            'id' => (int) $registro->id,
            'idCursos' => (int) $registro->idCursos,
            'cursoLabel' => $this->cursoLabelConNivel($curso, (int) $registro->idCursos),
            'importe' => CuotasFormato::importeParaInput($registro->importe),
            'signo1v' => trim((string) ($registro->signo1v ?? '-')),
            'valor1v' => $this->valorParaInput($registro->valor1v),
            'porcan1v' => trim((string) ($registro->porcan1v ?? '%')),
            'signo2v' => trim((string) ($registro->signo2v ?? '+')),
            'valor2v' => $this->valorParaInput($registro->valor2v),
            'porcan2v' => trim((string) ($registro->porcan2v ?? '%')),
            'signo3v' => trim((string) ($registro->signo3v ?? '+')),
            'valor3v' => $this->valorParaInput($registro->valor3v),
            'porcan3v' => trim((string) ($registro->porcan3v ?? '%')),
            'signo4v' => trim((string) ($registro->signo4v ?? '+')),
            'valor4v' => $this->valorParaInput($registro->valor4v),
            'porcan4v' => trim((string) ($registro->porcan4v ?? '%')),
        ];
    }

    private function valorParaInput(mixed $valor): string
    {
        return number_format((float) ($valor ?? 0), 2, ',', '');
    }

    private function cursoLabelConNivel(?Curso $curso, int $idCursos): string
    {
        if ($curso === null) {
            return 'Curso #'.$idCursos;
        }

        $nombre = $curso->nombreParaListado();
        $abrev = trim((string) ($curso->nivel?->abrev ?? ''));

        if ($abrev !== '') {
            return $nombre.' ('.$abrev.')';
        }

        return $nombre;
    }
}
