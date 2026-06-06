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
            ['id' => 24],
            [
                'id' => 24,
                'orden' => 22,
                'tema' => 'CERTIFICADOS',
                'descripcion' => 'Solicitud de pase: listado de legajos de nivel medio, datos en paseprovisorio y emisión de PDF.',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 24)->delete();
        }
    }
};
