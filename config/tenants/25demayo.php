<?php

/*
 | 25 de Mayo — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=25demayo en el despliegue de ese colegio.
 */

return [
    'institucional' => [
        'membrete_subtitulo' => 'DE ENSEÑANZA PRIVADA',
        'membrete_adscripcion' => 'Adscripto a la Provincia Ley Nacional Nº 24049/91 y Provincial Nº 8253/92',
    ],

    'portal_docente' => [
        'menu' => [
            'inicial' => [
                'horario' => true,
            ],
            'primario' => [
                'horario' => true,
            ],
            'secundario' => [
                'horario' => true,
            ],
        ],
    ],

    'cuotas' => [
        'facturacion_afip' => [
            'habilitado' => true,
            'modo' => 'devengamiento',
            // Certificados WSAA/WSFE: carpeta y nombres de archivo en Parámetros del sistema (ento).
            'cbte_tipo' => 15,
            'nota_credito_tipo' => 12,
            'cbte_tipo_asociado' => 15,
            'produccion' => true,
            // Prueba local: no llama a AFIP (CAE simulado). Para homologación real: simular => false.
            'simular' => true,
        ],
    ],
];
