<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proyectos extracurriculares — tablas nuevas (aditivas).
 * Equivalente SQL: database/sql/create_ext_proyectos_extracurriculares.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ext_tipo_registro')) {
            Schema::create('ext_tipo_registro', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->string('nombre', 120);
            });
        }

        if (Schema::hasTable('ext_tipo_registro')) {
            $existe = DB::table('ext_tipo_registro')->where('id', 1)->exists();
            if (! $existe) {
                DB::table('ext_tipo_registro')->insert([
                    'id' => 1,
                    'nombre' => 'Actividad Extraprogramática',
                ]);
            }
        }

        if (! Schema::hasTable('ext_actividades')) {
            Schema::create('ext_actividades', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_tipo_registro')->default(1);
                $table->unsignedInteger('id_nivel');
                $table->unsignedInteger('id_terlec');
                $table->unsignedInteger('id_profesor_proponente');
                $table->string('nombre', 255);
                $table->string('lugar', 255)->nullable();
                $table->string('horario', 255)->nullable();
                $table->text('descripcion')->nullable();
                $table->text('evaluacion')->nullable();
                $table->string('tipo_grupo', 20)->default('cursos'); // cursos | alumnos
                $table->string('estado', 20)->default('pendiente'); // pendiente | aprobado
                $table->unsignedInteger('aprobado_por')->nullable();
                $table->dateTime('aprobado_at')->nullable();
                $table->dateTime('comunicado_at')->nullable();
                $table->timestamps();

                $table->index(['id_nivel', 'id_terlec', 'estado'], 'idx_ext_act_ctx_estado');
                $table->index(['id_profesor_proponente', 'id_nivel', 'id_terlec'], 'idx_ext_act_prop');
            });
        }

        if (! Schema::hasTable('ext_fechas')) {
            Schema::create('ext_fechas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_actividad');
                $table->date('fecha');
                $table->time('hora_inicio')->nullable();
                $table->time('hora_fin')->nullable();

                $table->index(['id_actividad', 'fecha'], 'idx_ext_fechas_act_fecha');
                $table->index(['fecha'], 'idx_ext_fechas_fecha');
            });
        }

        if (! Schema::hasTable('ext_actividad_cursos')) {
            Schema::create('ext_actividad_cursos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_actividad');
                $table->unsignedInteger('id_curso');
                $table->unique(['id_actividad', 'id_curso'], 'uk_ext_act_curso');
            });
        }

        if (! Schema::hasTable('ext_actividad_alumnos')) {
            Schema::create('ext_actividad_alumnos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_actividad');
                $table->unsignedInteger('id_legajo');
                $table->unique(['id_actividad', 'id_legajo'], 'uk_ext_act_alumno');
            });
        }

        if (! Schema::hasTable('ext_actividad_docentes')) {
            Schema::create('ext_actividad_docentes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_actividad');
                $table->unsignedInteger('id_profesor');
                $table->string('rol', 20); // a_cargo | otro
                $table->unique(['id_actividad', 'id_profesor', 'rol'], 'uk_ext_act_doc_rol');
                $table->index(['id_actividad', 'rol'], 'idx_ext_act_doc_rol');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_actividad_docentes');
        Schema::dropIfExists('ext_actividad_alumnos');
        Schema::dropIfExists('ext_actividad_cursos');
        Schema::dropIfExists('ext_fechas');
        Schema::dropIfExists('ext_actividades');
        Schema::dropIfExists('ext_tipo_registro');
    }
};
