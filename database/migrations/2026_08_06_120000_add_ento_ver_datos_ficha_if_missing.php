<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menú de Alumnos: un flag por nivel para Actualización de Datos + Ficha de Matrícula.
 * Equivalente a database/sql/ento_ver_datos_ficha_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        if (Schema::hasColumn('ento', 'verDatosFicha')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            $column = $table->tinyInteger('verDatosFicha')->default(1);
            if (Schema::hasColumn('ento', 'imprBoleOff')) {
                $column->after('imprBoleOff');
            } elseif (Schema::hasColumn('ento', 'verBimesOff')) {
                $column->after('verBimesOff');
            } elseif (Schema::hasColumn('ento', 'verNotasOff')) {
                $column->after('verNotasOff');
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de ento.
    }
};
