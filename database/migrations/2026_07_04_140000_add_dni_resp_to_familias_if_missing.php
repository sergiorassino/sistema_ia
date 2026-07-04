<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DNI del responsable económico en `familias` (facturación AFIP).
 * Equivalente a database/sql/familias_dni_resp_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('familias')) {
            return;
        }

        if (Schema::hasColumn('familias', 'dniResp')) {
            return;
        }

        Schema::table('familias', function (Blueprint $table) {
            $column = $table->string('dniResp', 20)->nullable();
            if (Schema::hasColumn('familias', 'responsable')) {
                $column->after('responsable');
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas legacy de familias.
    }
};
