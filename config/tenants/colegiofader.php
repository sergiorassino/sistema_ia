<?php

/*
 | IPEM N° 206 Fernando Fader — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=colegiofader en el despliegue de ese colegio.
 */

return [
    'login' => [
        'niveles_ids' => [3],
    ],

    /**
     * Recibos cooperadora — envío real al pagador (COOP_MAIL_* en .env del despliegue).
     * Otros tenants no declaran este bloque: quedan con simulado true (default).
     */
    'cooperadora' => [
        'recibo_email' => [
            'simulado' => false,
        ],
    ],

    'emails_masivos' => [
        'simulado' => false,
    ],
];
