<?php

namespace App\Services\SincroGe;

use Illuminate\Support\Facades\DB;
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

    /** Índices de columnas (0-based) según encabezado oficial GE. */
    private const COL_CURSO = 0;

    private const COL_SECCION = 1;

    private const COL_DNI = 5;

    private const COL_APELLIDO = 6;

    private const COL_NOMBRE = 7;

    private const COL_COD_MATERIA = 12;

    private const COL_ESPACIO_CURRICULAR = 11;

    private const COL_NOTA_FINAL = 72;

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

    /** @var array<string, array{idMatPlan: int, idCursos: int, idMaterias: int, matPlanMateria: string}|null> */
    private array $materiaCache = [];

    /** @var array<string, int|null> */
    private array $legajoCache = [];

    /** @var list<string> */
    private array $notasPermitidas = [];

    public function import(string $absolutePath, int $idTerlec, int $idNivel): GeCsvImportResult
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException('No se puede leer el archivo CSV.');
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        $this->materiaCache = [];
        $this->legajoCache = [];
        $this->notasPermitidas = $this->loadNotasPermitidas($idNivel);

        $issues = [];
        $totalDataRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;
        $lineNumber = 0;
        $pendingUpdates = 0;

        try {
            // Encabezado
            $header = fgetcsv($handle, 0, self::DELIMITER);
            if ($header === false) {
                throw new RuntimeException('El archivo CSV está vacío o no tiene encabezado.');
            }

            DB::beginTransaction();

            while (($rawRow = fgetcsv($handle, 0, self::DELIMITER)) !== false) {
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

                $updateResult = $this->updateCalificacionRow($idLegajos, $idTerlec, $materia, $payload);

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
        return DB::table('matricula')
            ->where('idTerlec', $idTerlec)
            ->where('idNivel', $idNivel)
            ->where('idCursos', $idCursos)
            ->where('idLegajos', $idLegajos)
            ->exists();
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
     * @return int 1 si hay exactamente una fila coincidente (aunque los valores no cambien), 0 si ninguna, -2 si varias
     */
    private function updateCalificacionRow(int $idLegajos, int $idTerlec, array $materia, array $payload): int
    {
        $where = [
            'idLegajos' => $idLegajos,
            'idTerlec' => $idTerlec,
            'idMaterias' => $materia['idMaterias'],
        ];

        $count = DB::table('calificaciones')->where($where)->count();

        if ($count === 0) {
            return 0;
        }

        if ($count > 1) {
            return -2;
        }

        // MySQL/Laravel devuelven 0 filas "afectadas" si los valores ya eran iguales; eso no es un error.
        DB::table('calificaciones')->where($where)->update($payload);

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
