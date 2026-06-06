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
            ['id' => 14],
            [
                'id' => 14,
                'orden' => 13,
                'tema' => 'HORARIOS',
                'descripcion' => 'Configuración de horarios (turnos, días, reloj) y carga de horas cátedra por docente.',
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 14)->delete();
        }
    }
};
