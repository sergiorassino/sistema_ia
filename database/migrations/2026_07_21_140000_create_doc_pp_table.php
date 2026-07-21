<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de planificaciones y programas (PDF) — tabla nueva doc_pp.
 * Un documento por materia del año y tipo (plan|prog).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doc_pp')) {
            return;
        }

        Schema::create('doc_pp', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('idNivel');
            $table->unsignedInteger('idTerlec');
            $table->unsignedInteger('idMaterias');
            $table->unsignedInteger('idCursos');
            $table->string('tipo', 8);
            $table->string('nombre_archivo', 255);
            $table->unsignedTinyInteger('aprobado')->default(0);
            $table->string('observaciones', 500)->nullable();
            $table->unsignedInteger('subido_por')->nullable();
            $table->dateTime('subido_en')->nullable();

            $table->unique(['idMaterias', 'tipo'], 'doc_pp_materias_tipo_unique');
            $table->index(['idNivel', 'idTerlec', 'tipo'], 'doc_pp_nivel_terlec_tipo_idx');
            $table->index(['idCursos'], 'doc_pp_cursos_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_pp');
    }
};
