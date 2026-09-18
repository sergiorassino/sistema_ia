<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Textos del comunicado de bloqueo / desbloqueo de matrícula en `ento` (por nivel).
 * Equivalente a database/sql/ento_mensaje_com_bloq_desbloq_matricula_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        if (! Schema::hasColumn('ento', 'mensajeComBloqMatricula')) {
            Schema::table('ento', function (Blueprint $table) {
                $column = $table->string('mensajeComBloqMatricula', 2000)->nullable();
                if (Schema::hasColumn('ento', 'mensajeBloqAdmi')) {
                    $column->after('mensajeBloqAdmi');
                } elseif (Schema::hasColumn('ento', 'mensajeBloqPeda')) {
                    $column->after('mensajeBloqPeda');
                } elseif (Schema::hasColumn('ento', 'verLibreDeuda')) {
                    $column->after('verLibreDeuda');
                }
            });
        }

        if (! Schema::hasColumn('ento', 'mensajeComDesbloqMatricula')) {
            Schema::table('ento', function (Blueprint $table) {
                $column = $table->string('mensajeComDesbloqMatricula', 2000)->nullable();
                if (Schema::hasColumn('ento', 'mensajeComBloqMatricula')) {
                    $column->after('mensajeComBloqMatricula');
                } elseif (Schema::hasColumn('ento', 'mensajeBloqAdmi')) {
                    $column->after('mensajeBloqAdmi');
                }
            });
        }
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de ento.
    }
};
