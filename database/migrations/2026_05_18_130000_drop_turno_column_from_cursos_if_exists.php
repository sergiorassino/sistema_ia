<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El turno de clase queda solo en cursos.idTurnoClase (FK a turnos_clase).
     */
    public function up(): void
    {
        if (! Schema::hasTable('cursos') || ! Schema::hasColumn('cursos', 'turno')) {
            return;
        }

        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('turno');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cursos') || Schema::hasColumn('cursos', 'turno')) {
            return;
        }

        Schema::table('cursos', function (Blueprint $table) {
            $table->string('turno', 20)->nullable()->after('s');
        });
    }
};
