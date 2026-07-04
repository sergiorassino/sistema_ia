<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensaje auxiliar de facturación AFIP en cuotas generadas (CAE, errores, etc.).
 * Equivalente a database/sql/cuotasgeneradas_mensaje_resultado_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuotasgeneradas')) {
            return;
        }

        if (Schema::hasColumn('cuotasgeneradas', 'mensajeResultado')) {
            return;
        }

        Schema::table('cuotasgeneradas', function (Blueprint $table) {
            $column = $table->string('mensajeResultado', 500)->nullable();
            if (Schema::hasColumn('cuotasgeneradas', 'nroComp')) {
                $column->after('nroComp');
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas legacy de cuotasgeneradas.
    }
};
