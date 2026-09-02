<?php

namespace App\Support\LibroDeTemas;

use App\Models\Curso;
use App\Models\LibroDeTema;
use App\Support\NivelSistema;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

/**
 * Libro de temas (tabla legacy `librodetemas`): clases por materia del ciclo/nivel.
 */
final class LibroDeTemasService
{
    public const TABLA = 'librodetemas';

    /** @return list<string> */
    public static function sugerenciasCaracter(): array
    {
        return [
            'Introducción',
            'Desarrollo',
            'Cierre',
            'Teórico',
            'Práctico',
            'Evaluativa',
            'Diagnóstico',
        ];
    }

    public static function tablaDisponible(): bool
    {
        return Schema::hasTable(self::TABLA);
    }

    public static function mensajeTablaFaltante(): string
    {
        return 'La tabla librodetemas no está disponible en este colegio.';
    }

    public static function esPortalDocente(): bool
    {
        return request()->routeIs('portalDocente.*');
    }

    /** Id del profesor de sesión para filtrar `ppc`; 0 si no hay contexto usable. */
    private static function idProfesorSesion(): int
    {
        return (int) (schoolCtx()->idProfesor ?? 0);
    }

    /**
     * Cursos con al menos una materia asignada al profesor en `ppc` (ciclo y nivel de sesión).
     *
     * @return list<int>
     */
    public static function idsCursosAsignados(): array
    {
        $idProfesor = self::idProfesorSesion();
        if ($idProfesor < 1) {
            return [];
        }

        $ctx = schoolCtx();

        return DB::table('ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->where('ppc.idProfesor', $idProfesor)
            ->where('m.idNivel', (int) ($ctx->idNivel ?? 0))
            ->where('m.idTerlec', (int) ($ctx->idTerlec ?? 0))
            ->pluck('m.idCursos')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function claveNivelMenu(?int $idNivel = null): ?string
    {
        $id = $idNivel ?? (int) (schoolCtx()->idNivel ?? 0);

        return match ($id) {
            NivelSistema::INICIAL => 'inicial',
            NivelSistema::PRIMARIO => 'primario',
            NivelSistema::SECUNDARIO => 'secundario',
            default => null,
        };
    }

    public static function likeEscape(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto);
    }

    /**
     * Materia del ciclo/nivel de sesión. En portal docente exige asignación `ppc`.
     */
    public static function materiaEnAlcance(int $idMateria, bool $soloPpcDelProfesor): ?stdClass
    {
        if ($idMateria < 1 || ! self::tablaDisponible()) {
            return null;
        }

        $ctx = schoolCtx();
        $idTerlec = (int) ($ctx->idTerlec ?? 0);
        if ($idTerlec < 1) {
            return null;
        }

        $query = DB::table('materias as m')
            ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('m.id', $idMateria)
            ->where('m.idTerlec', $idTerlec);

        if ($soloPpcDelProfesor) {
            $idProfesor = self::idProfesorSesion();
            if ($idProfesor < 1) {
                return null;
            }
            $query->where('m.idNivel', (int) ($ctx->idNivel ?? 0))
                ->join('ppc', function ($join) use ($idProfesor) {
                    $join->on('ppc.idMateria', '=', 'm.id')
                        ->where('ppc.idProfesor', '=', $idProfesor);
                });
        } else {
            SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'm.idNivel');
        }

        $row = $query->first([
            'm.id as idMateria',
            'm.materia',
            'm.abrev',
            'm.idCursos as idCurso',
            'c.cursec',
        ]);

        return $row !== null ? self::hidratarMateria($row) : null;
    }

    /**
     * Cursos del ciclo/nivel (en portal: solo los que tienen materia asignada en `ppc`).
     *
     * @return Collection<int, Curso>
     */
    public static function cursosParaSelector(bool $soloPpcDelProfesor): Collection
    {
        $ctx = schoolCtx();
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        $query = Curso::query()
            ->with(['curplan', 'turnoClase'])
            ->where('idTerlec', $idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id');

        if ($soloPpcDelProfesor) {
            $ids = self::idsCursosAsignados();
            if ($ids === []) {
                return collect();
            }
            $query->where('idNivel', (int) ($ctx->idNivel ?? 0))
                ->whereIn('Id', $ids);
        } else {
            SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');
        }

        return $query->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    /**
     * Materias del curso elegido en el ciclo/nivel (en portal: filtradas por `ppc`).
     *
     * @return Collection<int, object{id: int, materia: string, abrev: string|null, ord: int|null}>
     */
    public static function materiasDelCurso(int $idCurso, bool $soloPpcDelProfesor): Collection
    {
        if ($idCurso < 1) {
            return collect();
        }

        $ctx = schoolCtx();
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        $query = DB::table('materias as m')
            ->where('m.idCursos', $idCurso)
            ->where('m.idTerlec', $idTerlec);

        if ($soloPpcDelProfesor) {
            $idProfesor = self::idProfesorSesion();
            if ($idProfesor < 1) {
                return collect();
            }
            $query->where('m.idNivel', (int) ($ctx->idNivel ?? 0))
                ->join('ppc', function ($join) use ($idProfesor) {
                    $join->on('ppc.idMateria', '=', 'm.id')
                        ->where('ppc.idProfesor', '=', $idProfesor);
                });
        } else {
            SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'm.idNivel');
        }

        return $query
            ->distinct()
            ->orderBy('m.ord')
            ->orderBy('m.id')
            ->get(['m.id', 'm.materia', 'm.abrev', 'm.ord']);
    }

    public static function queryClases(int $idMateria): Builder
    {
        return LibroDeTema::query()
            ->where('idMateria', $idMateria)
            ->orderBy('fecha')
            ->orderBy('id');
    }

    public static function scopedOrFail(int $id, int $idMateria): LibroDeTema
    {
        $reg = LibroDeTema::query()
            ->where('id', $id)
            ->where('idMateria', $idMateria)
            ->first();

        abort_unless($reg !== null, 404);

        return $reg;
    }

    public static function ultimaClase(int $idMateria): ?LibroDeTema
    {
        return LibroDeTema::query()
            ->where('idMateria', $idMateria)
            ->orderByDesc('id')
            ->first();
    }

    public static function proximoClaseNro(int $idMateria): int
    {
        $max = (int) LibroDeTema::query()
            ->where('idMateria', $idMateria)
            ->max('claseNro');

        return max(1, $max + 1);
    }

    public static function ultimaUnidad(int $idMateria): int
    {
        $ultima = self::ultimaClase($idMateria);

        return $ultima !== null ? (int) $ultima->unidad : 0;
    }

    private static function hidratarMateria(object $row): stdClass
    {
        $sec = trim((string) ($row->cursec ?? ''));
        $docentes = trim((string) ($row->docentes ?? ''));
        $docentes = trim($docentes, " ·,");

        return (object) [
            'idMateria' => (int) $row->idMateria,
            'materia' => trim((string) ($row->materia ?? '')),
            'abrev' => trim((string) ($row->abrev ?? '')) ?: null,
            'idCurso' => (int) $row->idCurso,
            'cursoLabel' => $sec !== '' ? $sec : ('Curso '.(int) $row->idCurso),
            'docentes' => $docentes !== '' && $docentes !== ',' ? $docentes : '',
        ];
    }
}
