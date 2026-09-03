<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag por tipo: 1 = incluir en el recuadro de totales de Gestión de inasistencias; 0 = no mostrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inasistencias_valores')) {
            return;
        }

        if (Schema::hasColumn('inasistencias_valores', 'mostrarTotal')) {
            return;
        }

        $afterTextoCidi = Schema::hasColumn('inasistencias_valores', 'texto_cidi');

        Schema::table('inasistencias_valores', function (Blueprint $table) use ($afterTextoCidi) {
            if ($afterTextoCidi) {
                $table->unsignedTinyInteger('mostrarTotal')->default(0)->after('texto_cidi');
            } else {
                $table->unsignedTinyInteger('mostrarTotal')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('inasistencias_valores') && Schema::hasColumn('inasistencias_valores', 'mostrarTotal')) {
            Schema::table('inasistencias_valores', function (Blueprint $table) {
                $table->dropColumn('mostrarTotal');
            });
        }
    }
};
