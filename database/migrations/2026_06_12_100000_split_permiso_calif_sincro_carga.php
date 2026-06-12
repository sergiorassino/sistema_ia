<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa el permiso 9 (antes sincro + carga) en:
 * - orden 9: importar desde CIDI/GE
 * - orden 71: carga manual
 *
 * Usuarios con el bit 9 activo reciben también el bit 71 (compatibilidad).
 */
return new class extends Migration
{
    private const ORDEN_SINCRO = 9;

    private const ORDEN_CARGA = 71;

    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if (! in_array((int) $permiso['orden'], [self::ORDEN_SINCRO, self::ORDEN_CARGA], true)) {
                continue;
            }

            DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);
        }

        if (! Schema::hasTable('profesores') || ! Schema::hasColumn('profesores', 'permisos_ia')) {
            return;
        }

        DB::table('profesores')
            ->whereNotNull('permisos_ia')
            ->orderBy('id')
            ->chunkById(200, function ($profesores): void {
                foreach ($profesores as $profesor) {
                    $permisos = (string) $profesor->permisos_ia;
                    if ($permisos === '' || strlen($permisos) <= self::ORDEN_SINCRO || ($permisos[self::ORDEN_SINCRO] ?? '0') !== '1') {
                        continue;
                    }

                    $chars = str_split(str_pad($permisos, self::ORDEN_CARGA + 1, '0'));
                    $chars[self::ORDEN_CARGA] = '1';

                    DB::table('profesores')
                        ->where('id', $profesor->id)
                        ->update(['permisos_ia' => implode('', $chars)]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 71)->delete();
        }

        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')->updateOrInsert(
            ['id' => 10],
            [
                'orden' => self::ORDEN_SINCRO,
                'tema' => 'CALIFICACIONES SECUNDARIO',
                'descripcion' => 'Importar calificaciones desde CIDI/GE y carga manual de calificaciones (secundario).',
            ],
        );
    }
};
