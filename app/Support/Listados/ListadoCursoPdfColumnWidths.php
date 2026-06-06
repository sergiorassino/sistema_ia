<?php

namespace App\Support\Listados;

/**
 * Anchos de columnas para el PDF de listado por curso (% y mm).
 */
final class ListadoCursoPdfColumnWidths
{
    private const PCT_NUM_POCAS = 4.0;

    private const PCT_NUM_NORMAL = 3.0;

    private const PCT_NUM_MUCHAS = 2.5;

    /**
     * @param  list<string>  $keys  claves del catálogo (sin columna Nº)
     * @return array{num: float, columns: array<string, float>}
     */
    public static function paraKeys(array $keys): array
    {
        $count = count($keys);
        $pctNum = match (true) {
            $count <= 5 => self::PCT_NUM_POCAS,
            $count <= 12 => self::PCT_NUM_NORMAL,
            default => self::PCT_NUM_MUCHAS,
        };

        $resto = 100.0 - $pctNum;
        if ($count === 0) {
            return ['num' => 100.0, 'columns' => []];
        }

        $weights = [];
        $sum = 0;
        foreach ($keys as $key) {
            $w = self::pesoColumna($key);
            $weights[$key] = $w;
            $sum += $w;
        }

        $columns = [];
        $asignado = 0.0;
        $lastKey = $keys[array_key_last($keys)];
        foreach ($keys as $key) {
            if ($key === $lastKey) {
                $columns[$key] = round($resto - $asignado, 4);

                continue;
            }
            $pct = round($resto * ($weights[$key] / $sum), 4);
            $columns[$key] = $pct;
            $asignado += $pct;
        }

        return ['num' => $pctNum, 'columns' => $columns];
    }

    /**
     * @param  list<string>  $keys
     * @return list<float> [Nº, col₁, col₂, …] en mm; la suma coincide con $anchoUtilMm
     */
    public static function anchosMm(float $anchoUtilMm, array $keys): array
    {
        $pct = self::paraKeys($keys);
        $anchos = [round($anchoUtilMm * $pct['num'] / 100, 2)];

        foreach ($keys as $key) {
            $p = (float) ($pct['columns'][$key] ?? 0);
            $anchos[] = round($anchoUtilMm * $p / 100, 2);
        }

        $diff = round($anchoUtilMm - array_sum($anchos), 2);
        if ($diff !== 0.0 && $anchos !== []) {
            $anchos[count($anchos) - 1] += $diff;
        }

        return $anchos;
    }

    private static function pesoColumna(string $key): int
    {
        if ($key === ListadoCursoPdfFieldCatalog::KEY_APELLIDO_NOMBRE) {
            return 3;
        }

        if ($key === 'condiciones.condicion') {
            return 1;
        }

        $col = str_contains($key, '.') ? substr($key, strrpos($key, '.') + 1) : $key;

        static $estrechas = [
            'dni', 'dnimad', 'dnipad', 'dnitut', 'cuil',
            'telefono', 'telemad', 'telepad', 'teletut', 'telltm', 'telltp', 'telltt',
            'codpos', 'cpmad', 'cppad', 'sexo', 'legajo', 'libro', 'folio', 'codigo', 'identif',
            'vivemad', 'vivepad', 'bloqmatr', 'bloqadmi', 'idnivel', 'idFamilias',
            'fechnaci', 'fechnacmad', 'fechnacpad',
            'conducta1', 'conducta2', 'acept1', 'acept2', 'acept3', 'acept4', 'inscripto',
            'nroMatricula', 'fechaMatricula',
        ];
        if (in_array($col, $estrechas, true)) {
            return 1;
        }

        static $anchas = [
            'callenum', 'email', 'emailmad', 'emailpad', 'emailtut',
            'domimad', 'domipad', 'contacto1', 'contacto2', 'contacto3',
            'obs', 'obs_web', 'obsMatr', 'obsAnual',
            'needes_detalle', 'motivo_detalle', 'acopro_detalle',
            'escori', 'destino', 'emeravis', 'retira', 'retira1', 'retira2',
            'parroquia', 'hermanos', 'vivecon', 'ec_padres', 'acopro',
            'reglamApenom', 'reglamDni', 'reglamEmail', 'fechhora', 'fechActDatos',
            'ln_ciudad', 'ln_depto', 'ln_provincia', 'ln_pais',
            'lugtramad', 'lugtrapad', 'lugtratut',
        ];
        if (in_array($col, $anchas, true)) {
            return 3;
        }

        return 2;
    }
}
