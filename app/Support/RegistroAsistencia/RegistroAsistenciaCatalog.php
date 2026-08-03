<?php

namespace App\Support\RegistroAsistencia;

/**
 * Variantes del Registro de Asistencia (PDF mensual).
 */
final class RegistroAsistenciaCatalog
{
    public const CON_DATOS = 'con_datos';

    public const SIN_DATOS = 'sin_datos';

    /** @return list<string> */
    public static function keys(): array
    {
        return [self::CON_DATOS, self::SIN_DATOS];
    }

    public static function normalize(?string $valor): string
    {
        $valor = strtolower(trim((string) $valor));

        return in_array($valor, self::keys(), true) ? $valor : self::CON_DATOS;
    }

    public static function esConDatos(?string $valor): bool
    {
        return self::normalize($valor) === self::CON_DATOS;
    }

    /** Default global: los tres niveles usan datos (faltas + estadísticas). */
    public static function defaultParaNivel(int $idNivel): string
    {
        return self::CON_DATOS;
    }

    public static function etiqueta(string $valor): string
    {
        return match (self::normalize($valor)) {
            self::CON_DATOS => 'Con datos (faltas y estadísticas)',
            default => 'Sin datos (llenado manual)',
        };
    }
}
