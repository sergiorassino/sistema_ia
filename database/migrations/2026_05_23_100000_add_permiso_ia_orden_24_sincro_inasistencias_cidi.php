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
            ['id' => 26],
            [
                'id' => 26,
                'orden' => 24,
                'tema' => 'ASISTENCIA ESTUDIANTES',
                'descripcion' => 'Importar inasistencias de estudiantes desde CSV CIDI/GE (InasistenciasDetalle).',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 26)->delete();
        }
    }
};
