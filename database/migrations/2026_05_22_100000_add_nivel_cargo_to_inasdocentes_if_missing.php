<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas usadas por el módulo legacy de inasistencias docentes (formulario PHP).
 * En dumps antiguos pueden no existir; se agregan solo si faltan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inasdocentes')) {
            return;
        }

        Schema::table('inasdocentes', function (Blueprint $table) {
            if (! Schema::hasColumn('inasdocentes', 'idNivel')) {
                $table->unsignedInteger('idNivel')->default(0)->after('idProfesores');
            }
            if (! Schema::hasColumn('inasdocentes', 'idCargosXProfesor')) {
                $table->unsignedInteger('idCargosXProfesor')->default(0)->after('idNivel');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inasdocentes')) {
            return;
        }

        Schema::table('inasdocentes', function (Blueprint $table) {
            if (Schema::hasColumn('inasdocentes', 'idCargosXProfesor')) {
                $table->dropColumn('idCargosXProfesor');
            }
            if (Schema::hasColumn('inasdocentes', 'idNivel')) {
                $table->dropColumn('idNivel');
            }
        });
    }
};
