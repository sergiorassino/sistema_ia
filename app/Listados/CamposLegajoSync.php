<?php

namespace App\Listados;

use App\Models\CampoLegajo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CamposLegajoSync
{
    /**
     * Alinea `campos_legajo` con el esquema actual de `legajos`:
     * inserta columnas nuevas y elimina filas cuya columna ya no existe en la tabla.
     *
     * @return array{insertados: int, eliminados: int}
     */
    public function sincronizarDesdeSchema(): array
    {
        if (! Schema::hasTable('legajos') || ! Schema::hasTable('campos_legajo')) {
            return ['insertados' => 0, 'eliminados' => 0];
        }

        $columnasLegajos = Schema::getColumnListing('legajos');
        if ($columnasLegajos === []) {
            return ['insertados' => 0, 'eliminados' => 0];
        }

        return DB::transaction(function () use ($columnasLegajos) {
            $eliminados = CampoLegajo::query()
                ->whereNotIn('columna', $columnasLegajos)
                ->delete();

            $maxOrden   = (int) CampoLegajo::query()->max('orden');
            $insertados = 0;

            foreach ($columnasLegajos as $nombre) {
                if (in_array($nombre, CampoLegajo::COLUMNAS_EXCLUIDAS, true)
                    || in_array($nombre, CampoLegajo::COLUMNAS_FIJAS_ALUMNO, true)) {
                    continue;
                }
                if (CampoLegajo::query()->where('columna', $nombre)->exists()) {
                    continue;
                }

                $maxOrden++;
                CampoLegajo::create([
                    'columna'          => $nombre,
                    'etiqueta'         => null,
                    'visible_listado'  => true,
                    'orden'            => $maxOrden,
                    'solapa_legajo_id' => null,
                    'orden_en_solapa'  => 0,
                ]);
                $insertados++;
            }

            return ['insertados' => $insertados, 'eliminados' => $eliminados];
        });
    }
}
