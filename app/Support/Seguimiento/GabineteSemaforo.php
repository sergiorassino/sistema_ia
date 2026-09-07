<?php

namespace App\Support\Seguimiento;

/**
 * Semáforo de seguimiento de gabinete (marca por legajo).
 *
 * 0 = sin marca; 1 = verde; 2 = amarillo; 3 = rojo.
 */
final class GabineteSemaforo
{
    public const NINGUNO = 0;

    public const VERDE = 1;

    public const AMARILLO = 2;

    public const ROJO = 3;

    /** @return list<int> */
    public static function colores(): array
    {
        return [self::VERDE, self::AMARILLO, self::ROJO];
    }

    public static function esValido(int $color): bool
    {
        return in_array($color, self::colores(), true);
    }

    public static function normalizar(mixed $valor): int
    {
        $n = (int) $valor;

        return self::esValido($n) ? $n : self::NINGUNO;
    }

    public static function etiqueta(int $color): string
    {
        return match (self::normalizar($color)) {
            self::VERDE => 'Verde',
            self::AMARILLO => 'Amarillo',
            self::ROJO => 'Rojo',
            default => 'Sin marca',
        };
    }

    public static function claseFondoNombre(int $color): string
    {
        return match (self::normalizar($color)) {
            self::VERDE => 'se-gabinete-nombre--verde',
            self::AMARILLO => 'se-gabinete-nombre--amarillo',
            self::ROJO => 'se-gabinete-nombre--rojo',
            default => '',
        };
    }

    /**
     * RGB de fondo para PDF (más saturado que un pastel, en especial el amarillo).
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgbFondoPdf(int $color): array
    {
        return match (self::normalizar($color)) {
            self::VERDE => [102, 187, 106],
            self::AMARILLO => [255, 202, 40],
            self::ROJO => [239, 154, 154],
            default => [255, 255, 255],
        };
    }

    /**
     * Filtro de listado: vacío = todos; verde/amarillo/rojo; sin = sin marca.
     */
    public static function normalizarFiltro(mixed $valor): string
    {
        $v = strtolower(trim((string) $valor));

        return in_array($v, ['verde', 'amarillo', 'rojo', 'sin'], true) ? $v : '';
    }

    public static function colorDesdeFiltro(string $filtro): int
    {
        return match (self::normalizarFiltro($filtro)) {
            'verde' => self::VERDE,
            'amarillo' => self::AMARILLO,
            'rojo' => self::ROJO,
            default => self::NINGUNO,
        };
    }
}
