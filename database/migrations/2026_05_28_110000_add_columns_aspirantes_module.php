<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas legacy: aspiento, aspicursos, aspirantes.
 * No tocamos columnas existentes. Solo agregamos lo necesario para el módulo nuevo,
 * con checks idempotentes (Schema::hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aspiento')) {
            Schema::table('aspiento', function (Blueprint $table) {
                if (! Schema::hasColumn('aspiento', 'titulo')) {
                    $table->string('titulo', 150)->nullable();
                }
                if (! Schema::hasColumn('aspiento', 'token')) {
                    $table->string('token', 64)->nullable();
                }
                if (! Schema::hasColumn('aspiento', 'activo')) {
                    $table->boolean('activo')->default(0);
                }
                if (! Schema::hasColumn('aspiento', 'idTerlec')) {
                    $table->unsignedInteger('idTerlec')->nullable();
                }
                if (! Schema::hasColumn('aspiento', 'mensaje_publico')) {
                    $table->text('mensaje_publico')->nullable();
                }
                if (! Schema::hasColumn('aspiento', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (! Schema::hasColumn('aspiento', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            // Índice único en token (solo si no existe). Comprobación vía INFORMATION_SCHEMA.
            $idxExiste = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                ['aspiento', 'aspiento_token_unique']
            );
            if (! $idxExiste || (int) $idxExiste->c === 0) {
                try {
                    DB::statement('ALTER TABLE `aspiento` ADD UNIQUE KEY `aspiento_token_unique` (`token`)');
                } catch (\Throwable $e) {
                    // Si la columna no existe aún por alguna razón, no romper la migración.
                }
            }
        }

        if (Schema::hasTable('aspicursos')) {
            Schema::table('aspicursos', function (Blueprint $table) {
                if (! Schema::hasColumn('aspicursos', 'idAspiento')) {
                    $table->unsignedInteger('idAspiento')->nullable();
                }
                if (! Schema::hasColumn('aspicursos', 'idCursos')) {
                    $table->unsignedInteger('idCursos')->nullable();
                }
                if (! Schema::hasColumn('aspicursos', 'activo')) {
                    $table->boolean('activo')->default(1);
                }
            });

            $idxAspicursos = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                ['aspicursos', 'aspicursos_aspiento_curso_unique']
            );
            if (! $idxAspicursos || (int) $idxAspicursos->c === 0) {
                try {
                    DB::statement('ALTER TABLE `aspicursos` ADD UNIQUE KEY `aspicursos_aspiento_curso_unique` (`idAspiento`, `idCursos`)');
                } catch (\Throwable $e) {
                    // ignorar
                }
            }
        }

        if (Schema::hasTable('aspirantes')) {
            Schema::table('aspirantes', function (Blueprint $table) {
                if (! Schema::hasColumn('aspirantes', 'idAspiento')) {
                    $table->unsignedInteger('idAspiento')->nullable();
                }
                if (! Schema::hasColumn('aspirantes', 'idCursos')) {
                    $table->unsignedInteger('idCursos')->nullable();
                }
                if (! Schema::hasColumn('aspirantes', 'idNivel')) {
                    $table->unsignedInteger('idNivel')->nullable();
                }
                if (! Schema::hasColumn('aspirantes', 'ip_origen')) {
                    $table->string('ip_origen', 45)->nullable();
                }
                if (! Schema::hasColumn('aspirantes', 'user_agent')) {
                    $table->string('user_agent', 255)->nullable();
                }
                if (! Schema::hasColumn('aspirantes', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // No revertimos columnas en tablas legacy. Reversión manual si fuera necesario.
    }
};
