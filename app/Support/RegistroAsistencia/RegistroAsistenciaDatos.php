<?php

namespace App\Support\RegistroAsistencia;

use App\Models\Curso;
use App\Models\Feriado;
use App\Models\Inasistencia;
use App\Models\Matricula;
use App\Models\Terlec;
use App\Support\Listados\ListadoEstudiantesFormatoMes;
use App\Support\NivelSistema;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arma los datos del Registro de Asistencia (una hoja por curso) desde el legacy ScriptCase/FPDF.
 */
final class RegistroAsistenciaDatos
{
    /** Tipos que se marcan en la grilla diaria (no educación física). */
    private const TIPOS_DIA = [2, 3, 4, 6, 7];

    /**
     * @param  list<int>  $cursoIds  IDs en orden de impresión
     * @return array{
     *   con_datos: bool,
     *   implementacion: string,
     *   mes: int,
     *   ano: int,
     *   nombre_mes: string,
     *   nivel_etiqueta: string,
     *   id_nivel: int,
     *   header: array{insti: string, direccion: string, localidad: string, provincia: string, cue: string, ee: string, logo_file: ?string},
     *   cursos: list<array<string, mixed>>
     * }
     */
    public static function build(array $cursoIds, int $mes, ?int $idNivel = null, ?int $idTerlec = null): array
    {
        $ctx = schoolCtx();
        $idNivel = $idNivel ?? (int) $ctx->idNivel;
        $idTerlec = $idTerlec ?? (int) $ctx->idTerlec;
        $mes = ListadoEstudiantesFormatoMes::normalizarMes($mes);
        if ($mes < 1 || $cursoIds === []) {
            return self::vacio($idNivel);
        }

        $terlec = Terlec::query()->where('id', $idTerlec)->first();
        $ano = (int) ($terlec?->ano ?? $ctx->terlecAno() ?? now()->year);
        if ($ano < 1900) {
            $ano = (int) now()->year;
        }

        $implementacion = tenantRegistroAsistenciaImplementacion($idNivel);
        $conDatos = RegistroAsistenciaCatalog::esConDatos($implementacion);

        [$inicioTerlec, $finTerlec] = self::rangoTerlec($ano, $idTerlec);
        $fechaInicioMes = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
        $fechaFinMes = $fechaInicioMes->copy()->endOfMonth()->startOfDay();

        $mesComienzo = (int) $inicioTerlec->month;
        if ($mes === $mesComienzo) {
            $fechaInicioMes = $inicioTerlec->copy();
        }
        if ($mes === 12) {
            $fechaFinMes = $finTerlec->copy();
        }

        $fechaCorteAlumnos = ($mes === $mesComienzo)
            ? $fechaInicioMes->copy()->addDay()->toDateString()
            : $fechaInicioMes->toDateString();

        $fechaInicioAcumulado = self::fechaInicioAcumulado($idTerlec, $inicioTerlec);
        $diasEnMes = (int) Carbon::createFromDate($ano, $mes, 1)->daysInMonth;

        $feriadosMap = self::feriadosDelMes($idNivel, $ano, $mes);
        $cantFeriadosEnRango = self::contarFeriadosEntre(
            $idNivel,
            $fechaInicioMes->toDateString(),
            $fechaFinMes->toDateString()
        );

        $cursos = Curso::query()
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->whereIn('Id', $cursoIds)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden']);

        $porId = $cursos->keyBy(fn (Curso $c) => (int) $c->Id);
        $hojas = [];
        foreach ($cursoIds as $cursoId) {
            $curso = $porId->get($cursoId);
            if (! $curso) {
                continue;
            }
            $hojas[] = self::hojaCurso(
                $curso,
                $conDatos,
                $ano,
                $mes,
                $diasEnMes,
                $fechaInicioMes->toDateString(),
                $fechaFinMes->toDateString(),
                $fechaCorteAlumnos,
                $fechaInicioAcumulado->toDateString(),
                $inicioTerlec->toDateString(),
                $finTerlec->toDateString(),
                $feriadosMap,
                $cantFeriadosEnRango,
            );
        }

        return [
            'con_datos' => $conDatos,
            'implementacion' => $implementacion,
            'mes' => $mes,
            'ano' => $ano,
            'nombre_mes' => ListadoEstudiantesFormatoMes::nombreMes($mes),
            'nivel_etiqueta' => self::etiquetaNivel($idNivel),
            'id_nivel' => $idNivel,
            'header' => schoolPdfHeaderData(),
            'cursos' => $hojas,
        ];
    }

    /**
     * @return array{con_datos: bool, implementacion: string, mes: int, ano: int, nombre_mes: string, nivel_etiqueta: string, id_nivel: int, header: array, cursos: list}
     */
    private static function vacio(int $idNivel): array
    {
        $impl = tenantRegistroAsistenciaImplementacion($idNivel);

        return [
            'con_datos' => RegistroAsistenciaCatalog::esConDatos($impl),
            'implementacion' => $impl,
            'mes' => 0,
            'ano' => 0,
            'nombre_mes' => '',
            'nivel_etiqueta' => self::etiquetaNivel($idNivel),
            'id_nivel' => $idNivel,
            'header' => schoolPdfHeaderData(),
            'cursos' => [],
        ];
    }

    private static function etiquetaNivel(int $idNivel): string
    {
        return match ($idNivel) {
            NivelSistema::INICIAL => 'Nivel Inicial',
            NivelSistema::PRIMARIO => 'Nivel Primario',
            NivelSistema::SECUNDARIO => 'Nivel Secundario',
            default => trim((string) (schoolCtx()->nivelNombre() ?? 'Nivel')),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function rangoTerlec(int $ano, int $idTerlec): array
    {
        $inicio = Carbon::createFromDate($ano, 2, 1)->startOfDay();
        $fin = Carbon::createFromDate($ano, 12, 31)->startOfDay();

        if (! Schema::hasTable('terlec')) {
            return [$inicio, $fin];
        }

        $cols = ['id', 'ano'];
        if (Schema::hasColumn('terlec', 'fechaInicio')) {
            $cols[] = 'fechaInicio';
        }
        if (Schema::hasColumn('terlec', 'fechaFin')) {
            $cols[] = 'fechaFin';
        }

        $row = DB::table('terlec')->where('ano', $ano)->first($cols);
        if ($row === null) {
            $row = DB::table('terlec')->where('id', $idTerlec)->first($cols);
        }

        if ($row !== null) {
            $fi = self::parseFecha($row->fechaInicio ?? null);
            $ff = self::parseFecha($row->fechaFin ?? null);
            if ($fi !== null) {
                $inicio = $fi;
            }
            if ($ff !== null) {
                $fin = $ff;
            }
        }

        return [$inicio, $fin];
    }

    private static function fechaInicioAcumulado(int $idTerlec, Carbon $fallback): Carbon
    {
        // Legacy: SELECT fechaInicio FROM ento WHERE id = idTerlec (a menudo coincide con inicio de clases).
        if (Schema::hasTable('ento') && Schema::hasColumn('ento', 'fechaInicio')) {
            $raw = DB::table('ento')->where('id', $idTerlec)->value('fechaInicio')
                ?? DB::table('ento')->where('Id', $idTerlec)->value('fechaInicio');
            $parsed = self::parseFecha($raw);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return $fallback->copy();
    }

    private static function parseFecha(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '' || $raw === '0000-00-00') {
            return null;
        }
        try {
            return Carbon::parse((string) $raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string> fecha Y-m-d => nombre
     */
    private static function feriadosDelMes(int $idNivel, int $ano, int $mes): array
    {
        if (! Schema::hasTable('feriados')) {
            return [];
        }

        $desde = sprintf('%04d-%02d-01', $ano, $mes);
        $hasta = Carbon::createFromDate($ano, $mes, 1)->endOfMonth()->toDateString();

        return Feriado::query()
            ->where('idNivel', $idNivel)
            ->whereBetween('fechaFeriado', [$desde, $hasta])
            ->orderBy('fechaFeriado')
            ->get(['fechaFeriado', 'nombre'])
            ->mapWithKeys(function ($f) {
                $fecha = $f->fechaFeriado instanceof Carbon
                    ? $f->fechaFeriado->toDateString()
                    : (string) $f->fechaFeriado;

                return [$fecha => trim((string) $f->nombre)];
            })
            ->all();
    }

    private static function contarFeriadosEntre(int $idNivel, string $desde, string $hasta): int
    {
        if (! Schema::hasTable('feriados')) {
            return 0;
        }

        return (int) Feriado::query()
            ->where('idNivel', $idNivel)
            ->whereBetween('fechaFeriado', [$desde, $hasta])
            ->count();
    }

    /**
     * @param  array<string, string>  $feriadosMap
     * @return array<string, mixed>
     */
    private static function hojaCurso(
        Curso $curso,
        bool $conDatos,
        int $ano,
        int $mes,
        int $diasEnMes,
        string $fechaInicioMes,
        string $fechaFinMes,
        string $fechaCorteAlumnos,
        string $fechaInicioAcumulado,
        string $inicioTerlec,
        string $finTerlec,
        array $feriadosMap,
        int $cantFeriadosEnRango,
    ): array {
        $cursoId = (int) $curso->Id;

        $alumnos = Matricula::query()
            ->from('matricula')
            ->join('legajos', 'matricula.idLegajos', '=', 'legajos.id')
            ->where('matricula.idCursos', $cursoId)
            ->where('matricula.idCondiciones', '<', 5)
            ->where('matricula.fechaMatricula', '<', $fechaFinMes)
            ->where(function ($q) use ($fechaInicioMes) {
                $q->whereNull('matricula.fechaBaja')
                    ->orWhere('matricula.fechaBaja', '>', $fechaInicioMes);
            })
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('legajos.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('legajos.nombre'))
            ->get([
                'matricula.id as id',
                'legajos.apellido',
                'legajos.nombre',
                'legajos.sexo',
            ]);

        $idsMat = $alumnos->pluck('id')->map(fn ($id) => (int) $id)->all();

        $inasPorMatFecha = collect();
        $sumasMes = collect();
        $sumasAcum = collect();

        if ($conDatos && $idsMat !== []) {
            $inasPorMatFecha = Inasistencia::query()
                ->whereIn('idMatricula', $idsMat)
                ->whereBetween('fecha', [
                    sprintf('%04d-%02d-01', $ano, $mes),
                    Carbon::createFromDate($ano, $mes, 1)->endOfMonth()->toDateString(),
                ])
                ->whereIn('tipo', self::TIPOS_DIA)
                ->selectRaw('idMatricula, fecha, SUM(cantidad) as cant')
                ->groupBy('idMatricula', 'fecha')
                ->get()
                ->groupBy(fn ($r) => (int) $r->idMatricula)
                ->map(fn (Collection $rows) => $rows->mapWithKeys(function ($r) {
                    $f = $r->fecha instanceof Carbon ? $r->fecha->toDateString() : (string) $r->fecha;

                    return [$f => (float) $r->cant];
                }));

            $sumasMes = self::sumasPorMatricula($idsMat, $fechaInicioMes, $fechaFinMes);
            $sumasAcum = self::sumasPorMatricula($idsMat, $fechaInicioAcumulado, $fechaFinMes);
        }

        $filas = [];
        $totaInasM = 0.0;
        $totaInasV = 0.0;
        $totaInasVM = 0.0;
        $diasHabilesBase = 0;
        $nro = 0;

        foreach ($alumnos as $alu) {
            $nro++;
            $idMat = (int) $alu->id;
            $apenom = trim((string) $alu->apellido.' '.$alu->nombre);
            if (mb_strlen($apenom) > 23) {
                $apenom = mb_substr($apenom, 0, 23);
            }
            $sexo = (int) ($alu->sexo ?? 0);

            $celdas = [];
            $porFecha = $inasPorMatFecha->get($idMat, collect());

            for ($dd = 1; $dd <= $diasEnMes; $dd++) {
                $fecha = sprintf('%04d-%02d-%02d', $ano, $mes, $dd);
                $fechaDelDiaCmp = sprintf('%04d-%02d-%02d', $ano, $mes, $dd);
                $dow = (int) Carbon::createFromDate($ano, $mes, $dd)->dayOfWeekIso;
                $fueraRango = ($fechaDelDiaCmp < $inicioTerlec || $fechaDelDiaCmp > $finTerlec);

                if ($dow >= 6 || $fueraRango) {
                    $celdas[$dd] = '/';
                } elseif (! $conDatos) {
                    $celdas[$dd] = '';
                    if ($nro === 1) {
                        $diasHabilesBase++;
                    }
                } else {
                    $cant = (float) ($porFecha[$fecha] ?? 0);
                    $celdas[$dd] = self::fmtCant($cant);
                    if ($nro === 1) {
                        $diasHabilesBase++;
                    }
                    if ($cant > 0) {
                        if ($sexo === 1) {
                            $totaInasM += $cant;
                        } elseif ($sexo === 2) {
                            $totaInasV += $cant;
                        }
                        $totaInasVM += $cant;
                    }
                }
            }
            for ($dd = $diasEnMes + 1; $dd <= 31; $dd++) {
                $celdas[$dd] = '//';
            }

            $mesSums = $sumasMes->get($idMat, [
                'tot' => 0.0, 'jus' => 0.0, 'inj' => 0.0, 'ef' => 0.0,
            ]);
            $acumSums = $sumasAcum->get($idMat, [
                'tot' => 0.0, 'ef' => 0.0,
            ]);

            $filas[] = [
                'nro' => $nro,
                'apenom' => $apenom,
                'sexo' => $sexo,
                'celdas' => $celdas,
                'tot' => $conDatos ? self::fmtCant($mesSums['tot']) : '',
                'jus' => $conDatos ? self::fmtCant($mesSums['jus']) : '',
                'inj' => $conDatos ? self::fmtCant($mesSums['inj']) : '',
                'ef' => $conDatos ? self::fmtCant($mesSums['ef']) : '',
                'acu' => $conDatos ? self::fmtCant($acumSums['tot']) : '',
                'aef' => $conDatos ? self::fmtCant($acumSums['ef']) : '',
            ];
        }

        $totalesDia = [];
        for ($dd = 1; $dd <= $diasEnMes; $dd++) {
            $fecha = sprintf('%04d-%02d-%02d', $ano, $mes, $dd);
            $dow = (int) Carbon::createFromDate($ano, $mes, $dd)->dayOfWeekIso;
            if ($dow >= 6) {
                $totalesDia[$dd] = '/';
            } elseif (! $conDatos) {
                $totalesDia[$dd] = '';
            } else {
                $suma = 0.0;
                foreach ($filas as $f) {
                    $v = $f['celdas'][$dd] ?? '';
                    if ($v !== '' && $v !== '/' && $v !== '//') {
                        $suma += (float) str_replace(',', '.', $v);
                    }
                }
                $totalesDia[$dd] = self::fmtCant($suma);
            }
        }
        for ($dd = $diasEnMes + 1; $dd <= 31; $dd++) {
            $totalesDia[$dd] = $dd === $diasEnMes + 1 && $diasEnMes < 31 ? '/' : '//';
        }
        // Legacy: if daysInMonth < 31, one extra "/" after the day totals loop
        if ($diasEnMes < 31) {
            $totalesDia[$diasEnMes + 1] = '/';
            for ($dd = $diasEnMes + 2; $dd <= 31; $dd++) {
                $totalesDia[$dd] = '';
            }
        }

        $stats = self::estadisticasCurso(
            $cursoId,
            $conDatos,
            $fechaCorteAlumnos,
            $fechaFinMes,
            $diasHabilesBase,
            $cantFeriadosEnRango,
            $totaInasM,
            $totaInasV,
            $totaInasVM,
        );

        return [
            'id' => $cursoId,
            'cursec' => $curso->nombreParaListado(),
            'feriados' => $feriadosMap,
            'dias_en_mes' => $diasEnMes,
            'alumnos' => $filas,
            'totales_dia' => $totalesDia,
            'estadisticas' => $stats,
        ];
    }

    /**
     * @param  list<int>  $idsMat
     * @return Collection<int, array{tot: float, jus: float, inj: float, ef: float}>
     */
    private static function sumasPorMatricula(array $idsMat, string $desde, string $hasta): Collection
    {
        $rows = Inasistencia::query()
            ->whereIn('idMatricula', $idsMat)
            ->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw("idMatricula,
                SUM(CASE WHEN tipo <> 5 THEN cantidad ELSE 0 END) as tot,
                SUM(CASE WHEN tipo <> 5 AND just = 'J' THEN cantidad ELSE 0 END) as jus,
                SUM(CASE WHEN tipo <> 5 AND just = 'I' THEN cantidad ELSE 0 END) as inj,
                SUM(CASE WHEN tipo = 5 THEN cantidad ELSE 0 END) as ef")
            ->groupBy('idMatricula')
            ->get();

        return $rows->mapWithKeys(fn ($r) => [
            (int) $r->idMatricula => [
                'tot' => (float) $r->tot,
                'jus' => (float) $r->jus,
                'inj' => (float) $r->inj,
                'ef' => (float) $r->ef,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function estadisticasCurso(
        int $cursoId,
        bool $conDatos,
        string $fechaCorteAlumnos,
        string $fechaFinMes,
        int $diasHabilesBase,
        int $cantFeriados,
        float $totaInasM,
        float $totaInasV,
        float $totaInasVM,
    ): array {
        $diasHabiles = max(0, $diasHabilesBase - $cantFeriados);

        if (! $conDatos) {
            return [
                'mostrar_valores' => false,
                'dias_habiles' => null,
                'al_dia_1' => ['v' => '', 'm' => '', 't' => ''],
                'entrados' => ['v' => '', 'm' => '', 't' => ''],
                'salidos' => ['v' => '', 'm' => '', 't' => ''],
                'quedan' => ['v' => '', 'm' => '', 't' => ''],
                'tot_asist' => ['v' => '', 'm' => '', 't' => ''],
                'tot_inas' => ['v' => '', 'm' => '', 't' => ''],
                'asist_media' => ['v' => '', 'm' => '', 't' => ''],
                'pct_asist' => ['v' => '', 'm' => '', 't' => ''],
            ];
        }

        $base = Matricula::query()
            ->from('matricula')
            ->join('legajos', 'matricula.idLegajos', '=', 'legajos.id')
            ->where('matricula.idCursos', $cursoId)
            ->where('matricula.idCondiciones', '<', 5)
            ->where('matricula.fechaMatricula', '<', $fechaCorteAlumnos);

        $cantAluCur = (int) (clone $base)->count('matricula.id');

        $cantFemCur = (int) (clone $base)
            ->where('legajos.sexo', 1)
            ->where(function ($q) use ($fechaCorteAlumnos) {
                $q->whereNull('matricula.fechaBaja')
                    ->orWhere('matricula.fechaBaja', '>', $fechaCorteAlumnos);
            })
            ->count('matricula.id');

        $cantMasCur = (int) (clone $base)
            ->where('legajos.sexo', 2)
            ->where(function ($q) use ($fechaCorteAlumnos) {
                $q->whereNull('matricula.fechaBaja')
                    ->orWhere('matricula.fechaBaja', '>', $fechaCorteAlumnos);
            })
            ->count('matricula.id');

        $entradosBase = Matricula::query()
            ->from('matricula')
            ->join('legajos', 'matricula.idLegajos', '=', 'legajos.id')
            ->where('matricula.idCursos', $cursoId)
            ->where('matricula.idCondiciones', '<', 5)
            ->whereBetween('matricula.fechaMatricula', [$fechaCorteAlumnos, $fechaFinMes]);

        $cantFemEntrados = (int) (clone $entradosBase)->where('legajos.sexo', 1)->count('matricula.id');
        $cantMasEntrados = (int) (clone $entradosBase)->where('legajos.sexo', 2)->count('matricula.id');

        $salidosBase = Matricula::query()
            ->from('matricula')
            ->join('legajos', 'matricula.idLegajos', '=', 'legajos.id')
            ->where('matricula.idCursos', $cursoId)
            ->where('matricula.idCondiciones', '<', 5)
            ->whereBetween('matricula.fechaBaja', [$fechaCorteAlumnos, $fechaFinMes]);

        $cantFemSalidos = (int) (clone $salidosBase)->where('legajos.sexo', 1)->count('matricula.id');
        $cantMasSalidos = (int) (clone $salidosBase)->where('legajos.sexo', 2)->count('matricula.id');

        $denFem = $cantFemCur > 0 ? $cantFemCur : 1;
        $denMas = $cantMasCur > 0 ? $cantMasCur : 1;
        $denAlu = $cantAluCur > 0 ? $cantAluCur : 1;
        $dh = $diasHabiles > 0 ? $diasHabiles : 1;

        $totasiM = ($denFem * $diasHabiles) - $totaInasM;
        $totasiV = ($denMas * $diasHabiles) - $totaInasV;
        $totasiVM = ($denAlu * $diasHabiles) - $totaInasVM;

        return [
            'mostrar_valores' => true,
            'dias_habiles' => $diasHabiles,
            'al_dia_1' => [
                'v' => (string) $cantMasCur,
                'm' => (string) $cantFemCur,
                't' => (string) ($cantMasCur + $cantFemCur),
            ],
            'entrados' => [
                'v' => (string) $cantMasEntrados,
                'm' => (string) $cantFemEntrados,
                't' => (string) ($cantMasEntrados + $cantFemEntrados),
            ],
            'salidos' => [
                'v' => (string) $cantMasSalidos,
                'm' => (string) $cantFemSalidos,
                't' => (string) ($cantMasSalidos + $cantFemSalidos),
            ],
            'quedan' => [
                'v' => (string) ($cantMasCur + $cantMasEntrados - $cantMasSalidos),
                'm' => (string) ($cantFemCur + $cantFemEntrados - $cantFemSalidos),
                't' => (string) ($cantMasCur + $cantFemCur + $cantMasEntrados + $cantFemEntrados - $cantMasSalidos - $cantFemSalidos),
            ],
            'tot_asist' => [
                'v' => self::fmtCant($totasiV),
                'm' => self::fmtCant($totasiM),
                't' => self::fmtCant($totasiVM),
            ],
            'tot_inas' => [
                'v' => self::fmtCant($totaInasV),
                'm' => self::fmtCant($totaInasM),
                't' => self::fmtCant($totaInasVM),
            ],
            'asist_media' => [
                'v' => self::fmtDec($totasiV / $dh),
                'm' => self::fmtDec($totasiM / $dh),
                't' => self::fmtDec($totasiVM / $dh),
            ],
            'pct_asist' => [
                'v' => self::fmtDec(($totasiV * 100) / ($denMas * $dh)),
                'm' => self::fmtDec(($totasiM * 100) / ($denFem * $dh)),
                't' => self::fmtDec(($totasiVM * 100) / ($denAlu * $dh)),
            ],
        ];
    }

    private static function fmtCant(float $n): string
    {
        if (abs($n) < 0.00001) {
            return '';
        }
        if (abs($n - round($n)) < 0.00001) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    private static function fmtDec(float $n): string
    {
        return (string) round($n, 2);
    }
}
