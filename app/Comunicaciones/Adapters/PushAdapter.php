<?php

namespace App\Comunicaciones\Adapters;

use App\Push\PushSubscriptionRepository;
use App\Push\WebPushService;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\ComMensajeEnvio;

class PushAdapter
{
    /**
     * Envía notificación push a un destinatario y registra el resultado.
     *
     * Para familias: user_key = legajo.id (igual que el portal de alumnos).
     * Para profesores: user_key = 'p:{profesor.id}' — requiere que el profesor
     *   haya suscripto con ese prefijo. Pendiente: UI de suscripción push para profesores.
     */
    public static function enviar(
        ComMensajeDestinatario $destinatario,
        ComMensaje $mensaje,
        string $nombreColegio = ''
    ): ComMensajeEnvio {
        $userKey = static::resolverUserKey($destinatario);

        if ($userKey === null || ! PushSubscriptionRepository::hasAnyForUserKey($userKey)) {
            return static::registrar($destinatario, 'no_aplicable', 'Sin suscripción push activa');
        }

        $hiloId     = (int) $destinatario->id_hilo;
        $urlDestino = route('alumnos.comunicaciones.abrir', ['id' => $hiloId]);

        $asunto    = mb_substr((string) $mensaje->hilo?->asunto, 0, 80);
        $contenido = mb_substr((string) $mensaje->contenido, 0, 280);

        $result = WebPushService::sendToUsers(
            [$userKey],
            $asunto,
            $contenido,
            $urlDestino,
            $nombreColegio !== '' ? $nombreColegio : null
        );

        $ok = ($result['ok'] ?? 0) > 0;
        $estado = $ok ? 'enviado' : 'fallido';
        $motivo = $ok ? null : (implode('; ', $result['errors'] ?? []) ?: 'Error desconocido');

        return static::registrar($destinatario, $estado, $motivo);
    }

    private static function resolverUserKey(ComMensajeDestinatario $destinatario): ?string
    {
        if ($destinatario->tipo_destinatario === 'familia' && $destinatario->id_legajo) {
            return (string) $destinatario->id_legajo;
        }
        if ($destinatario->tipo_destinatario === 'profesor' && $destinatario->id_profesor) {
            return 'p:' . $destinatario->id_profesor;
        }
        return null;
    }

    private static function registrar(
        ComMensajeDestinatario $destinatario,
        string $estado,
        ?string $motivo
    ): ComMensajeEnvio {
        return ComMensajeEnvio::create([
            'id_mensaje_destinatario' => $destinatario->id,
            'medio'                   => 'push',
            'estado'                  => $estado,
            'motivo'                  => $motivo,
            'enviado_at'              => $estado === 'enviado' ? now() : null,
        ]);
    }
}
