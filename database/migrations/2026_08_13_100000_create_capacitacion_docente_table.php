<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla nueva (aditiva) — cursos de capacitación docente.
 * Equivalente SQL: database/sql/create_capacitacion_docente.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('capacitacion_docente')) {
            return;
        }

        Schema::create('capacitacion_docente', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_profesor');
            $table->unsignedInteger('id_nivel');
            $table->date('fecha');
            $table->string('nombre', 255);
            $table->string('entidad_otorgante', 255);
            $table->string('duracion', 80);
            $table->string('modalidad', 20); // presencial | virtual | hibrida
            $table->string('certificado_archivo', 255)->nullable();
            $table->timestamps();

            $table->index(['id_nivel', 'id_profesor', 'fecha'], 'idx_cap_doc_nivel_prof_fecha');
            $table->index(['id_nivel', 'fecha'], 'idx_cap_doc_nivel_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacitacion_docente');
    }
};
