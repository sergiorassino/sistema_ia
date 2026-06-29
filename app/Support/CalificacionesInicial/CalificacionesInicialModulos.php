<?php

namespace App\Support\CalificacionesInicial;

use App\Http\Controllers\CalificacionesInicial\BoletinInicialSfqLotePdfController;
use App\Http\Controllers\CalificacionesInicial\BoletinInicialSfqPdfController;
use App\Livewire\CalificacionesInicial\Sfq\BoletinInicialSfqIndex;
use App\Livewire\CalificacionesInicial\Sfq\CargaCalificacionesInicialSfqIndex;
use App\Livewire\CalificacionesInicial\Sfq\CargaCalificacionesInicialSfqIndicadoresForm;
use App\Livewire\CalificacionesInicial\Sfq\CargaCalificacionesInicialSfqObservacionesForm;

/**
 * Registro de variantes (`implementacion`) de módulos de calificaciones inicial.
 *
 * @see docs/07-versionado-de-modulos-por-tenant.md
 */
final class CalificacionesInicialModulos
{
    public const CARGA_NOTAS = 'carga_notas';

    public const BOLETIN = 'boletin';

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function registro(): array
    {
        return [
            self::CARGA_NOTAS => [
                'sfq' => [
                    'livewire' => CargaCalificacionesInicialSfqIndex::class,
                    'livewire_indicadores' => CargaCalificacionesInicialSfqIndicadoresForm::class,
                    'livewire_observaciones' => CargaCalificacionesInicialSfqObservacionesForm::class,
                    'ruta_portal' => 'portalDocente.calificacionesInicialSfq.carga',
                    'ruta_portal_indicadores' => 'portalDocente.calificacionesInicialSfq.carga.indicadores',
                    'ruta_portal_observaciones' => 'portalDocente.calificacionesInicialSfq.carga.observaciones',
                    'ruta_staff' => 'calificacionesInicialSfq.carga',
                    'ruta_staff_indicadores' => 'calificacionesInicialSfq.carga.indicadores',
                    'ruta_staff_observaciones' => 'calificacionesInicialSfq.carga.observaciones',
                ],
            ],
            self::BOLETIN => [
                'sfq' => [
                    'livewire' => BoletinInicialSfqIndex::class,
                    'pdf_controller' => BoletinInicialSfqPdfController::class,
                    'pdf_lote_controller' => BoletinInicialSfqLotePdfController::class,
                    'ruta_portal' => 'portalDocente.calificacionesInicialSfq.boletin',
                    'ruta_portal_pdf' => 'portalDocente.calificacionesInicialSfq.boletin.pdf',
                    'ruta_portal_pdf_lote' => 'portalDocente.calificacionesInicialSfq.boletin.pdfLote',
                    'ruta_staff' => 'calificacionesInicialSfq.boletin',
                    'ruta_staff_pdf' => 'calificacionesInicialSfq.boletin.pdf',
                    'ruta_staff_pdf_lote' => 'calificacionesInicialSfq.boletin.pdfLote',
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function modulos(): array
    {
        return [
            self::CARGA_NOTAS,
            self::BOLETIN,
        ];
    }

    public static function implementacionConfigurada(string $modulo): ?string
    {
        if (! in_array($modulo, self::modulos(), true)) {
            return null;
        }

        $valor = config("tenant.calificaciones_inicial.{$modulo}.implementacion");

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
            'indicadores' => $def['ruta_staff_indicadores'] ?? null,
            'observaciones' => $def['ruta_staff_observaciones'] ?? null,
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
            'indicadores' => $def['ruta_portal_indicadores'] ?? null,
            'observaciones' => $def['ruta_portal_observaciones'] ?? null,
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
