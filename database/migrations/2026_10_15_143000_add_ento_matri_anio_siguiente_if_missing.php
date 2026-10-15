<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menú de Alumnos: la ficha y la aceptación de documentos usan el año siguiente
 * a `idTerlecVerNotas` cuando el flag del nivel está en 1.
 * Equivalente a database/sql/ento_matri_anio_siguiente_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        if (Schema::hasColumn('ento', 'matriAnioSiguiente')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            $column = $table->tinyInteger('matriAnioSiguiente')->default(0);
            if (Schema::hasColumn('ento', 'idTerlecVerNotas')) {
                $column->after('idTerlecVerNotas');
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de ento.
    }
};
