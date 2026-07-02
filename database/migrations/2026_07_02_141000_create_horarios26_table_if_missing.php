<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla legacy `horarios26` (grilla de horas cátedra).
 * Equivalente a database/sql/horarios26_tabla_idempotente.sql — sin datos semilla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('horarios26')) {
            Schema::create('horarios26', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('idProfesores')->default(0);
                $table->integer('idMaterias')->default(0);
                $table->string('idDia', 3)->default('0');
                $table->integer('idHora')->default(0);
                $table->unsignedTinyInteger('idTurnoClase')->nullable();
                $table->integer('idCursos')->default(0);
            });

            return;
        }

        if (! Schema::hasColumn('horarios26', 'idTurnoClase')) {
            Schema::table('horarios26', function (Blueprint $table) {
                $table->unsignedTinyInteger('idTurnoClase')->nullable()->after('idHora');
            });
        }
    }

    public function down(): void
    {
        // No eliminar tabla ni columnas (datos de negocio / posible legacy).
    }
};
