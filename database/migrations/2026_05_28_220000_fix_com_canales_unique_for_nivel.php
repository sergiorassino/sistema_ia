<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige instalaciones donde se agregó id_nivel pero quedó el índice uq_canal_par (solo emisor/receptor).
 */
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

        $primerNivel = DB::table('niveles')->orderBy('id')->value('id');
        if ($primerNivel !== null) {
            DB::table('com_canales')->whereNull('id_nivel')->update(['id_nivel' => $primerNivel]);
        }

        $niveles = DB::table('niveles')->orderBy('id')->pluck('id');
        if ($niveles->count() > 1) {
            $sinNivel = DB::table('com_canales')->whereNull('id_nivel')->get();
            foreach ($sinNivel as $canal) {
                $primero = true;
                foreach ($niveles as $idNivel) {
                    if ($primero) {
                        DB::table('com_canales')->where('id', $canal->id)->update(['id_nivel' => $idNivel]);
                        $primero = false;
                    } else {
                        $existe = DB::table('com_canales')
                            ->where('id_nivel', $idNivel)
                            ->where('rol_emisor', $canal->rol_emisor)
                            ->where('rol_receptor', $canal->rol_receptor)
                            ->exists();
                        if (! $existe) {
                            DB::table('com_canales')->insert([
                                'id_nivel'          => $idNivel,
                                'rol_emisor'        => $canal->rol_emisor,
                                'rol_receptor'      => $canal->rol_receptor,
                                'puede_iniciar'     => $canal->puede_iniciar,
                                'puede_responder'   => $canal->puede_responder,
                                'medios_permitidos' => $canal->medios_permitidos,
                                'activo'            => $canal->activo,
                                'created_at'        => $canal->created_at ?? now(),
                                'updated_at'        => now(),
                            ]);
                        }
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

        if (static::indexExists('com_canales', 'uq_canal_par')) {
            Schema::table('com_canales', function (Blueprint $table) {
                $table->dropUnique('uq_canal_par');
            });
        }

        if (! static::indexExists('com_canales', 'uq_canal_nivel_par')) {
            Schema::table('com_canales', function (Blueprint $table) {
                $table->unique(['id_nivel', 'rol_emisor', 'rol_receptor'], 'uq_canal_nivel_par');
            });
        }
    }

    public function down(): void
    {
        // Sin reversión automática: evitar pérdida de canales por nivel.
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
