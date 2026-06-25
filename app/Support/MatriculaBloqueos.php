<?php

namespace App\Support;

use App\Models\CuotaGenerada;
use App\Models\Matricula;

/**
 * Bloqueos pedagógico y administrativo por matrícula (ciclo lectivo).
 */
final class MatriculaBloqueos
{
    public static function bloqmatr(?Matricula $matricula): bool
    {
        return (bool) ($matricula?->bloqmatr ?? false);
    }

    public static function bloqadmi(?Matricula $matricula): bool
    {
        return (bool) ($matricula?->bloqadmi ?? false);
    }

    public static function estaBloqueado(?Matricula $matricula): bool
    {
        return self::bloqmatr($matricula) || self::bloqadmi($matricula);
    }

    /**
     * Matrícula del ciclo de la cuota generada (por idMatricula o legajo + idTerlec).
     */
    public static function paraCuotaGenerada(CuotaGenerada $registro): ?Matricula
    {
        if ($registro->relationLoaded('matricula')) {
            return $registro->matricula;
        }

        $idMatricula = (int) ($registro->idMatricula ?? 0);
        if ($idMatricula > 0) {
            return Matricula::query()->find($idMatricula);
        }

        $idLegajo = (int) ($registro->idLegajos ?? 0);
        $idTerlec = (int) ($registro->idTerlec ?? 0);
        if ($idLegajo < 1 || $idTerlec < 1) {
            return null;
        }

        return Matricula::query()
            ->where('idLegajos', $idLegajo)
            ->where('idTerlec', $idTerlec)
            ->first();
    }
}
