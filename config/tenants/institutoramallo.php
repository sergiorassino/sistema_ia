<?php

/*
 | Instituto Ramallo — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=institutoramallo en el despliegue de ese colegio.
 */

return [
    'cuotas' => [
        // Los % de interés en 2.º/3.er venc. y post-venc. son totales del tramo, no diarios.
        'interes_mora_modo' => 'total',

        // Sin SIRO: sin código de pago electrónico ni barras/QR en cupones.
        'siro' => [
            'habilitado' => false,
        ],

        'facturacion_afip' => [
            'habilitado' => true,
            /** Factura al imputar pago (legacy Ramallo). */
            'modo' => 'pago',
            // Certificados WSAA/WSFE: carpeta y nombres de archivo en Parámetros del sistema (ento).
            // Respaldo opcional en tenant para desarrollo: cert_usuario_id, cert_key, cert_crt.
            'cbte_tipo' => 15,
            'nota_credito_tipo' => 12,
            'cbte_tipo_asociado' => 15,
            'produccion' => true,
            'simular' => false,
        ],
    ],

    'autogestion' => [
        /*
         | Menú de Alumnos — solo Gestión de Aranceles (misma UI que San Francisco de Asís).
         | Sin inicio, datos, ficha, calificaciones, inasistencias, horario ni comunicados.
         */
        'menu_inicio' => [
            'habilitado' => false,
        ],
        'actualizacion_datos' => [
            'habilitado' => false,
        ],
        'ficha_matricula' => [
            'habilitado' => false,
        ],
        'aranceles_escolares' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
            'menu_etiqueta' => 'Gestión de Aranceles',
        ],
        'horario_clase' => [
            'habilitado' => false,
        ],
        'consulta_calificaciones' => [
            'habilitado' => false,
        ],
        'informe_inasistencias' => [
            'habilitado' => false,
        ],
        'comunicaciones' => [
            'habilitado' => false,
        ],
    ],
];
