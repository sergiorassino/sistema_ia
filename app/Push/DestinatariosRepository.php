<?php

namespace App\Push;

use App\Models\Curso;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Facades\DB;

class DestinatariosRepository
{
    /**
     * @return list<array{id:int,label:string}>
     */
    public static function cursosDelContexto(int $idNivel, int $idTerlec): array
    {
        return Curso::query()
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('idCurPlan')
            ->orderBy('Id')
            ->get()
            ->map(fn (Curso $c) => ['id' => (int) $c->Id, 'label' => $c->nombreParaListado()])
            ->all();
    }

    /**
     * Busca alumnos por prefijo de apellido o nombre entre los matriculados en el nivel y ciclo lectivo actuales.
     *
     * Se usa `matricula` (como el resto del módulo) porque `legajos.idnivel` suele estar en 0 o desactualizado en BD legacy.
     *
     * @return list<array{id:int,label:string,dni:?string}>
     */
    public static function buscarAlumnos(int $idNivel, int $idTerlec, string $termino, int $limit = 20): array
    {
        $t = trim($termino);
        if ($t === '') {
            return [];
        }

        $prefix = addcslashes($t, '%_\\') . '%';

        $q = DB::table('legajos as l')
            ->join('matricula as m', 'm.idLegajos', '=', 'l.id')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereNotNull('m.idLegajos')
            ->where(function ($w) use ($prefix) {
                $w->where('l.apellido', 'like', $prefix)
                    ->orWhere('l.nombre', 'like', $prefix);
            })
            ->select(['l.id', 'l.apellido', 'l.nombre', 'l.dni'])
            ->distinct()
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->limit(max(1, min(50, $limit)));

        return $q->get()->map(function ($r) {
            $label = trim((string) $r->apellido . ', ' . (string) $r->nombre);
            $dni = $r->dni !== null ? (string) $r->dni : null;
            return ['id' => (int) $r->id, 'label' => $label, 'dni' => $dni];
        })->all();
    }

    /**
     * Listado de alumnos matriculados en el nivel y ciclo lectivo (selector con checkboxes).
     * Si $filtro está vacío, devuelve todos hasta el límite; si no, filtra por apellido, nombre o DNI.
     *
     * @return list<array{id:int,label:string,dni:?string}>
     */
    public static function alumnosMatriculadosParaSelector(int $idNivel, int $idTerlec, string $filtro = '', int $limit = 2500): array
    {
        $limit = max(1, min(3000, $limit));
        $q = DB::table('legajos as l')
            ->join('matricula as m', 'm.idLegajos', '=', 'l.id')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereNotNull('m.idLegajos')
            ->select(['l.id', 'l.apellido', 'l.nombre', 'l.dni'])
            ->distinct();

        $t = trim($filtro);
        if ($t !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $t) . '%';
            $q->where(function ($w) use ($like) {
                $w->where('l.apellido', 'like', $like)
                    ->orWhere('l.nombre', 'like', $like)
                    ->orWhere('l.dni', 'like', $like);
            });
        }

        return $q->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $label = trim((string) $r->apellido . ', ' . (string) $r->nombre);
                $dni = $r->dni !== null ? (string) $r->dni : null;

                return ['id' => (int) $r->id, 'label' => $label, 'dni' => $dni];
            })
            ->all();
    }

    /**
     * @return list<string> user_keys (legajos.id) de un curso del contexto.
     */
    public static function alumnosPorCurso(int $idNivel, int $idTerlec, int $idCurso): array
    {
        return self::pluckLegajosPorCurso(
            self::queryAlumnosPorCurso($idNivel, $idTerlec, $idCurso)
        );
    }

    /**
     * Alumnos regulares del curso: condición Regular (`idCondiciones` = 1) y sin fecha de baja.
     *
     * @return list<string> user_keys (legajos.id)
     */
    public static function alumnosRegularesPorCurso(int $idNivel, int $idTerlec, int $idCurso): array
    {
        $q = self::queryAlumnosPorCurso($idNivel, $idTerlec, $idCurso)
            ->whereIn(
                'm.idCondiciones',
                ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::REGULARES)
            )
            ->where(function ($w) {
                $w->whereNull('m.fechaBaja')
                    ->orWhere('m.fechaBaja', '0000-00-00')
                    ->orWhere('m.fechaBaja', '');
            });

        return self::pluckLegajosPorCurso($q);
    }

    private static function queryAlumnosPorCurso(int $idNivel, int $idTerlec, int $idCurso)
    {
        return DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->where('m.idCursos', $idCurso)
            ->whereNotNull('m.idLegajos')
            ->distinct()
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'));
    }

    /**
     * @return list<string>
     */
    private static function pluckLegajosPorCurso($query): array
    {
        return $query
            ->pluck('m.idLegajos')
            ->map(fn ($v) => (string) $v)
            ->all();
    }

    /**
     * @return list<string> user_keys (legajos.id) de todo el colegio en el contexto (nivel + ciclo lectivo).
     */
    public static function alumnosDelColegio(int $idNivel, int $idTerlec): array
    {
        return DB::table('matricula as m')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->whereNotNull('m.idLegajos')
            ->distinct()
            ->pluck('m.idLegajos')
            ->map(fn ($v) => (string) $v)
            ->all();
    }

    /**
     * @param list<string> $userKeys
     * @return array<string,string> user_key => "Apellido, Nombre"
     */
    public static function nombresPorUserKeys(array $userKeys): array
    {
        if (empty($userKeys)) {
            return [];
        }

        $rows = DB::table('legajos')
            ->whereIn('id', $userKeys)
            ->get(['id', 'apellido', 'nombre']);

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->id] = trim((string) $r->apellido . ', ' . (string) $r->nombre);
        }
        return $out;
    }
}

