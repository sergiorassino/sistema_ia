<?php

namespace App\Support\Cuotas;

use App\Support\Alumnos\ArancelesEscolares;

/**
 * Formato de importes y fechas para el módulo de aranceles (administración).
 */
final class CuotasFormato
{
    public static function formatearImporte(float|int|string|null $valor): string
    {
        return ArancelesEscolares::formatearImporte($valor);
    }

    public static function formatearFecha(mixed $fecha): string
    {
        return ArancelesEscolares::formatearFecha($fecha);
    }

    public static function formatearFechaHora(mixed $fecha): string
    {
        if ($fecha instanceof \Carbon\CarbonInterface) {
            return $fecha->format('d/m/Y H:i:s');
        }

        $raw = trim((string) ($fecha ?? ''));
        if ($raw === '' || $raw === '0000-00-00') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($raw)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return $raw;
        }
    }

    public static function formatearDni(mixed $dni): string
    {
        return ArancelesEscolares::formatearDni($dni);
    }

    /**
     * Convierte texto con formato argentino (110.000,50) o punto decimal a float.
     */
    public static function parseImporte(?string $texto): float
    {
        $raw = trim((string) ($texto ?? ''));
        if ($raw === '') {
            return 0.0;
        }

        $raw = str_replace(['$', ' '], '', $raw);

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        return round((float) $raw, 2);
    }

    /**
     * Para inputs type="text" en Livewire (muestra con separador de miles).
     */
    public static function importeParaInput(float|int|string|null $valor): string
    {
        return self::formatearImporte($valor);
    }

    /**
     * Parte el nombre de nivel en dos líneas para la grilla (p. ej. NIVELPRIMAR → NIVEL / PRIMAR).
     *
     * @return array{0: string, 1: string}
     */
    public static function nivelEnDosLineas(string $nivel): array
    {
        $texto = mb_strtoupper(trim($nivel));
        if ($texto === '') {
            return ['', ''];
        }

        if (preg_match('/\s/u', $texto)) {
            $partes = preg_split('/\s+/u', $texto, 2) ?: [];

            return [trim((string) ($partes[0] ?? '')), trim((string) ($partes[1] ?? ''))];
        }

        foreach (['NIVEL', 'SECUNDARIO', 'SECUND', 'PRIMARIO', 'PRIMAR', 'MEDIO', 'INICIAL'] as $prefijo) {
            if (str_starts_with($texto, $prefijo)) {
                $resto = trim(mb_substr($texto, mb_strlen($prefijo)));

                return [$prefijo, $resto];
            }
        }

        $mitad = (int) ceil(mb_strlen($texto) / 2);

        return [mb_substr($texto, 0, $mitad), mb_substr($texto, $mitad)];
    }

    /**
     * Saldo pendiente (faltapa) en edición manual: importe + interés − pagado − bonificación.
     */
    public static function calcularFaltapa(
        float $importe,
        float $pagado,
        float $bonificacion = 0.0,
        float $interes = 0.0,
    ): float
    {
        return round(max(0, ($importe + $interes) - $pagado - $bonificacion), 2);
    }

    /**
     * Resalta en rojo las coincidencias del término de búsqueda (HTML escapado salvo el marcado).
     */
    public static function resaltarTerminoBusqueda(string $texto, string $termino): string
    {
        $textoEsc = e($texto);
        $termino = trim($termino);
        if ($termino === '') {
            return $textoEsc;
        }

        $palabras = preg_split('/\s+/u', $termino, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($palabras === []) {
            return $textoEsc;
        }

        $partes = array_map(
            static fn (string $p) => preg_quote($p, '/'),
            $palabras,
        );

        $reemplazado = preg_replace(
            '/('.implode('|', $partes).')/iu',
            '<mark class="rounded-sm bg-red-100 px-0.5 font-semibold text-red-700">$1</mark>',
            $textoEsc,
        );

        return $reemplazado ?? $textoEsc;
    }
}
