<?php

namespace App\Support;

/**
 * Clases CSS de color por nivel pedagógico (chips en listados de matrícula).
 * Mismos criterios que en ABM Legajos de estudiantes.
 */
final class MatriculaNivelEstilo
{
    public const CHIP_INICIAL = 'se-mat-nivel-inicial';

    public const CHIP_PRIMARIO = 'se-mat-nivel-primario';

    public const CHIP_SECUNDARIO = 'se-mat-nivel-secundario';

    public const CHIP_DEFAULT = 'se-mat-nivel-default';

    public const CAL_INICIAL = 'se-cal-nivel-inicial';

    public const CAL_PRIMARIO = 'se-cal-nivel-primario';

    public const CAL_SECUNDARIO = 'se-cal-nivel-secundario';

    public const CAL_DEFAULT = 'se-cal-nivel-default';

    public const CAL_INSTITUCIONAL = 'se-cal-nivel-institucional';

    /**
     * Clase de color del texto en el calendario (mes, semana y día).
     * Actividad institucional en negro; el resto, el mismo color que el chip del legajo.
     */
    public static function claseCalendarioActividad(bool $institucional, ?string $nivelNombre): string
    {
        if ($institucional) {
            return self::CAL_INSTITUCIONAL;
        }

        return match (self::claseChipPorNombreNivel($nivelNombre)) {
            self::CHIP_INICIAL => self::CAL_INICIAL,
            self::CHIP_PRIMARIO => self::CAL_PRIMARIO,
            self::CHIP_SECUNDARIO => self::CAL_SECUNDARIO,
            default => self::CAL_DEFAULT,
        };
    }

    public static function claseChipPorNombreNivel(?string $nivelNombre): string
    {
        $nivelNombre = mb_strtolower(trim((string) $nivelNombre));

        return match (true) {
            str_contains($nivelNombre, 'inicial') => self::CHIP_INICIAL,
            str_contains($nivelNombre, 'primar') => self::CHIP_PRIMARIO,
            str_contains($nivelNombre, 'secund') => self::CHIP_SECUNDARIO,
            default => self::CHIP_DEFAULT,
        };
    }
}
