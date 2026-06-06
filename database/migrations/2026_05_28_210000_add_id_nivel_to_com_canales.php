<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('com_canales')) {
            return;
        }

        if (! Schema::hasColumn('com_canales', 'id_nivel')) {
            Schema::table('com_canales', function (Blueprint $table) {
                $table->unsignedInteger('id_nivel')->nullable()->after('id');
            });
        }

        // Quitar el índice viejo (solo emisor/receptor) antes de duplicar filas por nivel.
        if (static::indexExists('com_canales', 'uq_canal_par')) {
            Schema::table('com_canales', function (Blueprint $table) {
                $table->dropUnique('uq_canal_par');
            });
        }

        $niveles = DB::table('niveles')->orderBy('id')->pluck('id');
        $primerNivel = $niveles->first();

        if ($primerNivel !== null) {
            DB::table('com_canales')->whereNull('id_nivel')->update(['id_nivel' => $primerNivel]);
        }

        if ($niveles->count() > 1) {
            $pares = DB::table('com_canales')
                ->select('rol_emisor', 'rol_receptor')
                ->groupBy('rol_emisor', 'rol_receptor')
                ->get();

            foreach ($pares as $par) {
                $plantilla = DB::table('com_canales')
                    ->where('rol_emisor', $par->rol_emisor)
                    ->where('rol_receptor', $par->rol_receptor)
                    ->orderBy('id')
                    ->first();

                if ($plantilla === null) {
                    continue;
                }

                foreach ($niveles as $idNivel) {
                    $existe = DB::table('com_canales')
                        ->where('id_nivel', $idNivel)
                        ->where('rol_emisor', $plantilla->rol_emisor)
                        ->where('rol_receptor', $plantilla->rol_receptor)
                        ->exists();

                    if (! $existe) {
                        DB::table('com_canales')->insert([
                            'id_nivel'          => $idNivel,
                            'rol_emisor'        => $plantilla->rol_emisor,
                            'rol_receptor'      => $plantilla->rol_receptor,
                            'puede_iniciar'     => $plantilla->puede_iniciar,
                            'puede_responder'   => $plantilla->puede_responder,
                            'medios_permitidos' => $plantilla->medios_permitidos,
                            'activo'            => $plantilla->activo,
                            'created_at'        => $plantilla->created_at ?? now(),
                            'updated_at'        => now(),
                        ]);
                    }
                }
            }
        }

        if (Schema::hasColumn('com_canales', 'id_nivel') && $primerNivel !== null) {
            $pendientes = DB::table('com_canales')->whereNull('id_nivel')->count();
            if ($pendientes === 0) {
                DB::statement('ALTER TABLE `com_canales` MODIFY `id_nivel` INT UNSIGNED NOT NULL');
            }
        }

        if (! static::indexExists('com_canales', 'uq_canal_nivel_par')) {
            Schema::table('com_canales', function (Blueprint $table) {
                $table->unique(['id_nivel', 'rol_emisor', 'rol_receptor'], 'uq_canal_nivel_par');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('com_canales') || ! Schema::hasColumn('com_canales', 'id_nivel')) {
            return;
        }

        if (static::indexExists('com_canales', 'uq_canal_nivel_par')) {
            Schema::table('com_canales', function (Blueprint $table) {
                $table->dropUnique('uq_canal_nivel_par');
            });
        }

        foreach (DB::table('com_canales')->select('rol_emisor', 'rol_receptor')->distinct()->get() as $par) {
            $ids = DB::table('com_canales')
                ->where('rol_emisor', $par->rol_emisor)
                ->where('rol_receptor', $par->rol_receptor)
                ->orderBy('id_nivel')
                ->pluck('id');
            if ($ids->count() > 1) {
                DB::table('com_canales')->whereIn('id', $ids->slice(1)->all())->delete();
            }
        }

        Schema::table('com_canales', function (Blueprint $table) {
            $table->dropColumn('id_nivel');
        });

        if (! static::indexExists('com_canales', 'uq_canal_par')) {
            Schema::table('com_canales', function (Blueprint $table) {
                $table->unique(['rol_emisor', 'rol_receptor'], 'uq_canal_par');
            });
        }
    }

    private static function indexExists(string $table, string $index): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$table, $index]
        );

        return $row !== null;
    }
};
