<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tema visible del catálogo: Proyectos extracurriculares → Actividad Institucional.
 *
 * Solo actualiza `permisos_ia` (órdenes 96, 109 y 110). No toca `profesores.permisos_ia`.
 * Equivalente: database/sql/rename_permisos_ia_actividad_institucional.sql
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
                    'descripcion' => $permiso['descripcion'],
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        DB::table('permisos_ia')->where('orden', 96)->update([
            'tema' => 'PROYECTOS EXTRACURRICULARES',
            'descripcion' => 'Aprobar proyectos extracurriculares presentados por docentes y comunicar a los involucrados (organizadores, docentes del curso y preceptores).',
        ]);
        DB::table('permisos_ia')->where('orden', 109)->update([
            'tema' => 'PROYECTOS EXTRACURRICULARES',
            'descripcion' => 'Ver y presentar proyectos extracurriculares propios en el Menú de Secretaría (listado, alta y edición).',
        ]);
        DB::table('permisos_ia')->where('orden', 110)->update([
            'tema' => 'PROYECTOS EXTRACURRICULARES',
            'descripcion' => 'Ver actividades extracurriculares aprobadas e imprimir autorizaciones de padres o tutores para los alumnos involucrados.',
        ]);
    }
};
