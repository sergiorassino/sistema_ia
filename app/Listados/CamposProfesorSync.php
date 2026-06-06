<?php

namespace App\Listados;

use App\Models\CampoProfesor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CamposProfesorSync
{
    /**
     * @return array{insertados: int, eliminados: int}
     */
    public function sincronizarDesdeSchema(): array
    {
        if (! Schema::hasTable('profesores') || ! Schema::hasTable('campos_profesores')) {
            return ['insertados' => 0, 'eliminados' => 0];
        }

        $columnas = Schema::getColumnListing('profesores');
        if ($columnas === []) {
            return ['insertados' => 0, 'eliminados' => 0];
        }

        return DB::transaction(function () use ($columnas) {
            $eliminados = CampoProfesor::query()
                ->whereNotIn('columna', $columnas)
                ->delete();

            $maxOrden = (int) CampoProfesor::query()->max('orden');
            $insertados = 0;

            foreach ($columnas as $nombre) {
                if (in_array($nombre, CampoProfesor::COLUMNAS_EXCLUIDAS, true)
                    || in_array($nombre, CampoProfesor::COLUMNAS_FIJAS_DOCENTE, true)) {
                    continue;
                }
                if (CampoProfesor::query()->where('columna', $nombre)->exists()) {
                    continue;
                }

                $maxOrden++;
                CampoProfesor::create([
                    'columna' => $nombre,
                    'etiqueta' => null,
                    'visible_listado' => true,
                    'orden' => $maxOrden,
                    'solapa_legajo_profesor_id' => null,
                    'orden_en_solapa' => 0,
                ]);
                $insertados++;
            }

            return ['insertados' => $insertados, 'eliminados' => $eliminados];
        });
    }
}
