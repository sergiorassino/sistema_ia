<?php

namespace App\Support\PlanificacionesProgramas;

use App\Models\Curso;
use App\Models\Materia;
use App\Models\Terlec;
use App\Support\EntoTerlecVerNotas;
use App\Support\NivelSistema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PlanificacionesProgramasConsulta
{
    public const ORDEN_CURSO = 'curso';

    public const ORDEN_MATERIA = 'materia';

    /** @return list<string> */
    public static function columnasRequeridas(): array
    {
        return [
            'pp_plan',
            'pp_prog',
            'pp_aprobPlan',
            'pp_aprobProg',
            'pp_obsPlan',
            'pp_obsProg',
            'pp_nombrePlan',
            'pp_nombreProg',
        ];
    }

    /** @return list<string> */
    public static function columnasFaltantes(): array
    {
        if (! Schema::hasTable('materias')) {
            return self::columnasRequeridas();
        }

        $faltantes = [];
        foreach (self::columnasRequeridas() as $columna) {
            if (! Schema::hasColumn('materias', $columna)) {
                $faltantes[] = $columna;
            }
        }

        return $faltantes;
    }

    public static function moduloDisponible(): bool
    {
        return self::columnasFaltantes() === [];
    }

    /**
     * Años lectivos del sistema según `ento.idTerlecVerNotas` por nivel pedagógico.
     *
     * @return list<int>
     */
    public static function aniosLectivosSistema(): array
    {
        $anios = [];

        foreach (NivelSistema::nivelesPedagogicosParaSelector() as $nivel) {
            $ano = EntoTerlecVerNotas::anoParaNivel((int) $nivel->id);
            if ($ano !== null && $ano > 0) {
                $anios[] = $ano;
            }
        }

        $anios = array_values(array_unique($anios));
        rsort($anios);

        return $anios;
    }

    /**
     * @return LengthAwarePaginator<int, object>
     */
    public static function listadoPaginado(
        int $idNivel,
        int $idTerlec,
        string $busqueda = '',
        string $orden = self::ORDEN_CURSO,
        int $porPagina = 50,
    ): LengthAwarePaginator {
        $query = self::queryBase($idNivel, $idTerlec);

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('materias.materia', 'like', $like)
                    ->orWhere('cursos.cursec', 'like', $like);
            });
        }

        if ($orden === self::ORDEN_MATERIA) {
            $query->orderBy('materias.materia')->orderBy('cursos.cursec')->orderBy('materias.id');
        } else {
            $query->orderByRaw('COALESCE(cursos.orden, 9999) asc')
                ->orderBy('cursos.cursec')
                ->orderBy('materias.ord')
                ->orderBy('materias.id');
        }

        return $query->paginate($porPagina);
    }

    /**
     * @return Collection<int, object>
     */
    public static function filasPublicasPorAnio(int $anio): Collection
    {
        $terlecId = (int) (Terlec::query()->where('ano', $anio)->orderByDesc('id')->value('id') ?? 0);
        if ($terlecId <= 0) {
            return collect();
        }

        $filas = self::queryBase(0, $terlecId)
            ->addSelect([
                'materias.pp_prog',
                'materias.pp_aprobProg',
                'materias.pp_nombreProg',
            ])
            ->get();

        return $filas
            ->filter(function (object $fila): bool {
                return (int) ($fila->pp_prog ?? 0) === 1
                    && (int) ($fila->pp_aprobProg ?? 0) === 1
                    && trim((string) ($fila->pp_nombreProg ?? '')) !== '';
            })
            ->map(function (object $fila) use ($anio) {
                $nombre = trim((string) ($fila->pp_nombreProg ?? ''));
                $idNivel = (int) ($fila->idNivel ?? 0);
                $fila->tiene_programa = true;
                $fila->texto_programa = $nombre !== '' ? $nombre : (string) $fila->materia;
                $fila->url_programa = PlanificacionesProgramasStorage::urlPublica(
                    $anio,
                    PlanificacionesProgramasStorage::TIPO_PROG,
                    $idNivel,
                    $nombre,
                );

                return $fila;
            })
            ->values();
    }

    public static function materiaEnContexto(int $idMateria, int $idNivel, int $idTerlec): ?object
    {
        return self::queryBase($idNivel, $idTerlec)
            ->where('materias.id', $idMateria)
            ->first();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private static function queryBase(int $idNivel, int $idTerlec)
    {
        $query = Materia::query()
            ->from('materias')
            ->join('cursos', 'cursos.Id', '=', 'materias.idCursos')
            ->join('terlec', 'terlec.id', '=', 'materias.idTerlec')
            ->select([
                'materias.id',
                'materias.materia',
                'materias.idCursos',
                'materias.idNivel',
                'materias.idTerlec',
                'materias.pp_plan',
                'materias.pp_prog',
                'materias.pp_aprobPlan',
                'materias.pp_aprobProg',
                'materias.pp_obsPlan',
                'materias.pp_obsProg',
                'materias.pp_nombrePlan',
                'materias.pp_nombreProg',
                'cursos.cursec',
                'cursos.orden as curso_orden',
                'terlec.ano as ano_lectivo',
            ])
            ->where('materias.idTerlec', $idTerlec);

        if ($idNivel > 0) {
            $query->where('materias.idNivel', $idNivel);
        }

        return $query;
    }

    public static function estadoDocumento(object $fila, string $tipo): string
    {
        $cols = PlanificacionesProgramasStorage::columnasPorTipo($tipo);
        $tiene = (int) ($fila->{$cols['flag']} ?? 0) === 1
            && trim((string) ($fila->{$cols['nombre']} ?? '')) !== '';

        if (! $tiene) {
            return 'vacio';
        }

        return (int) ($fila->{$cols['aprob']} ?? 0) === 1 ? 'aprobado' : 'pendiente';
    }

    public static function etiquetaCurso(object $fila): string
    {
        $cursec = trim((string) ($fila->cursec ?? ''));

        return $cursec !== '' ? $cursec : ('Curso '.(int) ($fila->idCursos ?? 0));
    }
}
