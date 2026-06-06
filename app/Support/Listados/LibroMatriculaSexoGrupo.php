<?php

namespace App\Support\Listados;

use App\Models\Sexo;

/**
 * Agrupación de sexo para totales del Libro de Matrícula (varones / mujeres / otros).
 */
final class LibroMatriculaSexoGrupo
{
    public const VARON = 'varon';

    public const MUJER = 'mujer';

    public const OTRO = 'otro';

    public static function clasificar(mixed $valorSexoAlmacenado): string
    {
        $etiqueta = Sexo::etiquetaParaValorAlmacenado($valorSexoAlmacenado);
        if ($etiqueta === '') {
            return self::OTRO;
        }

        $norm = self::normalizarTexto($etiqueta);

        if (self::coincideVaron($norm)) {
            return self::VARON;
        }

        if (self::coincideMujer($norm)) {
            return self::MUJER;
        }

        return self::OTRO;
    }

    private static function normalizarTexto(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');
        $t = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü'], ['a', 'e', 'i', 'o', 'u', 'u'], $t);

        return $t;
    }

    private static function coincideVaron(string $norm): bool
    {
        foreach (['masculino', 'varon', 'hombre', 'masc'] as $token) {
            if (str_contains($norm, $token)) {
                return true;
            }
        }

        return $norm === 'm';
    }

    private static function coincideMujer(string $norm): bool
    {
        foreach (['femenino', 'mujer', 'fem'] as $token) {
            if (str_contains($norm, $token)) {
                return true;
            }
        }

        return $norm === 'f';
    }
}
