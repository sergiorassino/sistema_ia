<?php

namespace App\Support\Listados;

/**
 * Tamaño de foto carnet para el modelo «Listado de fotos» (lado en cm → mm).
 */
final class ListadoEstudiantesFormatoTamanoFoto
{
    public const PEQUENO = 'pequeno';

    public const MEDIANO = 'mediano';

    public const GRANDE = 'grande';

    /** @return list<string> */
    public static function keys(): array
    {
        return [
            self::PEQUENO,
            self::MEDIANO,
            self::GRANDE,
        ];
    }

    public static function normalize(?string $tamano): string
    {
        $tamano = trim((string) $tamano);

        return in_array($tamano, self::keys(), true)
            ? $tamano
            : self::MEDIANO;
    }

    public static function ladoMm(string $tamano): float
    {
        return match (self::normalize($tamano)) {
            self::PEQUENO => 20.0,
            self::GRANDE => 80.0,
            default => 40.0,
        };
    }

    public static function etiqueta(string $tamano): string
    {
        return match (self::normalize($tamano)) {
            self::PEQUENO => 'Pequeño (2×2 cm)',
            self::GRANDE => 'Grande (8×8 cm)',
            default => 'Mediano (4×4 cm)',
        };
    }

    /**
     * @return list<array{key: string, label: string, descripcion: string}>
     */
    public static function paraUi(): array
    {
        return [
            [
                'key' => self::PEQUENO,
                'label' => self::etiqueta(self::PEQUENO),
                'descripcion' => 'Más fotos por página. Útil para reconocer al grupo completo.',
            ],
            [
                'key' => self::MEDIANO,
                'label' => self::etiqueta(self::MEDIANO),
                'descripcion' => 'Tamaño carnet. Equilibrio entre cantidad y detalle.',
            ],
            [
                'key' => self::GRANDE,
                'label' => self::etiqueta(self::GRANDE),
                'descripcion' => 'Pocas fotos por página, más legibles para identificar.',
            ],
        ];
    }
}
