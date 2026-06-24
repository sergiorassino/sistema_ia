<?php

use App\Support\Migrations\EnsureCursosTurnoColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        EnsureCursosTurnoColumns::aplicar();
    }

    public function down(): void
    {
        if (Schema::hasTable('cursos') && Schema::hasColumn('cursos', 'idTurnoClase')) {
            Schema::table('cursos', function (Blueprint $table) {
                $table->dropColumn('idTurnoClase');
            });
        }
    }
};
