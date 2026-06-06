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
            ['id' => 19],
            [
                'id' => 19,
                'orden' => 17,
                'tema' => 'CERTIFICADOS',
                'descripcion' => 'Certificado escolar de alumno/a regular: listado de matriculados del año en curso y emisión de PDF.',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 19)->delete();
        }
    }
};
