<?php

namespace App\Services\SincroGe;

use App\Support\Database\PersistenciaColumnas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Importa calificaciones desde el CSV exportado por GE/CIDI (formato EE…).
 *
 * ## Contexto de sesión (obligatorio)
 * - `idTerlec` y `idNivel` provienen de `schoolCtx()` al importar.
 * - Solo se consideran materias y matrículas de ese ciclo y nivel.
 *
 * ## Por cada fila del CSV
 * 1. Curso: texto GE («PRIMER AÑO»…) → número `cursos.c`; división → `TRIM(cursos.s)`.
 * 2. Materia: `Cód. Esp. Curricular` → `matplan.codGE|codGE2|codGE3` en `materias` del ciclo/nivel/curso.
 * 3. Alumno: columna DNI del CSV → `legajos.id` (`idLegajos`); el UPDATE nunca usa el DNI directamente.
 * 4. Matrícula: debe existir en `matricula` (mismo `idTerlec`, `idNivel`, `idCursos`, `idLegajos`).
 * 5. Notas: siempre se arma el UPDATE con `ic01`–`ic28` y `calif` desde el CSV (celdas vacías → cadena vacía en BD, para reflejar borrados en GE).
 * 6. Validación opcional contra `notaspermitidas` del nivel.
 * 7. Longitud máxima de cada columna (según esquema): si el valor no entra, la fila falla (evita truncado silencioso de MySQL). Errores de BD se capturan y no se cuentan como éxito.
 *
 * ## Criterio único de actualización (tabla `calificaciones`)
 * Exactamente **una** fila debe cumplir:
 *   `idLegajos` + `idTerlec` + `idMaterias`
 * (`idMaterias` es el de `materias` resuelto por el código GE; mismo criterio que la grilla
 * «Carga de calificaciones» secundario). Si hay 0 o más de 1 coincidencia, la fila falla.
 *
 * ## Transacción
 * Una transacción global: commit solo si hubo al menos una fila actualizada; si no, rollback.
 */
final class GeCsvImporter
{
    private const DELIMITER = ';';

    /** Evita respuestas enormes si el CSV tiene miles de filas con error. */
    private const MAX_ISSUES = 250;

    /**
     * Cantidad exacta de columnas del layout GE/CIDI secundario actual (EE1220762 y equivalentes).
     * Si GE agrega o quita columnas, fallar aquí evita importar con índices desfasados.
     */
    public const EXPECTED_COLUMN_COUNT = 82;

    /** Índices de columnas (0-based) según encabezado oficial GE (bloque eval/JIS estable). */
    private const COL_CURSO = 0;

    private const COL_SECCION = 1;

    private const COL_DNI = 5;

    private const COL_APELLIDO = 6;

    private const COL_NOMBRE = 7;

    private const COL_COD_MATERIA = 12;

    private const COL_ESPACIO_CURRICULAR = 11;

    /** Índice fijo de «NOTA FINAL» en el layout GE actual (después de Apren. Eval. 1–8 y GRAL.). */
    public const COL_NOTA_FINAL = 80;

    /** Índice fijo de «ESTADO» en el layout GE actual. */
    private const COL_ESTADO = 81;

    /** Pares [nota, recup1, recup2] por evaluación → ic01..ic24. */
    private const EVAL_COLS = [
        [15, 17, 19],
        [21, 23, 25],
        [27, 29, 31],
        [33, 35, 37],
        [39, 41, 43],
        [45, 47, 49],
        [51, 53, 55],
        [57, 59, 61],
    ];

    private const JIS_COLS = [
        ['n' => 63, 'r' => 65],
        ['n' => 67, 'r' => 69],
    ];

    private const CURSO_TEXTO = [
        'PRIMER AÑO' => 1,
        'SEGUNDO AÑO' => 2,
        'TERCER AÑO' => 3,
        'CUARTO AÑO' => 4,
        'QUINTO AÑO' => 5,
        'SEXTO AÑO' => 6,
    ];

    /** @var array<string, int> nombre columna BD => longitud máxima (character_maximum_length) */
    private array $columnMaxLengths = [];

    /** @var array<string, array{idMatPlan: int, idCursos: int, idMaterias: int, matPlanMateria: string}|null> */
    private array $materiaCache = [];

    /** @var array<string, int|null> */
    private array $legajoCache = [];

    /** @var array<string, bool> */
    private array $matriculaCache = [];

    /** @var list<string> */
    private array $notasPermitidas = [];

    /**
     * Tope de fragmentos a unir cuando GE inserta saltos de línea sin comillas
     * en textos largos (p. ej. «Apren. Eval.»).
     */
    private const MAX_ROW_STITCH = 20;

    /**
     * Une dos resultados consecutivos de fgetcsv: el salto de línea no citado
     * queda dentro del último campo de $first y el resto de columnas de $next se anexan.
     *
     * @param  list<string|null>  $first
     * @param  list<string|null>  $next
     * @return list<string|null>
     */
    public static function mergeRowFragments(array $first, array $next): array
    {
        if ($first === []) {
            return $next;
        }

        $merged = $first;
        $lastIdx = count($merged) - 1;
        $merged[$lastIdx] = (string) ($merged[$lastIdx] ?? '')."\n".(string) ($next[0] ?? '');

        $n = count($next);
        for ($i = 1; $i < $n; $i++) {
            $merged[] = $next[$i];
        }

        return $merged;
    }

    /**
     * Une una continuación «rellenada» a 82 columnas (patrón NSSC/GE):
     * la fila de alumno queda sin ESTADO y el resto del texto Apren./horarios
     * + INSCRIPTO viene en la(s) línea(s) siguiente(s), cada una también con 82 cols.
     *
     * @param  list<string|null>  $base
     * @param  list<string|null>  $cont
     * @return list<string|null>
     */
    public static function mergePaddedContinuation(array $base, array $cont): array
    {
        $expected = self::EXPECTED_COLUMN_COUNT;
        while (count($base) < $expected) {
            $base[] = '';
        }
        $base = array_slice($base, 0, $expected);

        $end = count($cont) - 1;
        while ($end >= 0 && trim((string) ($cont[$end] ?? '')) === '') {
            $end--;
        }
        if ($end < 0) {
            return $base;
        }
        $useful = array_slice($cont, 0, $end + 1);

        $padStart = $expected;
        for ($i = $expected - 1; $i >= 0; $i--) {
            if (trim((string) ($base[$i] ?? '')) !== '') {
                $padStart = $i + 1;
                break;
            }
        }

        $slots = $expected - $padStart;
        if ($slots <= 0) {
            $last = $expected - 1;
            for ($i = $expected - 1; $i >= 0; $i--) {
                if (trim((string) ($base[$i] ?? '')) !== '') {
                    $last = $i;
                    break;
                }
            }
            foreach ($useful as $val) {
                if (trim((string) $val) === '') {
                    continue;
                }
                $base[$last] = rtrim((string) ($base[$last] ?? ''))."\n".trim((string) $val);
            }

            return $base;
        }

        if (count($useful) > $slots) {
            $useful = self::compressFieldsToSlots($useful, $slots);
        }

        foreach ($useful as $j => $val) {
            $base[$padStart + $j] = $val;
        }

        return $base;
    }

    /**
     * Reduce campos de una continuación al cupo de columnas vacías al final,
     * quitando vacíos intermedios (GE suele meter un «;» de más al partir la línea).
     *
     * @param  list<string|null>  $fields
     * @return list<string|null>
     */
    public static function compressFieldsToSlots(array $fields, int $slots): array
    {
        if ($slots < 1) {
            return [];
        }

        while (count($fields) > $slots) {
            $removed = false;
            for ($i = count($fields) - 2; $i >= 1; $i--) {
                if (trim((string) ($fields[$i] ?? '')) === '') {
                    array_splice($fields, $i, 1);
                    $removed = true;
                    break;
                }
            }
            if ($removed) {
                continue;
            }

            $last = array_pop($fields);
            while (count($fields) > $slots - 1) {
                $extra = array_shift($fields);
                if ($fields === []) {
                    $fields[] = $extra;
                    break;
                }
                $fields[0] = rtrim((string) ($fields[0] ?? '')).' '.trim((string) ($extra ?? ''));
            }
            $fields[] = $last;
            break;
        }

        return $fields;
    }

    /**
     * Valida el encabezado contra el layout GE/CIDI secundario vigente.
     * Devuelve mensaje de error o null si es válido. No escribe en BD.
     *
     * @param  list<string|null>|false  $header
     */
    public static function mensajeSiLayoutInvalido(array|false $header): ?string
    {
        if ($header === false || $header === []) {
            return 'El archivo CSV está vacío o no tiene encabezado.';
        }

        $count = count($header);
        if ($count !== self::EXPECTED_COLUMN_COUNT) {
            return "El archivo tiene {$count} columnas, pero el sistema espera exactamente "
                .self::EXPECTED_COLUMN_COUNT
                .' (layout GE/CIDI secundario actual; Nota final en la columna '
                .(self::COL_NOTA_FINAL + 1)
                .'). GE puede haber cambiado el export: no se importó ningún dato. '
                .'Actualice el módulo de sincronización antes de volver a intentar.';
        }

        $joined = mb_strtoupper(implode(';', array_map(
            static fn ($v) => trim(str_replace("\xEF\xBB\xBF", '', (string) ($v ?? ''))),
            $header
        )), 'UTF-8');

        if (! str_contains($joined, 'ESPACIO CURRICULAR') || ! str_contains($joined, 'NOTA EVAL 1')) {
            return 'El encabezado no coincide con el listado GE/CIDI de calificaciones (faltan columnas esperadas). '
                .'No se importó ningún dato.';
        }

        $nombreNotaFinal = mb_strtoupper(
            trim(str_replace("\xEF\xBB\xBF", '', (string) ($header[self::COL_NOTA_FINAL] ?? ''))),
            'UTF-8'
        );
        if ($nombreNotaFinal !== 'NOTA FINAL') {
            return 'En la columna '.(self::COL_NOTA_FINAL + 1).' se esperaba «NOTA FINAL» y el archivo tiene «'
                .trim((string) ($header[self::COL_NOTA_FINAL] ?? '')).'». '
                .'El layout de GE cambió: no se importó ningún dato. Actualice el módulo de sincronización.';
        }

        return null;
    }

    public function import(string $absolutePath, int $idTerlec, int $idNivel): GeCsvImportResult
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException('No se puede leer el archivo CSV.');
        }

        // Archivos GE suelen tener miles de filas; cada una implica varias consultas.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        $this->materiaCache = [];
        $this->legajoCache = [];
        $this->matriculaCache = [];
        $this->notasPermitidas = $this->loadNotasPermitidas($idNivel);
        $this->columnMaxLengths = $this->loadColumnMaxLengths();

        $issues = [];
        $totalDataRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;
        $lineNumber = 0;
        $pendingUpdates = 0;

        try {
            // Encabezado
            $header = fgetcsv($handle, 0, self::DELIMITER);
            $layoutError = self::mensajeSiLayoutInvalido($header);
            if ($layoutError !== null) {
                throw new RuntimeException($layoutError);
            }

            DB::beginTransaction();

            while (($rawRow = $this->readLogicalCsvRow($handle)) !== false) {
                $lineNumber++;

                if ($this->isEmptyRow($rawRow)) {
                    continue;
                }

                $totalDataRows++;
                $row = $this->normalizeRow($rawRow);

                $cursoNum = $this->mapCurso($row[self::COL_CURSO] ?? '');
                $seccion = trim((string) ($row[self::COL_SECCION] ?? ''));
                $codMat = trim((string) ($row[self::COL_COD_MATERIA] ?? ''));
                $dniRaw = trim((string) ($row[self::COL_DNI] ?? ''));

                if ($cursoNum === null) {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'curso_invalido', 'Curso no reconocido en el archivo.', $this->formatIssueContext($row));

                    continue;
                }

                if ($codMat === '') {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'codigo_materia_vacio', 'Falta el código de espacio curricular (GE).', $this->formatIssueContext($row));

                    continue;
                }

                if ($dniRaw === '' || ! ctype_digit($dniRaw)) {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'dni_invalido', 'El DNI debe ser numérico.', $this->formatIssueContext($row));

                    continue;
                }

                $dni = (int) $dniRaw;

                $materia = $this->resolveMateria($codMat, $cursoNum, $seccion, $idTerlec, $idNivel);
                if ($materia === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'materia_no_encontrada',
                        "No se encontró la materia con código GE «{$codMat}» para {$cursoNum}° «{$seccion}».",
                        $this->formatIssueContext($row)
                    );

                    continue;
                }

                $context = $this->formatIssueContext($row, $materia['matPlanMateria']);

                $idLegajos = $this->resolveLegajoId($dni);
                if ($idLegajos === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'legajo_no_encontrado',
                        "No existe legajo con DNI {$dni}.",
                        $context
                    );

                    continue;
                }

                if (! $this->tieneMatricula($idTerlec, $idNivel, $materia['idCursos'], $idLegajos)) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'matricula_no_encontrada',
                        'El alumno no está matriculado en el curso/división del archivo para el ciclo lectivo activo.',
                        $context.' · '.$this->matriculaMismatchDebug($idLegajos, $idTerlec, $idNivel, $materia['idCursos'])
                    );

                    continue;
                }

                $payload = $this->buildGradePayload($row);

                $oversized = $this->findOversizedFields($payload);
                if ($oversized !== []) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'valor_demasiado_largo',
                        'Hay valores que no entran en la columna de la base de datos: '.implode('; ', $oversized).'.',
                        $context
                    );

                    continue;
                }

                $invalidNotes = $this->findInvalidNotes($payload);
                if ($invalidNotes !== []) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'nota_no_permitida',
                        'Hay notas que no están en el catálogo del nivel: '.implode(', ', $invalidNotes).'.',
                        $context
                    );

                    continue;
                }

                try {
                    $updateResult = $this->updateCalificacionRow($idLegajos, $idTerlec, $materia, $payload);
                } catch (QueryException $e) {
                    $skippedRows++;
                    $msg = PersistenciaColumnas::mensajeDesdeQueryException($e)
                        ?? 'Error de base de datos al guardar la calificación (el valor puede no caber en la columna o el esquema no coincide).';
                    $issues[] = $this->issue($lineNumber, 'error_base_datos', $msg, $context);

                    continue;
                }

                if ($updateResult === -2) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'calificacion_ambigua',
                        'Hay más de un registro en calificaciones con el mismo idLegajos + idTerlec + idMaterias; no se actualizó.',
                        $context.' · '.$this->califMatchDebug($idLegajos, $idTerlec, $materia['idMaterias'])
                    );

                    continue;
                }

                if ($updateResult < 1) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'calificacion_no_encontrada',
                        'No existe exactamente una fila en calificaciones con idLegajos + idTerlec + idMaterias (criterio único de sincronización).',
                        $context.' · '.$this->califMatchDebug($idLegajos, $idTerlec, $materia['idMaterias'])
                    );

                    continue;
                }

                $updatedRows++;
                $pendingUpdates++;
            }

            $committed = $pendingUpdates > 0;
            if ($committed) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            $issuesTruncated = count($issues) > self::MAX_ISSUES;
            if ($issuesTruncated) {
                $issues = array_slice($issues, 0, self::MAX_ISSUES);
            }

            return new GeCsvImportResult(
                totalDataRows: $totalDataRows,
                updatedRows: $updatedRows,
                skippedRows: $skippedRows,
                committed: $committed,
                issues: $issues,
                issuesTruncated: $issuesTruncated,
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

    /**
     * Lee una fila lógica del CSV GE/CIDI.
     *
     * GE a veces exporta textos largos (Apren. Eval. / horarios) con saltos de línea
     * reales y sin comillas CSV. Hay dos variantes:
     * 1) Fragmentos con menos de 82 columnas (p. ej. Montecristo).
     * 2) Fragmentos ya «rellenados» a 82 columnas, sin ESTADO, y continuación
     *    en la línea siguiente (p. ej. NSSC: «Operaciones básicas…» / «14:05»).
     *
     * @param  resource  $handle
     * @return list<string|null>|false
     */
    private function readLogicalCsvRow($handle): array|false
    {
        $row = fgetcsv($handle, 0, self::DELIMITER);
        if ($row === false) {
            return false;
        }

        $merges = 0;
        while (count($row) < self::EXPECTED_COLUMN_COUNT && $merges < self::MAX_ROW_STITCH) {
            $pos = ftell($handle);
            if ($pos === false) {
                break;
            }

            $next = fgetcsv($handle, 0, self::DELIMITER);
            if ($next === false) {
                break;
            }

            if ($this->pareceInicioDeFilaGe($next)) {
                fseek($handle, $pos);

                break;
            }

            $row = self::mergeRowFragments($row, $next);
            $merges++;
        }

        // Variante rellenada a 82 cols: fila de alumno sin ESTADO + continuaciones.
        $padMerges = 0;
        while (
            $this->pareceInicioDeFilaGe($row)
            && $this->estadoVacio($row)
            && $padMerges < self::MAX_ROW_STITCH
        ) {
            $pos = ftell($handle);
            if ($pos === false) {
                break;
            }

            $next = fgetcsv($handle, 0, self::DELIMITER);
            if ($next === false) {
                break;
            }

            if ($this->pareceInicioDeFilaGe($next)) {
                fseek($handle, $pos);

                break;
            }

            if ($this->isEmptyRow($next)) {
                $padMerges++;

                continue;
            }

            $row = self::mergePaddedContinuation($row, $next);
            $padMerges++;
        }

        return $row;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function pareceInicioDeFilaGe(array $row): bool
    {
        $curso = mb_strtoupper(trim((string) ($row[0] ?? '')), 'UTF-8');

        return isset(self::CURSO_TEXTO[$curso]);
    }

    /**
     * @param  list<string|null>  $row
     */
    private function estadoVacio(array $row): bool
    {
        return trim((string) ($row[self::COL_ESTADO] ?? '')) === '';
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

            // Textos de Apren. partidos por CRLF: una sola línea para no ensuciar payload/contextos.
            $s = preg_replace("/[\r\n]+/u", ' ', $s) ?? $s;

            $out[] = trim($s);
        }

        return $out;
    }

    /**
     * Contexto legible para la columna «Contexto» del informe de importación.
     *
     * @param  list<string>  $row
     */
    private function formatIssueContext(array $row, ?string $materiaNombre = null): string
    {
        $parts = [];

        $dni = trim((string) ($row[self::COL_DNI] ?? ''));
        if ($dni !== '') {
            $parts[] = 'DNI '.$dni;
        }

        $apellido = trim((string) ($row[self::COL_APELLIDO] ?? ''));
        $nombre = trim((string) ($row[self::COL_NOMBRE] ?? ''));
        $alumno = trim("{$apellido} {$nombre}");
        if ($alumno !== '') {
            $parts[] = $alumno;
        }

        $curso = trim(trim((string) ($row[self::COL_CURSO] ?? '')).' '.trim((string) ($row[self::COL_SECCION] ?? '')));
        if ($curso !== '') {
            $parts[] = $curso;
        }

        $materia = $materiaNombre ?? trim((string) ($row[self::COL_ESPACIO_CURRICULAR] ?? ''));
        if ($materia !== '') {
            $parts[] = $materia;
        }

        return implode(' · ', $parts);
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

    private function mapCurso(string $texto): ?int
    {
        $key = mb_strtoupper(trim($texto), 'UTF-8');

        return self::CURSO_TEXTO[$key] ?? null;
    }

    /**
     * @return array{idMatPlan: int, idCursos: int, idMaterias: int, matPlanMateria: string}|null
     */
    private function resolveMateria(string $codMat, int $cursoNum, string $seccion, int $idTerlec, int $idNivel): ?array
    {
        $cacheKey = "{$codMat}|{$cursoNum}|{$seccion}|{$idTerlec}|{$idNivel}";
        if (array_key_exists($cacheKey, $this->materiaCache)) {
            return $this->materiaCache[$cacheKey];
        }

        $row = DB::table('materias')
            ->join('cursos', 'materias.idCursos', '=', 'cursos.Id')
            ->join('matplan', 'materias.idMatPlan', '=', 'matplan.id')
            ->where('materias.idTerlec', $idTerlec)
            ->where('materias.idNivel', $idNivel)
            ->where('cursos.c', $cursoNum)
            ->whereRaw('TRIM(cursos.s) = ?', [$seccion])
            ->where(function ($q) use ($codMat) {
                $q->whereRaw('TRIM(matplan.codGE) = ?', [$codMat])
                    ->orWhereRaw('TRIM(matplan.codGE2) = ?', [$codMat])
                    ->orWhereRaw('TRIM(matplan.codGE3) = ?', [$codMat]);
            })
            ->select([
                'matplan.id as idMatPlan',
                'matplan.matPlanMateria',
                'materias.id as idMaterias',
                'materias.idCursos',
            ])
            ->first();

        $resolved = $row ? [
            'idMatPlan' => (int) $row->idMatPlan,
            'idCursos' => (int) $row->idCursos,
            'idMaterias' => (int) $row->idMaterias,
            'matPlanMateria' => (string) $row->matPlanMateria,
        ] : null;

        $this->materiaCache[$cacheKey] = $resolved;

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

    private function tieneMatricula(int $idTerlec, int $idNivel, int $idCursos, int $idLegajos): bool
    {
        $cacheKey = "{$idTerlec}|{$idNivel}|{$idCursos}|{$idLegajos}";
        if (array_key_exists($cacheKey, $this->matriculaCache)) {
            return $this->matriculaCache[$cacheKey];
        }

        $exists = DB::table('matricula')
            ->where('idTerlec', $idTerlec)
            ->where('idNivel', $idNivel)
            ->where('idCursos', $idCursos)
            ->where('idLegajos', $idLegajos)
            ->exists();

        $this->matriculaCache[$cacheKey] = $exists;

        return $exists;
    }

    /**
     * Texto de ayuda para `matricula_no_encontrada`: curso que exige el CSV (vía materia) vs matrículas del alumno en el mismo ciclo/nivel.
     */
    private function matriculaMismatchDebug(int $idLegajos, int $idTerlec, int $idNivel, int $idCursosEsperado): string
    {
        $esperado = DB::table('cursos')
            ->where('Id', $idCursosEsperado)
            ->select(['Id', 'c', 's', 'cursec'])
            ->first();

        $esperadoTxt = $esperado
            ? sprintf(
                'Curso esperado (materia del CSV): idCursos=%d (%s)',
                $idCursosEsperado,
                $this->cursoEtiqueta($esperado)
            )
            : sprintf('Curso esperado (materia del CSV): idCursos=%d (sin fila en `cursos`)', $idCursosEsperado);

        $filas = DB::table('matricula as m')
            ->leftJoin('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('m.idLegajos', $idLegajos)
            ->where('m.idTerlec', $idTerlec)
            ->where('m.idNivel', $idNivel)
            ->orderBy('m.id')
            ->get(['m.idCursos', 'c.c', 'c.s', 'c.cursec']);

        if ($filas->isEmpty()) {
            return $esperadoTxt.'. En este ciclo lectivo y nivel no hay matrícula para este alumno (tabla `matricula`).';
        }

        $partes = [];
        foreach ($filas as $f) {
            $idC = (int) ($f->idCursos ?? 0);
            $partes[] = sprintf('idCursos=%d (%s)', $idC, $this->cursoEtiqueta($f));
        }

        return $esperadoTxt.'. Matrícula del alumno en este ciclo/nivel: '.implode('; ', $partes).'.';
    }

    /** Etiqueta breve de curso para el informe (object con c, s, cursec). */
    private function cursoEtiqueta(object $row): string
    {
        $cursec = trim((string) ($row->cursec ?? ''));
        if ($cursec !== '') {
            return $cursec;
        }

        $c = $row->c ?? null;
        $s = $row->s ?? null;
        if ($c !== null && $c !== '' && $s !== null && trim((string) $s) !== '') {
            return trim((string) $c).'° '.trim((string) $s);
        }

        return 'sin cursec/c,s';
    }

    /**
     * @param  array{idMatPlan: int, idCursos: int, idMaterias: int, matPlanMateria: string}  $materia
     * @param  array<string, string>  $payload
     * @return int 1 si OK, 0 si ninguna, -2 si varias
     */
    private function updateCalificacionRow(int $idLegajos, int $idTerlec, array $materia, array $payload): int
    {
        $ids = DB::table('calificaciones')
            ->where('idLegajos', $idLegajos)
            ->where('idTerlec', $idTerlec)
            ->where('idMaterias', $materia['idMaterias'])
            ->limit(2)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        if ($ids->count() > 1) {
            return -2;
        }

        // Validación de longitud previa + QueryException evitan truncados/silencios; se evita
        // un SELECT post-guardado por fila (muy costoso en CSV de miles de registros).
        DB::table('calificaciones')->where('id', (int) $ids->first())->update($payload);

        return 1;
    }

    private function califMatchDebug(int $idLegajos, int $idTerlec, int $idMaterias): string
    {
        $n = DB::table('calificaciones')
            ->where('idLegajos', $idLegajos)
            ->where('idTerlec', $idTerlec)
            ->where('idMaterias', $idMaterias)
            ->count();

        return "idLegajos={$idLegajos}, idTerlec={$idTerlec}, idMaterias={$idMaterias} → {$n} fila(s)";
    }

    /**
     * @return array<string, int>
     */
    private function loadColumnMaxLengths(): array
    {
        if (! Schema::hasTable('calificaciones')) {
            return [];
        }

        $lengths = [];
        foreach (Schema::getColumns('calificaciones') as $col) {
            $name = (string) ($col['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $len = $col['length'] ?? null;
            if ($len === null || $len === '') {
                continue;
            }
            $lengths[$name] = (int) $len;
        }

        return $lengths;
    }

    /**
     * @param  array<string, string>  $payload
     * @return list<string>
     */
    private function findOversizedFields(array $payload): array
    {
        if ($this->columnMaxLengths === []) {
            return [];
        }

        $oversized = [];
        foreach ($payload as $field => $value) {
            if ($value === '') {
                continue;
            }
            $max = $this->columnMaxLengths[$field] ?? null;
            if ($max === null || $max < 1) {
                continue;
            }
            $len = mb_strlen($value, 'UTF-8');
            if ($len > $max) {
                $preview = mb_strlen($value, 'UTF-8') > 40
                    ? mb_substr($value, 0, 40, 'UTF-8').'…'
                    : $value;
                $oversized[] = "{$field} ({$len} caracteres, máx. {$max}): «{$preview}»";
            }
        }

        return $oversized;
    }

    /**
     * @param  list<string>  $row
     * @return array<string, string>
     */
    private function buildGradePayload(array $row): array
    {
        $payload = [];
        $ic = 1;

        foreach (self::EVAL_COLS as [$colN, $colR1, $colR2]) {
            $payload[sprintf('ic%02d', $ic++)] = (string) ($row[$colN] ?? '');
            $payload[sprintf('ic%02d', $ic++)] = (string) ($row[$colR1] ?? '');
            $payload[sprintf('ic%02d', $ic++)] = (string) ($row[$colR2] ?? '');
        }

        foreach (self::JIS_COLS as $jis) {
            $payload[sprintf('ic%02d', $ic++)] = (string) ($row[$jis['n']] ?? '');
            $payload[sprintf('ic%02d', $ic++)] = (string) ($row[$jis['r']] ?? '');
        }

        $payload['calif'] = (string) ($row[self::COL_NOTA_FINAL] ?? '');

        return $payload;
    }

    /**
     * @param  array<string, string>  $payload
     * @return list<string>
     */
    private function findInvalidNotes(array $payload): array
    {
        if ($this->notasPermitidas === []) {
            return [];
        }

        $invalid = [];
        foreach ($payload as $field => $value) {
            if ($field === 'calif') {
                continue;
            }
            if ($value === '') {
                continue;
            }
            if (! in_array($value, $this->notasPermitidas, true)) {
                $invalid[] = "{$field}={$value}";
            }
        }

        return $invalid;
    }

    /**
     * @return list<string>
     */
    private function loadNotasPermitidas(int $idNivel): array
    {
        return DB::table('notaspermitidas')
            ->where('idNivel', $idNivel)
            ->pluck('nota')
            ->map(fn ($n) => trim((string) $n))
            ->filter(fn ($n) => $n !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{line: int, code: string, message: string, detail?: string}
     */
    private function issue(int $line, string $code, string $message, string $detail = ''): array
    {
        $item = [
            'line' => $line + 1, // +1 por encabezado
            'code' => $code,
            'message' => $message,
        ];
        if ($detail !== '') {
            $item['detail'] = $detail;
        }

        return $item;
    }
}
