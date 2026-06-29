<?php

namespace App\Support\CalificacionesSecundario\Epq;

/**
 * Campos y etiquetas — carga de calificaciones secundario EPQ.
 */
final class CalificacionesEpqSecundarioCatalogo
{
    public const IMPLEMENTACION = 'epq';

    /** Orden de columnas en la planilla (de izquierda a derecha). */
    public const CAMPOS_NOTA = [
        'ic07', 'ic14', 'ic31', 'ic21', 'ic28', 'ic32', 'ic33', 'ic34', 'dic', 'feb',
    ];

    /** Columnas con mayor ancho y etiqueta en negrita (1º/2º cuatrimestre y nota final). */
    public const CAMPO_CUAT_1 = 'ic31';

    public const CAMPO_CUAT_2 = 'ic32';

    public const CAMPO_NOTA_FINAL = 'ic34';

    /**
     * @return list<array{label: string, cols: list<array{field: string, label: string}>}>
     */
    public static function gruposCuatrimestre(): array
    {
        return [
            [
                'label' => '1º CUATRIMESTRE',
                'cols' => [
                    ['field' => 'ic07', 'label' => '1º INF'],
                    ['field' => 'ic14', 'label' => '2º INF'],
                    ['field' => 'ic31', 'label' => '1º CUAT'],
                ],
            ],
            [
                'label' => '2º CUATRIMESTRE',
                'cols' => [
                    ['field' => 'ic21', 'label' => '3º INF'],
                    ['field' => 'ic28', 'label' => '4º INF'],
                    ['field' => 'ic32', 'label' => '2º CUAT'],
                ],
            ],
        ];
    }

    /**
     * Columnas finales (sin subgrupo de cuatrimestre).
     *
     * @return list<array{field: string, label: string}>
     */
    public static function columnasFinales(): array
    {
        return [
            ['field' => 'ic33', 'label' => 'Ev. Int.'],
            ['field' => 'ic34', 'label' => 'Nota Final'],
            ['field' => 'dic', 'label' => 'Dic'],
            ['field' => 'feb', 'label' => 'Feb'],
        ];
    }

    public static function etiquetaCampoNota(string $campo): string
    {
        foreach (array_merge(self::gruposCuatrimestre(), [['cols' => self::columnasFinales()]]) as $grupo) {
            foreach ($grupo['cols'] as $col) {
                if ($col['field'] === $campo) {
                    return $col['label'];
                }
            }
        }

        return $campo;
    }

    public static function esCampoDestacado(string $campo): bool
    {
        return in_array($campo, [self::CAMPO_CUAT_1, self::CAMPO_CUAT_2, self::CAMPO_NOTA_FINAL], true);
    }
}
