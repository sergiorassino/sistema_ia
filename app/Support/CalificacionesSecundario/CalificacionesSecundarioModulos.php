<?php

namespace App\Support\CalificacionesSecundario;

use App\Http\Controllers\CalificacionesSecundario\BoletinEpqSecundarioLotePdfController;
use App\Http\Controllers\CalificacionesSecundario\BoletinEpqSecundarioPdfController;
use App\Http\Controllers\CalificacionesSecundario\CargaCalificacionesEpqSecundarioPdfController;
use App\Http\Controllers\CalificacionesSecundario\PlanillaCalificacionesEpqSecundarioPdfController;
use App\Http\Controllers\CalificacionesSecundario\PlanillaCalificacionesPdfController;
use App\Livewire\CalificacionesSecundario\CargaCalificacionesSecundario;
use App\Livewire\CalificacionesSecundario\Epq\BoletinEpqSecundarioIndex;
use App\Livewire\CalificacionesSecundario\Epq\CargaCalificacionesEpqSecundario;
use App\Livewire\CalificacionesSecundario\Epq\PlanillaCalificacionesEpqSecundario;
use App\Livewire\CalificacionesSecundario\PlanillaCalificacionesSecundario;

/**
 * Registro de variantes (`implementacion`) de módulos de calificaciones secundario.
 *
 * @see docs/07-versionado-de-modulos-por-tenant.md
 */
final class CalificacionesSecundarioModulos
{
    public const CARGA = 'carga';

    public const BOLETIN = 'boletin';

    public const PLANILLA = 'planilla';

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function registro(): array
    {
        return [
            self::CARGA => [
                'estandar' => [
                    'livewire' => CargaCalificacionesSecundario::class,
                    'ruta_portal' => 'portalDocente.calificaciones',
                    'ruta_portal_form' => 'portalDocente.calificaciones.carga',
                    'ruta_portal_pdf' => 'portalDocente.calificaciones.pdf',
                    'ruta_staff' => 'calificacionesSecundario.carga',
                ],
                'epq' => [
                    'livewire' => CargaCalificacionesEpqSecundario::class,
                    'ruta_portal' => 'portalDocente.calificaciones',
                    'ruta_portal_form' => 'portalDocente.calificacionesEpq.carga',
                    'ruta_portal_pdf' => 'portalDocente.calificacionesEpq.pdf',
                    'ruta_staff' => 'calificacionesSecundarioEpq.carga',
                    'ruta_staff_pdf' => 'calificacionesSecundarioEpq.carga.pdf',
                    'pdf_controller' => CargaCalificacionesEpqSecundarioPdfController::class,
                ],
            ],
            self::BOLETIN => [
                'epq' => [
                    'livewire' => BoletinEpqSecundarioIndex::class,
                    'pdf_controller' => BoletinEpqSecundarioPdfController::class,
                    'pdf_lote_controller' => BoletinEpqSecundarioLotePdfController::class,
                    'ruta_staff' => 'calificacionesSecundarioEpq.boletin',
                    'ruta_staff_pdf' => 'calificacionesSecundarioEpq.boletin.pdf',
                    'ruta_staff_pdf_lote' => 'calificacionesSecundarioEpq.boletin.pdfLote',
                ],
            ],
            self::PLANILLA => [
                'estandar' => [
                    'livewire' => PlanillaCalificacionesSecundario::class,
                    'pdf_controller' => PlanillaCalificacionesPdfController::class,
                    'ruta_staff' => 'calificacionesSecundario.planilla',
                    'ruta_staff_pdf' => 'calificacionesSecundario.planilla.pdf',
                ],
                'epq' => [
                    'livewire' => PlanillaCalificacionesEpqSecundario::class,
                    'pdf_controller' => PlanillaCalificacionesEpqSecundarioPdfController::class,
                    'ruta_staff' => 'calificacionesSecundarioEpq.planilla',
                    'ruta_staff_pdf' => 'calificacionesSecundarioEpq.planilla.pdf',
                    'ruta_portal' => 'portalDocente.calificacionesSecundarioEpq.planilla',
                    'ruta_portal_pdf' => 'portalDocente.calificacionesSecundarioEpq.planilla.pdf',
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function modulos(): array
    {
        return [self::CARGA, self::BOLETIN, self::PLANILLA];
    }

    public static function implementacionConfigurada(string $modulo): ?string
    {
        if (! in_array($modulo, self::modulos(), true)) {
            return null;
        }

        $valor = config("tenant.calificaciones_secundario.{$modulo}.implementacion");

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
            'pdf' => $def['ruta_portal_pdf'] ?? null,
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

    /**
     * Carga estándar (Eval/JIS). `null` en config equivale a la ruta histórica.
     * La variante EPQ no calcula promedio anual automático.
     */
    public static function cargaEsEstandar(): bool
    {
        $impl = self::implementacionConfigurada(self::CARGA);

        return $impl === null || $impl === 'estandar';
    }
}
