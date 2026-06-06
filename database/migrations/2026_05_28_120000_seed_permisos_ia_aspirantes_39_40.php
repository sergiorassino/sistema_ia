<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        $ordenes = [PermisosIaCatalog::ASPIRANTES_GESTION, PermisosIaCatalog::ASPIRANTES_CAMPOS];

        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if (! in_array((int) $permiso['orden'], $ordenes, true)) {
                continue;
            }

            DB::table('permisos_ia')->updateOrInsert(
                ['id' => $permiso['id']],
                [
                    'orden'       => (int) $permiso['orden'],
                    'tema'        => (string) $permiso['tema'],
                    'descripcion' => (string) $permiso['descripcion'],
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')->whereIn('id', [41, 42])->delete();
    }
};
