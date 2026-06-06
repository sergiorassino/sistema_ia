<?php

namespace App\Support\Aspirantes;

/**
 * Opciones de un campo parametrizado: texto con valores separados por #.
 * Si hay al menos una opción, el formulario público muestra un &lt;select&gt;.
 */
final class CampoAspiranteOpciones
{
    /**
     * @return list<string>
     */
    public static function parse(?string $opciones): array
    {
        if ($opciones === null || trim($opciones) === '') {
            return [];
        }

        $out = [];
        foreach (explode('#', $opciones) as $parte) {
            $t = trim($parte);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return array_values(array_unique($out));
    }

    public static function normalizarEntrada(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $t = trim($valor);

        return $t === '' ? null : mb_substr($t, 0, 500);
    }
}
