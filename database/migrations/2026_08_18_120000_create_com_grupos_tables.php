<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('com_grupos')) {
            Schema::create('com_grupos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre', 100);
                $table->unsignedInteger('id_profesor');
                $table->unsignedInteger('id_nivel');
                $table->string('tipo_destinatario', 40);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->unique(['id_profesor', 'id_nivel', 'nombre'], 'uq_com_grupo_dueno_nombre');
                $table->index(['id_profesor', 'id_nivel'], 'idx_com_grupos_dueno');
                $table->index(['id_nivel', 'tipo_destinatario'], 'idx_com_grupos_nivel_tipo');
            });
        }

        if (! Schema::hasTable('com_grupos_miembros')) {
            Schema::create('com_grupos_miembros', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_grupo');
                $table->enum('tipo_miembro', ['legajo', 'profesor']);
                $table->unsignedInteger('id_legajo')->nullable();
                $table->unsignedInteger('id_profesor')->nullable();
                $table->string('nombre_snapshot', 150)->nullable();

                $table->foreign('id_grupo')->references('id')->on('com_grupos')->onDelete('cascade');
                $table->unique(['id_grupo', 'id_legajo'], 'uq_com_grupo_legajo');
                $table->unique(['id_grupo', 'id_profesor'], 'uq_com_grupo_profesor');
                $table->index(['tipo_miembro', 'id_legajo'], 'idx_com_gmiem_legajo');
                $table->index(['tipo_miembro', 'id_profesor'], 'idx_com_gmiem_profesor');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_grupos_miembros');
        Schema::dropIfExists('com_grupos');
    }
};
