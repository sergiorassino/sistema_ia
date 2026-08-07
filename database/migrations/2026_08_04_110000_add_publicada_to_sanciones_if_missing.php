<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Algunos tenants legacy no tienen `sanciones.publicada` (int/tinyint, default 1).
 * La migración de notif. a padres posiciona columnas con ->after('publicada').
 * Equivalente a database/sql/sanciones_publicada_idempotente.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sanciones')) {
            return;
        }

        if (Schema::hasColumn('sanciones', 'publicada')) {
            return;
        }

        Schema::table('sanciones', function (Blueprint $table) {
            $column = $table->tinyInteger('publicada')->default(1);
            if (Schema::hasColumn('sanciones', 'solipor')) {
                $column->after('solipor');
            } elseif (Schema::hasColumn('sanciones', 'motivo')) {
                $column->after('motivo');
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de sanciones (legacy multi-tenant).
    }
};
