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
            ['id' => 22],
            [
                'id' => 22,
                'orden' => 20,
                'tema' => 'CERTIFICADOS',
                'descripcion' => 'Certificado de asistencia del profesor: listado de personal del legajo y emisión de PDF.',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 22)->delete();
        }
    }
};
