<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código de establecimiento educativo (EE) en `ento`.
 * Equivalente a database/sql/ento_ee_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        if (Schema::hasColumn('ento', 'ee')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            $column = $table->string('ee', 30)->nullable();
            if (Schema::hasColumn('ento', 'cue')) {
                $column->after('cue');
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de ento.
    }
};
