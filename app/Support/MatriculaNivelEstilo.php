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
