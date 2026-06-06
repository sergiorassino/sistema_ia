<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texto del tipo tal como viene en el CSV CIDI (columna «Tipo») para vincular con el catálogo local.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inasistencias_valores')) {
            return;
        }

        if (! Schema::hasColumn('inasistencias_valores', 'texto_cidi')) {
            Schema::table('inasistencias_valores', function (Blueprint $table) {
                $table->string('texto_cidi', 120)->nullable()->after('concepto');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inasistencias_valores') && Schema::hasColumn('inasistencias_valores', 'texto_cidi')) {
            Schema::table('inasistencias_valores', function (Blueprint $table) {
                $table->dropColumn('texto_cidi');
            });
        }
    }
};
