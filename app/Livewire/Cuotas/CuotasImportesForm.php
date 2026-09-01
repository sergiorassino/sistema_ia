<?php

namespace App\Livewire\Cuotas;

use App\Models\CuotasImporte;
use App\Models\Curso;
use App\Support\Cuotas\CuotasImportesCatalog;
use App\Support\Database\PersistenciaColumnas;
use App\Support\Navegacion\ContextoCuotasImportesSesion;
use App\Support\PermisosCuotas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Edición de importes y fórmulas por curso (`cuotasimportes`) para una plantilla de cuota.
 *
 * La grilla no viaja en el snapshot Livewire: cada celda se persiste con `commitDraftCell`
 * en modo renderless (mismo criterio que la carga de calificaciones).
 */
class CuotasImportesForm extends Component
{
    public int $idCuotas = 0;

    public function mount(): void
    {
        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);

        $idCuotas = ContextoCuotasImportesSesion::idCuotas();
        abort_if($idCuotas === null, 404);

        $this->idCuotas = $idCuotas;
        CuotasImportesCatalog::cuotaDelCicloOrFail($idCuotas);
    }

    /**
     * Persiste una celda (importe, valor, signo o %/$) sin remorph de la grilla.
     */
    public function commitDraftCell(string $key, string $field, string $value): void
    {
        $this->skipRender();

        abort_unless(PermisosCuotas::puedeImportesPorCurso(), 403);

        $field = trim($field);
        if (! in_array($field, CuotasImportesCatalog::camposEditables(), true)) {
            return;
        }

        $id = (int) $key;
        if ($id <= 0) {
            return;
        }

        $registro = CuotasImportesCatalog::importeDelCicloOrFail($id, $this->idCuotas);
        $clave = (string) $registro->id;
        $revert = CuotasImportesCatalog::formatearCampoParaInput($field, $registro->{$field} ?? null);
        $row = CuotasImportesCatalog::valoresDraftDesdeRegistro($registro);
        $row[$field] = trim($value);

        $rateKey = 'cuotas-importes:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 120)) {
            $this->emitirResultadoCelda([
                'ok' => false,
                'key' => $clave,
                'field' => $field,
                'value' => $revert,
                'message' => 'Demasiados intentos. Espere un momento e intente nuevamente.',
            ]);

            return;
        }

        $validator = Validator::make(
            ['draft' => [$clave => $row]],
            CuotasImportesCatalog::reglasFila($clave, $row),
        );
        if ($validator->fails()) {
            $this->emitirResultadoCelda([
                'ok' => false,
                'key' => $clave,
                'field' => $field,
                'value' => $revert,
                'message' => (string) ($validator->errors()->first() ?: 'Valor inválido.'),
            ]);

            return;
        }

        try {
            CuotasImportesCatalog::validarMontos($row, "draft.{$clave}.");
        } catch (ValidationException $e) {
            $this->emitirResultadoCelda([
                'ok' => false,
                'key' => $clave,
                'field' => $field,
                'value' => $revert,
                'message' => (string) ($e->validator->errors()->first() ?: 'Valor inválido.'),
            ]);

            return;
        }

        $persistido = CuotasImportesCatalog::valorPersistidoParaCampo($field, $row[$field]);
        $formateado = CuotasImportesCatalog::formatearCampoParaInput($field, $persistido);

        if (CuotasImportesCatalog::campoEquivaleAlRegistro($registro, $field, $persistido)) {
            $this->emitirResultadoCelda([
                'ok' => true,
                'key' => $clave,
                'field' => $field,
                'value' => $formateado,
                'message' => '',
            ]);

            return;
        }

        RateLimiter::hit($rateKey, 60);

        try {
            $registro->update([$field => $persistido]);
        } catch (QueryException $e) {
            $this->emitirResultadoCelda([
                'ok' => false,
                'key' => $clave,
                'field' => $field,
                'value' => $revert,
                'message' => PersistenciaColumnas::mensajeDesdeQueryException($e)
                    ?? 'No se pudo guardar. Intente nuevamente.',
            ]);

            return;
        }

        $this->emitirResultadoCelda([
            'ok' => true,
            'key' => $clave,
            'field' => $field,
            'value' => $formateado,
            'message' => '',
        ]);
    }

    public function render()
    {
        $cuota = CuotasImportesCatalog::cuotaDelCicloOrFail($this->idCuotas);
        $ano = (int) schoolCtx()->terlecAno();

        return view('livewire.cuotas.importes-form', [
            'filas' => $this->cargarFilas(),
            'cuota' => $cuota,
            'opcionesSigno' => CuotasImportesCatalog::opcionesSigno(),
            'opcionesPorcan' => CuotasImportesCatalog::opcionesPorcan(),
            'leyendasPorcan' => CuotasImportesCatalog::leyendasPorcan(),
            'ano' => $ano,
        ])->layout(layoutMenuStaff(), [
            'pageTitle' => 'Importes — '.trim((string) $cuota->nombre),
        ]);
    }

    /**
     * @param  array{ok: bool, key: string, field: string, value: string, message: string}  $payload
     */
    private function emitirResultadoCelda(array $payload): void
    {
        $this->js('window.seCiiApplyCellResult && window.seCiiApplyCellResult('.Js::from($payload).')');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cargarFilas(): array
    {
        $filas = [];

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
            $filas[(string) $registro->id] = $this->filaDesdeModelo($registro);
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    private function filaDesdeModelo(CuotasImporte $registro): array
    {
        return array_merge(
            CuotasImportesCatalog::valoresDraftDesdeRegistro($registro),
            [
                'id' => (int) $registro->id,
                'idCursos' => (int) $registro->idCursos,
                'cursoLabel' => $this->cursoLabelConNivel($registro->curso, (int) $registro->idCursos),
            ],
        );
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
