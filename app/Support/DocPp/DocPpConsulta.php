<?php

namespace App\Support\DocPp;

use App\Models\DocPp;
use App\Models\Materia;
use App\Models\Terlec;
use App\Support\EntoTerlecVerNotas;
use App\Support\NivelSistema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class DocPpConsulta
{
    public const ORDEN_CURSO = 'curso';

    public const ORDEN_MATERIA = 'materia';

    public static function tablaDisponible(): bool
    {
        return Schema::hasTable('doc_pp');
    }

    /**
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
        $query = Materia::query()
            ->from('materias')
            ->join('cursos', 'cursos.Id', '=', 'materias.idCursos')
            ->join('terlec', 'terlec.id', '=', 'materias.idTerlec')
            ->leftJoin('doc_pp as doc_plan', function ($join) {
                $join->on('doc_plan.idMaterias', '=', 'materias.id')
                    ->where('doc_plan.tipo', '=', DocPpStorage::TIPO_PLAN);
            })
            ->leftJoin('doc_pp as doc_prog', function ($join) {
                $join->on('doc_prog.idMaterias', '=', 'materias.id')
                    ->where('doc_prog.tipo', '=', DocPpStorage::TIPO_PROG);
            })
            ->select([
                'materias.id',
                'materias.materia',
                'materias.idCursos',
                'materias.idNivel',
                'materias.idTerlec',
                'cursos.cursec',
                'terlec.ano as ano_lectivo',
                'doc_plan.id as plan_id',
                'doc_plan.aprobado as plan_aprobado',
                'doc_plan.observaciones as plan_obs',
                'doc_plan.nombre_archivo as plan_nombre',
                'doc_prog.id as prog_id',
                'doc_prog.aprobado as prog_aprobado',
                'doc_prog.observaciones as prog_obs',
                'doc_prog.nombre_archivo as prog_nombre',
            ])
            ->where('materias.idNivel', $idNivel)
            ->where('materias.idTerlec', $idTerlec);

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

    public static function materiaEnContexto(int $idMateria, int $idNivel, int $idTerlec): ?object
    {
        return Materia::query()
            ->from('materias')
            ->join('cursos', 'cursos.Id', '=', 'materias.idCursos')
            ->join('terlec', 'terlec.id', '=', 'materias.idTerlec')
            ->select([
                'materias.id',
                'materias.materia',
                'materias.idCursos',
                'materias.idNivel',
                'materias.idTerlec',
                'cursos.cursec',
                'terlec.ano as ano_lectivo',
            ])
            ->where('materias.id', $idMateria)
            ->where('materias.idNivel', $idNivel)
            ->where('materias.idTerlec', $idTerlec)
            ->first();
    }

    public static function documentoDeMateria(int $idMateria, string $tipo): ?DocPp
    {
        if (! self::tablaDisponible() || ! DocPpStorage::tipoValido($tipo)) {
            return null;
        }

        return DocPp::query()
            ->where('idMaterias', $idMateria)
            ->where('tipo', $tipo)
            ->first();
    }

    public static function documentoEnContexto(int $idDoc, int $idNivel, int $idTerlec): ?DocPp
    {
        if (! self::tablaDisponible()) {
            return null;
        }

        return DocPp::query()
            ->where('id', $idDoc)
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->first();
    }

    /**
     * Programas aprobados para descarga pública por año lectivo.
     *
     * @return Collection<int, object>
     */
    public static function programasPublicosPorAnio(int $anio): Collection
    {
        if (! self::tablaDisponible()) {
            return collect();
        }

        $terlecId = (int) (Terlec::query()->where('ano', $anio)->orderByDesc('id')->value('id') ?? 0);
        if ($terlecId <= 0) {
            return collect();
        }

        return DocPp::query()
            ->from('doc_pp')
            ->join('materias', 'materias.id', '=', 'doc_pp.idMaterias')
            ->join('cursos', 'cursos.Id', '=', 'doc_pp.idCursos')
            ->select([
                'doc_pp.id',
                'doc_pp.idNivel',
                'doc_pp.nombre_archivo',
                'materias.materia',
                'cursos.cursec',
            ])
            ->where('doc_pp.idTerlec', $terlecId)
            ->where('doc_pp.tipo', DocPpStorage::TIPO_PROG)
            ->where('doc_pp.aprobado', 1)
            ->where('doc_pp.nombre_archivo', '!=', '')
            ->orderByRaw('COALESCE(cursos.orden, 9999) asc')
            ->orderBy('cursos.cursec')
            ->orderBy('materias.ord')
            ->orderBy('materias.id')
            ->get()
            ->map(function (object $fila) use ($anio) {
                $nombre = trim((string) ($fila->nombre_archivo ?? ''));
                $cursec = self::etiquetaCurso($fila);
                $partes = preg_split('/\s+/', trim($cursec), 2) ?: [];

                $fila->tiene_programa = true;
                $fila->texto_programa = $nombre !== '' ? $nombre : (string) $fila->materia;
                $fila->nombreMateria = (string) $fila->materia;
                $fila->curso = $partes[0] ?? $cursec;
                $fila->seccion = $partes[1] ?? '';
                $fila->url_programa = DocPpStorage::urlPublica(
                    $anio,
                    DocPpStorage::TIPO_PROG,
                    (int) $fila->idNivel,
                    $nombre,
                );

                return $fila;
            });
    }

    public static function estadoCelda(?int $idDoc, ?int $aprobado): string
    {
        if ($idDoc === null || $idDoc <= 0) {
            return 'vacio';
        }

        return (int) $aprobado === 1 ? 'aprobado' : 'pendiente';
    }

    public static function etiquetaCurso(object $fila): string
    {
        $cursec = trim((string) ($fila->cursec ?? ''));

        return $cursec !== '' ? $cursec : ('Curso '.(int) ($fila->idCursos ?? 0));
    }
}
