<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla legacy `preceptoresporcurso` (preceptor(es) por curso y ciclo).
 * Si el tenant ya la tiene (ScriptCase / otro colegio), no se recrea.
 * Si existe con esquema incompleto, solo agrega columnas faltantes
 * sin pisar variantes `idProfesor` / `idNiveles`.
 * Equivalente a database/sql/preceptoresporcurso_tabla_idempotente.sql (CREATE + ALTER).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('preceptoresporcurso')) {
            Schema::create('preceptoresporcurso', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('idCursos');
                $table->integer('idProfesores');
                $table->integer('idTerlec');
                $table->integer('idNivel');

                $table->index(['idCursos', 'idTerlec', 'idNivel'], 'idx_ppc_curso_terlec_nivel');
                $table->index('idProfesores', 'idx_ppc_profesor');
            });

            return;
        }

        if (! Schema::hasColumn('preceptoresporcurso', 'idTerlec')) {
            Schema::table('preceptoresporcurso', function (Blueprint $table) {
                $table->integer('idTerlec')->nullable();
            });
        }

        if (
            ! Schema::hasColumn('preceptoresporcurso', 'idNivel')
            && ! Schema::hasColumn('preceptoresporcurso', 'idNiveles')
        ) {
            Schema::table('preceptoresporcurso', function (Blueprint $table) {
                $table->integer('idNivel')->nullable();
            });
        }

        if (
            ! Schema::hasColumn('preceptoresporcurso', 'idProfesores')
            && ! Schema::hasColumn('preceptoresporcurso', 'idProfesor')
        ) {
            Schema::table('preceptoresporcurso', function (Blueprint $table) {
                $table->integer('idProfesores')->nullable();
            });
        }
    }

    public function down(): void
    {
        // No eliminar tabla ni columnas (datos de negocio / posible legacy).
    }
};
