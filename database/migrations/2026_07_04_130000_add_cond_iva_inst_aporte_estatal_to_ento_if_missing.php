<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Condición IVA del emisor y aporte estatal en `ento` (facturación AFIP).
 * Equivalente a database/sql/ento_cond_iva_inst_aporte_estatal_idempotente.sql.
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
            if (! Schema::hasColumn('ento', 'condIvaInst')) {
                $column = $table->string('condIvaInst', 40)->nullable();
                if (Schema::hasColumn('ento', 'cuit')) {
                    $column->after('cuit');
                }
            }
            if (! Schema::hasColumn('ento', 'aporteEstatal')) {
                $column = $table->string('aporteEstatal', 10)->nullable();
                if (Schema::hasColumn('ento', 'condIvaInst')) {
                    $column->after('condIvaInst');
                } elseif (Schema::hasColumn('ento', 'cuit')) {
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
