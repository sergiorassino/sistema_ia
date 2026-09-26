<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presupuesto de la actividad en proyectos extracurriculares.
 * Equivalente SQL: database/sql/add_presupuesto_to_ext_actividades.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ext_actividades')) {
            return;
        }

        if (Schema::hasColumn('ext_actividades', 'presupuesto')) {
            return;
        }

        Schema::table('ext_actividades', function (Blueprint $table) {
            $col = $table->text('presupuesto')->nullable();
            if (Schema::hasColumn('ext_actividades', 'evaluacion')) {
                $col->after('evaluacion');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ext_actividades')) {
            return;
        }

        if (Schema::hasColumn('ext_actividades', 'presupuesto')) {
            Schema::table('ext_actividades', function (Blueprint $table) {
                $table->dropColumn('presupuesto');
            });
        }
    }
};
