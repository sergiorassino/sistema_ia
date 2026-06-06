<?php

namespace App\Support\MatrizAnaliticos;

use Illuminate\Support\Facades\DB;

/**
 * Calificación en letras para analítico (tabla enletras + códigos especiales legacy).
 */
final class CalificacionEnLetras
{
    /** @var array<string, string>|null */
    private static ?array $tabla = null;

    public static function resolver(string $calif): string
    {
        $c = mb_strtolower(trim($calif));
        if ($c === '') {
            return '';
        }

        $especial = match ($c) {
            'aprob' => 'APROBADO',
            'adeud' => 'ADEUDA',
            'elimi' => 'ELIMINADO',
            'excep' => 'EXCEPCIONAL',
            'apequ' => 'APROB. POR EQUIV.',
            'a-ams' => 'A-AMS',
            'a-as' => 'A-AS',
            default => null,
        };
        if ($especial !== null) {
            return $especial;
        }

        return self::tabla()[$c] ?? self::tabla()[$calif] ?? mb_strtoupper($calif, 'UTF-8');
    }

    /** @return array<string, string> */
    private static function tabla(): array
    {
        if (self::$tabla !== null) {
            return self::$tabla;
        }

        self::$tabla = [];
        if (! \Illuminate\Support\Facades\Schema::hasTable('enletras')) {
            return self::$tabla;
        }

        $rows = DB::table('enletras')->select(['nota', 'enLetras'])->get();
        foreach ($rows as $row) {
            $nota = trim((string) ($row->nota ?? ''));
            $letras = trim((string) ($row->enLetras ?? ''));
            if ($nota === '' || $letras === '') {
                continue;
            }
            self::$tabla[mb_strtolower($nota, 'UTF-8')] = $letras;
            self::$tabla[$nota] = $letras;
        }

        return self::$tabla;
    }
}
