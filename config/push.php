<?php

return [
    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@ejemplo.edu'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cliente HTTP (Guzzle) hacia FCM / push
    |--------------------------------------------------------------------------
    | En Windows suele aparecer "cURL error 60: SSL certificate problem" si no hay
    | bundle de CA. Definí WEB_PUSH_CA_BUNDLE con ruta absoluta a cacert.pem
    | (https://curl.se/ca/cacert.pem) o configurá curl.cainfo en php.ini.
    |
    | WEB_PUSH_SSL_VERIFY=false solo para entornos de prueba (inseguro).
    */
    'http' => [
        'timeout'   => (int) env('WEB_PUSH_HTTP_TIMEOUT', 30),
        'verify'    => filter_var(env('WEB_PUSH_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
        'ca_bundle' => env('WEB_PUSH_CA_BUNDLE', ''),
    ],
];

