<?php

namespace App\Services\SincroCidiInasistencias;

use App\Models\Inasistencia;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Importa inasistencias desde el CSV «InasistenciasDetalle» exportado por CIDI/GE.
 *
 * - Omite filas con tipo PRESENTE.
 * - Resuelve alumno por DNI y curso/división del archivo (mismo criterio que sincro calificaciones).
 * - Inserta o actualiza en `inasistencias` (clave: matrícula + fecha + tipo).
 * - Si el registro ya existe y coincide con CIDI, no hace nada; si difiere cantidad/just/obs, actualiza.
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
        'PRIMER AÑO' => 1,
        'SEGUNDO AÑO' => 2,
        'TERCER AÑO' => 3,
        'CUARTO AÑO' => 4,
        'QUINTO AÑO' => 5,
        'SEXTO AÑO' => 6,
    ];

    /** @var array<string, int|null> */
    private array $cursoCache = [];

    /** @var array<string, int|null> */
    private array $legajoCache = [];

    /** @var array<string, int|null> */
    private array $matriculaCache = [];

    public function import(string $absolutePath, int $idTerlec, int $idNivel): CidiInasistenciasCsvImportResult
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException('No se puede leer el archivo CSV.');
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        $mapper = new CidiInasistenciaTipoMapper();
        if ($mapper->catalogoVacio()) {
            fclose($handle);

            throw new RuntimeException('No hay tipos de inasistencia configurados en inasistencias_valores.');
        }

        if (! $mapper->tieneTextosCidiConfigurados()) {
            fclose($handle);

            throw new RuntimeException(
                'Ningún tipo en inasistencias_valores tiene «texto CIDI» configurado. Complete la vinculación en esta pantalla antes de importar.'
            );
        }

        $this->cursoCache = [];
        $this->legajoCache = [];
        $this->matriculaCache = [];

        $issues = [];
        $totalDataRows = 0;
        $insertedRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;
        $skippedPresenteRows = 0;
        $skippedSinCambioRows = 0;
        $lineNumber = 0;
        $pendingChanges = 0;

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

                $row = $this->normalizeRow($rawRow);
                $tipoCidi = trim((string) ($row[self::COL_TIPO] ?? ''));

                if ($mapper->esPresente($tipoCidi)) {
                    $skippedPresenteRows++;

                    continue;
                }

                $totalDataRows++;

                $cursoNum = $this->mapCurso($row[self::COL_CURSO] ?? '');
                $seccion = trim((string) ($row[self::COL_SECCION] ?? ''));
                $dniRaw = trim((string) ($row[self::COL_DNI] ?? ''));
                $fechaRaw = trim((string) ($row[self::COL_FECHA] ?? ''));

                $context = $this->formatIssueContext($row);

                if ($cursoNum === null) {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'curso_invalido', 'Curso no reconocido en el archivo.', $context);

                    continue;
                }

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

                $resolvedTipo = $mapper->resolve($tipoCidi);
                if ($resolvedTipo === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'tipo_no_mapeado',
                        "No se pudo vincular el tipo CIDI «{$tipoCidi}» con ningún texto_cidi de inasistencias_valores.",
                        $context
                    );

                    continue;
                }

                $dni = (int) $dniRaw;
                $idCursos = $this->resolveCursoId($cursoNum, $seccion, $idTerlec, $idNivel);
                if ($idCursos === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'curso_no_encontrado',
                        "No existe el curso {$cursoNum}° división «{$seccion}» en el ciclo y nivel activos.",
                        $context
                    );

                    continue;
                }

                $idLegajos = $this->resolveLegajoId($dni);
                if ($idLegajos === null) {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'legajo_no_encontrado', "No existe legajo con DNI {$dni}.", $context);

                    continue;
                }

                $idMatricula = $this->resolveMatriculaId($idTerlec, $idNivel, $idCursos, $idLegajos);
                if ($idMatricula === null) {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'matricula_no_encontrada',
                        'El alumno no está matriculado en el curso/división del archivo para el ciclo lectivo activo.',
                        $context.' · '.$this->matriculaMismatchDebug($idLegajos, $idTerlec, $idNivel, $idCursos)
                    );

                    continue;
                }

                $payload = $this->buildPayload($idMatricula, $fecha, $resolvedTipo);
                $sync = $this->sincronizarRegistro($payload);

                if ($sync === 'inserted') {
                    $insertedRows++;
                    $pendingChanges++;
                } elseif ($sync === 'updated') {
                    $updatedRows++;
                    $pendingChanges++;
                } elseif ($sync === 'unchanged') {
                    $skippedSinCambioRows++;
                } else {
                    $skippedRows++;
                    $issues[] = $this->issue(
                        $lineNumber,
                        'inasistencia_ambigua',
                        'Hay más de un registro con la misma matrícula, fecha y tipo; no se actualizó.',
                        $context
                    );
                }
            }

            $committed = $pendingChanges > 0;
            if ($committed) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            $issuesTruncated = count($issues) > self::MAX_ISSUES;
            if ($issuesTruncated) {
                $issues = array_slice($issues, 0, self::MAX_ISSUES);
            }

            return new CidiInasistenciasCsvImportResult(
                totalDataRows: $totalDataRows,
                insertedRows: $insertedRows,
                updatedRows: $updatedRows,
                skippedRows: $skippedRows,
                skippedPresenteRows: $skippedPresenteRows,
                skippedSinCambioRows: $skippedSinCambioRows,
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
     * @return array{idMatricula: int, fecha: string, tipo: string, cantidad: float, just: string|null, obs: string}
     */
    private function buildPayload(int $idMatricula, string $fecha, ResolvedCidiInasistenciaTipo $tipo): array
    {
        return [
            'idMatricula' => $idMatricula,
            'fecha' => $fecha,
            'tipo' => (string) $tipo->idTipo,
            'cantidad' => round($tipo->cantidad, 2),
            'just' => $tipo->just,
            'obs' => 'CIDI',
        ];
    }

    /**
     * @param  array{idMatricula: int, fecha: string, tipo: string, cantidad: float, just: string|null, obs: string}  $payload
     * @return 'inserted'|'updated'|'unchanged'|'ambiguous'
     */
    private function sincronizarRegistro(array $payload): string
    {
        $existentes = Inasistencia::query()
            ->where('idMatricula', $payload['idMatricula'])
            ->whereDate('fecha', $payload['fecha'])
            ->where('tipo', $payload['tipo'])
            ->orderBy('id')
            ->get();

        if ($existentes->isEmpty()) {
            Inasistencia::create($payload);

            return 'inserted';
        }

        if ($existentes->count() > 1) {
            return 'ambiguous';
        }

        /** @var Inasistencia $existente */
        $existente = $existentes->first();

        if ($this->payloadCoincideConRegistro($payload, $existente)) {
            return 'unchanged';
        }

        $existente->update([
            'cantidad' => $payload['cantidad'],
            'just' => $payload['just'],
            'obs' => $payload['obs'],
        ]);

        return 'updated';
    }

    /**
     * @param  array{idMatricula: int, fecha: string, tipo: string, cantidad: float, just: string|null, obs: string}  $payload
     */
    private function payloadCoincideConRegistro(array $payload, Inasistencia $existente): bool
    {
        $cantExistente = $existente->cantidad !== null ? round((float) $existente->cantidad, 2) : null;
        $cantPayload = round((float) $payload['cantidad'], 2);

        if ($cantExistente === null && $cantPayload !== 0.0) {
            return false;
        }

        if ($cantExistente !== null && abs($cantExistente - $cantPayload) > 0.009) {
            return false;
        }

        if (! $this->justificacionCoincide($payload['just'] ?? null, $existente->just)) {
            return false;
        }

        return trim((string) ($existente->obs ?? '')) === trim($payload['obs']);
    }

    private function justificacionCoincide(?string $payloadJust, mixed $existenteJust): bool
    {
        return $this->valorJustParaComparar($payloadJust) === $this->valorJustParaComparar(
            is_scalar($existenteJust) ? (string) $existenteJust : null
        );
    }

    /** @return 'J'|'I'|null */
    private function valorJustParaComparar(?string $just): ?string
    {
        if ($just === null) {
            return null;
        }

        $j = strtoupper(trim($just));
        if ($j === '') {
            return null;
        }

        if ($j === 'J') {
            return 'J';
        }

        if ($j === 'I' || $j === 'N') {
            return 'I';
        }

        return $j;
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
            $y = (int) $m[3];
            $mo = (int) $m[2];
            $d = (int) $m[1];

            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }

        return null;
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
    private function formatIssueContext(array $row): string
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

        $tipo = trim((string) ($row[self::COL_TIPO] ?? ''));
        $fecha = trim((string) ($row[self::COL_FECHA] ?? ''));
        if ($tipo !== '' || $fecha !== '') {
            $parts[] = trim("{$tipo} · {$fecha}", ' ·');
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

    private function matriculaMismatchDebug(int $idLegajos, int $idTerlec, int $idNivel, int $idCursosEsperado): string
    {
        $esperado = DB::table('cursos')
            ->where('Id', $idCursosEsperado)
            ->select(['Id', 'c', 's', 'cursec'])
            ->first();

        $esperadoTxt = $esperado
            ? sprintf('Curso esperado: idCursos=%d (%s)', $idCursosEsperado, $this->cursoEtiqueta($esperado))
            : sprintf('Curso esperado: idCursos=%d', $idCursosEsperado);

        $filas = DB::table('matricula as m')
            ->leftJoin('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('m.idLegajos', $idLegajos)
            ->where('m.idTerlec', $idTerlec)
            ->where('m.idNivel', $idNivel)
            ->orderBy('m.id')
            ->get(['m.idCursos', 'c.c', 'c.s', 'c.cursec']);

        if ($filas->isEmpty()) {
            return $esperadoTxt.'. Sin matrícula en este ciclo/nivel.';
        }

        $partes = [];
        foreach ($filas as $f) {
            $partes[] = sprintf('idCursos=%d (%s)', (int) ($f->idCursos ?? 0), $this->cursoEtiqueta($f));
        }

        return $esperadoTxt.'. Matrícula del alumno: '.implode('; ', $partes).'.';
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

        return 'sin cursec';
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
