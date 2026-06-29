<?php

namespace App\Support\CalificacionesInicial\Sfq;

use App\Models\Ento;
use App\Models\Matricula;
use App\Models\Terlec;
use Illuminate\Support\Facades\DB;

/**
 * Datos para informes inicial SFQ — pedagógico (por etapa) o Bellas Artes (3 etapas en una hoja).
 */
final class BoletinInicialSfqDatos
{
    /**
     * @return array{ok: bool, error?: string, data?: array<string, mixed>}
     */
    public static function buildForMatriculaEnContexto(int $idMatricula, string $tipoInforme): array
    {
        $mat = CalificacionesInicialSfqDatos::matriculaEnContexto($idMatricula);
        if ($mat === null) {
            return ['ok' => false, 'error' => 'Matrícula no encontrada en el contexto activo.'];
        }

        $meta = CalificacionesInicialSfqCatalogo::metaTipoInforme($tipoInforme);
        if ($meta === null) {
            return ['ok' => false, 'error' => 'Tipo de informe no válido.'];
        }

        try {
            $data = ($meta['variante'] ?? '') === 'bellas_artes'
                ? self::buildBellasArtes($mat)
                : self::buildPedagogico($mat, (int) ($meta['etapaPedagogica'] ?? 1), $meta);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return ['ok' => false, 'error' => $e->getMessage() !== '' ? $e->getMessage() : 'No disponible.'];
        }

        return ['ok' => true, 'data' => $data];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function buildPedagogico(Matricula $matricula, int $etapa, array $meta): array
    {
        CalificacionesInicialSfqDatos::abortSiEsquemaIncompleto();

        $etapa = match ($etapa) {
            2, 3 => $etapa,
            default => 1,
        };

        $campoIc = (string) ($meta['campoIc'] ?? 'ic01');
        $campoObs = (string) ($meta['campoObs'] ?? 'obs01');

        $matricula->loadMissing(['legajo', 'curso']);
        $curso = CalificacionesInicialSfqDatos::cursoEnContexto((int) $matricula->idCursos);
        abort_if($curso === null, 404);

        $materia = CalificacionesInicialSfqDatos::materiaPrincipalCurso((int) $curso->Id);
        abort_if($materia === null, 404);

        $fila = CalificacionesInicialSfqDatos::filaCalificaciones((int) $matricula->id, (int) $materia->ord);
        $ic = $fila !== null ? (string) ($fila->{$campoIc} ?? '') : '';
        $obs = $fila !== null ? trim((string) ($fila->{$campoObs} ?? '')) : '';

        $sala = (int) ($curso->c ?? 0);
        $filasInd = CalificacionesInicialSfqDatos::indicadoresParaCarga(
            $sala,
            $etapa,
            CalificacionesInicialSfqCatalogo::AREA_PEDAGOGICO,
        );
        $filasInd = CalificacionesInicialSfqDatos::fusionarNotasEnIndicadores($filasInd, $ic);

        $comunes = self::datosComunesEncabezado($matricula, $curso);

        return array_merge($comunes, [
            'variante' => 'pedagogico',
            'tipoInforme' => (string) ($meta['etiqueta'] ?? ''),
            'etapa' => $etapa,
            'nombreEtapa' => CalificacionesInicialSfqCatalogo::etiquetaEtapaInforme($etapa),
            'docente' => self::docenteCursoPorOrd((int) $curso->Id, 1),
            'observaciones' => $obs,
            'gruposEdani' => self::agruparPorEdani($filasInd),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildBellasArtes(Matricula $matricula): array
    {
        CalificacionesInicialSfqDatos::abortSiEsquemaIncompleto();
        CalificacionesInicialSfqDatos::abortSiObservacionesBaInexistentes();

        $matricula->loadMissing(['legajo', 'curso']);
        $curso = CalificacionesInicialSfqDatos::cursoEnContexto((int) $matricula->idCursos);
        abort_if($curso === null, 404);

        $materia = CalificacionesInicialSfqDatos::materiaPrincipalCurso((int) $curso->Id);
        abort_if($materia === null, 404);

        $fila = CalificacionesInicialSfqDatos::filaCalificaciones((int) $matricula->id, (int) $materia->ord);
        $sala = (int) ($curso->c ?? 0);

        $secciones = [];
        foreach (CalificacionesInicialSfqCatalogo::seccionesInformeBellasArtes() as $def) {
            $campoIc = (string) $def['campoIc'];
            $campoObs = (string) $def['campoObs'];
            $ic = $fila !== null ? (string) ($fila->{$campoIc} ?? '') : '';
            $obs = $fila !== null ? trim((string) ($fila->{$campoObs} ?? '')) : '';

            $filasInd = CalificacionesInicialSfqDatos::indicadoresPorSalaEtapa(
                $sala,
                (int) $def['etapaIndicadores'],
            );
            $filasInd = CalificacionesInicialSfqDatos::fusionarNotasEnIndicadores($filasInd, $ic);

            $secciones[] = [
                'titulo' => (string) $def['titulo'],
                'observaciones' => $obs,
                'gruposEdani' => self::agruparPorEdani($filasInd),
            ];
        }

        $comunes = self::datosComunesEncabezado($matricula, $curso);

        return array_merge($comunes, [
            'variante' => 'bellas_artes',
            'tipoInforme' => 'Bellas Artes',
            'docente' => self::docenteCursoPorOrd((int) $curso->Id, 2),
            'secciones' => $secciones,
        ]);
    }

    /**
     * @param  object{cursec?: mixed, Id?: mixed}  $curso
     * @return array<string, mixed>
     */
    private static function datosComunesEncabezado(Matricula $matricula, object $curso): array
    {
        $legajo = $matricula->legajo;
        $cursec = trim((string) ($curso->cursec ?? ''));

        $terlec = Terlec::query()->find((int) $matricula->idTerlec, ['ano']);
        $ctx = schoolCtx();
        $ento = Ento::query()
            ->where('idNivel', (int) $ctx->idNivel)
            ->first(['insti']);

        $membreteRel = config('tenant.boletin_inicial.membrete');
        $membrete = is_string($membreteRel) && $membreteRel !== ''
            ? public_path($membreteRel)
            : null;
        if ($membrete !== null && ! is_file($membrete)) {
            $membrete = null;
        }

        $tituloInst = trim((string) config('tenant.boletin_inicial.titulo_institucion', ''));
        if ($tituloInst === '') {
            $tituloInst = trim((string) ($ento?->insti ?? ''));
        }
        if ($tituloInst === '') {
            $tituloInst = 'E.P. SAN FRANCISCO';
        }

        return [
            'idMatricula' => (int) $matricula->id,
            'apellido' => trim((string) ($legajo?->apellido ?? '')),
            'nombre' => trim((string) ($legajo?->nombre ?? '')),
            'cursec' => $cursec,
            'anoLectivo' => (string) ($terlec?->ano ?? schoolCtx()->terlecAno()),
            'tituloInstitucion' => $tituloInst,
            'membrete' => $membrete,
        ];
    }

    public static function docenteCursoPorOrd(int $idCurso, int $ordMateria): string
    {
        $ctx = schoolCtx();

        $nombre = DB::table('profesores as p')
            ->join('ppc', 'ppc.idProfesor', '=', 'p.id')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->where('m.idCursos', $idCurso)
            ->where('m.ord', $ordMateria)
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->selectRaw("TRIM(CONCAT(COALESCE(p.apellido, ''), ' ', COALESCE(p.nombre, ''))) as docente")
            ->value('docente');

        return trim((string) ($nombre ?? ''));
    }

    /**
     * @param  list<array{id: int, idEdani: int, edani: string, indicador: string, nota: string}>  $filas
     * @return list<array{edani: string, filas: list<array{indicador: string, nota: string}>}>
     */
    private static function agruparPorEdani(array $filas): array
    {
        $grupos = [];
        $idActual = null;
        $idx = -1;

        foreach ($filas as $fila) {
            $idEdani = (int) ($fila['idEdani'] ?? 0);
            if ($idActual !== $idEdani) {
                $grupos[] = [
                    'edani' => (string) ($fila['edani'] ?? ''),
                    'filas' => [],
                ];
                $idx++;
                $idActual = $idEdani;
            }

            $grupos[$idx]['filas'][] = [
                'indicador' => (string) ($fila['indicador'] ?? ''),
                'nota' => self::notaPdf((string) ($fila['nota'] ?? '')),
            ];
        }

        return $grupos;
    }

    private static function notaPdf(string $digito): string
    {
        $legible = CalificacionesInicialSfqCatalogo::notaLegible($digito);

        return $legible !== '' ? mb_strtoupper($legible, 'UTF-8') : '';
    }
}
