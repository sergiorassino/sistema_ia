<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso orden 89 — Editar Observación Factura (ARCA / comprobante AFIP).
 *
 * Equivalente a database/sql/permiso_ia_orden_89_arca_obs_factura.sql
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    private const ORDEN = PermisosIaCatalog::ADMIN_ARCA_OBS_FACTURA;

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
            DB::table('permisos_ia')->where('id', 89)->delete();
        }
    }
};
