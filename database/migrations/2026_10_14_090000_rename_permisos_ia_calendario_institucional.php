<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tema visible del catálogo: Actividad Institucional → Calendario Institucional.
 *
 * Solo actualiza `permisos_ia` (órdenes 96, 109 y 110). No toca `profesores.permisos_ia`.
 * Equivalente: database/sql/rename_permisos_ia_calendario_institucional.sql
 */
return new class extends Migration
{
    /** @var list<int> */
    private const ORDENES = [
        PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_APROBAR,
        PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_VER,
        PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_DOCUMENTOS,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        $catalogo = collect(PermisosIaCatalog::definicionCatalogo())->keyBy('orden');

        foreach (self::ORDENES as $orden) {
            $permiso = $catalogo->get($orden);
            if ($permiso === null) {
                continue;
            }

            DB::table('permisos_ia')
                ->where('orden', $orden)
                ->update([
                    'tema' => $permiso['tema'],
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')
            ->whereIn('orden', self::ORDENES)
            ->update([
                'tema' => 'ACTIVIDAD INSTITUCIONAL',
            ]);
    }
};
