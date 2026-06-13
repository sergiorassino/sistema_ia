<?php

namespace App\Support\CalificacionesPrimario;

use App\Http\Controllers\CalificacionesPrimario\PlanillaCalificacionesPrimarioPdfController;
use App\Livewire\CalificacionesPrimario\CargaCalificacionesPrimarioForm;
use App\Livewire\CalificacionesPrimario\CargaCalificacionesPrimarioIndex;
use App\Livewire\CalificacionesPrimario\CargaCalificacionesPrimarioMateria;
use App\Livewire\CalificacionesPrimario\PlanillaCalificacionesPrimario;

/**
 * Registro de variantes (`implementacion`) de módulos de calificaciones primario.
 *
 * La clave `implementacion` (p. ej. `montecristo`) identifica la versión en código,
 * no el tenant: otro colegio puede reutilizar la misma variante vía config.
 *
 * @see docs/07-versionado-de-modulos-por-tenant.md
 */
final class CalificacionesPrimarioModulos
{
    public const CARGA_ESTUDIANTE = 'carga_estudiante';

    public const CARGA_MATERIA = 'carga_materia';

    public const PLANILLA = 'planilla';

    /**
     * @return array<string, array<string, array{
     *     livewire: class-string,
     *     livewire_form?: class-string,
     *     pdf_controller?: class-string,
     *     ruta_portal: string,
     *     ruta_portal_form?: string,
     *     ruta_portal_pdf?: string,
     *     ruta_staff: string,
     *     ruta_staff_form?: string,
     *     ruta_staff_pdf?: string
     * }>>
     */
    public static function registro(): array
    {
        return [
            self::CARGA_ESTUDIANTE => [
                'montecristo' => [
                    'livewire' => CargaCalificacionesPrimarioIndex::class,
                    'livewire_form' => CargaCalificacionesPrimarioForm::class,
                    'ruta_portal' => 'portalDocente.calificacionesPrimario.carga',
                    'ruta_portal_form' => 'portalDocente.calificacionesPrimario.carga.alumno',
                    'ruta_staff' => 'calificacionesPrimario.carga',
                    'ruta_staff_form' => 'calificacionesPrimario.carga.alumno',
                ],
            ],
            self::CARGA_MATERIA => [
                'montecristo' => [
                    'livewire' => CargaCalificacionesPrimarioMateria::class,
                    'ruta_portal' => 'portalDocente.calificacionesPrimario.cargaMateria',
                    'ruta_staff' => 'calificacionesPrimario.cargaMateria',
                ],
            ],
            self::PLANILLA => [
                'montecristo' => [
                    'livewire' => PlanillaCalificacionesPrimario::class,
                    'pdf_controller' => PlanillaCalificacionesPrimarioPdfController::class,
                    'ruta_portal' => 'portalDocente.calificacionesPrimario.planilla',
                    'ruta_portal_pdf' => 'portalDocente.calificacionesPrimario.planilla.pdf',
                    'ruta_staff' => 'calificacionesPrimario.planilla',
                    'ruta_staff_pdf' => 'calificacionesPrimario.planilla.pdf',
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function modulos(): array
    {
        return [
            self::CARGA_ESTUDIANTE,
            self::CARGA_MATERIA,
            self::PLANILLA,
        ];
    }

    public static function implementacionConfigurada(string $modulo): ?string
    {
        if (! in_array($modulo, self::modulos(), true)) {
            return null;
        }

        $valor = config("tenant.calificaciones_primario.{$modulo}.implementacion");

        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

        return trim((string) $valor);
    }

    public static function moduloActivo(string $modulo): bool
    {
        $impl = self::implementacionConfigurada($modulo);

        return $impl !== null && isset(self::registro()[$modulo][$impl]);
    }

    public static function abortSiModuloInactivo(string $modulo): void
    {
        abort_unless(self::moduloActivo($modulo), 404);
    }

    /**
     * @return array{
     *     livewire: class-string,
     *     livewire_form?: class-string,
     *     pdf_controller?: class-string,
     *     ruta_portal: string,
     *     ruta_portal_form?: string,
     *     ruta_portal_pdf?: string,
     *     ruta_staff: string,
     *     ruta_staff_form?: string,
     *     ruta_staff_pdf?: string
     * }|null
     */
    public static function definicionActiva(string $modulo): ?array
    {
        $impl = self::implementacionConfigurada($modulo);
        if ($impl === null) {
            return null;
        }

        return self::registro()[$modulo][$impl] ?? null;
    }

    public static function rutaStaff(string $modulo, string $accion = 'index'): string
    {
        $def = self::definicionActiva($modulo);
        abort_if($def === null, 404);

        $nombre = match ($accion) {
            'form' => $def['ruta_staff_form'] ?? null,
            'pdf' => $def['ruta_staff_pdf'] ?? null,
            default => $def['ruta_staff'],
        };

        abort_if($nombre === null || $nombre === '', 404);

        return $nombre;
    }

    public static function rutaPortal(string $modulo, string $accion = 'index'): string
    {
        $def = self::definicionActiva($modulo);
        abort_if($def === null, 404);

        $nombre = match ($accion) {
            'form' => $def['ruta_portal_form'] ?? null,
            'pdf' => $def['ruta_portal_pdf'] ?? null,
            default => $def['ruta_portal'],
        };

        abort_if($nombre === null || $nombre === '', 404);

        return $nombre;
    }
}
