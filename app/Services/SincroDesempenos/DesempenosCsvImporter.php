<?php

namespace App\Services\SincroDesempenos;

use App\Support\SincroDesempenos\DesempenosCsvColumnMapper;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Importa desempeños e inasistencias de etapa desde CSV (primario).
 *
 * Actualiza en `matricula`:
 * - Etapa 1: obs1, just1, inju1
 * - Etapa 2: obs2, just2, inju2
 */
final class DesempenosCsvImporter
{
    private const DELIMITER = ';';

    private const MAX_ISSUES = 250;

    /** @var array<string, int|null> */
    private array $cursoCache = [];

    /** @var array<string, int|null> */
    private array $legajoCache = [];

    /** @var array<string, int|null> */
    private array $matriculaCache = [];

    public function import(string $absolutePath, int $idTerlec, int $idNivel, int $etapa): DesempenosCsvImportResult
    {
        if ($etapa !== 1 && $etapa !== 2) {
            throw new RuntimeException('La etapa debe ser 1 (primera) o 2 (segunda).');
        }

        if (! is_readable($absolutePath)) {
            throw new RuntimeException('No se puede leer el archivo CSV.');
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        $this->cursoCache = [];
        $this->legajoCache = [];
        $this->matriculaCache = [];

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

            $mapper = new DesempenosCsvColumnMapper($header);
            if (! $mapper->esEncabezadoValido()) {
                throw new RuntimeException(
                    'El encabezado del CSV no es válido. Debe incluir columnas de grado, DNI y desempeño (separador «;»).'
                );
            }

            $updateFields = $etapa === 1
                ? ['obs1', 'just1', 'inju1']
                : ['obs2', 'just2', 'inju2'];

            DB::beginTransaction();

            while (($rawRow = fgetcsv($handle, 0, self::DELIMITER)) !== false) {
                $lineNumber++;

                if ($this->isEmptyRow($rawRow)) {
                    continue;
                }

                $totalDataRows++;
                $row = $mapper->normalizarFila($rawRow);
                $campos = $mapper->extraerCampos($row);

                $cursoNum = $this->mapCurso($campos['grado']);
                $seccion = trim($campos['division']);
                $dniRaw = preg_replace('/\D+/', '', trim($campos['dni'])) ?? '';

                $context = $this->formatIssueContext($campos);

                if ($cursoNum === null) {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'curso_invalido', 'Grado o año no reconocido en el archivo.', $context);

                    continue;
                }

                if ($seccion === '') {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'division_vacia', 'Falta la división o sección.', $context);

                    continue;
                }

                if ($dniRaw === '' || ! ctype_digit($dniRaw)) {
                    $skippedRows++;
                    $issues[] = $this->issue($lineNumber, 'dni_invalido', 'El DNI debe ser numérico.', $context);

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
                        $context
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
                        $context
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
                        $context.' · '.$this->matriculaMismatchDebug($idLegajos, $idTerlec, $idNivel, $idCursos)
                    );

                    continue;
                }

                $payload = [
                    $updateFields[0] => $campos['desemp'],
                    $updateFields[1] => $campos['just'],
                    $updateFields[2] => $campos['inju'],
                ];

                DB::table('matricula')->where('id', $idMatricula)->update($payload);

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

            return new DesempenosCsvImportResult(
                totalDataRows: $totalDataRows,
                updatedRows: $updatedRows,
                skippedRows: $skippedRows,
                committed: $committed,
                etapa: $etapa,
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

    private function mapCurso(string $texto): ?int
    {
        $t = mb_strtoupper(trim($texto), 'UTF-8');
        if ($t === '') {
            return null;
        }

        if (ctype_digit($t)) {
            $n = (int) $t;

            return $n >= 1 && $n <= 6 ? $n : null;
        }

        if (str_contains($t, 'PRIMER')) {
            return 1;
        }
        if (str_contains($t, 'SEGUNDO')) {
            return 2;
        }
        if (str_contains($t, 'TERCER')) {
            return 3;
        }
        if (str_contains($t, 'CUARTO')) {
            return 4;
        }
        if (str_contains($t, 'QUINTO')) {
            return 5;
        }
        if (str_contains($t, 'SEXTO')) {
            return 6;
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
     * @param  array{grado: string, division: string, turno: string, dni: string, apellido: string, nombre: string, desemp: string, just: string, inju: string}  $campos
     */
    private function formatIssueContext(array $campos): string
    {
        $parts = [];

        $dni = trim($campos['dni']);
        if ($dni !== '') {
            $parts[] = 'DNI '.$dni;
        }

        $alumno = trim($campos['apellido'].' '.$campos['nombre']);
        if ($alumno !== '') {
            $parts[] = $alumno;
        }

        $curso = trim($campos['grado'].' '.$campos['division']);
        if ($curso !== '') {
            $parts[] = $curso;
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
