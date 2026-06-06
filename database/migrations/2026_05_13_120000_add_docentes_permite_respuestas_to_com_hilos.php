<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hilos scope "docentes" guardaban familia_puede_responder=false por legado sin significado
 * de "solo informativo", lo que bloqueaba respuestas al reutilizar esa columna.
 * Esta bandera es solo para docentes: NULL/true = permitir respuestas en hilo, false = solo informativo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('com_hilos')) {
            return;
        }
        if (Schema::hasColumn('com_hilos', 'docentes_permite_respuestas')) {
            return;
        }

        Schema::table('com_hilos', function (Blueprint $table) {
            $table->boolean('docentes_permite_respuestas')
                ->nullable()
                ->after('familia_puede_responder');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('com_hilos') && Schema::hasColumn('com_hilos', 'docentes_permite_respuestas')) {
            Schema::table('com_hilos', function (Blueprint $table) {
                $table->dropColumn('docentes_permite_respuestas');
            });
        }
    }
};
