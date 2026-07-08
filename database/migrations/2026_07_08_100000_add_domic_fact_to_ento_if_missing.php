<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domicilio fiscal AFIP del emisor en `ento` (puede diferir del domicilio real).
 * Equivalente a database/sql/ento_domic_fact_idempotente.sql.
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
            if (! Schema::hasColumn('ento', 'domicFact')) {
                $column = $table->string('domicFact', 100)->nullable();
                if (Schema::hasColumn('ento', 'cuit')) {
                    $column->after('cuit');
                }
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas legacy de ento.
    }
};
