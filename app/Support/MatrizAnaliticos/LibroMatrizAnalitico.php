<?php

namespace App\Support\MatrizAnaliticos;

use App\Models\AnaliticoDato;
use App\Models\Calificacion;
use App\Support\CalificacionesSecundario\CierreAnualSecundario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Libro matriz / pase / analítico: listado de legajos y edición de calificaciones en matriz.
 */
final class LibroMatrizAnalitico
{
    private const SESSION_BUSCAR_LISTADO = 'matriz_analiticos_listado_buscar';

    /** Leyenda por defecto del campo «Para completar» cuando está en blanco en el formulario. */
    private const LEYENDA_PARA_COMPLETAR_DEFAULT = 'Para completar los estudios correspondientes a la Educación Secundaria Obligatoria Ley de Educación Nacional 26.206, Ley Provincial de Educación 9870, Res. Min. 344/11, Res. Min. 668/11, deberá cursar y aprobar todos los espacios curriculares de:';

    public static function etiquetaApro(?int $apro): string
    {
        return CierreAnualSecundario::etiquetaApro($apro);
    }

    /**
     * Legajos con última inscripción en el colegio (cualquier nivel).
     *
     * @return LengthAwarePaginator<int, object{
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     nivel: string
     * }>
     */
    public static function paginarLegajos(?string $buscar, int $porPagina = 30): LengthAwarePaginator
    {
        $ultimaPorLegajo = DB::table('matricula as m')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
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
            ->select([
                'm.idLegajos',
                DB::raw('MAX(m.id) as idMatricula'),
            ])
            ->groupBy('m.idLegajos');

        $q = DB::table('legajos as l')
            ->leftJoinSub($ultimaMatricula, 'um', 'um.idLegajos', '=', 'l.id')
            ->leftJoin('matricula as m', 'm.id', '=', 'um.idMatricula')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->leftJoin('niveles as nv', 'nv.id', '=', 'm.idNivel')
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
                'nv.nivel',
            ])
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->orderBy('l.id');

        self::aplicarFiltroBusquedaLegajos($q, $buscar, 'l');

        return $q->paginate(max(10, min(100, $porPagina)))
            ->through(static function (object $r): array {
                return [
                    'idLegajos' => (int) $r->idLegajos,
                    'apellido' => trim((string) ($r->apellido ?? '')),
                    'nombre' => trim((string) ($r->nombre ?? '')),
                    'dni' => trim((string) ($r->dni ?? '')),
                    'curso' => self::cursoLabelDesdeFila($r),
                    'nivel' => trim((string) ($r->nivel ?? '')),
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
     *     nivel: string
     * }|null
     */
    public static function alumno(int $idLegajos): ?array
    {
        if ($idLegajos < 1) {
            return null;
        }

        $row = DB::table('legajos')->where('id', $idLegajos)->first(['id', 'apellido', 'nombre', 'dni']);
        if ($row === null) {
            return null;
        }

        $ultimaPorLegajo = DB::table('matricula as m')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->leftJoin('niveles as nv', 'nv.id', '=', 'm.idNivel')
            ->where('m.idLegajos', $idLegajos)
            ->orderByDesc('t.ano')
            ->orderByDesc('m.id')
            ->select([
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
                'nv.nivel',
            ])
            ->first();

        return [
            'idLegajos' => (int) $row->id,
            'apellido' => trim((string) ($row->apellido ?? '')),
            'nombre' => trim((string) ($row->nombre ?? '')),
            'dni' => trim((string) ($row->dni ?? '')),
            'curso' => $ultimaPorLegajo ? self::cursoLabelDesdeFila($ultimaPorLegajo) : '',
            'nivel' => $ultimaPorLegajo ? trim((string) ($ultimaPorLegajo->nivel ?? '')) : '',
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     ano_lectivo: int|string,
     *     curso: string,
     *     materia: string,
     *     calif: string,
     *     mes: string,
     *     ano: string,
     *     cond: string,
     *     escuapro: string,
     *     apro: int,
     *     apro_etiqueta: string
     * }>
     */
    public static function lineasEdicion(int $idLegajos, int $idNivel, string $apellido = '', string $nombre = ''): array
    {
        $filas = CierreAnualSecundario::historialAlumno($idLegajos, $idNivel, $apellido, $nombre);
        $out = [];

        foreach ($filas as $f) {
            $out[] = [
                'id' => (int) $f['id'],
                'ano_lectivo' => $f['ano_lectivo'],
                'curso' => $f['curso'],
                'materia' => $f['materia'],
                'calif' => $f['calif'],
                'mes' => self::valorCampoEditable($f['mes'] ?? null),
                'ano' => self::valorCampoEditable($f['ano'] ?? null),
                'cond' => $f['cond'],
                'escuapro' => $f['escuapro'],
                'apro' => (int) $f['apro'],
                'apro_etiqueta' => $f['apro_etiqueta'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{
     *     id: int,
     *     calif?: string,
     *     mes?: string,
     *     ano?: string,
     *     cond?: string,
     *     escuapro?: string
     * }>  $lineas
     * @return array{ok: int, omitidos: int}
     */
    public static function guardarLineas(int $idLegajos, int $idNivel, array $lineas): array
    {
        if ($idLegajos < 1 || $idNivel < 1 || $lineas === []) {
            return ['ok' => 0, 'omitidos' => 0];
        }

        $ok = 0;
        $omitidos = 0;

        DB::transaction(function () use ($idLegajos, $idNivel, $lineas, &$ok, &$omitidos) {
            foreach ($lineas as $linea) {
                $id = (int) ($linea['id'] ?? 0);
                if ($id < 1) {
                    $omitidos++;

                    continue;
                }

                if (! self::calificacionPerteneceAlumnoNivel($id, $idLegajos, $idNivel)) {
                    $omitidos++;

                    continue;
                }

                $mes = self::normalizarMes($linea['mes'] ?? null);
                $ano = self::normalizarAnoMatricula($linea['ano'] ?? null);

                Calificacion::query()
                    ->where('id', $id)
                    ->where('idLegajos', $idLegajos)
                    ->update([
                        'calif' => self::truncar((string) ($linea['calif'] ?? ''), 10),
                        'mes' => $mes,
                        'ano' => $ano,
                        'cond' => self::truncar((string) ($linea['cond'] ?? ''), 20),
                        'escuapro' => self::truncar((string) ($linea['escuapro'] ?? ''), 100),
                    ]);

                $ok++;
            }
        });

        return ['ok' => $ok, 'omitidos' => $omitidos];
    }

    /**
     * Datos adicionales del analítico (analiticodatos) para un legajo.
     *
     * @return array{
     *     id: int|null,
     *     analCohorte: string,
     *     analObservaciones: string,
     *     analParaCompletar: string,
     *     analValidez: string,
     *     serie: string,
     *     numero: string,
     *     analLibroFolio: string,
     *     analFechaEmision: string,
     *     analParaPre: string
     * }
     */
    public static function datosAdicionales(int $idLegajos): array
    {
        $vacios = [
            'id' => null,
            'analCohorte' => '',
            'analObservaciones' => '',
            'analParaCompletar' => self::leyendaParaCompletarParaFormulario(''),
            'analValidez' => '',
            'serie' => '',
            'numero' => '',
            'analLibroFolio' => '',
            'analFechaEmision' => '',
            'analParaPre' => '',
        ];

        if ($idLegajos < 1) {
            return $vacios;
        }

        $row = AnaliticoDato::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return $vacios;
        }

        return [
            'id' => (int) $row->id,
            'analCohorte' => self::cohorteParaFormulario($row->analCohorte),
            'analObservaciones' => trim((string) ($row->analObservaciones ?? '')),
            'analParaCompletar' => self::leyendaParaCompletarParaFormulario($row->analParaCompletar),
            'analValidez' => trim((string) ($row->analValidez ?? '')),
            'serie' => trim((string) ($row->serie ?? '')),
            'numero' => trim((string) ($row->numero ?? '')),
            'analLibroFolio' => trim((string) ($row->analLibroFolio ?? '')),
            'analFechaEmision' => $row->analFechaEmision
                ? $row->analFechaEmision->format('Y-m-d')
                : '',
            'analParaPre' => trim((string) ($row->analParaPre ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reglasDatosAdicionales(): array
    {
        return [
            'analCohorte' => ['nullable', 'string', 'max:30'],
            'analObservaciones' => ['nullable', 'string', 'max:65535'],
            'analParaCompletar' => ['nullable', 'string', 'max:65535'],
            'analValidez' => ['nullable', 'string', 'max:50'],
            'serie' => ['nullable', 'string', 'max:6'],
            'numero' => ['nullable', 'string', 'max:20'],
            'analLibroFolio' => ['nullable', 'string', 'max:50'],
            'analFechaEmision' => ['nullable', 'date'],
            'analParaPre' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @param  array{
     *     analCohorte?: string,
     *     analObservaciones?: string,
     *     analParaCompletar?: string,
     *     analValidez?: string,
     *     serie?: string,
     *     numero?: string,
     *     analLibroFolio?: string,
     *     analFechaEmision?: string|null,
     *     analParaPre?: string
     * }  $datos
     */
    public static function guardarDatosAdicionales(int $idLegajos, array $datos): bool
    {
        if ($idLegajos < 1 || self::alumno($idLegajos) === null) {
            return false;
        }

        $payload = [
            'idLegajos' => $idLegajos,
            'analCohorte' => self::cohorteParaBase($datos['analCohorte'] ?? ''),
            'analObservaciones' => self::textoNullable($datos['analObservaciones'] ?? ''),
            'analParaCompletar' => self::textoNullable($datos['analParaCompletar'] ?? ''),
            'analValidez' => self::truncar((string) ($datos['analValidez'] ?? ''), 50),
            'serie' => self::truncar((string) ($datos['serie'] ?? ''), 6),
            'numero' => self::truncar((string) ($datos['numero'] ?? ''), 20),
            'analLibroFolio' => self::truncar((string) ($datos['analLibroFolio'] ?? ''), 50),
            'analFechaEmision' => self::fechaNullable($datos['analFechaEmision'] ?? null),
            'analParaPre' => self::truncar((string) ($datos['analParaPre'] ?? ''), 200),
        ];

        $existente = AnaliticoDato::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->first();

        if ($existente !== null) {
            $existente->update($payload);

            return true;
        }

        AnaliticoDato::query()->create($payload);

        return true;
    }

    private static function leyendaParaCompletarParaFormulario(mixed $valor): string
    {
        $t = trim((string) ($valor ?? ''));

        return $t !== '' ? $t : self::LEYENDA_PARA_COMPLETAR_DEFAULT;
    }

    private static function cohorteParaFormulario(mixed $valor): string
    {
        $t = trim((string) $valor);

        return $t === '0' ? '' : $t;
    }

    private static function cohorteParaBase(string $valor): string
    {
        $t = trim($valor);

        return $t === '' ? '0' : self::truncar($t, 30);
    }

    private static function textoNullable(string $valor): ?string
    {
        $t = trim($valor);

        return $t === '' ? null : $t;
    }

    private static function fechaNullable(mixed $fecha): ?string
    {
        $t = trim((string) $fecha);

        return $t === '' ? null : $t;
    }

    private static function calificacionPerteneceAlumnoNivel(int $id, int $idLegajos, int $idNivel): bool
    {
        return DB::table('calificaciones as c')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.id', $id)
            ->where('c.idLegajos', $idLegajos)
            ->where('cu.idNivel', $idNivel)
            ->exists();
    }

    private static function valorCampoEditable(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return trim((string) $valor);
    }

    private static function normalizarMes(mixed $mes): ?int
    {
        $t = trim((string) $mes);
        if ($t === '') {
            return null;
        }

        $n = (int) $t;
        if ($n < 1 || $n > 12) {
            return null;
        }

        return $n;
    }

    private static function normalizarAnoMatricula(mixed $ano): ?int
    {
        $t = trim((string) $ano);
        if ($t === '') {
            return null;
        }

        $n = (int) $t;
        if ($n < 1900 || $n > 2100) {
            return null;
        }

        return $n;
    }

    private static function truncar(string $valor, int $max): string
    {
        $v = trim($valor);

        return $v === '' ? '' : mb_substr($v, 0, $max);
    }

    private static function normalizarBusqueda(?string $buscar): string
    {
        $t = trim((string) $buscar);

        return mb_strlen($t) >= 2 ? $t : '';
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function aplicarFiltroBusquedaLegajos($query, ?string $buscar, string $alias = 'l'): void
    {
        $termino = self::normalizarBusqueda($buscar);
        if ($termino === '') {
            return;
        }

        $like = '%'.$termino.'%';
        $palabras = preg_split('/\s+/u', $termino, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $query->where(function ($w) use ($alias, $like, $termino, $palabras) {
            $w->where("{$alias}.apellido", 'like', $like)
                ->orWhere("{$alias}.nombre", 'like', $like)
                ->orWhere("{$alias}.dni", 'like', $like)
                ->orWhereRaw("CONCAT({$alias}.apellido, ' ', {$alias}.nombre) LIKE ?", [$like])
                ->orWhereRaw("CONCAT({$alias}.apellido, ', ', {$alias}.nombre) LIKE ?", [$like]);

            if (count($palabras) >= 2) {
                $apellido = $palabras[0];
                $nombre = implode(' ', array_slice($palabras, 1));

                $w->orWhere(function ($sub) use ($alias, $apellido, $nombre) {
                    $sub->where("{$alias}.apellido", 'like', '%'.$apellido.'%')
                        ->where("{$alias}.nombre", 'like', '%'.$nombre.'%');
                });

                $w->orWhere(function ($sub) use ($alias, $apellido, $nombre) {
                    $sub->where("{$alias}.nombre", 'like', '%'.$apellido.'%')
                        ->where("{$alias}.apellido", 'like', '%'.$nombre.'%');
                });
            }
        });
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

    public static function buscarDesdeRequest(): string
    {
        return trim((string) request()->query('buscar', ''));
    }

    public static function persistirBuscarListado(?string $buscar): void
    {
        session([self::SESSION_BUSCAR_LISTADO => trim((string) $buscar)]);
    }

    public static function buscarRetornoListado(): string
    {
        $desdeRequest = self::buscarDesdeRequest();
        if ($desdeRequest !== '') {
            return $desdeRequest;
        }

        return trim((string) session(self::SESSION_BUSCAR_LISTADO, ''));
    }

    /**
     * @return array<string, string>
     */
    public static function queryFiltroListado(?string $buscar): array
    {
        $t = trim((string) $buscar);

        return $t === '' ? [] : ['buscar' => $t];
    }

    public static function urlListado(?string $buscar = null): string
    {
        return route('matrizAnaliticos.libroMatriz', self::queryFiltroListado($buscar));
    }

    public static function rutaEditar(?string $buscar = null): string
    {
        return route('matrizAnaliticos.libroMatriz.editar', self::queryFiltroListado($buscar));
    }

    public static function rutaDatosAdicionales(?string $buscar = null): string
    {
        return route('matrizAnaliticos.libroMatriz.datosAdicionales', self::queryFiltroListado($buscar));
    }
}
