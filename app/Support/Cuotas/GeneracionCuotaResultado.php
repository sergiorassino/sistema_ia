<?php

namespace App\Support\Cuotas;

/**
 * Resultado de intentar generar un registro en cuotasgeneradas.
 */
final class GeneracionCuotaResultado
{
    private function __construct(
        public readonly bool $exito,
        public readonly string $mensaje,
        public readonly ?int $idCuotaGenerada = null,
    ) {}

    public static function exito(string $mensaje, int $idCuotaGenerada): self
    {
        return new self(true, $mensaje, $idCuotaGenerada);
    }

    public static function fallo(string $mensaje): self
    {
        return new self(false, $mensaje, null);
    }
}
