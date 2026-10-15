<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personas autorizadas para comunicación y retiro del estudiante
 * (San Francisco de Asís — actualización de datos).
 *
 * Equivalente SQL: database/sql/legajo_autorizados_emergencia.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legajo_autorizados_emergencia')) {
            return;
        }

        Schema::create('legajo_autorizados_emergencia', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idLegajo');
            $table->unsignedTinyInteger('orden');
            $table->string('apellido_nombre', 120);
            $table->string('dni', 11);
            $table->string('relacion', 60);
            $table->string('telefono', 40);

            $table->unique(['idLegajo', 'orden'], 'uk_legajo_aut_emerg_orden');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legajo_autorizados_emergencia');
    }
};
