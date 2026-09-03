<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     | Áulica External API (deuda / cuenta corriente).
     | Credenciales solo en .env del despliegue (nunca en config/tenants).
     | Activación por colegio: config('tenant.aulica_deuda.habilitado').
     */
    'aulica' => [
        'username' => env('AULICA_USERNAME'),
        'password' => env('AULICA_PASSWORD'),
        'codigo' => env('AULICA_CODIGO'),
        'ambiente' => env('AULICA_AMBIENTE'),
        'timeout' => (int) env('AULICA_TIMEOUT', 15),
        'ca_bundle' => env('AULICA_CA_BUNDLE', env('WEB_PUSH_CA_BUNDLE')),
        'ssl_verify' => filter_var(env('AULICA_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
