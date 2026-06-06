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
            ['id' => 13],
            [
                'id' => 13,
                'orden' => 12,
                'tema' => 'EXÁMENES',
                'descripcion' => 'Módulo de exámenes: materias adeudadas, gestión, listados y borrado de inscripciones.',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 13)->delete();
        }
    }
};
