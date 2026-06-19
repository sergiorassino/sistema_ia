<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doc_estudiante_tipos')) {
            return;
        }

        Schema::create('doc_estudiante_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40);
            $table->string('etiqueta', 120);
            /** @var list<string> ej. ["jpg","jpeg","pdf"] */
            $table->json('extensiones');
            $table->unsignedTinyInteger('max_archivos')->default(1);
            /** MB por archivo; null = default del sistema (2) */
            $table->unsignedTinyInteger('max_mb')->nullable();
            $table->boolean('obligatorio')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->unique('clave', 'doc_estudiante_tipos_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_estudiante_tipos');
    }
};
