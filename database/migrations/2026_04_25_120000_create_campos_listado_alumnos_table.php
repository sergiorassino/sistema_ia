<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración idempotente: no hace nada si la tabla ya existe.
 * Permite que colegios que ya corrieron la migración anterior puedan instalar
 * el paquete sin errores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campos_listado_alumnos')) {
            return;
        }

        Schema::create('campos_listado_alumnos', function (Blueprint $table) {
            $table->id();
            $table->string('columna', 64)->unique();
            $table->string('etiqueta', 150)->nullable();
            $table->boolean('visible_listado')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
        });
    }

    public function down(): void
    {
        // Informativo; no ejecutar en producción.
        Schema::dropIfExists('campos_listado_alumnos');
    }
};
