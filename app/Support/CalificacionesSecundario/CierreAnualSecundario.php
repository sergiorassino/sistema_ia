<?php

namespace App\Support\CalificacionesSecundario;

use App\Support\CalificacionesColoquioSecundario;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\PromedioAnualCalificacionesSecundario;
use App\Support\SchoolContext;
use Illuminate\Support\Facades\DB;

/**
 * Cierre anual (secundario): listado de matrículas, historial de calificaciones y pasaje a matriz (Dic / Feb).
 *
 * Porta la lógica legacy de cierreDic / cierreFeb sobre calificaciones del ciclo lectivo activo.
 */
final class CierreAnualSecundario
{
    public const MES_DICIEMBRE = 12;

    public const MES_FEBRERO = 2;

    /** @return list<int> */
    public static function idsCondicionesMatricula(): array
    {
        return ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::TODOS);
    }

    public static function etiquetaApro(?int $apro): string
    {
        return match ((int) ($apro ?? 0)) {
            1 => 'Adeudada',
            2 => 'Aprobada',
            default => 'Cursando',
        };
    }

    /**
     * Matrículas del ciclo lectivo y nivel del contexto (condiciones 1–4).
     *
     * @return list<array{
     *     idMatricula: int,
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     condicion: string
     * }>
     */
    public static function matriculasDelAnio(int $idNivel, int $idTerlec, ?string $buscar = null): array
    {
        if ($idNivel < 1 || $idTerlec < 1) {
            return [];
        }

        $q = DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->leftJoin('condiciones as co', 'co.id', '=', 'm.idCondiciones')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereIn('m.idCondiciones', self::idsCondicionesMatricula())
            ->whereNull('m.fechaBaja')
            ->select([
                'm.id as idMatricula',
                'l.id as idLegajos',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
                'co.condicion as condicion_matricula',
            ])
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->orderBy('l.id');

        $termino = self::normalizarBusqueda($buscar);
        if ($termino !== '') {
            $like = '%'.$termino.'%';
            $q->where(function ($w) use ($like) {
                $w->where('l.apellido', 'like', $like)
                    ->orWhere('l.nombre', 'like', $like)
                    ->orWhere('l.dni', 'like', $like);
            });
        }

        $out = [];
        foreach ($q->get() as $r) {
            $out[] = [
                'idMatricula' => (int) $r->idMatricula,
                'idLegajos' => (int) $r->idLegajos,
                'apellido' => trim((string) ($r->apellido ?? '')),
                'nombre' => trim((string) ($r->nombre ?? '')),
                'dni' => trim((string) ($r->dni ?? '')),
                'curso' => self::cursoLabelDesdeFila($r),
                'condicion' => trim((string) ($r->condicion_matricula ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Historial de todas las calificaciones del alumno en el nivel (cualquier ciclo lectivo).
     *
     * @return list<array{
     *     id: int,
     *     apellido: string,
     *     nombre: string,
     *     ano_lectivo: int|string,
     *     curso: string,
     *     idMaterias: int,
     *     materia: string,
     *     calif: string,
     *     dic: string,
     *     feb: string,
     *     mes: int|string|null,
     *     ano: int|string|null,
     *     cond: string,
     *     escuapro: string,
     *     apro: int,
     *     apro_etiqueta: string
     * }>
     */
    public static function historialAlumno(
        int $idLegajos,
        int $idNivel,
        string $apellido = '',
        string $nombre = '',
    ): array {
        if ($idLegajos < 1 || $idNivel < 1) {
            return [];
        }

        // Materias del curso/año lectivo de cada matrícula histórica (no el plan del año actual).
        $raw = DB::table('matricula as mat')
            ->join('cursos as cu', 'cu.Id', '=', 'mat.idCursos')
            ->join('terlec as t', 't.id', '=', 'mat.idTerlec')
            ->join('materias as m', function ($join) {
                $join->on('m.idCursos', '=', 'mat.idCursos')
                    ->on('m.idTerlec', '=', 'mat.idTerlec');
            })
            ->leftJoin('calificaciones as c', function ($join) use ($idLegajos) {
                $join->on('c.idMatricula', '=', 'mat.id')
                    ->on('c.idMaterias', '=', 'm.id')
                    ->where('c.idLegajos', '=', $idLegajos)
                    ->where('c.ord', '<', 16);
            })
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('matplan as mp', 'mp.id', '=', 'm.idMatPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->where('mat.idLegajos', $idLegajos)
            ->where('mat.idNivel', $idNivel)
            ->where('cu.idNivel', $idNivel)
            ->select([
                'c.id',
                'c.calif',
                'c.dic',
                'c.feb',
                'c.mes',
                'c.ano',
                'c.cond',
                'c.escuapro',
                'c.apro',
                'c.ord as calificacion_orden',
                'm.id as idMaterias',
                'm.materia',
                'm.ord as materia_orden',
                'm.idMatPlan as materia_idMatPlan',
                'm.idCurPlan as materia_idCurPlan',
                't.ano as ano_lectivo',
                'cu.cursec',
                'cu.orden as curso_orden',
                'cu.idCurPlan',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
                'mp.id as matplan_id',
                'mp.ord as matplan_orden',
                'mp.matPlanMateria',
                'mp.idCurPlan as matplan_idCurPlan',
                DB::raw(
                    '(SELECT MIN(c2.orden) FROM cursos c2 WHERE c2.idNivel = cu.idNivel'
                    .' AND c2.idCurPlan = COALESCE(NULLIF(cu.idCurPlan, 0), NULLIF(m.idCurPlan, 0), mp.idCurPlan))'
                    .' AS curplan_orden_ref'
                ),
            ])
            ->orderBy('t.ano')
            ->orderBy('mat.id')
            ->orderBy('m.ord')
            ->orderBy('m.id')
            ->get();

        $out = [];
        foreach ($raw as $r) {
            $materia = trim((string) ($r->materia ?? ''));
            if ($materia === '') {
                continue;
            }

            $apro = (int) ($r->apro ?? 0);
            $out[] = [
                'id' => (int) ($r->id ?? 0),
                'apellido' => $apellido,
                'nombre' => $nombre,
                'ano_lectivo' => $r->ano_lectivo ?? '',
                'curso' => self::cursoLabelDesdeFila($r),
                'idMaterias' => (int) ($r->idMaterias ?? 0),
                'materia' => $materia,
                'calif' => trim((string) ($r->calif ?? '')),
                'dic' => trim((string) ($r->dic ?? '')),
                'feb' => trim((string) ($r->feb ?? '')),
                'mes' => $r->mes,
                'ano' => $r->ano,
                'cond' => trim((string) ($r->cond ?? '')),
                'escuapro' => trim((string) ($r->escuapro ?? '')),
                'apro' => $apro,
                'apro_etiqueta' => self::etiquetaApro($apro),
                '_sort' => self::claveOrdenHistorial($r),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return self::compararOrdenHistorial($a['_sort'], $b['_sort']);
        });

        foreach ($out as &$fila) {
            unset($fila['_sort']);
        }
        unset($fila);

        return $out;
    }

    /**
     * Pasar materias aprobadas al matriz (cierre diciembre) — todo el secundario del ciclo lectivo activo.
     *
     * @return array{procesados: int, aprobados: int, omitidos: int, lote_id: int}
     */
    public static function pasarAprobadasMatrizDic(int $idNivel, int $idTerlec): array
    {
        CierreAnualJournal::asegurarTablas();

        $anoLectivo = self::anoTerlec($idTerlec);
        $escuapro = self::nombreInstitucion($idNivel);
        $actor = CierreAnualJournal::actorDesdeAuth();
        $ctx = schoolCtx();

        return DB::transaction(function () use (
            $idNivel,
            $idTerlec,
            $anoLectivo,
            $escuapro,
            $actor,
            $ctx,
        ) {
            $loteId = CierreAnualJournal::crearLote([
                'operacion' => 'dic',
                'id_nivel' => $idNivel,
                'id_terlec' => $idTerlec,
                'ano_lectivo' => $anoLectivo,
                'nivel_nombre' => $ctx->nivelNombre(),
                'id_profesor' => $actor['id_profesor'],
                'nombre_profesor' => $actor['nombre_profesor'],
            ]);

            $procesados = 0;
            $aprobados = 0;
            $omitidos = 0;

            self::calificacionesCicloActual($idNivel, $idTerlec)
                ->orderBy('c.id')
                ->chunk(200, function ($filas) use (
                    $loteId,
                    $anoLectivo,
                    $escuapro,
                    &$procesados,
                    &$aprobados,
                    &$omitidos,
                ) {
                    $detalle = [];
                    foreach ($filas as $fila) {
                        $procesados++;
                        $calif = trim((string) ($fila->calif ?? ''));
                        $dic = trim((string) ($fila->dic ?? ''));

                        if (! self::estaAprobadaEnDiciembre($calif, $dic)) {
                            $omitidos++;

                            continue;
                        }

                        $notaFinal = self::notaFinalCierre($calif, $dic, '', self::MES_DICIEMBRE);
                        $cond = self::condMatriculaParaFila($fila);
                        $antes = CierreAnualJournal::snapshotDesdeFila($fila);
                        $payload = [
                            'apro' => 2,
                            'calif' => $notaFinal,
                            'mes' => self::MES_DICIEMBRE,
                            'ano' => $anoLectivo,
                            'cond' => $cond,
                            'escuapro' => $escuapro,
                        ];

                        $afectados = DB::table('calificaciones')
                            ->where('id', (int) $fila->id)
                            ->where('idTerlec', (int) $fila->idTerlec)
                            ->update($payload);

                        if ($afectados > 0) {
                            $aprobados++;
                            $detalle[] = CierreAnualJournal::filaDetalle(
                                $loteId,
                                $fila,
                                CierreAnualJournal::TIPO_MATRIZ,
                                $antes,
                                CierreAnualJournal::snapshotTrasUpdate($antes, $payload),
                                self::cursoLabelDesdeFila($fila),
                            );
                        } else {
                            $omitidos++;
                        }
                    }
                    CierreAnualJournal::insertarFilas($detalle);
                });

            CierreAnualJournal::finalizarLote($loteId, [
                'procesados' => $procesados,
                'aprobados' => $aprobados,
                'previas' => 0,
                'omitidos' => $omitidos,
            ]);

            return [
                'procesados' => $procesados,
                'aprobados' => $aprobados,
                'omitidos' => $omitidos,
                'lote_id' => $loteId,
            ];
        });
    }

    /**
     * Aprobadas al matriz (feb) y reprobadas como previas — todo el secundario del ciclo lectivo activo.
     *
     * @return array{procesados: int, aprobados: int, previas: int, omitidos: int, lote_id: int}
     */
    public static function pasarAprobadasMatrizYPreviasFeb(int $idNivel, int $idTerlec): array
    {
        CierreAnualJournal::asegurarTablas();

        $anoLectivo = self::anoTerlec($idTerlec);
        $escuapro = self::nombreInstitucion($idNivel);
        $actor = CierreAnualJournal::actorDesdeAuth();
        $ctx = schoolCtx();

        return DB::transaction(function () use (
            $idNivel,
            $idTerlec,
            $anoLectivo,
            $escuapro,
            $actor,
            $ctx,
        ) {
            $loteId = CierreAnualJournal::crearLote([
                'operacion' => 'feb',
                'id_nivel' => $idNivel,
                'id_terlec' => $idTerlec,
                'ano_lectivo' => $anoLectivo,
                'nivel_nombre' => $ctx->nivelNombre(),
                'id_profesor' => $actor['id_profesor'],
                'nombre_profesor' => $actor['nombre_profesor'],
            ]);

            $procesados = 0;
            $aprobados = 0;
            $previas = 0;
            $omitidos = 0;

            self::calificacionesCicloActual($idNivel, $idTerlec)
                ->orderBy('c.id')
                ->chunk(200, function ($filas) use (
                    $loteId,
                    $anoLectivo,
                    $escuapro,
                    &$procesados,
                    &$aprobados,
                    &$previas,
                    &$omitidos,
                ) {
                    $detalle = [];
                    foreach ($filas as $fila) {
                        $procesados++;
                        $calif = trim((string) ($fila->calif ?? ''));
                        $dic = trim((string) ($fila->dic ?? ''));
                        $feb = trim((string) ($fila->feb ?? ''));

                        if (self::estaAprobadaEnFebrero($calif, $dic, $feb)) {
                            if (! self::necesitaPaseAMatriz($fila)) {
                                $omitidos++;

                                continue;
                            }

                            $notaFinal = self::notaFinalCierre($calif, $dic, $feb, self::MES_FEBRERO);
                            $cond = self::condMatriculaParaFila($fila);
                            $antes = CierreAnualJournal::snapshotDesdeFila($fila);
                            $payload = [
                                'apro' => 2,
                                'calif' => $notaFinal,
                                'mes' => self::MES_FEBRERO,
                                'ano' => $anoLectivo,
                                'cond' => $cond,
                                'escuapro' => $escuapro,
                                'condAdeuda' => null,
                                'inscri' => 0,
                            ];

                            $afectados = DB::table('calificaciones')
                                ->where('id', (int) $fila->id)
                                ->where('idTerlec', (int) $fila->idTerlec)
                                ->update($payload);

                            if ($afectados > 0) {
                                $aprobados++;
                                $detalle[] = CierreAnualJournal::filaDetalle(
                                    $loteId,
                                    $fila,
                                    CierreAnualJournal::TIPO_MATRIZ,
                                    $antes,
                                    CierreAnualJournal::snapshotTrasUpdate($antes, $payload),
                                    self::cursoLabelDesdeFila($fila),
                                );
                            } else {
                                $omitidos++;
                            }

                            continue;
                        }

                        if (self::yaMarcadaComoPrevia($fila)) {
                            $omitidos++;

                            continue;
                        }

                        $antes = CierreAnualJournal::snapshotDesdeFila($fila);
                        $payload = [
                            'apro' => 1,
                            'condAdeuda' => 'PR',
                            'inscri' => 0,
                        ];

                        $afectados = DB::table('calificaciones')
                            ->where('id', (int) $fila->id)
                            ->where('idTerlec', (int) $fila->idTerlec)
                            ->update($payload);

                        if ($afectados > 0) {
                            $previas++;
                            $detalle[] = CierreAnualJournal::filaDetalle(
                                $loteId,
                                $fila,
                                CierreAnualJournal::TIPO_PREVIA,
                                $antes,
                                CierreAnualJournal::snapshotTrasUpdate($antes, $payload),
                                self::cursoLabelDesdeFila($fila),
                            );
                        } else {
                            $omitidos++;
                        }
                    }
                    CierreAnualJournal::insertarFilas($detalle);
                });

            CierreAnualJournal::finalizarLote($loteId, [
                'procesados' => $procesados,
                'aprobados' => $aprobados,
                'previas' => $previas,
                'omitidos' => $omitidos,
            ]);

            return [
                'procesados' => $procesados,
                'aprobados' => $aprobados,
                'previas' => $previas,
                'omitidos' => $omitidos,
                'lote_id' => $loteId,
            ];
        });
    }

    public static function estaAprobadaEnDiciembre(string $calif, string $dic): bool
    {
        if (self::promedioAnualAprobado($calif)) {
            return true;
        }

        return CalificacionesColoquioSecundario::notaColoquioAprobada($dic);
    }

    public static function estaAprobadaEnFebrero(string $calif, string $dic, string $feb): bool
    {
        if (self::promedioAnualAprobado($calif)) {
            return true;
        }

        if (CalificacionesColoquioSecundario::notaColoquioAprobada($dic)) {
            return true;
        }

        return CalificacionesColoquioSecundario::notaColoquioAprobada($feb);
    }

    private static function promedioAnualAprobado(string $calif): bool
    {
        $n = CalificacionesColoquioSecundario::parseNotaColoquio($calif);

        return $n !== null
            && $n >= PromedioAnualCalificacionesSecundario::DEFAULT_NOTA_MINIMA_APROBACION;
    }

    private static function notaFinalCierre(string $calif, string $dic, string $feb, int $mesCierre): string
    {
        if ($mesCierre === self::MES_FEBRERO && CalificacionesColoquioSecundario::notaColoquioAprobada($feb)) {
            return CalificacionesColoquioSecundario::califDesdeNotaColoquio($feb);
        }

        if (CalificacionesColoquioSecundario::notaColoquioAprobada($dic)) {
            return CalificacionesColoquioSecundario::califDesdeNotaColoquio($dic);
        }

        if (self::promedioAnualAprobado($calif)) {
            return $calif;
        }

        return '';
    }

    private static function calificacionesCicloActual(int $idNivel, int $idTerlec)
    {
        return DB::table('calificaciones as c')
            ->join('matricula as m', 'c.idMatricula', '=', 'm.id')
            ->leftJoin('condiciones as co', 'co.id', '=', 'm.idCondiciones')
            ->leftJoin('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->leftJoin('materias as mat', 'mat.id', '=', 'c.idMaterias')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->where('c.idTerlec', $idTerlec)
            ->where('m.idNivel', $idNivel)
            ->whereNull('m.fechaBaja')
            ->select([
                'c.id',
                'c.idTerlec',
                'c.idLegajos',
                'c.idMatricula',
                'c.idMaterias',
                'c.calif',
                'c.dic',
                'c.feb',
                'c.apro',
                'c.mes',
                'c.ano',
                'c.cond',
                'c.escuapro',
                'c.condAdeuda',
                'c.inscri',
                'co.condicion as condicion_matricula',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'mat.materia',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
            ]);
    }

    /** Fila adeudada como previa (cierre febrero ya ejecutado). */
    private static function yaMarcadaComoPrevia(object $fila): bool
    {
        if ((int) ($fila->apro ?? 0) !== 1) {
            return false;
        }

        return strtoupper(trim((string) ($fila->condAdeuda ?? ''))) === 'PR';
    }

    /**
     * Aún no está cerrada al matriz como aprobada (incluye previa PR que debe promoverse).
     */
    private static function necesitaPaseAMatriz(object $fila): bool
    {
        if ((int) ($fila->apro ?? 0) !== 2) {
            return true;
        }

        return trim((string) ($fila->condAdeuda ?? '')) !== '';
    }

    private static function condMatriculaParaFila(object $fila): string
    {
        $cond = trim((string) ($fila->condicion_matricula ?? ''));

        return $cond !== '' ? mb_substr($cond, 0, 20) : 'Regular';
    }

    private static function anoTerlec(int $idTerlec): int
    {
        $ano = DB::table('terlec')->where('id', $idTerlec)->value('ano');

        return $ano !== null ? (int) $ano : (int) date('Y');
    }

    private static function nombreInstitucion(int $idNivel): string
    {
        $insti = trim((string) DB::table('ento')->where('idNivel', $idNivel)->value('insti'));

        return $insti !== '' ? mb_substr($insti, 0, 100) : '';
    }

    /**
     * Orden pedagógico: primer año / primera materia → último año / última materia (plan de estudios).
     *
     * @return array{
     *     curplan_orden_ref: int,
     *     matplan_orden: int,
     *     materia_orden: int,
     *     calificacion_orden: int,
     *     ano_lectivo: int,
     *     materia_plan: string
     * }
     */
    private static function claveOrdenHistorial(object $r): array
    {
        $materiaPlan = trim((string) ($r->matPlanMateria ?? ''));

        return [
            'curplan_orden_ref' => (int) ($r->curplan_orden_ref ?? $r->curso_orden ?? 9999),
            'matplan_orden' => (int) ($r->matplan_orden ?? 0),
            'materia_orden' => (int) ($r->materia_orden ?? 0),
            'calificacion_orden' => (int) ($r->calificacion_orden ?? 0),
            'ano_lectivo' => (int) ($r->ano_lectivo ?? 0),
            'materia_plan' => $materiaPlan !== '' ? $materiaPlan : trim((string) ($r->materia ?? '')),
        ];
    }

    /**
     * @param  array{curplan_orden_ref: int, matplan_orden: int, materia_orden: int, calificacion_orden: int, ano_lectivo: int, materia_plan: string}  $a
     * @param  array{curplan_orden_ref: int, matplan_orden: int, materia_orden: int, calificacion_orden: int, ano_lectivo: int, materia_plan: string}  $b
     */
    private static function compararOrdenHistorial(array $a, array $b): int
    {
        if ($a['curplan_orden_ref'] !== $b['curplan_orden_ref']) {
            return $a['curplan_orden_ref'] <=> $b['curplan_orden_ref'];
        }

        if ($a['matplan_orden'] !== $b['matplan_orden']) {
            return $a['matplan_orden'] <=> $b['matplan_orden'];
        }

        if ($a['materia_orden'] !== $b['materia_orden']) {
            return $a['materia_orden'] <=> $b['materia_orden'];
        }

        if ($a['calificacion_orden'] !== $b['calificacion_orden']) {
            return $a['calificacion_orden'] <=> $b['calificacion_orden'];
        }

        if ($a['ano_lectivo'] !== $b['ano_lectivo']) {
            return $a['ano_lectivo'] <=> $b['ano_lectivo'];
        }

        return strnatcasecmp($a['materia_plan'], $b['materia_plan']);
    }

    private static function normalizarBusqueda(?string $buscar): string
    {
        $t = trim((string) $buscar);

        return mb_strlen($t) >= 2 ? $t : '';
    }

    private static function cursoLabelDesdeFila(object $r): string
    {
        $sec = trim((string) ($r->cursec ?? ''));
        if ($sec !== '') {
            return $sec;
        }

        $nombrePlan = trim((string) ($r->curPlanCurso ?? ''));
        $extras = collect([$r->turnoClaseNombre ?? '', $r->c ?? '', $r->s ?? ''])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        if ($nombrePlan !== '') {
            return $extras->isNotEmpty()
                ? $nombrePlan.' · '.$extras->implode(' · ')
                : $nombrePlan;
        }

        if ($extras->isNotEmpty()) {
            return $extras->implode(' · ');
        }

        return '';
    }

    /**
     * @param  array{procesados: int, aprobados: int, omitidos: int, previas?: int, lote_id?: int}  $res
     * @return array{
     *     operacion: string,
     *     titulo: string,
     *     procesados: int,
     *     actualizados: int,
     *     aprobados: int,
     *     previas: int,
     *     omitidos: int,
     *     nivel: string,
     *     ano_lectivo: int|string,
     *     lote_id: int
     * }
     */
    public static function armarInformeCierre(string $operacion, array $res, SchoolContext $ctx): array
    {
        $aprobados = (int) ($res['aprobados'] ?? 0);
        $previas = (int) ($res['previas'] ?? 0);
        $procesados = (int) ($res['procesados'] ?? 0);
        $omitidos = (int) ($res['omitidos'] ?? 0);

        return [
            'operacion' => $operacion,
            'titulo' => $operacion === 'feb'
                ? 'Informe — Cierre febrero (matriz y previas)'
                : 'Informe — Cierre diciembre (matriz)',
            'procesados' => $procesados,
            'actualizados' => $aprobados + $previas,
            'aprobados' => $aprobados,
            'previas' => $previas,
            'omitidos' => $omitidos,
            'nivel' => $ctx->nivelNombre(),
            'ano_lectivo' => $ctx->terlecAno() ?? '—',
            'lote_id' => (int) ($res['lote_id'] ?? 0),
        ];
    }
}
