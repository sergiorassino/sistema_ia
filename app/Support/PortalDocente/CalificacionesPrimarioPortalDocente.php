<?php

namespace App\Support\PortalDocente;

use App\Models\Matricula;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\NivelSistema;
use Illuminate\Support\Facades\DB;

/**
 * Calificaciones primario en el Menú de Docentes (rutas portal + alcance ppc).
 */
final class CalificacionesPrimarioPortalDocente
{
    private const PORTAL_PREFIX = 'portalDocente.calificacionesPrimario.';

    public static function esPortalDocente(): bool
    {
        return PortalDocenteContext::esActivo();
    }

    public static function abortSiPortalBoletinIpeInactivo(): void
    {
        if (self::esPortalDocente()) {
            abort_unless(tenantPortalDocenteBoletinIpe(), 404);
        }
    }

    public static function rutaBoletinIpe(string $accion = 'index'): string
    {
        $nombre = self::esPortalDocente()
            ? match ($accion) {
                'pdf' => 'portalDocente.calificacionesPrimario.boletinIpe.pdf',
                'pdfLote' => 'portalDocente.calificacionesPrimario.boletinIpe.pdfLote',
                default => 'portalDocente.calificacionesPrimario.boletinIpe',
            }
            : match ($accion) {
                'pdf' => 'calificacionesPrimario.boletinIpe.pdf',
                'pdfLote' => 'calificacionesPrimario.boletinIpe.pdfLote',
                default => 'calificacionesPrimario.boletinIpe',
            };

        return route($nombre);
    }

    /**
     * Resuelve rutas staff/portal según la implementación activa del módulo.
     *
     * @param  array<string, mixed>|int|string  $parameters
     */
    public static function route(string $accion, array|int|string $parameters = []): string
    {
        [$modulo, $tipo] = self::resolverModuloYAccion($accion);

        $nombre = self::esPortalDocente()
            ? CalificacionesPrimarioModulos::rutaPortal($modulo, $tipo)
            : CalificacionesPrimarioModulos::rutaStaff($modulo, $tipo);

        return route($nombre, $parameters);
    }

    public static function rutaBoletinPrimEpq(string $accion = 'index'): string
    {
        $nombre = self::esPortalDocente()
            ? match ($accion) {
                'pdf' => CalificacionesPrimarioModulos::rutaPortal(CalificacionesPrimarioModulos::BOLETIN_PRIM, 'pdf'),
                'pdfLote' => CalificacionesPrimarioModulos::rutaPortal(CalificacionesPrimarioModulos::BOLETIN_PRIM, 'pdfLote'),
                default => CalificacionesPrimarioModulos::rutaPortal(CalificacionesPrimarioModulos::BOLETIN_PRIM),
            }
            : match ($accion) {
                'pdf' => CalificacionesPrimarioModulos::rutaStaff(CalificacionesPrimarioModulos::BOLETIN_PRIM, 'pdf'),
                'pdfLote' => CalificacionesPrimarioModulos::rutaStaff(CalificacionesPrimarioModulos::BOLETIN_PRIM, 'pdfLote'),
                default => CalificacionesPrimarioModulos::rutaStaff(CalificacionesPrimarioModulos::BOLETIN_PRIM),
            };

        return route($nombre);
    }

    public static function abortSiPortalBoletinPrimEpqInactivo(): void
    {
        if (self::esPortalDocente()) {
            abort_unless(
                CalificacionesPrimarioModulos::moduloActivo(CalificacionesPrimarioModulos::BOLETIN_PRIM)
                && (bool) config('tenant.portal_docente.menu.primario.boletin_ipe', false),
                404,
            );
        }
    }

    /** @return array{0: string, 1: string} */
    private static function resolverModuloYAccion(string $accion): array
    {
        return match ($accion) {
            'carga', 'carga.alumno' => [
                CalificacionesPrimarioModulos::CARGA_ESTUDIANTE,
                $accion === 'carga.alumno' ? 'form' : 'index',
            ],
            'carga.infoAdicional' => [
                CalificacionesPrimarioModulos::CARGA_ESTUDIANTE,
                'info',
            ],
            'cargaMateria' => [CalificacionesPrimarioModulos::CARGA_MATERIA, 'index'],
            'planilla', 'planilla.pdf' => [
                CalificacionesPrimarioModulos::PLANILLA,
                $accion === 'planilla.pdf' ? 'pdf' : 'index',
            ],
            default => abort(404),
        };
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

    public static function abortSiNoEsPrimario(): void
    {
        abort_unless(
            NivelSistema::esPrimario((int) (schoolCtx()->idNivel ?? 0)),
            403,
            'Este módulo corresponde al nivel primario.'
        );
    }

    /** @return list<int> */
    public static function idsCursosAsignados(): array
    {
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        if ($idProfesor < 1) {
            return [];
        }

        $ctx = schoolCtx();

        return DB::table('ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->where('ppc.idProfesor', $idProfesor)
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->pluck('m.idCursos')
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

    public static function abortSiProfesorSinMateria(int $idMateria, int $idCurso): void
    {
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        abort_unless(
            CalificacionesDocenteSecundario::profesorTieneMateria($idProfesor, $idMateria, $idCurso),
            404,
        );
    }
}
