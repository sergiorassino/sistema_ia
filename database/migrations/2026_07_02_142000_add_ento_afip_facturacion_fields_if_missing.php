<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos fiscales AFIP en `ento` (condicionIva, ingresosBrutos, fechaInicioAct, …).
 * Complementa 2026_06_24_120000_add_afip_cert_fields_to_ento_table.php.
 * Equivalente a database/sql/ento_afip_facturacion_idempotente.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            if (! Schema::hasColumn('ento', 'condicionIva')) {
                $table->string('condicionIva', 50)->nullable();
            }
            if (! Schema::hasColumn('ento', 'ptoVta')) {
                $column = $table->unsignedSmallInteger('ptoVta')->nullable();
                if (Schema::hasColumn('ento', 'cuit')) {
                    $column->after('cuit');
                }
            }
            if (! Schema::hasColumn('ento', 'afipCertCarpeta')) {
                $column = $table->string('afipCertCarpeta', 40)->nullable();
                if (Schema::hasColumn('ento', 'ptoVta')) {
                    $column->after('ptoVta');
                }
            }
            if (! Schema::hasColumn('ento', 'afipCertKey')) {
                $column = $table->string('afipCertKey', 120)->nullable();
                if (Schema::hasColumn('ento', 'afipCertCarpeta')) {
                    $column->after('afipCertCarpeta');
                }
            }
            if (! Schema::hasColumn('ento', 'afipCertCrt')) {
                $column = $table->string('afipCertCrt', 120)->nullable();
                if (Schema::hasColumn('ento', 'afipCertKey')) {
                    $column->after('afipCertKey');
                }
            }
            if (! Schema::hasColumn('ento', 'ingresosBrutos')) {
                $table->string('ingresosBrutos', 10)->nullable();
            }
            if (! Schema::hasColumn('ento', 'fechaInicioAct')) {
                $table->string('fechaInicioAct', 15)->nullable();
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas legacy de ento.
    }
};
