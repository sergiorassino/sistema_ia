<?php

namespace App\Support\Certificados;

use App\Models\SolicitudPase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

/**
 * Pase parcial — listado de legajos de nivel medio, solicitudpase y URL del PDF.
 */
final class PaseParcial
{
    /**
     * @return list<int>
     */
    public static function idsNivelMedio(): array
    {
        return DB::table('niveles')
            ->where(function ($w) {
                $w->where('nivel', 'like', '%Secundari%')
                    ->orWhere('nivel', 'like', '%Medio%');
            })
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * Legajos cuya última matrícula pertenece a nivel medio (secundario).
     *
     * @return LengthAwarePaginator<int, array{
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string
     * }>
     */
    public static function paginarAlumnos(?string $buscar, int $porPagina = 50): LengthAwarePaginator
    {
        $idsNivel = self::idsNivelMedio();
        if ($idsNivel === []) {
            return self::paginadorVacio();
        }

        $ultimaPorLegajo = DB::table('matricula as m')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->whereIn('m.idNivel', $idsNivel)
            ->select([
                'm.idLegajos',
                DB::raw('MAX(t.ano) as max_ano'),
            ])
            ->groupBy('m.idLegajos');

        $ultimaMatricula = DB::table('matricula as m')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->joinSub($ultimaPorLegajo, 'ul', function ($join) {
                $join->on('ul.idLegajos', '=', 'm.idLegajos')
                    ->on('ul.max_ano', '=', 't.ano');
            })
            ->whereIn('m.idNivel', $idsNivel)
            ->select([
                'm.idLegajos',
                DB::raw('MAX(m.id) as idMatricula'),
            ])
            ->groupBy('m.idLegajos');

        $q = DB::table('legajos as l')
            ->joinSub($ultimaMatricula, 'um', 'um.idLegajos', '=', 'l.id')
            ->join('matricula as m', 'm.id', '=', 'um.idMatricula')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->select([
                'l.id as idLegajos',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
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

        return $q->paginate(max(10, min(100, $porPagina)))
            ->through(static function (object $r): array {
                return [
                    'idLegajos' => (int) $r->idLegajos,
                    'apellido' => trim((string) ($r->apellido ?? '')),
                    'nombre' => trim((string) ($r->nombre ?? '')),
                    'dni' => trim((string) ($r->dni ?? '')),
                    'curso' => self::cursoLabelDesdeFila($r),
                ];
            });
    }

    /**
     * @return array{
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     idNivel: int
     * }|null
     */
    public static function alumnoElegible(int $idLegajos): ?array
    {
        if ($idLegajos < 1) {
            return null;
        }

        $idsNivel = self::idsNivelMedio();
        if ($idsNivel === []) {
            return null;
        }

        $row = DB::table('legajos as l')
            ->where('l.id', $idLegajos)
            ->first(['l.id', 'l.apellido', 'l.nombre', 'l.dni']);

        if ($row === null) {
            return null;
        }

        $ultima = DB::table('matricula as m')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->where('m.idLegajos', $idLegajos)
            ->whereIn('m.idNivel', $idsNivel)
            ->orderByDesc('t.ano')
            ->orderByDesc('m.id')
            ->select([
                'm.idNivel',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
            ])
            ->first();

        if ($ultima === null) {
            return null;
        }

        return [
            'idLegajos' => (int) $row->id,
            'apellido' => trim((string) ($row->apellido ?? '')),
            'nombre' => trim((string) ($row->nombre ?? '')),
            'dni' => trim((string) ($row->dni ?? '')),
            'curso' => self::cursoLabelDesdeFila($ultima),
            'idNivel' => (int) ($ultima->idNivel ?? 0),
        ];
    }

    /**
     * @return array{fecha: string, destino: string}
     */
    public static function valoresPorDefecto(): array
    {
        return [
            'fecha' => now()->format('Y-m-d'),
            'destino' => '',
        ];
    }

    /**
     * @return array{fecha: string, destino: string}|null
     */
    public static function datosGuardados(int $idLegajos): ?array
    {
        if ($idLegajos < 1) {
            return null;
        }

        $row = SolicitudPase::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'fecha' => $row->fecha?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'destino' => trim((string) ($row->destino ?? '')),
        ];
    }

    /**
     * @param  array{fecha: string, destino: string}  $datos
     */
    public static function guardar(int $idLegajos, array $datos): bool
    {
        if ($idLegajos < 1 || self::alumnoElegible($idLegajos) === null) {
            return false;
        }

        $existente = SolicitudPase::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        $payload = [
            'fecha' => $datos['fecha'],
            'destino' => $datos['destino'] !== '' ? $datos['destino'] : null,
        ];

        if ($existente !== null) {
            $existente->fill($payload);
            $existente->save();

            return true;
        }

        SolicitudPase::query()->create([
            'idLegajos' => $idLegajos,
            ...$payload,
        ]);

        return true;
    }

    /**
     * @param  array{fecha: string, destino: string}  $datos
     */
    public static function pdfPost(int $idLegajos, array $datos): array
    {
        return \App\Support\Pdf\PdfPost::datos(route('certificados.paseParcial.pdf'), [
            'idLegajos' => $idLegajos,
            'fecha' => $datos['fecha'],
            'destino' => $datos['destino'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function reglasFormulario(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'destino' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesValidacion(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'Fecha inválida.',
            'destino.required' => 'Indique el establecimiento de destino.',
            'destino.max' => 'El destino no puede superar 200 caracteres.',
        ];
    }

    /**
     * Matrícula activa del alumno en el ciclo lectivo indicado (nivel medio).
     *
     * @return array{
     *     idMatricula: int,
     *     idNivel: int,
     *     cursec: string
     * }|null
     */
    public static function matriculaEnCiclo(int $idLegajos, int $idTerlec): ?array
    {
        $idsNivel = self::idsNivelMedio();
        if ($idLegajos < 1 || $idTerlec < 1 || $idsNivel === []) {
            return null;
        }

        $row = DB::table('matricula as m')
            ->join('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->where('m.idLegajos', $idLegajos)
            ->where('m.idTerlec', $idTerlec)
            ->whereIn('m.idNivel', $idsNivel)
            ->whereNull('m.fechaBaja')
            ->orderByDesc('m.id')
            ->select([
                'm.id as idMatricula',
                'm.idNivel',
                'cu.cursec',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'idMatricula' => (int) $row->idMatricula,
            'idNivel' => (int) ($row->idNivel ?? 0),
            'cursec' => trim((string) ($row->cursec ?? '')),
        ];
    }

    /**
     * @return array{inasistencias: int, amonestaciones: int, observaciones: int}
     */
    public static function totalesInforme(int $idMatricula): array
    {
        if ($idMatricula < 1) {
            return ['inasistencias' => 0, 'amonestaciones' => 0, 'observaciones' => 0];
        }

        $inasistencias = (int) DB::table('inasistencias')
            ->where('idMatricula', $idMatricula)
            ->where('tipo', '<>', 5)
            ->sum('cantidad');

        $amonestaciones = (int) DB::table('sanciones')
            ->where('idMatricula', $idMatricula)
            ->where('idTipoSancion', 3)
            ->sum('cantidad');

        $observaciones = (int) DB::table('sanciones')
            ->where('idMatricula', $idMatricula)
            ->where('idTipoSancion', 2)
            ->sum('cantidad');

        return [
            'inasistencias' => $inasistencias,
            'amonestaciones' => $amonestaciones,
            'observaciones' => $observaciones,
        ];
    }

    /**
     * @return list<array{
     *     materia: string,
     *     ic01: string, ic02: string, ic03: string, ic04: string, ic05: string, ic06: string,
     *     ic07: string, ic08: string, ic09: string, ic10: string, ic11: string, ic12: string,
     *     ic13: string, ic14: string, ic15: string, ic16: string, ic17: string, ic18: string,
     *     ic19: string, ic20: string, ic21: string, ic22: string, ic23: string, ic24: string,
     *     ic25: string, ic26: string, ic27: string, ic28: string
     * }>
     */
    public static function filasCalificaciones(int $idMatricula, int $idTerlec): array
    {
        if ($idMatricula < 1 || $idTerlec < 1) {
            return [];
        }

        $idLegajos = (int) DB::table('matricula')->where('id', $idMatricula)->value('idLegajos');
        if ($idLegajos < 1) {
            return [];
        }

        $filas = DB::table('calificaciones as c')
            ->join('materias as ma', 'ma.id', '=', 'c.idMaterias')
            ->where('c.idLegajos', $idLegajos)
            ->where('c.idTerlec', $idTerlec)
            ->orderBy('ma.ord')
            ->orderBy('ma.id')
            ->select([
                'ma.materia',
                'c.ic01', 'c.ic02', 'c.ic03', 'c.ic04', 'c.ic05', 'c.ic06',
                'c.ic07', 'c.ic08', 'c.ic09', 'c.ic10', 'c.ic11', 'c.ic12',
                'c.ic13', 'c.ic14', 'c.ic15', 'c.ic16', 'c.ic17', 'c.ic18',
                'c.ic19', 'c.ic20', 'c.ic21', 'c.ic22', 'c.ic23', 'c.ic24',
                'c.ic25', 'c.ic26', 'c.ic27', 'c.ic28',
            ])
            ->get();

        $out = [];
        foreach ($filas as $f) {
            $row = ['materia' => trim((string) ($f->materia ?? ''))];
            for ($i = 1; $i <= 28; $i++) {
                $key = 'ic'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $row[$key] = self::notaCelda($f->{$key} ?? null);
            }
            $out[] = $row;
        }

        return $out;
    }

    public static function idNivelParaPdf(): int
    {
        $ids = self::idsNivelMedio();
        if ($ids === []) {
            return 0;
        }

        $ctx = schoolCtx();
        if ($ctx->idNivel > 0 && in_array((int) $ctx->idNivel, $ids, true)) {
            return (int) $ctx->idNivel;
        }

        return $ids[0];
    }

    private static function notaCelda(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return trim((string) $valor);
    }

    public static function normalizarBusqueda(?string $buscar): string
    {
        $t = trim((string) $buscar);
        if ($t === '') {
            return '';
        }

        return mb_strtolower($t, 'UTF-8');
    }

    /**
     * @return LengthAwarePaginator<int, never>
     */
    private static function paginadorVacio(): LengthAwarePaginator
    {
        return new Paginator([], 0, 50, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    private static function cursoLabelDesdeFila(object $r): string
    {
        $cursec = trim((string) ($r->cursec ?? ''));
        $plan = trim((string) ($r->curPlanCurso ?? ''));
        $turno = trim((string) ($r->turnoClaseNombre ?? ''));
        $c = trim((string) ($r->c ?? ''));
        $s = trim((string) ($r->s ?? ''));

        $extras = collect([$turno, $c, $s])
            ->filter(static fn (string $v) => $v !== '')
            ->values();

        if ($plan !== '') {
            return $extras->isNotEmpty()
                ? $plan.' · '.$extras->implode(' · ')
                : $plan;
        }

        if ($cursec !== '') {
            return $extras->isNotEmpty()
                ? $cursec.' · '.$extras->implode(' · ')
                : $cursec;
        }

        if ($extras->isNotEmpty()) {
            return $extras->implode(' · ');
        }

        return '';
    }
}
