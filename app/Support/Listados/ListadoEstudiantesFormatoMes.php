<?php

namespace App\Support\Listados;

use Carbon\Carbon;

/**
 * Utilidades de mes para el modelo «Calendario».
 */
final class ListadoEstudiantesFormatoMes
{
    /** @var list<string> */
    private const NOMBRES = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    public static function normalizarMes(mixed $mes): int
    {
        $mes = (int) $mes;

        return ($mes >= 1 && $mes <= 12) ? $mes : 0;
    }

    public static function normalizarAno(mixed $ano): int
    {
        $ano = (int) $ano;

        return ($ano >= 1900 && $ano <= 2100) ? $ano : 0;
    }

    public static function nombreMes(int $mes): string
    {
        return self::NOMBRES[self::normalizarMes($mes)] ?? '';
    }

    /**
     * @return list<array{dia: int, esFinDeSemana: bool}>
     */
    public static function diasDelMes(int $ano, int $mes): array
    {
        $mes = self::normalizarMes($mes);
        $ano = self::normalizarAno($ano);
        if ($mes < 1 || $ano < 1) {
            return [];
        }

        $inicio = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
        $ultimo = (int) $inicio->daysInMonth;
        $dias = [];

        for ($dia = 1; $dia <= $ultimo; $dia++) {
            $fecha = Carbon::createFromDate($ano, $mes, $dia);
            $dow = (int) $fecha->dayOfWeekIso;
            $dias[] = [
                'dia' => $dia,
                'esFinDeSemana' => $dow >= 6,
            ];
        }

        return $dias;
    }

    /**
     * @return list<array{valor: int, etiqueta: string}>
     */
    public static function opcionesSelector(): array
    {
        $opciones = [];
        foreach (self::NOMBRES as $num => $nombre) {
            $opciones[] = [
                'valor' => $num,
                'etiqueta' => $nombre,
            ];
        }

        return $opciones;
    }
}
