<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El tipo 1 pasa a llamarse Viaje Educativo.
 * En tipos 2 y 3 se vacían lugar y descripción (no son viajes).
 * Equivalente SQL: database/sql/rename_ext_tipo_registro_viaje_educativo.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ext_tipo_registro')) {
            return;
        }

        $existe = DB::table('ext_tipo_registro')->where('id', 1)->exists();
        if ($existe) {
            DB::table('ext_tipo_registro')->where('id', 1)->update([
                'nombre' => 'Viaje Educativo',
            ]);
        } else {
            DB::table('ext_tipo_registro')->insert([
                'id' => 1,
                'nombre' => 'Viaje Educativo',
            ]);
        }

        if (! Schema::hasTable('ext_actividades')) {
            return;
        }

        DB::table('ext_actividades')
            ->whereIn('id_tipo_registro', [2, 3])
            ->update([
                'lugar' => null,
                'descripcion' => null,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ext_tipo_registro')) {
            return;
        }

        DB::table('ext_tipo_registro')->where('id', 1)->update([
            'nombre' => 'Actividad Extraprogramática',
        ]);
    }
};
