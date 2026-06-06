<?php

namespace App\Support\CalificacionesPrimario;

use App\Models\Curso;
use App\Models\Ento;
use App\Models\Matricula;
use App\Support\AnoEnLetrasEs;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos para la planilla de calificaciones (primario, impresión PDF por curso).
 */
final class PlanillaCalificacionesPrimarioDatos
{
    /** Valor legacy ScriptCase para apreciación final (`ic03`). */
    public const ETAPA_APRECIACION_FINAL = 9;

    public static function normalizarEtapa(int $etapa): int
    {
        return match ($etapa) {
            2 => 2,
            self::ETAPA_APRECIACION_FINAL => self::ETAPA_APRECIACION_FINAL,
            default => 1,
        };
    }

    public static function campoNotaParaEtapa(int $etapa): string
    {
        return match (self::normalizarEtapa($etapa)) {
            2 => 'ic02',
            self::ETAPA_APRECIACION_FINAL => 'ic03',
            default => 'ic01',
        };
    }

    public static function etiquetaEtapa(int $etapa): string
    {
        return match (self::normalizarEtapa($etapa)) {
            2 => 'SEGUNDA',
            self::ETAPA_APRECIACION_FINAL => 'APRECIACIÓN FINAL',
            default => 'PRIMERA',
        };
    }

    /**
     * @return array{
     *     insti: string,
     *     categoria: string,
     *     direccion: string,
     *     localidad: string,
     *     departamento: string,
     *     ano: int,
     *     anoLetras: string,
     *     etapa: int,
     *     etapaEtiqueta: string,
     *     campoNota: string
     * }
     */
    public static function contextoPdf(int $etapa): array
    {
        $etapa = self::normalizarEtapa($etapa);
        $ctx = schoolCtx();
        $header = schoolPdfHeaderData();

        $ento = Ento::query()
            ->where('idNivel', (int) $ctx->idNivel)
            ->first(['categoria', 'departamento']);

        $ano = (int) ($ctx->terlecAno() ?? now()->year);
        $anoLetras = AnoEnLetrasEs::format($ano);

        return [
            'insti' => (string) ($header['insti'] ?? ''),
            'categoria' => trim((string) ($ento?->categoria ?? '')),
            'direccion' => (string) ($header['direccion'] ?? ''),
            'localidad' => (string) ($header['localidad'] ?? ''),
            'departamento' => trim((string) ($ento?->departamento ?? '')),
            'ano' => $ano,
            'anoLetras' => $anoLetras,
            'etapa' => $etapa,
            'etapaEtiqueta' => self::etiquetaEtapa($etapa),
            'campoNota' => self::campoNotaParaEtapa($etapa),
        ];
    }

    /**
     * @param  list<int>  $cursoIds  IDs en orden de impresión
     * @return list<array<string, mixed>>
     */
    public static function buildSecciones(array $cursoIds, int $etapa): array
    {
        $etapa = self::normalizarEtapa($etapa);
        $ctx = schoolCtx();
        $campoNota = self::campoNotaParaEtapa($etapa);

        $secciones = [];
        foreach ($cursoIds as $cursoId) {
            $curso = Curso::query()
                ->with('curplan')
                ->where('idNivel', (int) $ctx->idNivel)
                ->where('idTerlec', (int) $ctx->idTerlec)
                ->where('Id', $cursoId)
                ->first(['Id', 'cursec', 'c', 's', 'idCurPlan']);

            if (! $curso) {
                continue;
            }

            $grado = (int) ($curso->c ?? 0);
            $cicloGrado = $grado < 4 ? 'PRIMERO' : 'SEGUNDO';
            $cicloPlan = CalificacionesPrimarioCatalogo::cicloDesdeCurso($curso);

            $materias = CalificacionesPrimarioCatalogo::materiasParaCurso(
                (int) $curso->Id,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
                $cicloPlan,
            );

            $materiasLista = $materias
                ->map(fn (object $m) => [
                    'id' => (int) $m->id,
                    'ord' => (int) $m->ord,
                    'materia' => (string) $m->materia,
                    'abrev' => (string) $m->abrev,
                ])
                ->values()
                ->all();

            $ords = array_column($materiasLista, 'ord');

            $matriculas = Matricula::query()
                ->with('legajo')
                ->join('legajos as l', 'l.id', '=', 'matricula.idLegajos')
                ->where('matricula.idCursos', (int) $curso->Id)
                ->where('matricula.idTerlec', (int) $ctx->idTerlec)
                ->where('matricula.idNivel', (int) $ctx->idNivel)
                ->orderBy('l.apellido')
                ->orderBy('l.nombre')
                ->select('matricula.*')
                ->get();

            $notasPorMatricula = self::notasPorMatricula(
                $matriculas->map(fn (Matricula $m) => (int) $m->id)->all(),
                $ords,
                $campoNota,
            );

            $alumnos = [];
            $nro = 0;
            foreach ($matriculas as $mat) {
                $nro++;
                $legajo = $mat->legajo;
                $apellido = trim((string) ($legajo?->apellido ?? ''));
                $nombre = trim((string) ($legajo?->nombre ?? ''));
                $idMatricula = (int) $mat->id;

                $notas = [];
                foreach ($materiasLista as $matDef) {
                    $ord = (int) $matDef['ord'];
                    $notas[] = (string) ($notasPorMatricula[$idMatricula][$ord] ?? '');
                }

                $alumnos[] = [
                    'nro' => $nro,
                    'nombre' => trim($apellido.' '.$nombre),
                    'dni' => trim((string) ($legajo?->dni ?? '')),
                    'obsAnual' => trim((string) ($mat->obsAnual ?? '')),
                    'notas' => $notas,
                ];
            }

            $secciones[] = [
                'cursoLabel' => $curso->nombreParaListado(),
                'grado' => $grado,
                'division' => trim((string) ($curso->s ?? '')),
                'cicloGrado' => $cicloGrado,
                'esCicloPrimero' => $cicloGrado === 'PRIMERO',
                'materias' => $materiasLista,
                'alumnos' => $alumnos,
            ];
        }

        return $secciones;
    }

    /**
     * @param  list<int>  $idMatriculas
     * @param  list<int>  $ords
     * @return array<int, array<int, string>>
     */
    private static function notasPorMatricula(array $idMatriculas, array $ords, string $campoNota): array
    {
        if ($idMatriculas === [] || $ords === []) {
            return [];
        }

        if (! in_array($campoNota, ['ic01', 'ic02', 'ic03'], true)) {
            return [];
        }

        $filas = DB::table('calificaciones')
            ->whereIn('idMatricula', $idMatriculas)
            ->whereIn('ord', $ords)
            ->get(['idMatricula', 'ord', $campoNota]);

        $out = [];
        foreach ($filas as $r) {
            $idMat = (int) $r->idMatricula;
            $ord = (int) $r->ord;
            $out[$idMat][$ord] = trim((string) ($r->{$campoNota} ?? ''));
        }

        return $out;
    }

    public static function rutaEscudoProvincia(): ?string
    {
        $candidatos = [
            public_path('img/escudo-provincia-bn.jpg'),
            public_path('img/escudo-provincia.jpg'),
            base_path('public/img/escudo-provincia-bn.jpg'),
        ];

        foreach ($candidatos as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Ordena IDs de curso según el listado del nivel (misma secuencia que el selector).
     *
     * @param  list<int>  $cursoIds
     * @param  Collection<int, Curso>  $cursosPermitidos
     * @return list<int>
     */
    public static function ordenarIdsCursos(array $cursoIds, Collection $cursosPermitidos): array
    {
        $set = array_flip($cursoIds);
        $ordenados = [];
        foreach ($cursosPermitidos as $c) {
            $id = (int) $c->Id;
            if (isset($set[$id]) && ! in_array($id, $ordenados, true)) {
                $ordenados[] = $id;
            }
        }

        return $ordenados;
    }
}
