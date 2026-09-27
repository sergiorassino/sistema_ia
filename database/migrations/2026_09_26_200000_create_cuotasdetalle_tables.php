<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discriminación del importe de una plantilla de cuota, por curso.
 * Cada fila es un ítem (nombre e importe) de una cuota en un curso.
 *
 * Equivalente a database/sql/cuotasdetalle_tablas.sql.
 * Solo crea la tabla. Si ya existe, no la modifica.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuotasdetalle')) {
            return;
        }

        Schema::create('cuotasdetalle', function (Blueprint $table) {
            $table->id();
            $table->integer('idCuotas');
            $table->integer('idCursos');
            $table->unsignedInteger('orden')->default(0);
            $table->string('nombre', 180);
            $table->decimal('importe', 12, 2)->default(0);
            $table->index(['idCuotas', 'idCursos'], 'cuotasdetalle_cuota_curso_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotasdetalle');
    }
};
