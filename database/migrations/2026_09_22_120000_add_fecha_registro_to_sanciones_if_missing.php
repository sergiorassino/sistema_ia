<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha y hora en que se cargó la sanción (distinta de `sanciones.fecha`, día del hecho).
 * Equivalente a database/sql/sanciones_fecha_registro_idempotente.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sanciones')) {
            return;
        }

        if (Schema::hasColumn('sanciones', 'fechaRegistro')) {
            return;
        }

        Schema::table('sanciones', function (Blueprint $table) {
            $column = $table->dateTime('fechaRegistro')->nullable();
            if (Schema::hasColumn('sanciones', 'fecha')) {
                $column->after('fecha');
            } elseif (Schema::hasColumn('sanciones', 'idTipoSancion')) {
                $column->after('idTipoSancion');
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de sanciones (legacy multi-tenant).
    }
};
