<?php

namespace App\Support\CalificacionesSecundario\Epq;

/**
 * Ítems del pie del informe de calificaciones EPQ secundario (tabla {@see itemsboletin}).
 * Réplica de las consultas legacy ScriptCase (tipo 8 = Ed. Física; sanciones sin filtro publicada).
 */
final class ItemsBoletinEpqSecundarioCatalogo
{
    /**
     * @return list<array{orden: int, etiqueta: string, fuente: string, condicion_where: string}>
     */
    public static function definiciones(): array
    {
        return [
            [
                'orden' => 1,
                'etiqueta' => 'INASISTENCIAS:  Justif.',
                'fuente' => 'inasistencias',
                'condicion_where' => "just = 'J' and tipo <> 8",
            ],
            [
                'orden' => 2,
                'etiqueta' => 'Inasistencias Injustificadas',
                'fuente' => 'inasistencias',
                'condicion_where' => "just = 'I' and tipo <> 8",
            ],
            [
                'orden' => 3,
                'etiqueta' => 'Total Inasistencias',
                'fuente' => 'inasistencias',
                'condicion_where' => "tipo <> 8 and (just = 'J' or just = 'I')",
            ],
            [
                'orden' => 4,
                'etiqueta' => 'Inasistencias a Educación Física',
                'fuente' => 'inasistencias',
                'condicion_where' => 'tipo = 8',
            ],
            [
                'orden' => 5,
                'etiqueta' => 'Apercibimientos Escritos',
                'fuente' => 'sanciones',
                'condicion_where' => 'idTipoSancion = 2',
            ],
            [
                'orden' => 6,
                'etiqueta' => 'Amonestaciones',
                'fuente' => 'sanciones',
                'condicion_where' => 'idTipoSancion = 1',
            ],
        ];
    }
}
