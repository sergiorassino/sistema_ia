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

        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if (! in_array((int) $permiso['orden'], [
                PermisosIaCatalog::LEGAJOS_DOCENTES,
                PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO,
            ], true)) {
                continue;
            }

            DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);
        }

        if (! Schema::hasTable('profesores') || ! Schema::hasColumn('profesores', 'permisos_ia')) {
            return;
        }

        $ordenOrigen = PermisosIaCatalog::LEGAJOS_DOCENTES;
        $ordenNuevo = PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO;

        foreach (DB::table('profesores')->whereNotNull('permisos_ia')->get(['id', 'permisos_ia']) as $profesor) {
            $cadena = (string) $profesor->permisos_ia;
            if (($cadena[$ordenOrigen] ?? '0') !== '1') {
                continue;
            }

            while (strlen($cadena) <= $ordenNuevo) {
                $cadena .= '0';
            }

            if (($cadena[$ordenNuevo] ?? '0') === '1') {
                continue;
            }

            $chars = str_split($cadena);
            $chars[$ordenNuevo] = '1';

            DB::table('profesores')->where('id', $profesor->id)->update([
                'permisos_ia' => implode('', $chars),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 48)->delete();
        }
    }
};
