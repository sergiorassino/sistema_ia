<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros operativos legacy en `ento` (bloqueo bimestre / impresión boletines).
 * Equivalente a database/sql/ento_parametros_operativos_bimestre_boletin_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            if (! Schema::hasColumn('ento', 'verBimesOff')) {
                $column = $table->tinyInteger('verBimesOff')->default(0);
                if (Schema::hasColumn('ento', 'verOffMensaje')) {
                    $column->after('verOffMensaje');
                } elseif (Schema::hasColumn('ento', 'verNotasOff')) {
                    $column->after('verNotasOff');
                }
            }
            if (! Schema::hasColumn('ento', 'bimesOffMensaje')) {
                $column = $table->string('bimesOffMensaje', 300)->nullable();
                if (Schema::hasColumn('ento', 'verBimesOff')) {
                    $column->after('verBimesOff');
                } elseif (Schema::hasColumn('ento', 'verOffMensaje')) {
                    $column->after('verOffMensaje');
                }
            }
            if (! Schema::hasColumn('ento', 'imprBoleOff')) {
                $column = $table->tinyInteger('imprBoleOff')->default(0);
                if (Schema::hasColumn('ento', 'bimesOffMensaje')) {
                    $column->after('bimesOffMensaje');
                } elseif (Schema::hasColumn('ento', 'verBimesOff')) {
                    $column->after('verBimesOff');
                } elseif (Schema::hasColumn('ento', 'verOffMensaje')) {
                    $column->after('verOffMensaje');
                }
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas legacy de ento.
    }
};
