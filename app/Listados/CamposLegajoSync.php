<?php

namespace App\Listados;

use App\Models\CampoLegajo;
use App\Models\Legajo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CamposLegajoSync
{
    /**
     * Alinea `campos_legajo` con el esquema actual de `legajos` en la BD conectada:
     * inserta columnas nuevas, elimina obsoletas y renumerá `orden` según ORDINAL_POSITION.
     *
     * @return array{insertados: int, eliminados: int, orden_actualizado: int, base_datos: string, columnas_esquema: int}
     */
    public function sincronizarDesdeSchema(): array
    {
        $baseDatos = Legajo::nombreBaseDatosConectada();

        if (! Schema::hasTable('legajos') || ! Schema::hasTable('campos_legajo')) {
            return [
                'insertados' => 0,
                'eliminados' => 0,
                'orden_actualizado' => 0,
                'base_datos' => $baseDatos,
                'columnas_esquema' => 0,
            ];
        }

        $columnasLegajos = Legajo::columnasTabla();
        if ($columnasLegajos === []) {
            return [
                'insertados' => 0,
                'eliminados' => 0,
                'orden_actualizado' => 0,
                'base_datos' => $baseDatos,
                'columnas_esquema' => 0,
            ];
        }

        return DB::transaction(function () use ($columnasLegajos, $baseDatos) {
            $eliminados = CampoLegajo::query()
                ->whereNotIn('columna', $columnasLegajos)
                ->delete();

            $mapaOrden = CampoLegajo::mapaOrdenEsquemaLegajos();
            $insertados = 0;
            $ordenActualizado = 0;

            foreach ($columnasLegajos as $nombre) {
                if (in_array($nombre, CampoLegajo::COLUMNAS_EXCLUIDAS, true)
                    || in_array($nombre, CampoLegajo::COLUMNAS_FIJAS_ALUMNO, true)) {
                    continue;
                }

                $orden = $mapaOrden[$nombre] ?? 0;
                $campo = CampoLegajo::query()->where('columna', $nombre)->first();

                if ($campo === null) {
                    CampoLegajo::create([
                        'columna'          => $nombre,
                        'etiqueta'         => null,
                        'visible_listado'  => $nombre !== 'fotoCarnet',
                        'orden'            => $orden,
                        'solapa_legajo_id' => null,
                        'orden_en_solapa'  => 0,
                    ]);
                    $insertados++;

                    continue;
                }

                if ((int) $campo->orden !== $orden) {
                    $campo->orden = $orden;
                    $campo->save();
                    $ordenActualizado++;
                }
            }

            return [
                'insertados' => $insertados,
                'eliminados' => $eliminados,
                'orden_actualizado' => $ordenActualizado,
                'base_datos' => $baseDatos,
                'columnas_esquema' => count($columnasLegajos),
            ];
        });
    }
}
