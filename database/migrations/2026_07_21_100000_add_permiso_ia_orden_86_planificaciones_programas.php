<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permisos_ia')->updateOrInsert(
            ['orden' => 86],
            [
                'id' => 86,
                'tema' => 'EXÁMENES',
                'descripcion' => 'Gestión de planificaciones y programas: subida de PDF, aprobación para estudiantes y observaciones (tabla materias).',
            ],
        );
    }

    public function down(): void
    {
        DB::table('permisos_ia')->where('orden', 86)->delete();
    }
};
