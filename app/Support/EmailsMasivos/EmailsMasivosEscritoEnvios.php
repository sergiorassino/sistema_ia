<?php

namespace App\Support\EmailsMasivos;

use App\Models\EmailEnviado;
use App\Models\EmailEscrito;
use Illuminate\Support\Collection;

final class EmailsMasivosEscritoEnvios
{
    public static function escritoTieneEnvios(EmailEscrito $escrito, int $idNivel): bool
    {
        return EmailEnviado::query()
            ->where('idNiveles', $idNivel)
            ->where('subject', $escrito->subject)
            ->where('texto', $escrito->text)
            ->where('attached', (string) ($escrito->attached ?? ''))
            ->exists();
    }

    /**
     * @param  Collection<int, EmailEscrito>  $escritos
     * @return list<int>
     */
    public static function idsConEnvios(Collection $escritos, int $idNivel): array
    {
        $ids = [];
        foreach ($escritos as $escrito) {
            if (self::escritoTieneEnvios($escrito, $idNivel)) {
                $ids[] = (int) $escrito->id;
            }
        }

        return $ids;
    }

    public static function seedEnAlcance(int $idSeed, int $idNivel): ?EmailEnviado
    {
        return EmailEnviado::query()
            ->where('id', $idSeed)
            ->where('idNiveles', $idNivel)
            ->first();
    }

    public static function eliminarCampana(EmailEnviado $seed, int $idNivel): int
    {
        return EmailEnviado::query()
            ->where('idNiveles', $idNivel)
            ->where('idTerlec', (int) $seed->idTerlec)
            ->where('idProfesores', (int) $seed->idProfesores)
            ->where('fechhora', $seed->fechhora)
            ->where('subject', $seed->subject)
            ->where('texto', $seed->texto)
            ->delete();
    }
}
