<?php

use App\Support\NivelSistema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notaspermitidas') || ! Schema::hasColumn('notaspermitidas', 'escala')) {
            return;
        }

        $idNivelPrimario = NivelSistema::PRIMARIO;

        $existe = DB::table('notaspermitidas')
            ->where('idNivel', $idNivelPrimario)
            ->where('nota', '-')
            ->where('escala', 2)
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('notaspermitidas')->insert([
            'idNivel' => $idNivelPrimario,
            'nota' => '-',
            'escala' => 2,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('notaspermitidas') || ! Schema::hasColumn('notaspermitidas', 'escala')) {
            return;
        }

        DB::table('notaspermitidas')
            ->where('idNivel', NivelSistema::PRIMARIO)
            ->where('nota', '-')
            ->where('escala', 2)
            ->delete();
    }
};
