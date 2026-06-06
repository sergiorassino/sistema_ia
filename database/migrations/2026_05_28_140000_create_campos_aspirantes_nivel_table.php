<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campos_aspirantes_nivel')) {
            Schema::create('campos_aspirantes_nivel', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campo_aspirante_id');
                $table->unsignedInteger('idNivel');
                $table->boolean('visible')->default(false);
                $table->boolean('obligatorio')->default(false);

                $table->unique(['campo_aspirante_id', 'idNivel'], 'uq_campo_aspirante_nivel');
                $table->index(['idNivel'], 'idx_campos_asp_nivel_idNivel');

                $table->foreign('campo_aspirante_id', 'fk_campos_asp_nivel_campo')
                    ->references('id')
                    ->on('campos_aspirantes')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campos_aspirantes_nivel');
    }
};

