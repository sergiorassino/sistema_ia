<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComPermisosSeeder extends Seeder
{
    /**
     * Catálogo de permisos del módulo de comunicaciones en permisos_ia (órdenes 3–8).
     *
     * El middleware `permiso:N` y tienePermiso(N) usan profesores.permisos_ia.
     */
    public function run(): void
    {
        $permisos = [
            [
                'id'          => 4,
                'orden'       => 3,
                'tema'        => 'COMUNICACIONES',
                'descripcion' => 'Ver la bandeja de comunicados y los hilos de conversación.',
            ],
            [
                'id'          => 5,
                'orden'       => 4,
                'tema'        => 'COMUNICACIONES',
                'descripcion' => 'Iniciar nuevos comunicados hacia familias.',
            ],
            [
                'id'          => 6,
                'orden'       => 5,
                'tema'        => 'COMUNICACIONES - CONFIG',
                'descripcion' => 'Administrar la configuración de canales (quién puede comunicarse con quién y por qué medios).',
            ],
            [
                'id'          => 7,
                'orden'       => 6,
                'tema'        => 'COMUNICACIONES',
                'descripcion' => 'Borrar mensajes propios en un hilo.',
            ],
            [
                'id'          => 8,
                'orden'       => 7,
                'tema'        => 'COMUNICACIONES',
                'descripcion' => 'Borrar mensajes de otros participantes en un hilo.',
            ],
            [
                'id'          => 9,
                'orden'       => 8,
                'tema'        => 'COMUNICACIONES',
                'descripcion' => 'Acceder a la bandeja de revisión de comunicados.',
            ],
        ];

        foreach ($permisos as $permiso) {
            DB::table('permisos_ia')->updateOrInsert(
                ['id' => $permiso['id']],
                $permiso
            );
        }
    }
}
