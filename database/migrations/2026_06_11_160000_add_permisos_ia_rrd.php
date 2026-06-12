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

        $ordenes = [
            PermisosIaCatalog::RESERVA_MATERIAL_ADMIN,
            PermisosIaCatalog::RESERVA_MATERIAL_PROFESOR,
            PermisosIaCatalog::RESERVA_MATERIAL_LECTURA,
        ];

        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if (! in_array((int) $permiso['orden'], $ordenes, true)) {
                continue;
            }

            DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->whereIn('id', [68, 69, 70])->delete();
        }
    }
};
