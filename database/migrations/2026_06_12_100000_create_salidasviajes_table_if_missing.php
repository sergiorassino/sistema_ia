<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de salidas educativas (módulo Viajes).
 * En colegios sin tabla legacy se crea completa; si ya existe, solo se agregan columnas faltantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salidasviajes')) {
            Schema::create('salidasviajes', function (Blueprint $table) {
                $table->id();
                $table->string('titulo', 200)->default('');
                $table->date('desde')->nullable();
                $table->date('hasta')->nullable();
                $table->mediumText('texto')->nullable();
                $table->unsignedInteger('idTerlec')->default(0);
                $table->unsignedInteger('idNivel')->default(0);

                $table->index(['idNivel', 'idTerlec'], 'idx_salidasviajes_ctx');
            });

            return;
        }

        Schema::table('salidasviajes', function (Blueprint $table) {
            if (! Schema::hasColumn('salidasviajes', 'titulo')) {
                $table->string('titulo', 200)->default('');
            }
            if (! Schema::hasColumn('salidasviajes', 'desde')) {
                $table->date('desde')->nullable();
            }
            if (! Schema::hasColumn('salidasviajes', 'hasta')) {
                $table->date('hasta')->nullable();
            }
            if (! Schema::hasColumn('salidasviajes', 'texto')) {
                $table->mediumText('texto')->nullable();
            }
            if (! Schema::hasColumn('salidasviajes', 'idTerlec')) {
                $table->unsignedInteger('idTerlec')->default(0);
            }
            if (! Schema::hasColumn('salidasviajes', 'idNivel')) {
                $table->unsignedInteger('idNivel')->default(0);
            }
        });
    }

    public function down(): void
    {
        // No se elimina la tabla ni columnas (datos de negocio / posible legacy).
    }
};
