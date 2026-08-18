<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    | En APP_ENV=local el SMTP real se desactiva (MailDesarrollo): los mails van al log.
    | true solo para una prueba puntual en la PC. Producción no usa este flag.
    */
    'forzar_smtp_en_local' => filter_var(env('MAIL_FORCE_REAL', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "log", "array", "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        /**
         * Cooperadora — recibos de ingreso (origen estudiantes). Credenciales en COOP_MAIL_* (.env).
         * No usar para cuaderno de comunicados ni otros envíos pedagógicos.
         */
        'cooperadora' => [
            'transport' => 'smtp',
            'host' => env('COOP_MAIL_HOST', '127.0.0.1'),
            'port' => env('COOP_MAIL_PORT', 587),
            'encryption' => env('COOP_MAIL_ENCRYPTION', 'tls'),
            'username' => env('COOP_MAIL_USERNAME'),
            'password' => env('COOP_MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        /**
         * Sistemas Escolares — recuperación de contraseña y otros avisos transversales.
         * Cuenta compartida por todos los colegios: SE_CLIENTES_MAIL_* (.env).
         */
        'sistemas_escolares' => [
            'transport' => 'smtp',
            'host' => env('SE_CLIENTES_MAIL_HOST', 'smtp.gmail.com'),
            'port' => env('SE_CLIENTES_MAIL_PORT', 587),
            'encryption' => env('SE_CLIENTES_MAIL_ENCRYPTION', 'tls'),
            'username' => env('SE_CLIENTES_MAIL_USERNAME'),
            'password' => env('SE_CLIENTES_MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

];
