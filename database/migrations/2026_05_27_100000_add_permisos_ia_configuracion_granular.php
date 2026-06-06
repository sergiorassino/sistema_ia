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

        $granulares = [
            ['id' => 27, 'orden' => 25, 'descripcion' => 'Términos lectivos.'],
            ['id' => 28, 'orden' => 26, 'descripcion' => 'Niveles educativos.'],
            ['id' => 29, 'orden' => 27, 'descripcion' => 'Campos activos del legajo del estudiante.'],
            ['id' => 30, 'orden' => 28, 'descripcion' => 'Solapas del legajo del estudiante.'],
            ['id' => 31, 'orden' => 29, 'descripcion' => 'Campos activos del legajo del docente.'],
            ['id' => 32, 'orden' => 30, 'descripcion' => 'Solapas del legajo del docente.'],
            ['id' => 33, 'orden' => 31, 'descripcion' => 'Parámetros del sistema.'],
            ['id' => 34, 'orden' => 32, 'descripcion' => 'Notificaciones push (suscripción en este dispositivo).'],
            ['id' => 35, 'orden' => 33, 'descripcion' => 'Gestión de planes de estudio.'],
            ['id' => 36, 'orden' => 34, 'descripcion' => 'Gestión de cursos y materias del plan.'],
            ['id' => 37, 'orden' => 35, 'descripcion' => 'Gestión de cursos / grados / salas del año.'],
            ['id' => 38, 'orden' => 36, 'descripcion' => 'Gestión de asignaturas del año.'],
        ];

        foreach ($granulares as $permiso) {
            DB::table('permisos_ia')->updateOrInsert(
                ['id' => $permiso['id']],
                [
                    'id' => $permiso['id'],
                    'orden' => $permiso['orden'],
                    'tema' => 'CONFIGURACIÓN',
                    'descripcion' => $permiso['descripcion'],
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')->whereIn('id', range(27, 38))->delete();
    }
};
