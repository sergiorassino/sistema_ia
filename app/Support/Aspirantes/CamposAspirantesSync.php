<?php

namespace App\Support\Aspirantes;

use App\Models\Aspirante;
use App\Models\CampoAspirante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinea la tabla campos_aspirantes con las columnas reales de la tabla legacy `aspirantes`.
 * Mismo patrón que CamposLegajoSync.
 */
final class CamposAspirantesSync
{
    /**
     * @return array{insertados: int, eliminados: int}
     */
    public function sincronizarDesdeSchema(): array
    {
        if (! Schema::hasTable('aspirantes') || ! Schema::hasTable('campos_aspirantes')) {
            return ['insertados' => 0, 'eliminados' => 0];
        }

        $columnasAspirantes = Aspirante::columnasDisponibles();
        if ($columnasAspirantes === []) {
            return ['insertados' => 0, 'eliminados' => 0];
        }

        return DB::transaction(function () use ($columnasAspirantes) {
            $eliminados = CampoAspirante::query()
                ->whereNotIn('columna', $columnasAspirantes)
                ->delete();

            $maxOrden   = (int) CampoAspirante::query()->max('orden');
            $insertados = 0;

            foreach ($columnasAspirantes as $nombre) {
                if (CampoAspirante::query()->where('columna', $nombre)->exists()) {
                    continue;
                }
                $maxOrden++;
                CampoAspirante::create([
                    'columna' => $nombre,
                    'orden'   => $maxOrden,
                ]);
                $insertados++;
            }

            return ['insertados' => $insertados, 'eliminados' => $eliminados];
        });
    }
}
