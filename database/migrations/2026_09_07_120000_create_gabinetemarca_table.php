<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de color (semáforo) del seguimiento de gabinete, por legajo.
 *
 * Equivalente: database/sql/gabinetemarca.sql
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gabinetemarca')) {
            return;
        }

        Schema::create('gabinetemarca', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('idLegajos');
            $table->unsignedTinyInteger('color')->default(0);
            $table->unique('idLegajos', 'uk_gabinetemarca_legajo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gabinetemarca');
    }
};
