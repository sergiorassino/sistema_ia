<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destinatarios adicionales del aviso de situación áulica (portal docente).
 * Una fila por profesor y nivel. El preceptor del curso no se guarda acá.
 *
 * Equivalente: database/sql/situacion_aulica_destinatarios.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('situacion_aulica_destinatarios')) {
            return;
        }

        Schema::create('situacion_aulica_destinatarios', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('idNivel');
            $table->unsignedInteger('idProfesor');
            $table->unique(['idNivel', 'idProfesor'], 'uk_sit_aulica_dest_nivel_prof');
            $table->index('idNivel', 'idx_sit_aulica_dest_nivel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('situacion_aulica_destinatarios');
    }
};
