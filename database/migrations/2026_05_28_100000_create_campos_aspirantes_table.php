<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campos_aspirantes')) {
            Schema::create('campos_aspirantes', function (Blueprint $table) {
                $table->id();
                $table->string('columna', 80);
                $table->string('etiqueta', 100)->nullable();
                $table->boolean('visible')->default(false);
                $table->boolean('obligatorio')->default(false);
                $table->unsignedInteger('orden')->default(0);
                $table->unique('columna', 'campos_aspirantes_columna_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campos_aspirantes');
    }
};
