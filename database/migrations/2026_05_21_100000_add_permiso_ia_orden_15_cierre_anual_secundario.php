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
            ['id' => 17],
            [
                'id' => 17,
                'orden' => 15,
                'tema' => 'CALIFICACIONES SECUNDARIO',
                'descripcion' => 'Cierre anual: historial de calificaciones y pasaje al matriz (Dic / Feb).',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 17)->delete();
        }
    }
};
