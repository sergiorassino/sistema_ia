<?php

namespace App\Services\SincroCidiInasistencias;

use App\Models\Inasistencia;
use App\Models\InasistenciaValor;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Importa inasistencias desde el CSV «InasistenciasDetalle» exportado por CIDI/GE.
 *
 * Layout del CSV (separador «;»):
 *   Grado/Año ; División ; Turno ; Nro. Documento ; Apellido ; Nombre ; Tipo ; Fecha
 *   (col 0)     (col 1)   (col 2)    (col 3)        (col 4)   (col 5) (col 6)(col 7)
 *
 * Estrategia: dentro de una única transacción, primero elimina todos los registros con
 * obs = 'CIDI' del ciclo lectivo y nivel activos, luego inserta las filas del archivo.
 * Los registros cargados manualmente (obs distinto) no se tocan.
 *
 * El alumno se identifica solo por DNI: las novedades se graban en la matrícula vigente
 * del ciclo lectivo y nivel de sesión, aunque el curso/división del CSV CIDI no coincida
 * (p. ej. cambio de división a mitad de año).
 *
 * - `just` se determina siempre desde la columna Tipo:
 *     · contiene «INJUSTIFICADO» → 'I'
 *     · resto (llegada tarde, retiro, justificado) → 'J'
 * - `tipo` se resuelve vía `texto_cidi` con búsqueda en cascada:
 *     1) match exacto normalizado
 *     2) quitar «JUSTIFICADO» / «INJUSTIFICADO» + reintentar
 *     3) quitar fracción (1/4, 1/2, 3/4) + reintentar
 * - Permite un único concepto «AUSENTE» en el catálogo para ambas justificaciones.
 */
final class CidiInasistenciasCsvImporter
{
    private const DELIMITER = ';';

    private const MAX_ISSUES = 250;

    private const COL_CURSO = 0;

    private const COL_SECCION = 1;

    private const COL_DNI = 3;

    private const COL_APELLIDO = 4;

    private const COL_NOMBRE = 5;

    private const COL_TIPO = 6;

    private const COL_FECHA = 7;

    private const CURSO_TEXTO = [
        'PRIMER AÑO'   => 1,
        'SEGUNDO AÑO'  => 2,
        'TERCER AÑO'   => 3,
        'CUARTO AÑO'   => 4,
        'QUINTO AÑO'   => 5,
        'SEXTO AÑO'    => 6,
    ];

    /** @var array<string, int|null> */
    private array $cursoCache = [];

    /** @var array<string, int|null> */
    private array $legajoCache = [];

    /** @var array<string, int|null> */
    private array $matriculaCache = [];

    /**
     * Índice: texto CIDI normalizado → InasistenciaValor.
     *
     * @var array<string, InasistenciaValor>
     */
    private array $porTextoCidi = [];

    public function import(string $absolutePath, int $idTerlec, int $idNivel): CidiInasistenciasCsvImportResult
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException('No se puede leer el archivo CSV.');
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        $this->cargarCatalogo();

        if ($this->porTextoCidi === []) {
            fclose($handle);

            throw new RuntimeException(
                'Ningún tipo en inasistencias_valores tiene «texto CIDI» configurado. Complete la vinculación antes de importar.'
            );
        }

        $this->cursoCache    = [];
        $this->legajoCache   = [];
        $this->matriculaCache = [];

        $issues              = [];
        $totalDataRows       = 0;
        $insertedRows        = 0;
        $skippedRows         = 0;
        $skippedPresenteRows = 0;
        $lineNumber          = 0;
        $deletedRows         = 0;

        try {
            $header = fgetcsv($handle, 0, self::DELIMITER);
            if ($header === false) {
                throw new RuntimeException('El archivo CSV está vacío o no tiene encabezado.');
            }

            DB::beginTransaction();

            // Fase 1: eliminar todos los registros CIDI del ciclo/nivel activos.
            $deletedRows = $this->eliminarRegistrosCidi($idTerlec, $idNivel);

            // Fase 2: insertar los registros del archivo (omitir PRESENTE).
            while (($rawRow = fgetcsv($handle, 0, self::DELIMITER)) !== false) {
                $lineNumber++;

                if ($this->isEmptyRow($rawRow)) {
                    continue;
                }

                $row      = $this->normalizeRow($rawRow);
                $tipoCidi = trim((string) ($row[self::COL_TIPO] ?? ''));

                if ($this->esPresente($tipoCidi)) {
                    $skippedPresenteRows++;

                    continue;
                }

                $totalDataRows++;

                $cursoNum = $this->mapCurso($row[self::COL_CURSO] ?? '');
                $seccion  = trim((string) ($row[self::COL_SECCION] ?? ''));
                $dniRaw   = trim((string) ($row[self::COL_DNI] ?? ''));
                $fechaRaw = trim((string) ($row[self::COL_FECHA] ?? ''));

                $context = $this->formatIssueContext($row);

                if ($dniRaw === '' || ! ctype_digit($dniRaw)) {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'dni_invalido', 'El DNI debe ser numérico.', $context);

                    continue;
                }

                $fecha = $this->parseFecha($fechaRaw);
                if ($fecha === null) {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'fecha_invalida', 'Fecha inválida o vacía.', $context);

                    continue;
                }

                $tipoResuelto = $this->resolverTipo($tipoCidi);
                if ($tipoResuelto === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'tipo_no_mapeado',
                        "No se pudo vincular el tipo CIDI «{$tipoCidi}» con ningún texto_cidi de inasistencias_valores.",
                        $context
                    );

                    continue;
                }

                [$idTipo, $cantidad] = $tipoResuelto;
                $just = $this->resolverJust($tipoCidi);
                $dni  = (int) $dniRaw;

                $idLegajos = $this->resolveLegajoId($dni);
                if ($idLegajos === null) {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'legajo_no_encontrado', "No existe legajo con DNI {$dni}.", $context);

                    continue;
                }

                // Preferir el curso del CSV si existe matrícula ahí; si no, cualquier matrícula del ciclo/nivel.
                $idCursosPreferido = ($cursoNum !== null)
                    ? $this->resolveCursoId($cursoNum, $seccion, $idTerlec, $idNivel)
                    : null;

                $idMatricula = $this->resolveMatriculaId($idTerlec, $idNivel, $idLegajos, $idCursosPreferido);
                if ($idMatricula === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'matricula_no_encontrada',
                        'El alumno no está matriculado en el ciclo lectivo y nivel activos.',
                        $context
                    );

                    continue;
                }

                Inasistencia::create([
                    'idMatricula' => $idMatricula,
                    'fecha'       => $fecha,
                    'tipo'        => (string) $idTipo,
                    'cantidad'    => round($cantidad, 2),
                    'just'        => $just,
                    'obs'         => 'CIDI',
                ]);
                $insertedRows++;
            }

            DB::commit();

            $issuesTruncated = count($issues) > self::MAX_ISSUES;
            if ($issuesTruncated) {
                $issues = array_slice($issues, 0, self::MAX_ISSUES);
            }

            return new CidiInasistenciasCsvImportResult(
                totalDataRows:        $totalDataRows,
                insertedRows:         $insertedRows,
                updatedRows:          0,
                skippedRows:          $skippedRows,
                skippedPresenteRows:  $skippedPresenteRows,
                skippedSinCambioRows: 0,
                committed:            true,
                issues:               $issues,
                issuesTruncated:      $issuesTruncated,
                deletedRows:          $deletedRows,
            );
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $e;
        } finally {
            fclose($handle);
        }
    }

    // -------------------------------------------------------------------------
    // Catálogo texto_cidi
    // -------------------------------------------------------------------------

    private function cargarCatalogo(): void
    {
        $valores = InasistenciaValor::query()
            ->orderBy('concepto')
            ->get(['id', 'concepto', 'texto_cidi', 'cantidad']);

        $this->porTextoCidi = [];

        foreach ($valores as $valor) {
            $texto = trim((string) ($valor->texto_cidi ?? ''));
            if ($texto === '') {
                continue;
            }

            $key = InasistenciaValor::normalizarTexto($texto);
            if ($key !== '') {
                $this->porTextoCidi[$key] = $valor;
            }
        }
    }

    /**
     * Resuelve el InasistenciaValor a partir del texto del Tipo del CSV usando búsqueda en cascada:
     *   1. Match exacto normalizado
     *   2. Sin «JUSTIFICADO» / «INJUSTIFICADO»
     *   3. Sin fracción (1/4, 1/2, 3/4)
     *   4. Sin justificación Y sin fracción
     *
     * @return array{0: int, 1: float}|null  [idTipo, cantidad]
     */
    private function resolverTipo(string $tipoCidi): ?array
    {
        // 1. Exacto
        $valor = $this->buscarEnCatalogo($tipoCidi);
        if ($valor !== null) {
            return [$valor->id, $this->resolverCantidad($tipoCidi, $valor)];
        }

        // 2. Sin justificación
        $sinJust = $this->quitarJustificacion($tipoCidi);
        if ($sinJust !== $tipoCidi) {
            $valor = $this->buscarEnCatalogo($sinJust);
            if ($valor !== null) {
                return [$valor->id, $this->resolverCantidad($tipoCidi, $valor)];
            }
        }

        // 3. Sin fracción
        $sinFrac = $this->quitarFraccion($tipoCidi);
        if ($sinFrac !== $tipoCidi) {
            $valor = $this->buscarEnCatalogo($sinFrac);
            if ($valor !== null) {
                return [$valor->id, $this->resolverCantidad($tipoCidi, $valor)];
            }
        }

        // 4. Sin justificación ni fracción
        $sinJustFrac = $this->quitarFraccion($sinJust);
        if ($sinJustFrac !== $tipoCidi) {
            $valor = $this->buscarEnCatalogo($sinJustFrac);
            if ($valor !== null) {
                return [$valor->id, $this->resolverCantidad($tipoCidi, $valor)];
            }
        }

        return null;
    }

    private function buscarEnCatalogo(string $texto): ?InasistenciaValor
    {
        $key = InasistenciaValor::normalizarTexto($texto);

        return $key !== '' ? ($this->porTextoCidi[$key] ?? null) : null;
    }

    /**
     * Extrae la fracción del texto original (ej. «LLEGADA TARDE 1/2» → 0.5);
     * si no hay fracción, devuelve `cantidad` del catálogo o 1.0.
     */
    private function resolverCantidad(string $tipoCidi, InasistenciaValor $valor): float
    {
        $norm = InasistenciaValor::normalizarTexto($tipoCidi);

        if (str_contains($norm, '1/4') || preg_match('/\b1\s*\/\s*4\b/', $tipoCidi)) {
            return 0.25;
        }
        if (str_contains($norm, '1/2') || preg_match('/\b1\s*\/\s*2\b/', $tipoCidi)) {
            return 0.5;
        }
        if (str_contains($norm, '3/4') || preg_match('/\b3\s*\/\s*4\b/', $tipoCidi)) {
            return 0.75;
        }

        return $valor->cantidad !== null ? (float) $valor->cantidad : 1.0;
    }

    /**
     * Quita «JUSTIFICADO» e «INJUSTIFICADO» del texto (normalizado) y retorna la cadena limpia.
     */
    private function quitarJustificacion(string $texto): string
    {
        $s = trim(preg_replace('/\bINJUSTIFICADO\b/i', '', $texto) ?? $texto);
        $s = trim(preg_replace('/\bJUSTIFICADO\b/i', '', $s) ?? $s);

        return preg_replace('/\s{2,}/', ' ', $s) ?? $s;
    }

    /**
     * Quita fracciones literales «1/4», «1/2», «3/4» del texto.
     */
    private function quitarFraccion(string $texto): string
    {
        $s = preg_replace('/\b[13]\/[24]\b/', '', $texto) ?? $texto;

        return trim(preg_replace('/\s{2,}/', ' ', $s) ?? $s);
    }

    /**
     * Determina la justificación a partir del texto de la columna Tipo:
     *   - contiene «INJUSTIFICADO» → 'I'
     *   - contiene «JUSTIFICADO» (sin injustificado) → 'J'
     *   - resto (llegada tarde, retiro, etc.) → 'J'
     *
     * @return 'J'|'I'
     */
    private function resolverJust(string $tipoCidi): string
    {
        $norm = InasistenciaValor::normalizarTexto($tipoCidi);

        if (str_contains($norm, 'injustificad')) {
            return 'I';
        }

        return 'J';
    }

    private function esPresente(string $tipoCidi): bool
    {
        $norm = InasistenciaValor::normalizarTexto($tipoCidi);

        return $norm === 'presente' || str_starts_with($norm, 'presente ');
    }

    // -------------------------------------------------------------------------
    // Persistencia
    // -------------------------------------------------------------------------

    /**
     * Elimina todos los registros con obs='CIDI' del ciclo lectivo y nivel indicados.
     * Los registros manuales (obs distinto, p. ej. ed. física) no se tocan.
     */
    private function eliminarRegistrosCidi(int $idTerlec, int $idNivel): int
    {
        return (int) DB::table('inasistencias')
            ->where('obs', 'CIDI')
            ->whereIn('idMatricula', function ($q) use ($idTerlec, $idNivel) {
                $q->select('id')
                    ->from('matricula')
                    ->where('idTerlec', $idTerlec)
                    ->where('idNivel', $idNivel);
            })
            ->delete();
    }

    // -------------------------------------------------------------------------
    // Helpers de resolución
    // -------------------------------------------------------------------------

    private function resolveCursoId(int $cursoNum, string $seccion, int $idTerlec, int $idNivel): ?int
    {
        $cacheKey = "{$cursoNum}|{$seccion}|{$idTerlec}|{$idNivel}";
        if (array_key_exists($cacheKey, $this->cursoCache)) {
            return $this->cursoCache[$cacheKey];
        }

        $id = DB::table('cursos')
            ->where('idTerlec', $idTerlec)
            ->where('idNivel', $idNivel)
            ->where('c', $cursoNum)
            ->whereRaw('TRIM(s) = ?', [$seccion])
            ->value('Id');

        $resolved = $id !== null ? (int) $id : null;
        $this->cursoCache[$cacheKey] = $resolved;

        return $resolved;
    }

    private function resolveLegajoId(int $dni): ?int
    {
        $key = (string) $dni;
        if (array_key_exists($key, $this->legajoCache)) {
            return $this->legajoCache[$key];
        }

        $id = DB::table('legajos')->where('dni', $dni)->value('id');
        $resolved = $id !== null ? (int) $id : null;
        $this->legajoCache[$key] = $resolved;

        return $resolved;
    }

    /**
     * Matrícula vigente del alumno en el ciclo/nivel de sesión.
     * Si CIDI indica un curso y hay matrícula ahí, se usa; si no, la más reciente del ciclo/nivel
     * (cubre cambios de división a mitad de año).
     */
    private function resolveMatriculaId(int $idTerlec, int $idNivel, int $idLegajos, ?int $idCursosPreferido = null): ?int
    {
        $cacheKey = "{$idTerlec}|{$idNivel}|{$idLegajos}|".($idCursosPreferido ?? 0);
        if (array_key_exists($cacheKey, $this->matriculaCache)) {
            return $this->matriculaCache[$cacheKey];
        }

        if ($idCursosPreferido !== null) {
            $idPreferido = DB::table('matricula')
                ->where('idTerlec', $idTerlec)
                ->where('idNivel', $idNivel)
                ->where('idLegajos', $idLegajos)
                ->where('idCursos', $idCursosPreferido)
                ->orderBy('id')
                ->value('id');

            if ($idPreferido !== null) {
                $resolved = (int) $idPreferido;
                $this->matriculaCache[$cacheKey] = $resolved;

                return $resolved;
            }
        }

        $id = DB::table('matricula')
            ->where('idTerlec', $idTerlec)
            ->where('idNivel', $idNivel)
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->value('id');

        $resolved = $id !== null ? (int) $id : null;
        $this->matriculaCache[$cacheKey] = $resolved;

        return $resolved;
    }

    private function parseFecha(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $raw : null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            $y  = (int) $m[3];
            $mo = (int) $m[2];
            $d  = (int) $m[1];

            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }

        return null;
    }

    private function mapCurso(string $texto): ?int
    {
        $key = mb_strtoupper(trim($texto), 'UTF-8');

        return self::CURSO_TEXTO[$key] ?? null;
    }

    /**
     * @param  list<string|null>  $rawRow
     * @return list<string>
     */
    private function normalizeRow(array $rawRow): array
    {
        $out = [];
        foreach ($rawRow as $valor) {
            $s = (string) ($valor ?? '');
            if ($s === '') {
                $out[] = '';

                continue;
            }

            if (! mb_check_encoding($s, 'UTF-8')) {
                $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
            }

            $out[] = trim($s);
        }

        return $out;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) ($cell ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $row
     */
    private function formatIssueContext(array $row): string
    {
        $parts = [];

        $dni = trim((string) ($row[self::COL_DNI] ?? ''));
        if ($dni !== '') {
            $parts[] = 'DNI '.$dni;
        }

        $apellido = trim((string) ($row[self::COL_APELLIDO] ?? ''));
        $nombre   = trim((string) ($row[self::COL_NOMBRE] ?? ''));
        $alumno   = trim("{$apellido} {$nombre}");
        if ($alumno !== '') {
            $parts[] = $alumno;
        }

        $curso = trim(trim((string) ($row[self::COL_CURSO] ?? '')).' '.trim((string) ($row[self::COL_SECCION] ?? '')));
        if ($curso !== '') {
            $parts[] = $curso;
        }

        $tipo  = trim((string) ($row[self::COL_TIPO] ?? ''));
        $fecha = trim((string) ($row[self::COL_FECHA] ?? ''));
        if ($tipo !== '' || $fecha !== '') {
            $parts[] = trim("{$tipo} · {$fecha}", ' ·');
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array{line: int, code: string, message: string, detail?: string}
     */
    private function issue(int $line, string $code, string $message, string $detail = ''): array
    {
        $item = ['line' => $line, 'code' => $code, 'message' => $message];
        if ($detail !== '') {
            $item['detail'] = $detail;
        }

        return $item;
    }
}
