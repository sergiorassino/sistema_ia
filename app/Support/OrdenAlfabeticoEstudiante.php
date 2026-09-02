<?php

namespace App\Support;

use App\Models\Matricula;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Orden alfabético de estudiantes alineado a collation española.
 *
 * - Á/É/Í/Ó/Ú/Ü equivalen a A/E/I/O/U (Cáceres con las C, no después de Corzo).
 * - Ñ es letra propia, después de N y antes de O.
 * - Desempate: nombre, luego id de matrícula si está disponible.
 *
 * SQL: CONVERT(... USING utf8) COLLATE utf8_spanish_ci (independiente del charset de la columna).
 * PHP: la misma regla, para listados que ordenan en memoria.
 */
final class OrdenAlfabeticoEstudiante
{
    /**
     * Expresión ORDER BY segura (solo identificadores table.column).
     */
    public static function sql(string $columnaCalificada): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $columnaCalificada)) {
            throw new InvalidArgumentException('Columna inválida para orden alfabético.');
        }

        return 'CONVERT('.$columnaCalificada.' USING utf8) COLLATE utf8_spanish_ci';
    }

    /**
     * @param  EloquentBuilder|QueryBuilder|Relation  $query
     * @return EloquentBuilder|QueryBuilder|Relation
     */
    public static function orderBy(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $apellidoCol,
        string $nombreCol,
    ): EloquentBuilder|QueryBuilder|Relation {
        return $query
            ->orderByRaw(self::sql($apellidoCol))
            ->orderByRaw(self::sql($nombreCol));
    }

    public static function comparar(string $apellidoA, string $nombreA, string $apellidoB, string $nombreB): int
    {
        return self::clave($apellidoA, $nombreA) <=> self::clave($apellidoB, $nombreB);
    }

    public static function clave(string $apellido, string $nombre): string
    {
        return self::plegar($apellido)."\x01".self::plegar($nombre);
    }

    /**
     * @param  Collection<int, Matricula>  $matriculas
     * @return Collection<int, Matricula>
     */
    public static function ordenarMatriculas(Collection $matriculas): Collection
    {
        return $matriculas
            ->sort(function (Matricula $a, Matricula $b): int {
                $cmp = self::comparar(
                    (string) ($a->legajo?->apellido ?? ''),
                    (string) ($a->legajo?->nombre ?? ''),
                    (string) ($b->legajo?->apellido ?? ''),
                    (string) ($b->legajo?->nombre ?? ''),
                );
                if ($cmp !== 0) {
                    return $cmp;
                }

                return ((int) $a->id) <=> ((int) $b->id);
            })
            ->values();
    }

    /**
     * @param  Collection<int, object|array<string, mixed>>  $filas
     * @return Collection<int, object|array<string, mixed>>
     */
    public static function ordenarFilas(Collection $filas, string $keyApellido = 'apellido', string $keyNombre = 'nombre'): Collection
    {
        return $filas
            ->sort(function (mixed $a, mixed $b) use ($keyApellido, $keyNombre): int {
                return self::comparar(
                    self::valorCampo($a, $keyApellido),
                    self::valorCampo($a, $keyNombre),
                    self::valorCampo($b, $keyApellido),
                    self::valorCampo($b, $keyNombre),
                );
            })
            ->values();
    }

    /**
     * @param  list<int>  $ids
     * @param  Collection<int, Matricula>  $matriculasPorId
     * @return list<int>
     */
    public static function ordenarIdsPorLegajo(array $ids, Collection $matriculasPorId): array
    {
        usort($ids, function (int $a, int $b) use ($matriculasPorId): int {
            $ma = $matriculasPorId->get($a);
            $mb = $matriculasPorId->get($b);
            $cmp = self::comparar(
                (string) ($ma?->legajo?->apellido ?? ''),
                (string) ($ma?->legajo?->nombre ?? ''),
                (string) ($mb?->legajo?->apellido ?? ''),
                (string) ($mb?->legajo?->nombre ?? ''),
            );
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a <=> $b;
        });

        return array_values($ids);
    }

    private static function plegar(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');
        if ($t === '') {
            return '';
        }

        $t = strtr($t, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ç' => 'c',
        ]);

        $out = '';
        $len = mb_strlen($t, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($t, $i, 1, 'UTF-8');
            if ($ch === 'ñ') {
                $out .= "n\xFF";
            } else {
                $out .= $ch."\x00";
            }
        }

        return $out;
    }

    private static function valorCampo(mixed $fila, string $key): string
    {
        if (is_array($fila)) {
            return (string) ($fila[$key] ?? '');
        }

        if (is_object($fila)) {
            return (string) ($fila->{$key} ?? '');
        }

        return '';
    }
}
