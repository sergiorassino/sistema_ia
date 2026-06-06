<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            Schema::create('permisos_ia', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('orden');
                $table->string('tema', 50)->default('');
                $table->text('descripcion');
            });
        }

        if (Schema::hasTable('profesores') && ! Schema::hasColumn('profesores', 'permisos_ia')) {
            Schema::table('profesores', function (Blueprint $table) {
                $table->string('permisos_ia', 50)->nullable()->after('permisos');
            });
        }

        $permisos = [
            ['id' => 1, 'orden' => 0, 'tema' => 'ADMINISTRACIÓN', 'descripcion' => 'Administrar permisos del portal de gestión (sistema nuevo).'],
            ['id' => 3, 'orden' => 2, 'tema' => 'LEGAJOS ESTUDIANTES', 'descripcion' => 'Crear, editar y eliminar legajos de estudiantes; gestionar matrículas.'],
            ['id' => 4, 'orden' => 3, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Ver la bandeja de comunicados y los hilos de conversación.'],
            ['id' => 5, 'orden' => 4, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Iniciar nuevos comunicados hacia familias.'],
            ['id' => 6, 'orden' => 5, 'tema' => 'COMUNICACIONES - CONFIG', 'descripcion' => 'Administrar la configuración de canales (quién puede comunicarse con quién y por qué medios).'],
            ['id' => 7, 'orden' => 6, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Borrar mensajes propios en un hilo.'],
            ['id' => 8, 'orden' => 7, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Borrar mensajes de otros participantes en un hilo.'],
            ['id' => 9, 'orden' => 8, 'tema' => 'COMUNICACIONES', 'descripcion' => 'Acceder a la bandeja de revisión de comunicados.'],
            ['id' => 10, 'orden' => 9, 'tema' => 'CALIFICACIONES SECUNDARIO', 'descripcion' => 'Importar calificaciones desde CIDI/GE y carga manual de calificaciones (secundario).'],
            ['id' => 11, 'orden' => 10, 'tema' => 'CALIFICACIONES SECUNDARIO', 'descripcion' => 'Carga de coloquios Dic / Feb (secundario).'],
            ['id' => 12, 'orden' => 11, 'tema' => 'LEGAJOS DOCENTES', 'descripcion' => 'Crear, editar y eliminar legajos de docentes (ABM legajo docente).'],
            ['id' => 13, 'orden' => 12, 'tema' => 'EXÁMENES', 'descripcion' => 'Módulo de exámenes: materias adeudadas, gestión, listados y borrado de inscripciones.'],
            ['id' => 14, 'orden' => 13, 'tema' => 'HORARIOS', 'descripcion' => 'Configuración de horarios (turnos, días, reloj) y carga de horas cátedra por docente.'],
            ['id' => 15, 'orden' => 14, 'tema' => 'ADMINISTRACIÓN', 'descripcion' => 'Consultar permisos concedidos por usuario (módulo Permisos por Usuario).'],
        ];

        foreach ($permisos as $permiso) {
            DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('profesores') && Schema::hasColumn('profesores', 'permisos_ia')) {
            Schema::table('profesores', function (Blueprint $table) {
                $table->dropColumn('permisos_ia');
            });
        }

        Schema::dropIfExists('permisos_ia');
    }
};
