<?php

namespace App\Services\SincroGe;

use App\Support\CalificacionesPrimario\CalificacionesPrimarioNotasPermitidas;
use App\Support\NivelSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Importa calificaciones desde CSV GE/CIDI (primario: grados 1°–6°; inicial: salas 3–5).
 *
 * Formato CIDI (59 columnas).
 *
 * Primario: etapa 1 del archivo no se importa. Etapa 2 CIDI → 1ª etapa del sistema
 * (ic05–ic10, ic01); etapa 3 CIDI → 2ª etapa (ic11–ic16, ic02); apreciación final → ic03.
 *
 * Nivel inicial: solo se importan textos del informe de progreso (no se tocan notas `ic*`).
 * Cada fila se vincula a la materia por la columna M «Cód. Esp. Curricular» → `matplan.codGE`.
 * Textos por espacio curricular:
 * - «Nota Final Etapa 1» (col. AB / índice 27) → `obs01`
 * - «Nota Final Etapa 2» (col. AP / índice 41) → `obs02` (si es párrafo; si no, columna O)
 * Las columnas N/O «Observaciones» en el export GE suelen repetir el mismo texto de sala en
 * todas las materias; no alcanzan para el IPE por espacio. Si el texto trae «;» sin comillas,
 * se repara la fila (mismo criterio que desempeños / `DesempenosCsvColumnMapper`).
 *
 * Criterio de actualización: la misma fila que usa la carga por estudiante
 * (`idMatricula` + `idMaterias`, con fallback legacy por `ord`).
 */
final class GeCsvImporterPrimario
{
    private const DELIMITER = ';';

    private const MAX_ISSUES = 250;

    /** Layout GE/CIDI primario-inicial vigente. */
    public const EXPECTED_COLUMN_COUNT = 59;

    private const COL_CURSO = 0;

    private const COL_SECCION = 1;

    private const COL_DNI = 5;

    private const COL_APELLIDO = 6;

    private const COL_NOMBRE = 7;

    private const COL_ESPACIO_CURRICULAR = 11;

    /** Excel M — Cód. Esp. Curricular → matplan.codGE. */
    private const COL_COD_MATERIA = 12;

    /** Excel N — Observaciones Etapa 1 (texto de sala; fallback si falta Nota Final). */
    public const COL_OBS_SALA_1 = 13;

    /** Excel O — Observaciones Etapa 2 (fallback de obs02). */
    public const COL_OBS_SALA_2 = 14;

    /**
     * Excel AB — Nota Final Etapa 1.
     * En inicial GE guarda aquí el texto del IPE por espacio curricular → obs01.
     */
    public const COL_TEXTO_ETAPA_1 = 27;

    /**
     * Excel AP — Nota Final Etapa 2.
     * Texto del IPE 2ª etapa por espacio → obs02 (si es párrafo).
     */
    public const COL_TEXTO_ETAPA_2 = 41;

    /** CIDI etapa 2 → sistema 1ª etapa (parciales). */
    private const COL_CIDI_E2_N1 = 29;

    private const COL_CIDI_E2_N2 = 31;

    private const COL_CIDI_E2_N3 = 33;

    private const COL_CIDI_E2_N4 = 35;

    private const COL_CIDI_E2_N5 = 37;

    private const COL_CIDI_E2_N6 = 39;

    /** CIDI etapa 2 final → ic01. */
    private const COL_CIDI_E2_FINAL = 41;

    /** CIDI etapa 3 → sistema 2ª etapa (parciales). */
    private const COL_CIDI_E3_N1 = 43;

    private const COL_CIDI_E3_N2 = 45;

    private const COL_CIDI_E3_N3 = 47;

    private const COL_CIDI_E3_N4 = 49;

    private const COL_CIDI_E3_N5 = 51;

    private const COL_CIDI_E3_N6 = 53;

    /** CIDI etapa 3 final → ic02. */
    private const COL_CIDI_E3_FINAL = 55;

    /** Apreciación final CIDI → ic03. */
    private const COL_CIDI_AF = 57;

    private const CURSO_TEXTO = [
        'PRIMER GRADO' => 1,
        'SEGUNDO GRADO' => 2,
        'TERCER GRADO' => 3,
        'CUARTO GRADO' => 4,
        'QUINTO GRADO' => 5,
        'SEXTO GRADO' => 6,
        'SALA DE 3 AÑOS' => 3,
        'SALA DE 4 AÑOS' => 4,
        'SALA DE 5 AÑOS' => 5,
        'SALA DE TRES AÑOS' => 3,
        'SALA DE CUATRO AÑOS' => 4,
        'SALA DE CINCO AÑOS' => 5,
        'SALA DE 3' => 3,
        'SALA DE 4' => 4,
        'SALA DE 5' => 5,
    ];

    /** @var array<string, array{status: string, materia: array{idMatPlan: int, idCursos: int, idMaterias: int, matPlanMateria: string, escala: int}|null}> */
    private array $materiaCache = [];

    /** @var array<string, int|null> */
    private array $cursoCache = [];

    /** @var array<string, int|null> */
    private array $matriculaCache = [];

    /** @var array<string, int|null> */
    private array $legajoCache = [];

    /** @var list<string> */
    private array $notasPermitidas = [];

    /** @var array<int, list<string>> */
    private array $notasPermitidasPorEscala = [];

    /** Solo true cuando `import()` corre con nivel inicial (N/O → obs01/obs02). */
    private bool $importarObservacionesInicial = false;

    /** @var array{obs01: bool, obs02: bool} */
    private array $columnasObsDisponibles = ['obs01' => false, 'obs02' => false];

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
        $this->cursoCache = [];
        $this->matriculaCache = [];
        $this->legajoCache = [];
        $this->importarObservacionesInicial = NivelSistema::esInicial($idNivel);
        $this->columnasObsDisponibles = [
            'obs01' => $this->importarObservacionesInicial && Schema::hasColumn('calificaciones', 'obs01'),
            'obs02' => $this->importarObservacionesInicial && Schema::hasColumn('calificaciones', 'obs02'),
        ];
        $this->notasPermitidasPorEscala = CalificacionesPrimarioNotasPermitidas::listasPorEscala($idNivel);
        $this->notasPermitidas = array_values(array_unique(array_merge(
            $this->notasPermitidasPorEscala[CalificacionesPrimarioNotasPermitidas::ESCALA_CONCEPTOS] ?? [],
            $this->notasPermitidasPorEscala[CalificacionesPrimarioNotasPermitidas::ESCALA_LITERALES] ?? [],
        )));

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
                $codMat = $this->normalizeCodGe((string) ($row[self::COL_COD_MATERIA] ?? ''));
                $dniRaw = trim((string) ($row[self::COL_DNI] ?? ''));

                if ($cursoNum === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'curso_invalido',
                        'Curso no reconocido en el archivo (espere PRIMER GRADO … SEXTO GRADO, o SALA DE 3/4/5 AÑOS).',
                        $this->formatIssueContext($row)
                    );

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

                $idCursos = $this->resolveCursoId($cursoNum, $seccion, $idTerlec, $idNivel);
                if ($idCursos === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'curso_no_encontrado',
                        "No se encontró el curso {$cursoNum}° división «{$seccion}» en el ciclo y nivel activos.",
                        $this->formatIssueContext($row)
                    );

                    continue;
                }

                $idLegajos = $this->resolveLegajoId($dni);
                if ($idLegajos === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'legajo_no_encontrado',
                        "No existe legajo con DNI {$dni}.",
                        $this->formatIssueContext($row)
                    );

                    continue;
                }

                $idMatricula = $this->resolveMatriculaId($idTerlec, $idNivel, $idCursos, $idLegajos);
                if ($idMatricula === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'matricula_no_encontrada',
                        'El alumno no está matriculado en el curso/división del archivo para el ciclo lectivo activo.',
                        $this->formatIssueContext($row).' · '.$this->matriculaMismatchDebug($idLegajos, $idTerlec, $idNivel, $idCursos)
                    );

                    continue;
                }

                $materiaResult = $this->resolveMateria($codMat, $idCursos, $idTerlec, $idNivel);
                if ($materiaResult['status'] === 'ambiguous') {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'materia_ambigua',
                        "Hay más de una materia con codGE «{$codMat}» en el curso del alumno; no se actualizó.",
                        $this->formatIssueContext($row)
                    );

                    continue;
                }

                $materia = $materiaResult['materia'];
                if ($materia === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'materia_no_encontrada',
                        "No se encontró la materia con codGE «{$codMat}» en el curso {$cursoNum}° «{$seccion}».",
                        $this->formatIssueContext($row).' · '.$this->materiaCodGeDebug($idCursos, $idTerlec, $idNivel)
                    );

                    continue;
                }

                $context = $this->formatIssueContext($row, $materia['matPlanMateria']);

                $payload = $this->buildGradePayload($row);

                $obsSinColumna = $this->observacionesSinColumna($payload);
                if ($obsSinColumna !== []) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'columnas_inexistentes',
                        'Hay textos de observación CIDI pero faltan columnas en calificaciones: '.implode(', ', $obsSinColumna).'.',
                        $context
                    );

                    continue;
                }

                $payload = $this->filtrarObservacionesSegunEsquema($payload);

                $invalidNotes = $this->findInvalidNotes($payload, (int) ($materia['escala'] ?? 1));
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

                $updateResult = $this->updateCalificacionRow($idMatricula, $idLegajos, $idTerlec, $materia, $payload);

                if ($updateResult === -2) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'calificacion_ambigua',
                        'Hay más de un registro en calificaciones para este alumno y materia; no se actualizó.',
                        $context.' · '.$this->califMatchDebug($idMatricula, $idLegajos, $idTerlec, $materia['idMaterias'])
                    );

                    continue;
                }

                if ($updateResult < 1) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'calificacion_no_encontrada',
                        'No existe exactamente una fila en calificaciones para la matrícula y materia del alumno (criterio de la carga por estudiante).',
                        $context.' · '.$this->califMatchDebug($idMatricula, $idLegajos, $idTerlec, $materia['idMaterias'])
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

            $out[] = trim(str_replace("\xEF\xBB\xBF", '', $s));
        }

        return $out;
    }

    /**
     * Extrae obs01/obs02 del CSV inicial.
     *
     * Fuente principal: Nota Final Etapa 1/2 (texto por espacio curricular, columna M).
     * Fallback: columnas N/O si la nota final viene vacía.
     * Si hay columnas de más por «;» en el texto, repara como en desempeños.
     *
     * @param  list<string>  $cols
     * @return array{obs01: string, obs02: string}
     */
    public function extraerObservacionesInicial(array $cols): array
    {
        $row = $cols;

        // Reparar de izquierda a derecha: un «;» temprano desplaza todo lo posterior.
        // 1) Observaciones N
        if (count($row) > self::EXPECTED_COLUMN_COUNT
            && $this->pareceContinuacionTexto(
                (string) ($row[self::COL_OBS_SALA_1] ?? ''),
                (string) ($row[self::COL_OBS_SALA_2] ?? '')
            )
        ) {
            $row = $this->repararFilaPorTextoLibre($row, self::COL_OBS_SALA_1);
        }

        // 2) Nota Final Etapa 1 (texto por espacio curricular)
        if (count($row) > self::EXPECTED_COLUMN_COUNT
            && $this->pareceContinuacionTexto(
                (string) ($row[self::COL_TEXTO_ETAPA_1] ?? ''),
                (string) ($row[self::COL_TEXTO_ETAPA_1 + 1] ?? '')
            )
        ) {
            $row = $this->repararFilaPorTextoLibre($row, self::COL_TEXTO_ETAPA_1);
        }

        // 3) Nota Final Etapa 2
        if (count($row) > self::EXPECTED_COLUMN_COUNT
            && $this->pareceContinuacionTexto(
                (string) ($row[self::COL_TEXTO_ETAPA_2] ?? ''),
                (string) ($row[self::COL_TEXTO_ETAPA_2 + 1] ?? '')
            )
        ) {
            $row = $this->repararFilaPorTextoLibre($row, self::COL_TEXTO_ETAPA_2);
        }

        $nf1 = trim((string) ($row[self::COL_TEXTO_ETAPA_1] ?? ''));
        $nf2 = trim((string) ($row[self::COL_TEXTO_ETAPA_2] ?? ''));
        $sala1 = trim((string) ($row[self::COL_OBS_SALA_1] ?? ''));
        $sala2 = trim((string) ($row[self::COL_OBS_SALA_2] ?? ''));

        $obs01 = $this->pareceTextoObservacion($nf1) ? $nf1 : $sala1;

        if ($this->pareceTextoObservacion($nf2)) {
            $obs02 = $nf2;
        } elseif ($sala2 !== '' && ! $this->pareceContinuacionTexto($sala1, $sala2)) {
            $obs02 = $sala2;
        } else {
            $obs02 = '';
        }

        return [
            'obs01' => $obs01,
            'obs02' => $obs02,
        ];
    }

    /**
     * Repara filas con más columnas de las esperadas cuando un texto libre contiene «;»
     * sin comillas. Criterio de desempeños: unir fragmentos desde `$textoLibreIdx` hasta
     * dejar el número correcto de columnas fijas al final.
     *
     * @param  list<string>  $cols
     * @return list<string>
     */
    public function repararFilaPorTextoLibre(array $cols, int $textoLibreIdx): array
    {
        $expected = self::EXPECTED_COLUMN_COUNT;
        $total = count($cols);

        if ($total === $expected) {
            return $cols;
        }

        if ($total < $expected) {
            while (count($cols) < $expected) {
                $cols[] = '';
            }

            return array_slice($cols, 0, $expected);
        }

        if ($textoLibreIdx < 0 || $textoLibreIdx >= $expected) {
            return array_slice($cols, 0, $expected);
        }

        $finalCount = $expected - ($textoLibreIdx + 1);
        if ($finalCount < 0) {
            $finalCount = 0;
        }

        $startFinales = $total - $finalCount;
        if ($startFinales <= $textoLibreIdx) {
            return array_slice($cols, 0, $expected);
        }

        $partesTexto = array_slice($cols, $textoLibreIdx, $startFinales - $textoLibreIdx);
        $textoReconstruido = trim(implode(';', array_map(
            static fn ($v) => (string) ($v ?? ''),
            $partesTexto
        )));

        $nuevo = array_merge(
            array_slice($cols, 0, $textoLibreIdx),
            [$textoReconstruido],
            array_slice($cols, $startFinales)
        );

        while (count($nuevo) < $expected) {
            $nuevo[] = '';
        }

        return array_slice($nuevo, 0, $expected);
    }

    /**
     * Detecta si `$despues` es la continuación de un texto partido por «;».
     */
    public function pareceContinuacionTexto(string $antes, string $despues): bool
    {
        $antes = rtrim($antes);
        $despuesTrim = trim($despues);

        if ($antes === '' || $despuesTrim === '') {
            return false;
        }

        if (str_starts_with($despues, ' ') || str_starts_with($despues, "\t")) {
            return true;
        }

        $primera = mb_substr($despuesTrim, 0, 1);
        if ($primera !== '' && $primera !== mb_strtoupper($primera, 'UTF-8') && ! ctype_digit($primera)) {
            return true;
        }

        if (! preg_match('/[.!?…»"\'”]$/u', $antes) && mb_strlen($despuesTrim) > 40) {
            return true;
        }

        return false;
    }

    /** Nota corta (E, MB, …) vs párrafo de observación del IPE. */
    public function pareceTextoObservacion(string $valor): bool
    {
        $valor = trim($valor);
        if ($valor === '') {
            return false;
        }

        if (mb_strlen($valor) > 20) {
            return true;
        }

        return str_contains($valor, ' ');
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
        $key = preg_replace('/\s+/u', ' ', $key) ?? $key;

        if (isset(self::CURSO_TEXTO[$key])) {
            return self::CURSO_TEXTO[$key];
        }

        if (preg_match('/SALA\s+DE\s+(\d)/u', $key, $coincidencias) === 1) {
            $n = (int) $coincidencias[1];

            return ($n >= 1 && $n <= 5) ? $n : null;
        }

        if (str_contains($key, 'SALA')) {
            if (str_contains($key, 'TRES')) {
                return 3;
            }
            if (str_contains($key, 'CUATRO')) {
                return 4;
            }
            if (str_contains($key, 'CINCO')) {
                return 5;
            }
        }

        return null;
    }

    private function normalizeCodGe(string $codigo): string
    {
        return mb_strtoupper(trim($codigo), 'UTF-8');
    }

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

    /**
     * @return array{status: 'ok'|'not_found'|'ambiguous', materia: array{idMatPlan: int, idCursos: int, idMaterias: int, ord: int, matPlanMateria: string, escala: int}|null}
     */
    private function resolveMateria(string $codMat, int $idCursos, int $idTerlec, int $idNivel): array
    {
        $cacheKey = "{$codMat}|{$idCursos}|{$idTerlec}|{$idNivel}";
        if (array_key_exists($cacheKey, $this->materiaCache)) {
            return $this->materiaCache[$cacheKey];
        }

        $idCurPlan = DB::table('cursos')->where('Id', $idCursos)->value('idCurPlan');

        $query = DB::table('materias')
            ->join('matplan', 'materias.idMatPlan', '=', 'matplan.id')
            ->where('materias.idTerlec', $idTerlec)
            ->where('materias.idNivel', $idNivel)
            ->where('materias.idCursos', $idCursos)
            ->whereRaw('UPPER(TRIM(matplan.codGE)) = ?', [$codMat]);

        if ($idCurPlan !== null && (int) $idCurPlan > 0) {
            $query->where('matplan.idCurPlan', (int) $idCurPlan);
        }

        $rows = $query
            ->select(array_values(array_filter([
                'matplan.id as idMatPlan',
                'matplan.matPlanMateria',
                'matplan.codGE',
                'materias.id as idMaterias',
                'materias.idCursos',
                'materias.ord',
                Schema::hasColumn('materias', 'escala') ? 'materias.escala' : null,
            ])))
            ->orderBy('materias.id')
            ->get();

        if ($rows->count() > 1) {
            $resolved = ['status' => 'ambiguous', 'materia' => null];
            $this->materiaCache[$cacheKey] = $resolved;

            return $resolved;
        }

        $row = $rows->first();
        $materia = $row ? [
            'idMatPlan' => (int) $row->idMatPlan,
            'idCursos' => (int) $row->idCursos,
            'idMaterias' => (int) $row->idMaterias,
            'ord' => (int) ($row->ord ?? 0),
            'matPlanMateria' => (string) $row->matPlanMateria,
            'escala' => Schema::hasColumn('materias', 'escala')
                ? CalificacionesPrimarioNotasPermitidas::normalizarEscala($row->escala ?? 1)
                : CalificacionesPrimarioNotasPermitidas::ESCALA_CONCEPTOS,
        ] : null;

        $resolved = [
            'status' => $materia !== null ? 'ok' : 'not_found',
            'materia' => $materia,
        ];
        $this->materiaCache[$cacheKey] = $resolved;

        return $resolved;
    }

    private function materiaCodGeDebug(int $idCursos, int $idTerlec, int $idNivel): string
    {
        $idCurPlan = DB::table('cursos')->where('Id', $idCursos)->value('idCurPlan');

        $query = DB::table('materias')
            ->join('matplan', 'materias.idMatPlan', '=', 'matplan.id')
            ->where('materias.idTerlec', $idTerlec)
            ->where('materias.idNivel', $idNivel)
            ->where('materias.idCursos', $idCursos)
            ->whereNotNull('matplan.codGE')
            ->whereRaw("TRIM(matplan.codGE) <> ''");

        if ($idCurPlan !== null && (int) $idCurPlan > 0) {
            $query->where('matplan.idCurPlan', (int) $idCurPlan);
        }

        $codigos = $query
            ->orderBy('matplan.ord')
            ->orderBy('matplan.matPlanMateria')
            ->pluck('matplan.codGE')
            ->map(fn ($c) => mb_strtoupper(trim((string) $c), 'UTF-8'))
            ->unique()
            ->values()
            ->all();

        if ($codigos === []) {
            return 'El curso no tiene materias con codGE cargado en matplan.';
        }

        return 'codGE disponibles en el curso: '.implode(', ', $codigos).'.';
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

    private function resolveMatriculaId(int $idTerlec, int $idNivel, int $idCursos, int $idLegajos): ?int
    {
        $cacheKey = "{$idTerlec}|{$idNivel}|{$idCursos}|{$idLegajos}";
        if (array_key_exists($cacheKey, $this->matriculaCache)) {
            return $this->matriculaCache[$cacheKey];
        }

        $id = DB::table('matricula')
            ->where('idTerlec', $idTerlec)
            ->where('idNivel', $idNivel)
            ->where('idCursos', $idCursos)
            ->where('idLegajos', $idLegajos)
            ->orderBy('id')
            ->value('id');

        $resolved = $id !== null ? (int) $id : null;
        $this->matriculaCache[$cacheKey] = $resolved;

        return $resolved;
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
     * @param  array{idMatPlan: int, idCursos: int, idMaterias: int, ord: int, matPlanMateria: string}  $materia
     * @param  array<string, string>  $payload
     */
    private function updateCalificacionRow(int $idMatricula, int $idLegajos, int $idTerlec, array $materia, array $payload): int
    {
        $idMaterias = $materia['idMaterias'];

        $porMatricula = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('idMaterias', $idMaterias);

        $count = $porMatricula->count();
        if ($count === 1) {
            $porMatricula->update($payload);

            return 1;
        }
        if ($count > 1) {
            return -2;
        }

        $ord = (int) ($materia['ord'] ?? 0);
        if ($ord > 0) {
            $legacy = DB::table('calificaciones')
                ->where('idMatricula', $idMatricula)
                ->where('ord', $ord)
                ->where(function ($q): void {
                    $q->whereNull('idMaterias')
                        ->orWhere('idMaterias', 0);
                });

            $legacyCount = $legacy->count();
            if ($legacyCount === 1) {
                $legacy->update(array_merge($payload, ['idMaterias' => $idMaterias]));

                return 1;
            }
            if ($legacyCount > 1) {
                return -2;
            }
        }

        $whereLegajo = [
            'idLegajos' => $idLegajos,
            'idTerlec' => $idTerlec,
            'idMaterias' => $idMaterias,
        ];

        $countLegajo = DB::table('calificaciones')->where($whereLegajo)->count();
        if ($countLegajo === 0) {
            return 0;
        }
        if ($countLegajo > 1) {
            return -2;
        }

        DB::table('calificaciones')->where($whereLegajo)->update(array_merge($payload, [
            'idMatricula' => $idMatricula,
        ]));

        return 1;
    }

    private function califMatchDebug(int $idMatricula, int $idLegajos, int $idTerlec, int $idMaterias): string
    {
        $nMatricula = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('idMaterias', $idMaterias)
            ->count();

        $nLegajo = DB::table('calificaciones')
            ->where('idLegajos', $idLegajos)
            ->where('idTerlec', $idTerlec)
            ->where('idMaterias', $idMaterias)
            ->count();

        return "idMatricula={$idMatricula}, idMaterias={$idMaterias} → {$nMatricula} fila(s); idLegajos={$idLegajos}, idTerlec={$idTerlec} → {$nLegajo} fila(s)";
    }

    /**
     * Primario: CIDI etapa 2 → 1ª etapa; etapa 3 → 2ª; apreciación final → ic03.
     * Inicial: solo N/O → obs01/obs02 (con reparación si el texto trae «;»).
     *
     * @param  list<string>  $row
     * @return array<string, string>
     */
    private function buildGradePayload(array $row): array
    {
        if ($this->importarObservacionesInicial) {
            $obs = $this->extraerObservacionesInicial($row);

            return [
                'obs01' => $obs['obs01'],
                'obs02' => $obs['obs02'],
            ];
        }

        return [
            'ic01' => $this->celda($row, self::COL_CIDI_E2_FINAL),
            'ic02' => $this->celda($row, self::COL_CIDI_E3_FINAL),
            'ic03' => $this->celda($row, self::COL_CIDI_AF),
            'ic05' => $this->celda($row, self::COL_CIDI_E2_N1),
            'ic06' => $this->celda($row, self::COL_CIDI_E2_N2),
            'ic07' => $this->celda($row, self::COL_CIDI_E2_N3),
            'ic08' => $this->celda($row, self::COL_CIDI_E2_N4),
            'ic09' => $this->celda($row, self::COL_CIDI_E2_N5),
            'ic10' => $this->celda($row, self::COL_CIDI_E2_N6),
            'ic11' => $this->celda($row, self::COL_CIDI_E3_N1),
            'ic12' => $this->celda($row, self::COL_CIDI_E3_N2),
            'ic13' => $this->celda($row, self::COL_CIDI_E3_N3),
            'ic14' => $this->celda($row, self::COL_CIDI_E3_N4),
            'ic15' => $this->celda($row, self::COL_CIDI_E3_N5),
            'ic16' => $this->celda($row, self::COL_CIDI_E3_N6),
        ];
    }

    /**
     * @param  list<string>  $row
     */
    private function celda(array $row, int $columna): string
    {
        return trim((string) ($row[$columna] ?? ''));
    }

    /**
     * Campos de observación con valor en CSV cuya columna no existe en este tenant.
     *
     * @param  array<string, string>  $payload
     * @return list<string>
     */
    private function observacionesSinColumna(array $payload): array
    {
        if (! $this->importarObservacionesInicial) {
            return [];
        }

        $faltantes = [];
        foreach (['obs01', 'obs02'] as $campo) {
            $valor = trim((string) ($payload[$campo] ?? ''));
            if ($valor !== '' && ! ($this->columnasObsDisponibles[$campo] ?? false)) {
                $faltantes[] = $campo;
            }
        }

        return $faltantes;
    }

    /**
     * Quita obs01/obs02 del payload si la columna no existe (solo cuando el valor está vacío).
     *
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function filtrarObservacionesSegunEsquema(array $payload): array
    {
        if (! $this->importarObservacionesInicial) {
            return $payload;
        }

        foreach (['obs01', 'obs02'] as $campo) {
            if (! ($this->columnasObsDisponibles[$campo] ?? false)) {
                unset($payload[$campo]);
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, string>  $payload
     * @return list<string>
     */
    private function findInvalidNotes(array $payload, int $escala): array
    {
        $escala = CalificacionesPrimarioNotasPermitidas::normalizarEscala($escala);
        $permitidas = $this->notasPermitidasPorEscala[$escala] ?? [];
        if ($permitidas === []) {
            $permitidas = $this->notasPermitidas;
        }
        if ($permitidas === []) {
            return [];
        }

        $invalid = [];
        foreach ($payload as $field => $value) {
            if ($value === '' || str_starts_with($field, 'obs')) {
                continue;
            }
            if (! in_array($value, $permitidas, true)) {
                $invalid[] = "{$field}={$value}";
            }
        }

        return $invalid;
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
