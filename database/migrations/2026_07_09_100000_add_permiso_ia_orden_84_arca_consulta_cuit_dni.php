<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso orden 84 — Consulta CUIT por DNI (ARCA Padrón A13).
 *
 * Equivalente a database/sql/permiso_ia_orden_84_arca_consulta_cuit_dni.sql
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    private const ORDEN = PermisosIaCatalog::ADMIN_ARCA_CONSULTA_CUIT_DNI;

    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        $permiso = collect(PermisosIaCatalog::definicionCatalogo())
            ->firstWhere('orden', self::ORDEN);

        if ($permiso === null) {
            return;
        }

        DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);

        if (! Schema::hasTable('profesores') || ! Schema::hasColumn('profesores', 'permisos_ia')) {
            return;
        }

        $longitudMinima = self::ORDEN + 1;

        DB::table('profesores')
            ->whereNotNull('permisos_ia')
            ->orderBy('id')
            ->chunkById(200, function ($profesores) use ($longitudMinima): void {
                foreach ($profesores as $profesor) {
                    $cadena = (string) $profesor->permisos_ia;
                    if (strlen($cadena) >= $longitudMinima) {
                        continue;
                    }

                    DB::table('profesores')
                        ->where('id', $profesor->id)
                        ->update([
                            'permisos_ia' => str_pad($cadena, $longitudMinima, '0'),
                        ]);
                }
            });

        DB::table('profesores')
            ->whereNull('permisos_ia')
            ->update([
                'permisos_ia' => str_repeat('0', $longitudMinima),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 84)->delete();
        }
    }
};
