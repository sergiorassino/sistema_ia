<?php

namespace App\Support\PortalDocente;

use App\Models\Matricula;
use App\Support\NivelSistema;
use Illuminate\Support\Collection;

/**
 * Calificaciones inicial en el Menú de Docentes (rutas portal + alcance ppc).
 */
final class CalificacionesInicialPortalDocente
{
    public const MENU_INDICADORES = 'indicadores';

    public const MENU_OBSERVACIONES = 'observaciones';

    public const MENU_INFORME_PROGRESO = 'informe_progreso';

    public static function esPortalDocente(): bool
    {
        return PortalDocenteContext::esActivo();
    }

    public static function layout(): string
    {
        return self::esPortalDocente()
            ? 'layouts.docente'
            : \App\Support\ProfesorMenuPortal::layoutStaff();
    }

    public static function urlInicio(): string
    {
        return self::esPortalDocente()
            ? route('portalDocente.home')
            : route('dashboard');
    }

    public static function abortSiNoEsInicial(): void
    {
        abort_unless(
            NivelSistema::esInicial((int) (schoolCtx()->idNivel ?? 0)),
            403,
            'Este módulo corresponde al nivel inicial.'
        );
    }

    public static function abortSiMenuInactivo(string $menuKey): void
    {
        if (! self::esPortalDocente()) {
            return;
        }

        abort_unless(
            (bool) config("tenant.portal_docente.menu.inicial.{$menuKey}", false),
            404,
        );
    }

    /**
     * @param  array<string, mixed>|int|string  $parameters
     */
    public static function route(string $accion, array|int|string $parameters = []): string
    {
        $nombre = self::esPortalDocente()
            ? match ($accion) {
                'indicadores' => 'portalDocente.calificacionesInicial.indicadores',
                'indicadores.materia' => 'portalDocente.calificacionesInicial.indicadores.materia',
                'observaciones' => 'portalDocente.calificacionesInicial.observaciones',
                'observaciones.alumnos' => 'portalDocente.calificacionesInicial.observaciones.alumnos',
                'observaciones.carga' => 'portalDocente.calificacionesInicial.observaciones.carga',
                'informeProgreso' => 'portalDocente.calificacionesInicial.informeProgreso',
                default => abort(404),
            }
            : match ($accion) {
                'indicadores' => 'calificacionesInicial.indicadores',
                'indicadores.materia' => 'calificacionesInicial.indicadores.materia',
                'observaciones' => 'calificacionesInicial.observaciones',
                'observaciones.alumnos' => 'calificacionesInicial.observaciones.alumnos',
                'observaciones.carga' => 'calificacionesInicial.observaciones.carga',
                'informeProgreso' => 'calificacionesInicial.informeProgreso',
                default => abort(404),
            };

        return route($nombre, $parameters);
    }

    public static function rutaInformeProgreso(string $accion = 'pdf'): string
    {
        $nombre = self::esPortalDocente()
            ? match ($accion) {
                'pdfLote' => 'portalDocente.calificacionesInicial.informeProgreso.pdfLote',
                default => 'portalDocente.calificacionesInicial.informeProgreso.pdf',
            }
            : match ($accion) {
                'pdfLote' => 'calificacionesInicial.informeProgreso.pdfLote',
                default => 'calificacionesInicial.informeProgreso.pdf',
            };

        return route($nombre);
    }

    /** @return list<int> */
    public static function idsCursosAsignados(): array
    {
        return CalificacionesPrimarioPortalDocente::idsCursosAsignados();
    }

    /** @return list<int> */
    public static function idsMateriasAsignadas(): array
    {
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        if ($idProfesor < 1) {
            return [];
        }

        return collect(CalificacionesDocenteSecundario::materiasAsignadas($idProfesor))
            ->pluck('idMateria')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function abortSiProfesorSinCurso(int $idCurso): void
    {
        abort_unless(
            in_array($idCurso, self::idsCursosAsignados(), true),
            404,
        );
    }

    public static function abortSiProfesorSinMateria(int $idMateria, int $idCurso): void
    {
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        abort_unless(
            CalificacionesDocenteSecundario::profesorTieneMateria($idProfesor, $idMateria, $idCurso),
            404,
        );
    }

    public static function abortSiProfesorSinMatricula(int $idMatricula): void
    {
        $ctx = schoolCtx();

        $idCurso = Matricula::query()
            ->where('id', $idMatricula)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->value('idCursos');

        abort_unless(
            $idCurso !== null && in_array((int) $idCurso, self::idsCursosAsignados(), true),
            404,
        );
    }

    /**
     * Filtra espacios curriculares por asignación ppc en portal docente.
     *
     * @param  Collection<int, array{curso: \App\Models\Curso, materias: Collection<int, object>}>  $grupos
     * @return Collection<int, array{curso: \App\Models\Curso, materias: Collection<int, object>}>
     */
    public static function filtrarGruposMaterias(Collection $grupos): Collection
    {
        if (! self::esPortalDocente()) {
            return $grupos;
        }

        $idsMaterias = self::idsMateriasAsignadas();

        return $grupos
            ->map(function (array $grupo) use ($idsMaterias) {
                $materias = $grupo['materias']
                    ->filter(fn ($m) => in_array((int) $m->id, $idsMaterias, true))
                    ->values();

                return [
                    'curso' => $grupo['curso'],
                    'materias' => $materias,
                ];
            })
            ->filter(fn (array $grupo) => $grupo['materias']->isNotEmpty())
            ->values();
    }
}
