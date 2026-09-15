<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menú de Alumnos: flag por nivel para mostrar u ocultar Libre Deuda.
 * Equivalente a database/sql/ento_ver_libre_deuda_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        if (Schema::hasColumn('ento', 'verLibreDeuda')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            $column = $table->tinyInteger('verLibreDeuda')->default(1);
            if (Schema::hasColumn('ento', 'verDatosFicha')) {
                $column->after('verDatosFicha');
            } elseif (Schema::hasColumn('ento', 'imprBoleOff')) {
                $column->after('imprBoleOff');
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
