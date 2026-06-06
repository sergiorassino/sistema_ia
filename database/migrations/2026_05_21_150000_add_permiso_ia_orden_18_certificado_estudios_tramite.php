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
            ['id' => 20],
            [
                'id' => 20,
                'orden' => 18,
                'tema' => 'CERTIFICADOS',
                'descripcion' => 'Constancia de certificado de estudios en trámite: listado de matriculados y emisión de PDF.',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 20)->delete();
        }
    }
};
