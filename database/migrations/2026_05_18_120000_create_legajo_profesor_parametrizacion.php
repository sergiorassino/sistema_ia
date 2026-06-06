<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solapas_legajo_profesor')) {
            Schema::create('solapas_legajo_profesor', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 60);
                $table->string('slug', 30)->unique();
                $table->unsignedSmallInteger('orden')->default(0);
            });
        }

        if (! DB::table('solapas_legajo_profesor')->where('slug', 'docente')->exists()) {
            DB::table('solapas_legajo_profesor')->insert([
                ['nombre' => 'DOCENTE', 'slug' => 'docente', 'orden' => 1],
            ]);
        }

        if (! Schema::hasTable('campos_profesores')) {
            Schema::create('campos_profesores', function (Blueprint $table) {
                $table->id();
                $table->string('columna', 80);
                $table->string('etiqueta', 100)->nullable();
                $table->boolean('visible_listado')->default(true);
                $table->unsignedInteger('orden')->default(0);
                $table->foreignId('solapa_legajo_profesor_id')
                    ->nullable()
                    ->constrained('solapas_legajo_profesor')
                    ->nullOnDelete();
                $table->unsignedSmallInteger('orden_en_solapa')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campos_profesores');
        Schema::dropIfExists('solapas_legajo_profesor');
    }
};
