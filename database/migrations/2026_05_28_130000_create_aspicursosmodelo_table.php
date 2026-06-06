<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de "cursos modelo" para la inscripción de aspirantes (por nivel),
 * sin sección. Ej.: "Sala de 4", "Primero", "Segundo".
 *
 * Cada instancia (aspiento) marca cuáles aplican; lo seleccionado
 * se guarda en aspicursos.idCursoModelo (nueva columna), y el aspirante
 * elige uno en aspirantes.idCursoModelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aspicursosmodelo')) {
            Schema::create('aspicursosmodelo', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('idNivel');
                $table->string('nombre', 80);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->index('idNivel', 'aspicursosmodelo_idnivel_index');
            });
        }

        if (Schema::hasTable('aspicursos') && ! Schema::hasColumn('aspicursos', 'idCursoModelo')) {
            Schema::table('aspicursos', function (Blueprint $table) {
                $table->unsignedBigInteger('idCursoModelo')->nullable();
                $table->index('idCursoModelo', 'aspicursos_idcursomodelo_index');
            });
        }

        if (Schema::hasTable('aspirantes') && ! Schema::hasColumn('aspirantes', 'idCursoModelo')) {
            Schema::table('aspirantes', function (Blueprint $table) {
                $table->unsignedBigInteger('idCursoModelo')->nullable();
                $table->index('idCursoModelo', 'aspirantes_idcursomodelo_index');
            });
        }

        // Único (idAspiento, idCursoModelo) en aspicursos (idempotente).
        if (Schema::hasTable('aspicursos')) {
            $idx = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                ['aspicursos', 'aspicursos_aspiento_modelo_unique']
            );
            if (! $idx || (int) $idx->c === 0) {
                try {
                    DB::statement('ALTER TABLE `aspicursos` ADD UNIQUE KEY `aspicursos_aspiento_modelo_unique` (`idAspiento`, `idCursoModelo`)');
                } catch (\Throwable $e) {
                    // si las columnas todavía no existen, no romper
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aspicursosmodelo');
        // Las columnas en tablas legacy quedan; reversión manual si fuera necesario.
    }
};
