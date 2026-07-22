<?php

namespace App\Support\EmailsMasivos;

use App\Models\EmailEnviado;
use App\Models\EmailEscrito;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Collection;

final class EmailsMasivosEscritoEnvios
{
    public static function escritoTieneEnvios(EmailEscrito $escrito, ?int $idNivelIgnorado = null): bool
    {
        $query = EmailEnviado::query()
            ->where('subject', $escrito->subject)
            ->where('texto', $escrito->text)
            ->where('attached', (string) ($escrito->attached ?? ''));

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNiveles');

        return $query->exists();
    }

    /**
     * @param  Collection<int, EmailEscrito>  $escritos
     * @return list<int>
     */
    public static function idsConEnvios(Collection $escritos, ?int $idNivelIgnorado = null): array
    {
        if ($escritos->isEmpty()) {
            return [];
        }

        $query = EmailEnviado::query();
        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNiveles');

        $query->where(function ($outer) use ($escritos) {
            foreach ($escritos as $escrito) {
                $attached = (string) ($escrito->attached ?? '');
                $outer->orWhere(function ($q) use ($escrito, $attached) {
                    $q->where('subject', $escrito->subject)
                        ->where('texto', $escrito->text)
                        ->where('attached', $attached);
                });
            }
        });

        $clavesEnviados = $query
            ->select(['subject', 'texto', 'attached'])
            ->distinct()
            ->get()
            ->map(fn ($row) => self::claveEscrito(
                (string) $row->subject,
                (string) $row->texto,
                (string) ($row->attached ?? ''),
            ))
            ->flip()
            ->all();

        $ids = [];
        foreach ($escritos as $escrito) {
            $clave = self::claveEscrito(
                (string) $escrito->subject,
                (string) $escrito->text,
                (string) ($escrito->attached ?? ''),
            );
            if (isset($clavesEnviados[$clave])) {
                $ids[] = (int) $escrito->id;
            }
        }

        return $ids;
    }

    private static function claveEscrito(string $subject, string $text, string $attached): string
    {
        return md5($subject . "\0" . $text . "\0" . $attached);
    }

    public static function seedEnAlcance(int $idSeed, ?int $idNivelIgnorado = null): ?EmailEnviado
    {
        $query = EmailEnviado::query()->where('id', $idSeed);
        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNiveles');

        return $query->first();
    }

    public static function eliminarCampana(EmailEnviado $seed, ?int $idNivelIgnorado = null): int
    {
        $query = EmailEnviado::query()
            ->where('idTerlec', (int) $seed->idTerlec)
            ->where('idProfesores', (int) $seed->idProfesores)
            ->where('fechhora', $seed->fechhora)
            ->where('subject', $seed->subject)
            ->where('texto', $seed->texto);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNiveles');

        return $query->delete();
    }
}
