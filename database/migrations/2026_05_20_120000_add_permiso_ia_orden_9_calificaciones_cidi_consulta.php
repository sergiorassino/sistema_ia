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
            ['id' => 10],
            [
                'id' => 10,
                'orden' => 9,
                'tema' => 'CALIFICACIONES SECUNDARIO',
                'descripcion' => 'Importar calificaciones desde CIDI/GE y carga manual de calificaciones (secundario).',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 10)->delete();
        }
    }
};
