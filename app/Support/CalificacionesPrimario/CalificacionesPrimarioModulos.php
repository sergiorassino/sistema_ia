<?php

namespace App\Support\CalificacionesPrimario;

use App\Http\Controllers\CalificacionesPrimario\BoletinPrimEpqLotePdfController;
use App\Http\Controllers\CalificacionesPrimario\BoletinPrimEpqPdfController;
use App\Http\Controllers\CalificacionesPrimario\PlanillaCalificacionesEpqPdfController;
use App\Http\Controllers\CalificacionesPrimario\PlanillaCalificacionesPrimarioPdfController;
use App\Livewire\CalificacionesPrimario\CargaCalificacionesPrimarioForm;
use App\Livewire\CalificacionesPrimario\CargaCalificacionesPrimarioIndex;
use App\Livewire\CalificacionesPrimario\CargaCalificacionesPrimarioMateria;
use App\Livewire\CalificacionesPrimario\Epq\BoletinPrimEpqIndex;
use App\Livewire\CalificacionesPrimario\Epq\CargaCalificacionesEpqForm;
use App\Livewire\CalificacionesPrimario\Epq\CargaCalificacionesEpqIndex;
use App\Livewire\CalificacionesPrimario\Epq\InfoAdicionalEpqForm;
use App\Livewire\CalificacionesPrimario\Epq\PlanillaCalificacionesEpq;
use App\Livewire\CalificacionesPrimario\PlanillaCalificacionesPrimario;

/**
 * Registro de variantes (`implementacion`) de módulos de calificaciones primario.
 *
 * @see docs/07-versionado-de-modulos-por-tenant.md
 */
final class CalificacionesPrimarioModulos
{
    public const CARGA_ESTUDIANTE = 'carga_estudiante';

    public const CARGA_MATERIA = 'carga_materia';

    public const PLANILLA = 'planilla';

    public const BOLETIN_PRIM = 'boletin_prim';

    /**
     * @return array<string, array<string, array<string, mixed>>>
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
                'epq' => [
                    'livewire' => CargaCalificacionesEpqIndex::class,
                    'livewire_form' => CargaCalificacionesEpqForm::class,
                    'livewire_info_adicional' => InfoAdicionalEpqForm::class,
                    'ruta_portal' => 'portalDocente.calificacionesPrimarioEpq.carga',
                    'ruta_portal_form' => 'portalDocente.calificacionesPrimarioEpq.carga.alumno',
                    'ruta_portal_info' => 'portalDocente.calificacionesPrimarioEpq.infoAdicional',
                    'ruta_staff' => 'calificacionesPrimarioEpq.carga',
                    'ruta_staff_form' => 'calificacionesPrimarioEpq.carga.alumno',
                    'ruta_staff_info' => 'calificacionesPrimarioEpq.infoAdicional',
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
                'epq' => [
                    'livewire' => PlanillaCalificacionesEpq::class,
                    'pdf_controller' => PlanillaCalificacionesEpqPdfController::class,
                    'ruta_portal' => 'portalDocente.calificacionesPrimarioEpq.planilla',
                    'ruta_portal_pdf' => 'portalDocente.calificacionesPrimarioEpq.planilla.pdf',
                    'ruta_staff' => 'calificacionesPrimarioEpq.planilla',
                    'ruta_staff_pdf' => 'calificacionesPrimarioEpq.planilla.pdf',
                ],
            ],
            self::BOLETIN_PRIM => [
                'epq' => [
                    'livewire' => BoletinPrimEpqIndex::class,
                    'pdf_controller' => BoletinPrimEpqPdfController::class,
                    'pdf_lote_controller' => BoletinPrimEpqLotePdfController::class,
                    'ruta_portal' => 'portalDocente.calificacionesPrimarioEpq.boletin',
                    'ruta_portal_pdf' => 'portalDocente.calificacionesPrimarioEpq.boletin.pdf',
                    'ruta_portal_pdf_lote' => 'portalDocente.calificacionesPrimarioEpq.boletin.pdfLote',
                    'ruta_staff' => 'calificacionesPrimarioEpq.boletin',
                    'ruta_staff_pdf' => 'calificacionesPrimarioEpq.boletin.pdf',
                    'ruta_staff_pdf_lote' => 'calificacionesPrimarioEpq.boletin.pdfLote',
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
            self::BOLETIN_PRIM,
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

    public static function abortSiImplementacionInactiva(string $modulo, string $implementacionEsperada): void
    {
        abort_unless(
            self::implementacionConfigurada($modulo) === $implementacionEsperada
            && isset(self::registro()[$modulo][$implementacionEsperada]),
            404,
        );
    }

    /** @return array<string, mixed>|null */
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
            'info' => $def['ruta_staff_info'] ?? null,
            'pdf' => $def['ruta_staff_pdf'] ?? null,
            'pdfLote' => $def['ruta_staff_pdf_lote'] ?? null,
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
            'info' => $def['ruta_portal_info'] ?? null,
            'pdf' => $def['ruta_portal_pdf'] ?? null,
            'pdfLote' => $def['ruta_portal_pdf_lote'] ?? null,
            default => $def['ruta_portal'],
        };

        abort_if($nombre === null || $nombre === '', 404);

        return $nombre;
    }

    /** @return list<string> */
    public static function rutasActivasPortal(string $modulo): array
    {
        $def = self::definicionActiva($modulo);
        if ($def === null) {
            return [];
        }

        $out = [];
        foreach ($def as $key => $nombre) {
            if (! is_string($nombre) || ! str_starts_with($key, 'ruta_portal')) {
                continue;
            }
            $out[] = $nombre;
        }

        return array_values(array_unique($out));
    }
}
