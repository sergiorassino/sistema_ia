<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruta relativa de la foto carnet del estudiante (`legajos.fotoCarnet`).
 * Equivalente a database/sql/legajos_foto_carnet_idempotente.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legajos')) {
            return;
        }

        if (Schema::hasColumn('legajos', 'fotoCarnet')) {
            return;
        }

        Schema::table('legajos', function (Blueprint $table) {
            $table->string('fotoCarnet', 255)->nullable();
        });
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de legajos.
    }
};
