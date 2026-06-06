<?php

namespace App\Support\Cuotas;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Operadores mayor / menor / igual para filtros numéricos en consultas.
 */
final class FiltroComparacionNumerica
{
    public const OP_GT = 'gt';

    public const OP_LT = 'lt';

    public const OP_EQ = 'eq';

    /** @return array<string, string> */
    public static function opcionesEtiquetas(): array
    {
        return [
            '' => '—',
            self::OP_GT => 'Mayor que',
            self::OP_LT => 'Menor que',
            self::OP_EQ => 'Igual a',
        ];
    }

    /** @return list<string> */
    public static function operadoresPermitidos(): array
    {
        return [self::OP_GT, self::OP_LT, self::OP_EQ];
    }

    public static function normalizarOperador(mixed $valor): string
    {
        $op = trim((string) ($valor ?? ''));

        return in_array($op, self::operadoresPermitidos(), true) ? $op : '';
    }

    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function aplicar($query, string $columna, string $operador, ?float $valor): void
    {
        $operador = self::normalizarOperador($operador);
        if ($operador === '' || $valor === null) {
            return;
        }

        match ($operador) {
            self::OP_GT => $query->where($columna, '>', $valor),
            self::OP_LT => $query->where($columna, '<', $valor),
            self::OP_EQ => $query->where($columna, '=', $valor),
            default => null,
        };
    }

    public static function etiquetaFiltro(string $etiquetaCampo, string $operador, ?float $valor): string
    {
        $operador = self::normalizarOperador($operador);
        if ($operador === '' || $valor === null) {
            return '';
        }

        $simbolo = match ($operador) {
            self::OP_GT => '>',
            self::OP_LT => '<',
            default => '=',
        };

        return $etiquetaCampo.' '.$simbolo.' '.CuotasFormato::formatearImporte($valor);
    }
}
