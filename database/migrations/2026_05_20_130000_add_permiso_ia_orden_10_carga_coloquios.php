<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')->updateOrInsert(
            ['id' => 11],
            [
                'id' => 11,
                'orden' => 10,
                'tema' => 'CALIFICACIONES SECUNDARIO',
                'descripcion' => 'Carga de coloquios Dic / Feb (secundario).',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 11)->delete();
        }
    }
};
