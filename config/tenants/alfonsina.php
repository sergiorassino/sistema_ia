<?php

/*
 | Alfonsina — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=alfonsina en el despliegue de ese colegio.
 |
 | Parte diario estándar: 8 renglones de ausentes (más altos que los 12 por defecto).
 */

return [
    'parte_diario' => [
        'renglones_ausentes' => 8,
    ],

    'portal_docente' => [
        'menu' => [
            'secundario' => [
                'cuaderno_seguimiento_aulico' => true,
            ],
        ],
    ],
];
