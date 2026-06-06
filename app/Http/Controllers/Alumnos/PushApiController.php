<?php

namespace App\Http\Controllers\Alumnos;

use App\Http\Controllers\Controller;
use App\Push\PushSubscriptionRepository;
use App\Push\PushUserKey;
use App\Push\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushApiController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->json()->all();
        if (
            ! is_array($data)
            || empty($data['endpoint'])
            || empty($data['keys']['auth'])
            || empty($data['keys']['p256dh'])
        ) {
            return response()->json(['ok' => false, 'error' => 'Datos de suscripción inválidos'], 400);
        }

        $userKey = PushUserKey::forAuthenticatedUser();
        $userAgent = $request->userAgent();
        $deviceType = PushSubscriptionRepository::deviceTypeFromUserAgent($userAgent);

        $clientHints = null;
        if (! empty($data['client_hints'])) {
            $clientHints = is_string($data['client_hints']) ? $data['client_hints'] : json_encode($data['client_hints']);
        } else {
            $clientHints = PushSubscriptionRepository::clientHintsFromUserAgent($userAgent);
        }

        PushSubscriptionRepository::save(
            (string) $data['endpoint'],
            (string) $data['keys']['auth'],
            (string) $data['keys']['p256dh'],
            $userKey,
            $deviceType,
            $userAgent,
            $clientHints
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $data = $request->json()->all();
        if (! is_array($data) || empty($data['endpoint'])) {
            return response()->json(['ok' => false, 'error' => 'Falta endpoint'], 400);
        }

        PushSubscriptionRepository::deleteByEndpoint((string) $data['endpoint']);

        return response()->json(['ok' => true]);
    }

    public function send(Request $request)
    {
        $title = (string) ($request->input('title') ?? 'Notificación');
        $body = (string) ($request->input('body') ?? '');
        $url = (string) ($request->input('url') ?? route('alumnos.home'));

        $userKeys = [];
        $raw = $request->input('user_keys');
        if (is_array($raw)) {
            $userKeys = array_values(array_filter(array_map('strval', $raw)));
        } elseif (is_string($raw) && trim($raw) !== '') {
            $userKeys = array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        $nombreColegio = (string) (studentCtx()->insti ?? 'Notificación');

        if (empty($userKeys)) {
            // En autogestión, por defecto enviamos al alumno logueado.
            $userKeys = [(string) Auth::guard('alumno')->id()];
        }

        $result = WebPushService::sendToUsers($userKeys, $title, $body, $url, $nombreColegio);

        return response()->json([
            'ok' => true,
            'sent' => $result['ok'],
            'failed' => $result['fail'],
            'errors' => $result['errors'],
            'sent_user_keys' => $result['sent_user_keys'] ?? [],
            'failed_user_keys' => $result['failed_user_keys'] ?? [],
        ]);
    }
}
