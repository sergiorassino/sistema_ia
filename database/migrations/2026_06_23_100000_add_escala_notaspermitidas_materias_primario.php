<?php

use App\Support\NivelSistema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notaspermitidas') && ! Schema::hasColumn('notaspermitidas', 'escala')) {
            Schema::table('notaspermitidas', function (Blueprint $table) {
                $table->unsignedTinyInteger('escala')->default(1)->after('nota');
            });

            DB::table('notaspermitidas')->update(['escala' => 1]);

            $idNivelPrimario = NivelSistema::PRIMARIO;

            $guionExiste = DB::table('notaspermitidas')
                ->where('idNivel', $idNivelPrimario)
                ->where('nota', '-')
                ->exists();

            if (! $guionExiste) {
                DB::table('notaspermitidas')->insert([
                    'idNivel' => $idNivelPrimario,
                    'nota' => '-',
                    'escala' => 1,
                ]);
            } else {
                DB::table('notaspermitidas')
                    ->where('idNivel', $idNivelPrimario)
                    ->where('nota', '-')
                    ->update(['escala' => 1]);
            }

            foreach (['ML', 'L', 'EL', 'P', 'EP', 'PPI'] as $nota) {
                $existe = DB::table('notaspermitidas')
                    ->where('idNivel', $idNivelPrimario)
                    ->where('nota', $nota)
                    ->exists();

                if (! $existe) {
                    DB::table('notaspermitidas')->insert([
                        'idNivel' => $idNivelPrimario,
                        'nota' => $nota,
                        'escala' => 2,
                    ]);
                } else {
                    DB::table('notaspermitidas')
                        ->where('idNivel', $idNivelPrimario)
                        ->where('nota', $nota)
                        ->update(['escala' => 2]);
                }
            }

            if (! DB::table('notaspermitidas')
                ->where('idNivel', $idNivelPrimario)
                ->where('nota', '-')
                ->where('escala', 2)
                ->exists()) {
                DB::table('notaspermitidas')->insert([
                    'idNivel' => $idNivelPrimario,
                    'nota' => '-',
                    'escala' => 2,
                ]);
            }
        }

        if (Schema::hasTable('materias') && ! Schema::hasColumn('materias', 'escala')) {
            Schema::table('materias', function (Blueprint $table) {
                $after = Schema::hasColumn('materias', 'infoCalif') ? 'infoCalif' : 'abrev';
                $table->unsignedTinyInteger('escala')->default(1)->after($after);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('materias') && Schema::hasColumn('materias', 'escala')) {
            Schema::table('materias', function (Blueprint $table) {
                $table->dropColumn('escala');
            });
        }

        if (! Schema::hasTable('notaspermitidas') || ! Schema::hasColumn('notaspermitidas', 'escala')) {
            return;
        }

        $idNivelPrimario = NivelSistema::PRIMARIO;

        DB::table('notaspermitidas')
            ->where('idNivel', $idNivelPrimario)
            ->where('escala', 2)
            ->whereIn('nota', ['ML', 'L', 'EL', 'P', 'EP', 'PPI'])
            ->delete();

        DB::table('notaspermitidas')
            ->where('idNivel', $idNivelPrimario)
            ->where('nota', '-')
            ->where('escala', 2)
            ->delete();

        DB::table('notaspermitidas')
            ->where('idNivel', $idNivelPrimario)
            ->where('nota', '-')
            ->where('escala', 1)
            ->delete();

        Schema::table('notaspermitidas', function (Blueprint $table) {
            $table->dropColumn('escala');
        });
    }
};
