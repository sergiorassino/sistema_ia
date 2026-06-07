<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de última actualización de datos personales desde autogestión (`fechActDatos`).
 * Idempotente: solo agrega la columna si falta (Schema::hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legajos') && ! Schema::hasColumn('legajos', 'fechActDatos')) {
            Schema::table('legajos', function (Blueprint $table) {
                $table->dateTime('fechActDatos')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('legajos') && Schema::hasColumn('legajos', 'fechActDatos')) {
            Schema::table('legajos', function (Blueprint $table) {
                $table->dropColumn('fechActDatos');
            });
        }
    }
};
