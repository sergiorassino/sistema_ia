<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa el permiso 44 (antes aceptación + documentos familia) en:
 * - orden 44: documentos de aceptación (PDF por nivel)
 * - orden 83: documentos a subir (familia)
 *
 * Usuarios con el bit 44 activo reciben también el bit 83 (compatibilidad).
 */
return new class extends Migration
{
    private const ORDEN_ACEPTACION = 44;

    private const ORDEN_DOCUMENTOS_ESTUDIANTE = 83;

    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if (! in_array((int) $permiso['orden'], [
                self::ORDEN_ACEPTACION,
                self::ORDEN_DOCUMENTOS_ESTUDIANTE,
            ], true)) {
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
                    if ($permisos === '' || strlen($permisos) <= self::ORDEN_ACEPTACION || ($permisos[self::ORDEN_ACEPTACION] ?? '0') !== '1') {
                        continue;
                    }

                    $chars = str_split(str_pad($permisos, self::ORDEN_DOCUMENTOS_ESTUDIANTE + 1, '0'));
                    $chars[self::ORDEN_DOCUMENTOS_ESTUDIANTE] = '1';

                    DB::table('profesores')
                        ->where('id', $profesor->id)
                        ->update(['permisos_ia' => implode('', $chars)]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 83)->delete();
        }
    }
};
