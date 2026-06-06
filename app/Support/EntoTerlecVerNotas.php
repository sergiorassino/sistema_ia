<?php

namespace App\Support;

use App\Models\Ento;
use App\Models\Terlec;

/**
 * Ciclo lectivo autorizado para autogestión (docentes / alumnos) según `ento.idTerlecVerNotas`.
 */
final class EntoTerlecVerNotas
{
    public static function idParaNivel(int $idNivel): int
    {
        if ($idNivel < 1) {
            return 0;
        }

        return (int) (Ento::query()
            ->where('idNivel', $idNivel)
            ->value('idTerlecVerNotas') ?? 0);
    }

    public static function anoParaNivel(int $idNivel): ?int
    {
        $idTerlec = self::idParaNivel($idNivel);
        if ($idTerlec < 1) {
            return null;
        }

        $ano = (int) (Terlec::query()->whereKey($idTerlec)->value('ano') ?? 0);

        return $ano > 0 ? $ano : null;
    }

    public static function terlecPermitido(int $idNivel, int $idTerlecSeleccionado): bool
    {
        $idAutorizado = self::idParaNivel($idNivel);

        if ($idAutorizado < 1) {
            return false;
        }

        return $idTerlecSeleccionado === $idAutorizado;
    }

    public static function mensajeSoloAnoAutorizado(int $idNivel): string
    {
        $ano = self::anoParaNivel($idNivel);
        $anoTexto = $ano !== null ? (string) $ano : '—';

        return "La plataforma del docente está autorizada solo para el año lectivo {$anoTexto}";
    }
}
