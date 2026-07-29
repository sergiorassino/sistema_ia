<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observación libre para el impreso de factura AFIP (`ento.obsFactura`).
 * Equivalente a database/sql/ento_obs_factura_idempotente.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        if (Schema::hasColumn('ento', 'obsFactura')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            $table->text('obsFactura')->nullable();
        });
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de ento.
    }
};
