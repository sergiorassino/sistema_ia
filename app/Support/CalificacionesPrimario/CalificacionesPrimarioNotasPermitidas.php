<?php

namespace App\Support\CalificacionesPrimario;

use App\Support\NivelSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de notas permitidas por nivel y escala (`notaspermitidas.escala`).
 *
 * Escala 1: conceptos (E, MB, …) y guion «-».
 * Escala 2: literales (ML, L, EL, P, EP, PPI) y guion «-».
 * Celda vacía: válida en carga manual (borrar nota); no se persiste en `notaspermitidas`.
 */
final class CalificacionesPrimarioNotasPermitidas
{
    public const ESCALA_CONCEPTOS = 1;

    public const ESCALA_LITERALES = 2;

    public const NOTA_GUION = '-';

    /** @var array<string, array<int, list<string>>> */
    private static array $cache = [];

    public static function normalizarEscala(mixed $escala): int
    {
        return (int) $escala === self::ESCALA_LITERALES
            ? self::ESCALA_LITERALES
            : self::ESCALA_CONCEPTOS;
    }

    /**
     * @return array<int, list<string>>
     */
    public static function listasPorEscala(int $idNivel): array
    {
        $key = (string) $idNivel;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $vacias = [
            self::ESCALA_CONCEPTOS => [],
            self::ESCALA_LITERALES => [],
        ];

        if (! Schema::hasTable('notaspermitidas')) {
            $vacias = self::completarGuionEnPrimario($idNivel, $vacias);

            return self::$cache[$key] = $vacias;
        }

        $columnas = ['nota'];
        $tieneEscala = Schema::hasColumn('notaspermitidas', 'escala');
        if ($tieneEscala) {
            $columnas[] = 'escala';
        }

        $filas = DB::table('notaspermitidas')
            ->where('idNivel', $idNivel)
            ->orderBy('id')
            ->get($columnas);

        foreach ($filas as $fila) {
            $nota = trim((string) ($fila->nota ?? ''));
            if ($nota === '') {
                continue;
            }

            $escala = $tieneEscala
                ? self::normalizarEscala($fila->escala ?? self::ESCALA_CONCEPTOS)
                : self::ESCALA_CONCEPTOS;

            if (! in_array($nota, $vacias[$escala], true)) {
                $vacias[$escala][] = $nota;
            }
        }

        $vacias = self::completarGuionEnPrimario($idNivel, $vacias);

        return self::$cache[$key] = $vacias;
    }

    /**
     * El guion es válido en ambas escalas del primario (marcar sin calificación / corregir carga).
     *
     * @param  array<int, list<string>>  $porEscala
     * @return array<int, list<string>>
     */
    private static function completarGuionEnPrimario(int $idNivel, array $porEscala): array
    {
        if ($idNivel !== NivelSistema::PRIMARIO) {
            return $porEscala;
        }

        foreach ([self::ESCALA_CONCEPTOS, self::ESCALA_LITERALES] as $escala) {
            if (! in_array(self::NOTA_GUION, $porEscala[$escala], true)) {
                $porEscala[$escala][] = self::NOTA_GUION;
            }
        }

        return $porEscala;
    }

    /**
     * @return list<string>
     */
    public static function listaParaEscala(int $idNivel, int $escala): array
    {
        $porEscala = self::listasPorEscala($idNivel);

        return $porEscala[self::normalizarEscala($escala)] ?? [];
    }

    public static function catalogoActivoParaEscala(int $idNivel, int $escala): bool
    {
        return self::listaParaEscala($idNivel, $escala) !== [];
    }

    public static function notaPermitida(int $idNivel, int $escala, string $nota): bool
    {
        if ($nota === '') {
            return true;
        }

        $lista = self::listaParaEscala($idNivel, $escala);
        if ($lista === []) {
            return true;
        }

        return in_array($nota, $lista, true);
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }
}
