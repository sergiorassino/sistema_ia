<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * com_hilos_participantes nunca se pobló; los destinatarios de respuesta se infieren desde com_mensajes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('com_hilos_participantes');
    }

    public function down(): void
    {
        if (! Schema::hasTable('com_hilos_participantes')) {
            Schema::create('com_hilos_participantes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_hilo');
                $table->enum('tipo', ['profesor', 'familia']);
                $table->unsignedInteger('id_profesor')->nullable();
                $table->unsignedInteger('id_legajo')->nullable();
                $table->string('rol', 30)->nullable();
                $table->enum('vinculo', ['madre', 'padre', 'tutor', 'resp_admin', 'otro'])->nullable();
                $table->string('nombre_snapshot', 150)->nullable();
                $table->string('dni_snapshot', 20)->nullable();
                $table->timestamp('agregado_at')->useCurrent();

                $table->foreign('id_hilo')->references('id')->on('com_hilos')->onDelete('cascade');
                $table->index(['id_hilo', 'tipo', 'id_profesor']);
                $table->index(['id_hilo', 'tipo', 'id_legajo']);
            });
        }
    }
};
