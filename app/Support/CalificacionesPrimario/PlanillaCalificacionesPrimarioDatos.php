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
    public static function normalizarEtapa(int $etapa): int
    {
        return CalificacionesPrimarioCatalogo::normalizarEtapaPlanilla($etapa);
    }

    public static function campoNotaParaEtapa(int $etapa): string
    {
        return CalificacionesPrimarioCatalogo::campoNotaEtapa($etapa);
    }

    public static function etiquetaEtapa(int $etapa): string
    {
        return CalificacionesPrimarioCatalogo::etiquetaEtapaPlanilla($etapa);
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
                ->where('idNivel', (int) $ctx->idNivel)
                ->where('idTerlec', (int) $ctx->idTerlec)
                ->where('Id', $cursoId)
                ->first(['Id', 'cursec', 'c', 's']);

            if (! $curso) {
                continue;
            }

            $grado = (int) ($curso->c ?? 0);
            $cicloGrado = $grado < 4 ? 'PRIMERO' : 'SEGUNDO';

            $bloquesMaterias = CalificacionesPrimarioCatalogo::materiasParaPlanilla(
                (int) $curso->Id,
                (int) $ctx->idNivel,
                (int) $ctx->idTerlec,
            );

            $materiasCurriculares = $bloquesMaterias['curriculares']
                ->map(fn (object $m) => [
                    'id' => (int) $m->id,
                    'ord' => (int) $m->ord,
                    'materia' => (string) $m->materia,
                    'abrev' => (string) $m->abrev,
                    'esInstitucional' => 0,
                ])
                ->values()
                ->all();

            $materiasInstitucionales = $bloquesMaterias['institucionales']
                ->map(fn (object $m) => [
                    'id' => (int) $m->id,
                    'ord' => (int) $m->ord,
                    'materia' => (string) $m->materia,
                    'abrev' => (string) $m->abrev,
                    'esInstitucional' => 1,
                ])
                ->values()
                ->all();

            $materiasLista = array_merge($materiasCurriculares, $materiasInstitucionales);

            $idsMaterias = array_column($materiasLista, 'id');

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
                $idsMaterias,
                $materiasLista,
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
                    $idMaterias = (int) $matDef['id'];
                    $notas[] = (string) ($notasPorMatricula[$idMatricula][$idMaterias] ?? '');
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
                'materiasCurriculares' => $materiasCurriculares,
                'materiasInstitucionales' => $materiasInstitucionales,
                'materias' => $materiasLista,
                'alumnos' => $alumnos,
            ];
        }

        return $secciones;
    }

    /**
     * @param  list<int>  $idMatriculas
     * @param  list<int>  $idsMaterias
     * @param  list<array{id: int, ord: int}>  $materiasLista
     * @return array<int, array<int, string>>
     */
    private static function notasPorMatricula(array $idMatriculas, array $idsMaterias, array $materiasLista, string $campoNota): array
    {
        if ($idMatriculas === [] || $idsMaterias === []) {
            return [];
        }

        if (! in_array($campoNota, CalificacionesPrimarioCatalogo::camposNotaTodos(), true)) {
            return [];
        }

        $ordsLegacy = array_column($materiasLista, 'ord', 'id');

        $filas = DB::table('calificaciones')
            ->whereIn('idMatricula', $idMatriculas)
            ->where(function ($q) use ($idsMaterias, $ordsLegacy): void {
                $q->whereIn('idMaterias', $idsMaterias);
                $ords = array_values(array_filter(array_map('intval', $ordsLegacy)));
                if ($ords !== []) {
                    $q->orWhere(function ($q2) use ($ords): void {
                        $q2->whereIn('ord', $ords)
                            ->where(function ($q3): void {
                                $q3->whereNull('idMaterias')
                                    ->orWhere('idMaterias', 0);
                            });
                    });
                }
            })
            ->get(['idMatricula', 'idMaterias', 'ord', $campoNota]);

        $porIdMaterias = [];
        $porOrdLegacy = [];
        foreach ($filas as $r) {
            $idMat = (int) $r->idMatricula;
            $idMaterias = (int) ($r->idMaterias ?? 0);
            $valor = trim((string) ($r->{$campoNota} ?? ''));

            if ($idMaterias > 0) {
                $porIdMaterias[$idMat][$idMaterias] = $valor;

                continue;
            }

            $porOrdLegacy[$idMat][(int) $r->ord] = $valor;
        }

        $out = [];
        foreach ($idMatriculas as $idMatricula) {
            foreach ($materiasLista as $matDef) {
                $idMaterias = (int) $matDef['id'];
                $ord = (int) $matDef['ord'];
                $out[$idMatricula][$idMaterias] = $porIdMaterias[$idMatricula][$idMaterias]
                    ?? $porOrdLegacy[$idMatricula][$ord]
                    ?? '';
            }
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
