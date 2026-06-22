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
            'cert_usuario_id' => '1',
            'cert_key' => 'privada_prod.key',
            'cert_crt' => 'Sistemalaboratorio_2bbae7f592f630ba.crt',
            'cbte_tipo' => 15,
            'produccion' => true,
            // Por ahora no envía a AFIP: genera comprobante simulado en BD.
            'simular' => true,
        ],
    ],

    'autogestion' => [
        /**
         * Cuotas pendientes y comprobante de pago — todos los niveles (sin niveles_deshabilitados).
         */
        'aranceles_escolares' => [
            'habilitado' => true,
            'implementacion' => 'institutoramallo',
        ],

        // Sin horario de clase en autogestión (ningún nivel).
        'horario_clase' => [
            'habilitado' => false,
        ],

        // Sin consulta de calificaciones en autogestión (ningún nivel).
        'consulta_calificaciones' => [
            'habilitado' => false,
        ],

        // Sin informe de inasistencias en autogestión (ningún nivel).
        'informe_inasistencias' => [
            'habilitado' => false,
        ],

        // Sin cuaderno de comunicados ni push en autogestión (ningún nivel).
        'comunicaciones' => [
            'habilitado' => false,
        ],
    ],
];
