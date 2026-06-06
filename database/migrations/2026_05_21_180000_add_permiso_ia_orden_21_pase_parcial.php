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
            ['id' => 23],
            [
                'id' => 23,
                'orden' => 21,
                'tema' => 'CERTIFICADOS',
                'descripcion' => 'Pase parcial: listado de legajos de nivel medio, solicitud y emisión de PDF.',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 23)->delete();
        }
    }
};
