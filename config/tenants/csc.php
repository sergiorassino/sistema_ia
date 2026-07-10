<?php

/*
 | CSC — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=csc en el despliegue de ese colegio.
 */

return [
    'cuotas' => [
        'facturacion_afip' => [
            'habilitado' => true,
            'modo' => 'devengamiento',
            // Certificados WSAA/WSFE: carpeta y nombres de archivo en Parámetros del sistema (ento).
            'cbte_tipo' => 11,
            'nota_credito_tipo' => 12,
            'cbte_tipo_asociado' => 11,
            'produccion' => true,
            // Prueba local / homologación sin llamar a AFIP (CAE simulado). Para emisión real: simular => false.
            'simular' => true,
        ],
    ],
];
