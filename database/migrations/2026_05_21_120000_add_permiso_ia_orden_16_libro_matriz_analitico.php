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
            ['id' => 18],
            [
                'id' => 18,
                'orden' => 16,
                'tema' => 'MATRÍZ Y ANALÍTICOS',
                'descripcion' => 'Libro matriz, pase y certificado analítico: consulta y edición de calificaciones en matriz.',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 18)->delete();
        }
    }
};
