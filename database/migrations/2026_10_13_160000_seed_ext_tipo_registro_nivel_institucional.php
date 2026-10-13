<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Actividad de Nivel y Actividad Institucional en ext_tipo_registro.
 * Equivalente SQL: database/sql/seed_ext_tipo_registro_nivel_institucional.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ext_tipo_registro')) {
            return;
        }

        $filas = [
            2 => 'Actividad de Nivel',
            3 => 'Actividad Institucional',
        ];

        foreach ($filas as $id => $nombre) {
            $existe = DB::table('ext_tipo_registro')->where('id', $id)->exists();
            if ($existe) {
                DB::table('ext_tipo_registro')->where('id', $id)->update(['nombre' => $nombre]);

                continue;
            }
            DB::table('ext_tipo_registro')->insert([
                'id' => $id,
                'nombre' => $nombre,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ext_tipo_registro')) {
            return;
        }

        if (Schema::hasTable('ext_actividades')) {
            $enUso = DB::table('ext_actividades')->whereIn('id_tipo_registro', [2, 3])->exists();
            if ($enUso) {
                return;
            }
        }

        DB::table('ext_tipo_registro')->whereIn('id', [2, 3])->delete();
    }
};
