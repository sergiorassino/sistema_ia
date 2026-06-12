<?php

namespace App\Services\SincroGe;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Importa calificaciones de primario desde CSV GE/CIDI (formato grados 1°–6°).
 *
 * Por fila: curso (PRIMER GRADO…), división, DNI, código de espacio curricular
 * (`Cód. Esp. Curricular` → `matplan.codGE|codGE2|codGE3`), notas de evaluaciones 1 y 2
 * y aprobación final (`ic01`–`ic03`, `ic05`–`ic16`).
 *
 * Criterio de actualización: exactamente una fila en `calificaciones` con
 * `idLegajos` + `idTerlec` + `idMaterias` (misma materia del curso resuelta por código GE).
 */
final class GeCsvImporterPrimario
{
    private const DELIMITER = ';';

    private const MAX_ISSUES = 250;

    private const COL_CURSO = 0;

    private const COL_SECCION = 1;

    private const COL_DNI = 5;

    private const COL_APELLIDO = 6;

    private const COL_NOMBRE = 7;

    private const COL_ESPACIO_CURRICULAR = 11;

    private const COL_COD_MATERIA = 12;

    private const COL_N1E1 = 29;

    private const COL_N2E1 = 31;

    private const COL_N3E1 = 33;

    private const COL_N4E1 = 35;

    private const COL_N5E1 = 37;

    private const COL_N6E1 = 39;

    private const COL_E1 = 41;

    private const COL_N1E2 = 43;

    private const COL_N2E2 = 45;

    private const COL_N3E2 = 47;

    private const COL_N4E2 = 49;

    private const COL_N5E2 = 51;

    private const COL_N6E2 = 53;

    private const COL_E2 = 55;

    private const COL_AF = 57;

    private const CURSO_TEXTO = [
        'PRIMER GRADO' => 1,
        'SEGUNDO GRADO' => 2,
        'TERCER GRADO' => 3,
        'CUARTO GRADO' => 4,
        'QUINTO GRADO' => 5,
        'SEXTO GRADO' => 6,
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
                    $issues[] = $this->issue($lineNumber, 'curso_invalido', 'Curso/grado no reconocido en el archivo (espere PRIMER GRADO … SEXTO GRADO).', $this->formatIssueContext($row));

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
     * Campos de primario según exportación GE legacy (solo ic01–ic03 e ic05–ic16).
     *
     * @param  list<string>  $row
     * @return array<string, string>
     */
    private function buildGradePayload(array $row): array
    {
        return [
            'ic01' => (string) ($row[self::COL_E1] ?? ''),
            'ic02' => (string) ($row[self::COL_E2] ?? ''),
            'ic03' => (string) ($row[self::COL_AF] ?? ''),
            'ic05' => (string) ($row[self::COL_N1E1] ?? ''),
            'ic06' => (string) ($row[self::COL_N2E1] ?? ''),
            'ic07' => (string) ($row[self::COL_N3E1] ?? ''),
            'ic08' => (string) ($row[self::COL_N4E1] ?? ''),
            'ic09' => (string) ($row[self::COL_N5E1] ?? ''),
            'ic10' => (string) ($row[self::COL_N6E1] ?? ''),
            'ic11' => (string) ($row[self::COL_N1E2] ?? ''),
            'ic12' => (string) ($row[self::COL_N2E2] ?? ''),
            'ic13' => (string) ($row[self::COL_N3E2] ?? ''),
            'ic14' => (string) ($row[self::COL_N4E2] ?? ''),
            'ic15' => (string) ($row[self::COL_N5E2] ?? ''),
            'ic16' => (string) ($row[self::COL_N6E2] ?? ''),
        ];
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
            'line' => $line + 1,
            'code' => $code,
            'message' => $message,
        ];
        if ($detail !== '') {
            $item['detail'] = $detail;
        }

        return $item;
    }
}
