<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina columnas matweb_* si se hubieran creado en un despliegue intermedio
 * (el módulo usa documAcept1 … documAcept4 en su lugar).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            foreach ([
                'matweb_traslado_original_name',
                'matweb_traslado_path',
                'matweb_normas_original_name',
                'matweb_normas_path',
                'matweb_aec_original_name',
                'matweb_aec_path',
                'matweb_compromiso_original_name',
                'matweb_compromiso_path',
            ] as $col) {
                if (Schema::hasColumn('ento', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        // No se restauran columnas obsoletas.
    }
};
