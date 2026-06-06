<?php

namespace App\Support\Listados;

/**
 * Filtro por idCondiciones (matricula) para el PDF listado por curso.
 * Valores de query/UI validados contra esta lista (sin input libre hacia SQL).
 */
final class ListadoCursoCondicionFiltro
{
    public const REGULARES = 'regulares';

    /** Condiciones 2, 3 y 4. */
    public const SALIDOS = 'salidos';

    /** Condiciones 1, 2, 3 y 4. */
    public const TODOS = 'todos';

    /** @return list<string> */
    public static function keys(): array
    {
        return [self::REGULARES, self::SALIDOS, self::TODOS];
    }

    public static function normalize(?string $value): string
    {
        $v = strtolower(trim((string) $value));

        return in_array($v, self::keys(), true) ? $v : self::REGULARES;
    }

    /** @return list<int> */
    public static function idCondicionesParaQuery(string $normalizado): array
    {
        return match ($normalizado) {
            self::SALIDOS => [2, 3, 4],
            self::TODOS => [1, 2, 3, 4],
            default => [1],
        };
    }

    /** Columna «Cond.» al final del PDF cuando hay más de una condición posible en el conjunto. */
    public static function forzarColumnaCondicionEnPdf(string $normalizado): bool
    {
        return in_array($normalizado, [self::SALIDOS, self::TODOS], true);
    }

    /** Texto del modo que se muestra bajo el título en el PDF de estudiantes. */
    public static function etiquetaModoEstudiantesPdf(string $normalizado): string
    {
        return match ($normalizado) {
            self::SALIDOS => 'SALIDOS',
            self::TODOS => 'TODAS LAS CONDICIONES',
            default => 'REGULARES',
        };
    }

    /** Etiqueta para UI (selector de condición y resumen de plantillas). */
    public static function etiquetaUi(string $normalizado): string
    {
        return match (self::normalize($normalizado)) {
            self::SALIDOS => 'Salidos',
            self::TODOS => 'Todas',
            default => 'Regulares',
        };
    }
}
