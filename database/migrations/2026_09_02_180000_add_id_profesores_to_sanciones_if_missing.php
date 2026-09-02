<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Algunos tenants legacy no tienen `sanciones.idProfesores` (quién registró la sanción).
 * Lo usan Seguimiento disciplinario y Situación áulica (portal docente).
 * Equivalente a database/sql/sanciones_idprofesores_idempotente.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sanciones')) {
            return;
        }

        if (Schema::hasColumn('sanciones', 'idProfesores')) {
            return;
        }

        Schema::table('sanciones', function (Blueprint $table) {
            $column = $table->unsignedInteger('idProfesores')->default(0);
            if (Schema::hasColumn('sanciones', 'idTipoSancion')) {
                $column->after('idTipoSancion');
            } elseif (Schema::hasColumn('sanciones', 'idMatricula')) {
                $column->after('idMatricula');
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de sanciones (legacy multi-tenant).
    }
};
