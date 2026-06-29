<?php

namespace App\Support\PortalDocente;

use App\Models\Matricula;
use App\Support\CalificacionesInicial\CalificacionesInicialModulos;
use App\Support\NivelSistema;

/**
 * Calificaciones inicial SFQ — rutas portal/staff y alcance ppc.
 */
final class CalificacionesInicialSfqPortalDocente
{
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

    public static function abortSiMenuInactivo(): void
    {
        if (! self::esPortalDocente()) {
            return;
        }

        abort_unless(
            (bool) config('tenant.portal_docente.menu.inicial.carga_notas', false),
            404,
        );
    }

    public static function abortSiMenuBoletinInactivo(): void
    {
        if (! self::esPortalDocente()) {
            return;
        }

        abort_unless(
            (bool) config('tenant.portal_docente.menu.inicial.boletin', false),
            404,
        );
    }

    public static function rutaBoletin(string $accion = 'index'): string
    {
        $nombre = self::esPortalDocente()
            ? CalificacionesInicialModulos::rutaPortal(CalificacionesInicialModulos::BOLETIN, match ($accion) {
                'pdf' => 'pdf',
                'pdfLote' => 'pdfLote',
                default => 'index',
            })
            : CalificacionesInicialModulos::rutaStaff(CalificacionesInicialModulos::BOLETIN, match ($accion) {
                'pdf' => 'pdf',
                'pdfLote' => 'pdfLote',
                default => 'index',
            });

        return route($nombre);
    }

    /**
     * @param  array<string, mixed>|int|string  $parameters
     */
    public static function route(string $accion, array|int|string $parameters = []): string
    {
        $tipo = match ($accion) {
            'carga.indicadores', 'indicadores' => 'indicadores',
            'carga.observaciones', 'observaciones' => 'observaciones',
            default => 'index',
        };

        $nombre = self::esPortalDocente()
            ? CalificacionesInicialModulos::rutaPortal(CalificacionesInicialModulos::CARGA_NOTAS, $tipo)
            : CalificacionesInicialModulos::rutaStaff(CalificacionesInicialModulos::CARGA_NOTAS, $tipo);

        return route($nombre, $parameters);
    }

    /** @return list<int> */
    public static function idsCursosAsignados(): array
    {
        return CalificacionesPrimarioPortalDocente::idsCursosAsignados();
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
}
