<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal de cierre anual (secundario): lote por ejecución + snapshot por fila actualizada.
 * Equivalente SQL: database/sql/cierre_anual_lotes.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cierre_anual_lotes')) {
            Schema::create('cierre_anual_lotes', function (Blueprint $table) {
                $table->id();
                $table->string('operacion', 10);
                $table->unsignedInteger('id_nivel');
                $table->unsignedInteger('id_terlec');
                $table->unsignedSmallInteger('ano_lectivo')->nullable();
                $table->string('nivel_nombre', 80)->default('');
                $table->unsignedInteger('id_profesor')->default(0);
                $table->string('nombre_profesor', 150)->default('');
                $table->unsignedInteger('procesados')->default(0);
                $table->unsignedInteger('aprobados')->default(0);
                $table->unsignedInteger('previas')->default(0);
                $table->unsignedInteger('omitidos')->default(0);
                $table->unsignedInteger('actualizados')->default(0);
                $table->string('estado', 20)->default('aplicado');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('revertido_at')->nullable();
                $table->unsignedInteger('id_profesor_reverso')->nullable();
                $table->string('nombre_profesor_reverso', 150)->nullable();
                $table->unsignedInteger('revertidos_ok')->default(0);
                $table->unsignedInteger('revertidos_omitidos')->default(0);

                $table->index(['id_nivel', 'id_terlec', 'created_at'], 'idx_cierre_lotes_ctx');
                $table->index(['estado'], 'idx_cierre_lotes_estado');
            });
        }

        if (! Schema::hasTable('cierre_anual_lote_filas')) {
            Schema::create('cierre_anual_lote_filas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_lote');
                $table->unsignedInteger('id_calificacion');
                $table->unsignedInteger('id_legajos')->default(0);
                $table->unsignedInteger('id_matricula')->default(0);
                $table->unsignedInteger('id_materias')->default(0);
                $table->string('apellido', 100)->default('');
                $table->string('nombre', 100)->default('');
                $table->string('dni', 20)->default('');
                $table->string('materia', 150)->default('');
                $table->string('curso', 80)->default('');
                $table->string('tipo', 10);

                $table->unsignedTinyInteger('apro_antes')->default(0);
                $table->string('calif_antes', 20)->default('');
                $table->unsignedSmallInteger('mes_antes')->nullable();
                $table->unsignedSmallInteger('ano_antes')->nullable();
                $table->string('cond_antes', 20)->default('');
                $table->string('escuapro_antes', 100)->default('');
                $table->string('cond_adeuda_antes', 20)->nullable();
                $table->unsignedTinyInteger('inscri_antes')->default(0);

                $table->unsignedTinyInteger('apro_despues')->default(0);
                $table->string('calif_despues', 20)->default('');
                $table->unsignedSmallInteger('mes_despues')->nullable();
                $table->unsignedSmallInteger('ano_despues')->nullable();
                $table->string('cond_despues', 20)->default('');
                $table->string('escuapro_despues', 100)->default('');
                $table->string('cond_adeuda_despues', 20)->nullable();
                $table->unsignedTinyInteger('inscri_despues')->default(0);

                $table->timestamp('revertida_at')->nullable();

                $table->index(['id_lote'], 'idx_cierre_filas_lote');
                $table->index(['id_calificacion'], 'idx_cierre_filas_calif');
                $table->index(['id_legajos'], 'idx_cierre_filas_legajo');
                $table->index(['id_lote', 'tipo'], 'idx_cierre_filas_lote_tipo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cierre_anual_lote_filas');
        Schema::dropIfExists('cierre_anual_lotes');
    }
};
