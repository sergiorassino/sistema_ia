<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CUIT institucional en `ento`.
 * Equivalente a database/sql/ento_cuit_idempotente.sql.
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
            if (! Schema::hasColumn('ento', 'cuit')) {
                $column = $table->string('cuit', 13)->nullable();
                if (Schema::hasColumn('ento', 'insti')) {
                    $column->after('insti');
                }
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas legacy de ento.
    }
};
