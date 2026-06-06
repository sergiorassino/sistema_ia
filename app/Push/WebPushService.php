<?php

namespace App\Push;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private const MAX_PAYLOAD_BYTES = 1024;
    public const MAX_MENSAJE_CARACTERES = 280;

    private static function getWebPush(): WebPush
    {
        $publicKey = trim((string) (config('push.vapid.public_key') ?? ''));
        $privateKey = trim((string) (config('push.vapid.private_key') ?? ''));

        if ($publicKey === '' || $privateKey === '') {
            throw new \RuntimeException('Faltan VAPID_PUBLIC_KEY y/o VAPID_PRIVATE_KEY en .env');
        }

        $subject = trim((string) (config('push.vapid.subject') ?? 'mailto:admin@ejemplo.edu'));
        if ($subject !== '' && preg_match('/^mailto:\s+/', $subject)) {
            $subject = 'mailto:' . trim((string) preg_replace('/^mailto:\s+/', '', $subject));
        }
        if ($subject === '' || $subject === 'mailto:') {
            $subject = 'mailto:admin@ejemplo.edu';
        }

        $webPush = new WebPush(
            [
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ],
            [],
            max(5, (int) config('push.http.timeout', 30)),
            static::guzzleOptionsParaPush(),
        );

        $webPush->setAutomaticPadding(false);

        return $webPush;
    }

    /**
     * Opciones del cliente Guzzle usado por minishlink/web-push (verificación SSL / CA bundle).
     *
     * @return array<string, mixed>
     */
    private static function guzzleOptionsParaPush(): array
    {
        $opts = [];

        $ca = trim((string) config('push.http.ca_bundle', ''));
        if ($ca === '') {
            foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $iniCa) {
                if (is_string($iniCa) && $iniCa !== '' && is_file($iniCa)) {
                    $ca = $iniCa;
                    break;
                }
            }
        }

        if ($ca !== '' && is_file($ca)) {
            $opts['verify'] = $ca;
        } elseif (config('push.http.verify', true) === false) {
            $opts['verify'] = false;
        }

        return $opts;
    }

    private static function cuerpoConPrefijo(string $nombreAlumno, string $tituloOperador, string $cuerpoOperador): string
    {
        $lineas = [];
        if ($nombreAlumno !== '') {
            $lineas[] = $nombreAlumno;
        }
        if ($tituloOperador !== '') {
            $lineas[] = $tituloOperador;
        }
        $lineas[] = '';
        $lineas[] = $cuerpoOperador;

        return implode("\n", $lineas);
    }

    private static function mensajeCortoParaUsuario(string $reason): string
    {
        if (stripos($reason, 'SSL certificate') !== false || stripos($reason, 'curl error 60') !== false) {
            return 'Certificado SSL (cURL 60): en el servidor falta el bundle de CA para notificaciones push. Configurá WEB_PUSH_CA_BUNDLE (cacert.pem) o curl.cainfo en php.ini.';
        }
        if (stripos($reason, '410') !== false || stripos($reason, 'Gone') !== false || stripos($reason, 'No such subscription') !== false) {
            return 'Suscripción expirada o ya no válida (410). Que el alumno reactive notificaciones en ese dispositivo.';
        }
        if (stripos($reason, '404') !== false || stripos($reason, 'Not Found') !== false) {
            return 'Suscripción no encontrada (404). Que el alumno reactive notificaciones en ese dispositivo.';
        }
        if (stripos($reason, '413') !== false || stripos($reason, 'Payload Too Large') !== false) {
            return 'Mensaje demasiado largo para el servidor de notificaciones.';
        }
        if (stripos($reason, '403') !== false || stripos($reason, 'Forbidden') !== false) {
            return 'Acceso denegado por el servidor de notificaciones (403).';
        }
        if (strlen($reason) > 120) {
            return 'Error del servidor de notificaciones. Que el alumno reactive notificaciones en ese dispositivo.';
        }
        return $reason;
    }

    private static function truncatePayload(string $title, string $body, string $url): string
    {
        $title = mb_substr($title, 0, 80);
        $url = mb_substr($url, 0, 200);

        $bodyLen = mb_strlen($body);
        for ($n = $bodyLen; $n >= 0; $n -= 50) {
            $b = $n > 0 ? mb_substr($body, 0, $n) . ($n < $bodyLen ? '…' : '') : '';
            $payload = json_encode(['title' => $title, 'body' => $b, 'url' => $url], JSON_UNESCAPED_UNICODE);
            if (is_string($payload) && strlen($payload) <= self::MAX_PAYLOAD_BYTES) {
                return $payload;
            }
        }

        return (string) json_encode(['title' => $title, 'body' => '', 'url' => $url], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<string> $userKeys
     * @return array{ok:int,fail:int,errors:list<string>,sent_user_keys?:list<string>,failed_user_keys?:array<string,string>}
     */
    public static function sendToUsers(array $userKeys, string $title, string $body, string $url, ?string $nombreColegio = null): array
    {
        $subscriptions = PushSubscriptionRepository::getByUserKeys($userKeys);
        $subscribedKeys = PushSubscriptionRepository::getSubscribedUserKeys($userKeys);

        if (empty($subscriptions)) {
            $noDevice = array_values(array_diff($userKeys, $subscribedKeys));
            return [
                'ok' => 0,
                'fail' => 0,
                'errors' => [],
                'sent_user_keys' => $subscribedKeys,
                'failed_user_keys' => array_fill_keys($noDevice, 'Sin dispositivo suscrito'),
            ];
        }

        $nombreColegio = $nombreColegio !== null ? trim($nombreColegio) : '';

        $byEndpoint = [];
        foreach ($subscriptions as $row) {
            $ep = $row['endpoint'];
            $uk = $row['user_key'] ?? '';
            if (! isset($byEndpoint[$ep])) {
                $byEndpoint[$ep] = ['row' => $row, 'user_keys' => []];
            }
            if ($uk !== '' && ! in_array($uk, $byEndpoint[$ep]['user_keys'], true)) {
                $byEndpoint[$ep]['user_keys'][] = $uk;
            }
        }

        $webPush = self::getWebPush();
        $endpointToUserKeys = [];

        $nombres = DestinatariosRepository::nombresPorUserKeys($userKeys);

        foreach ($byEndpoint as $endpoint => $data) {
            $row = $data['row'];
            $userKey = isset($row['user_key']) ? trim((string) $row['user_key']) : '';

            $nombreAlumno = $userKey !== '' ? (string) ($nombres[$userKey] ?? '') : '';
            $cuerpoCompleto = self::cuerpoConPrefijo($nombreAlumno, $title, $body);
            $tituloNotif = $nombreColegio !== '' ? mb_substr($nombreColegio, 0, 80) : 'Notificación';
            $payload = self::truncatePayload($tituloNotif, $cuerpoCompleto, $url);

            $sub = Subscription::create([
                'endpoint' => $row['endpoint'],
                'keys' => [
                    'auth' => $row['auth_key'],
                    'p256dh' => $row['p256dh_key'],
                ],
                'contentEncoding' => 'aes128gcm',
            ]);

            $webPush->queueNotification($sub, $payload);
            $endpointToUserKeys[$endpoint] = $data['user_keys'];
        }

        $ok = 0;
        $fail = 0;
        $errors = [];
        $failedUserKeys = [];
        $userKeysWithSuccess = [];

        foreach (array_diff($userKeys, $subscribedKeys) as $uk) {
            $failedUserKeys[$uk] = 'Sin dispositivo suscrito';
        }

        $reports = iterator_to_array($webPush->flush());
        foreach ($reports as $report) {
            $endpoint = $report->getEndpoint();
            $userKeysForEndpoint = $endpointToUserKeys[$endpoint] ?? [];
            if ($report->isSuccess()) {
                $ok++;
                foreach ($userKeysForEndpoint as $uk) {
                    $userKeysWithSuccess[$uk] = true;
                }
            } else {
                $fail++;
                $reason = $report->getReason();
                $errors[] = $reason;
                $msg = self::mensajeCortoParaUsuario($reason);
                foreach ($userKeysForEndpoint as $uk) {
                    $failedUserKeys[$uk] ??= $msg;
                }

                if ($report->isSubscriptionExpired()) {
                    PushSubscriptionRepository::deleteByEndpoint($endpoint);
                }
            }
        }

        foreach (array_keys($userKeysWithSuccess) as $uk) {
            unset($failedUserKeys[$uk]);
        }

        return [
            'ok' => $ok,
            'fail' => $fail,
            'errors' => $errors,
            'sent_user_keys' => $subscribedKeys,
            'failed_user_keys' => $failedUserKeys,
        ];
    }
}

