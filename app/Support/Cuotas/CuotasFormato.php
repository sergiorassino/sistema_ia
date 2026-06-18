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

    public static function esFechaVacia(mixed $fecha): bool
    {
        return ArancelesEscolares::esFechaVacia($fecha);
    }

    /**
     * Valor para input type="date" (Y-m-d); vacío si la fecha legacy no tiene valor.
     */
    public static function fechaParaInputDate(mixed $fecha): string
    {
        if (self::esFechaVacia($fecha)) {
            return '';
        }

        if ($fecha instanceof \Carbon\CarbonInterface) {
            return $fecha->format('Y-m-d');
        }

        return self::parseFechaOpcional((string) $fecha) ?? '';
    }

    /**
     * Fecha opcional ingresada en formularios (dd/mm/aaaa o aaaa-mm-dd) → Y-m-d para BD.
     * Vacío → null. Texto incompleto (p. ej. "18/06/202") → null sin error.
     */
    public static function parseFechaOpcional(?string $texto): ?string
    {
        $texto = trim((string) ($texto ?? ''));
        if ($texto === '' || $texto === '0000-00-00') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            try {
                $parsed = \Carbon\Carbon::parse($texto);

                return $parsed->year >= 1900 ? $parsed->format('Y-m-d') : null;
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $texto)) {
            try {
                $parsed = \Carbon\Carbon::createFromFormat('d/m/Y', $texto);

                return $parsed->year >= 1900 ? $parsed->format('Y-m-d') : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Texto con dígitos que no es una fecha completa válida (entrada a medias).
     */
    public static function esFechaTextoIncompleto(?string $texto): bool
    {
        $texto = trim((string) ($texto ?? ''));
        if ($texto === '') {
            return false;
        }

        return self::parseFechaOpcional($texto) === null && preg_match('/\d/', $texto) === 1;
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
