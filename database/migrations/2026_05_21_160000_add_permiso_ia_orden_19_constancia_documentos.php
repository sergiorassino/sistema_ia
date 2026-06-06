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
            ['id' => 21],
            [
                'id' => 21,
                'orden' => 19,
                'tema' => 'CERTIFICADOS',
                'descripcion' => 'Constancia de documentos: listado de matriculados y emisión de PDF.',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 21)->delete();
        }
    }
};
