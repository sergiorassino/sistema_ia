<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<int> */
    private const ORDENES = [
        PermisosIaCatalog::COOP_PARAMETRIZACION,
        PermisosIaCatalog::COOP_INGRESOS,
        PermisosIaCatalog::COOP_EGRESOS,
        PermisosIaCatalog::COOP_MOVIMIENTOS,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if (! in_array((int) $permiso['orden'], self::ORDENES, true)) {
                continue;
            }

            DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->whereIn('id', [72, 73, 74, 75])->delete();
        }
    }
};
