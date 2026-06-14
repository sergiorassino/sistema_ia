<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coop_rubros_ingreso')) {
            return;
        }

        DB::statement("ALTER TABLE coop_rubros_ingreso MODIFY tipo ENUM('por_alumno', 'eventual', 'uniforme', 'origen_estudiantes', 'otros_origenes') NOT NULL");

        if (Schema::hasTable('coop_ingresos')) {
            DB::statement("ALTER TABLE coop_ingresos MODIFY tipo ENUM('por_alumno', 'eventual', 'uniforme', 'origen_estudiantes', 'otros_origenes') NOT NULL");
        }

        DB::table('coop_rubros_ingreso')
            ->whereIn('tipo', ['por_alumno', 'uniforme'])
            ->update(['tipo' => 'origen_estudiantes']);

        DB::table('coop_rubros_ingreso')
            ->where('tipo', 'eventual')
            ->update(['tipo' => 'otros_origenes']);

        if (Schema::hasTable('coop_ingresos')) {
            DB::table('coop_ingresos')
                ->whereIn('tipo', ['por_alumno', 'uniforme'])
                ->update(['tipo' => 'origen_estudiantes']);

            DB::table('coop_ingresos')
                ->where('tipo', 'eventual')
                ->update(['tipo' => 'otros_origenes']);
        }

        DB::statement("ALTER TABLE coop_rubros_ingreso MODIFY tipo ENUM('origen_estudiantes', 'otros_origenes') NOT NULL");

        if (Schema::hasTable('coop_ingresos')) {
            DB::statement("ALTER TABLE coop_ingresos MODIFY tipo ENUM('origen_estudiantes', 'otros_origenes') NOT NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('coop_rubros_ingreso')) {
            return;
        }

        DB::statement("ALTER TABLE coop_rubros_ingreso MODIFY tipo ENUM('por_alumno', 'eventual', 'uniforme', 'origen_estudiantes', 'otros_origenes') NOT NULL");

        if (Schema::hasTable('coop_ingresos')) {
            DB::statement("ALTER TABLE coop_ingresos MODIFY tipo ENUM('por_alumno', 'eventual', 'uniforme', 'origen_estudiantes', 'otros_origenes') NOT NULL");
        }

        DB::table('coop_rubros_ingreso')
            ->where('tipo', 'origen_estudiantes')
            ->update(['tipo' => 'por_alumno']);

        DB::table('coop_rubros_ingreso')
            ->where('tipo', 'otros_origenes')
            ->update(['tipo' => 'eventual']);

        if (Schema::hasTable('coop_ingresos')) {
            DB::table('coop_ingresos')
                ->where('tipo', 'origen_estudiantes')
                ->update(['tipo' => 'por_alumno']);

            DB::table('coop_ingresos')
                ->where('tipo', 'otros_origenes')
                ->update(['tipo' => 'eventual']);
        }

        DB::statement("ALTER TABLE coop_rubros_ingreso MODIFY tipo ENUM('por_alumno', 'eventual', 'uniforme') NOT NULL");

        if (Schema::hasTable('coop_ingresos')) {
            DB::statement("ALTER TABLE coop_ingresos MODIFY tipo ENUM('por_alumno', 'eventual', 'uniforme') NOT NULL");
        }
    }
};
