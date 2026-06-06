<?php

use App\Support\HorariosProfesores;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cursos') && ! Schema::hasColumn('cursos', 'idTurnoClase')) {
            Schema::table('cursos', function (Blueprint $table) {
                $table->unsignedTinyInteger('idTurnoClase')->nullable()->after('turno');
            });
        }

        if (Schema::hasColumn('cursos', 'idTurnoClase')) {
            foreach (DB::table('cursos')->whereNull('idTurnoClase')->cursor() as $row) {
                $text = trim((string) ($row->turno ?? ''));
                if ($text === '') {
                    continue;
                }
                $id = HorariosProfesores::inferirTurnoClaseDesdeCurso($text);
                if ($id > 0) {
                    DB::table('cursos')->where('Id', $row->Id)->update(['idTurnoClase' => $id]);
                }
            }
        }
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
