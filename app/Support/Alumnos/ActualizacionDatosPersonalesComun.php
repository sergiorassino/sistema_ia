<?php

namespace App\Support\Alumnos;

use App\Models\Legajo;
use App\Models\Matricula;
use App\Support\InformeInasistencias;
use Illuminate\Support\Facades\Schema;

/**
 * Utilidades compartidas entre variantes de actualización de datos personales.
 */
final class ActualizacionDatosPersonalesComun
{
    /**
     * @return array{legajo: Legajo, matricula: Matricula}|null
     */
    public static function contexto(): ?array
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $matricula = InformeInasistencias::matriculaAutogestion();
        if ($matricula === null) {
            return null;
        }

        $legajo = Legajo::query()->where('id', (int) $ctx->idLegajo)->first();
        if ($legajo === null) {
            return null;
        }

        return ['legajo' => $legajo, 'matricula' => $matricula];
    }

    public static function estaBloqueado(Legajo $legajo): bool
    {
        return (bool) ($legajo->bloqmatr ?? false) || (bool) ($legajo->bloqadmi ?? false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function persistirLegajo(Legajo $legajo, array $data): void
    {
        $id = (int) $legajo->id;
        if ($id < 1) {
            throw new \InvalidArgumentException('Legajo inválido.');
        }

        $data = self::filtrarColumnasLegajoActualizables($data);

        if (! Legajo::query()->where('id', $id)->exists()) {
            throw new \RuntimeException('No se encontró el legajo a actualizar.');
        }

        Legajo::query()->where('id', $id)->update($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function filtrarColumnasLegajoActualizables(array $data): array
    {
        if (! Schema::hasColumn('legajos', 'fechActDatos')) {
            unset($data['fechActDatos']);
        }

        return $data;
    }

    /**
     * Limpia espacios y caracteres invisibles frecuentes al copiar/pegar (p. ej. NBSP).
     */
    public static function normalizarEmailInput(mixed $value): string
    {
        $v = (string) $value;
        $v = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{FEFF}]/u', '', $v) ?? $v;

        return trim($v);
    }

    /**
     * E-mail obligatorio, guión (-) si no corresponde, o vacío si es opcional.
     */
    public static function emailInputAceptado(mixed $value, bool $opcional): bool
    {
        $v = self::normalizarEmailInput($value);
        if ($opcional && $v === '') {
            return true;
        }
        if ($v === '-') {
            return true;
        }
        if ($v === '') {
            return false;
        }

        return filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
    }
}
