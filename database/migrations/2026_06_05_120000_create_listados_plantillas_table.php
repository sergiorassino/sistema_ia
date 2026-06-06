<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listados_plantillas')) {
            return;
        }

        Schema::create('listados_plantillas', function (Blueprint $table) {
            $table->id();
            $table->integer('idNivel');
            $table->string('nombre', 120);
            // Lista ordenada de claves del catálogo (ListadoCursoPdfFieldCatalog).
            $table->json('campos');
            $table->string('condicion', 30)->default('regulares');
            $table->unsignedInteger('orden')->default(0);

            $table->index('idNivel', 'idx_listados_plantillas_nivel');
            $table->index(['idNivel', 'orden'], 'idx_listados_plantillas_orden');
        });
    }

    public function down(): void
    {
        // Irreversible por fila: borra todas las plantillas guardadas.
        Schema::dropIfExists('listados_plantillas');
    }
};
