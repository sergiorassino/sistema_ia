<?php

namespace App\Support\Navegacion;

/**
 * Plantilla de cuota activa en el editor de importes por curso — sin ID en la URL.
 */
final class ContextoCuotasImportesSesion
{
    private const SESSION_KEY = 'contexto_cuotas_importes';

    private const TTL_MINUTES = 120;

    public static function fijar(int $idCuotas): void
    {
        if ($idCuotas <= 0) {
            self::limpiar();

            return;
        }

        session([
            self::SESSION_KEY => [
                'idCuotas' => $idCuotas,
                'expira' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
            ],
        ]);
    }

    public static function idCuotas(): ?int
    {
        $data = session(self::SESSION_KEY);
        if (! is_array($data)) {
            return null;
        }

        if ((int) ($data['expira'] ?? 0) < now()->timestamp) {
            self::limpiar();

            return null;
        }

        $id = (int) ($data['idCuotas'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function limpiar(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
