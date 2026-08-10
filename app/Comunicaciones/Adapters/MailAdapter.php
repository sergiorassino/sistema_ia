<?php

namespace App\Comunicaciones\Adapters;

use App\Jobs\EnviarComunicadoMailLoteJob;
use App\Mail\ComunicadoMail;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\ComMensajeEnvio;
use App\Models\Legajo;
use App\Models\Profesor;
use App\Support\Mail\MailInstitucionalConfig;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailAdapter
{
    /**
     * Envía el mismo correo a varios destinatarios: por defecto un solo SMTP por fragmento (BCC),
     * manteniendo un registro por destinatario en com_mensajes_envios.
     *
     * @param  list<ComMensajeDestinatario>  $destinatarios  Destinatarios que deben recibir email (ya filtrados por canal).
     */
    public static function enviarCorreoParaVariosDestinatarios(
        ComMensaje $mensaje,
        array $destinatarios,
        string $nombreColegio = ''
    ): void {
        $mensaje->loadMissing('hilo');
        MailInstitucionalConfig::aplicarParaNivel((int) ($mensaje->hilo?->id_nivel ?? 0) ?: null);

        $pairs = [];
        foreach ($destinatarios as $d) {
            if (! $d instanceof ComMensajeDestinatario) {
                continue;
            }
            $email = static::resolverEmail($d);
            if ($email === null || trim($email) === '') {
                static::registrar($d, 'no_aplicable', 'Sin dirección de correo disponible');

                continue;
            }
            $pairs[] = [
                'dest'  => $d,
                'email' => mb_strtolower(trim($email)),
            ];
        }

        if ($pairs === []) {
            return;
        }

        if (static::correoPorCola()) {
            $ids = [];
            foreach ($pairs as $p) {
                $envio = ComMensajeEnvio::create([
                    'id_mensaje_destinatario' => $p['dest']->id,
                    'medio'                   => 'email',
                    'estado'                  => 'pendiente',
                    'motivo'                  => null,
                    'enviado_at'              => null,
                ]);
                $ids[] = (int) $envio->id;
            }
            EnviarComunicadoMailLoteJob::dispatch($ids, (int) $mensaje->id, $nombreColegio)->afterCommit();

            return;
        }

        $agrupar = config('comunicaciones.mail_agrupar_bcc', true);

        if (! $agrupar) {
            foreach ($pairs as $p) {
                try {
                    Mail::to($p['email'])->send(new ComunicadoMail($mensaje, $p['dest'], $nombreColegio));
                    static::registrar($p['dest'], 'enviado');
                } catch (Throwable $e) {
                    static::registrar($p['dest'], 'fallido', mb_substr($e->getMessage(), 0, 250));
                }
            }

            return;
        }

        $chunkSize = max(1, (int) config('comunicaciones.mail_bcc_chunk_destinatarios', 50));
        foreach (array_chunk($pairs, $chunkSize) as $chunk) {
            $emailsUnicos = [];
            foreach ($chunk as $p) {
                $emailsUnicos[$p['email']] = true;
            }
            $bcc = array_keys($emailsUnicos);
            if ($bcc === []) {
                continue;
            }
            try {
                Mail::bcc($bcc)->send(new ComunicadoMail($mensaje, null, $nombreColegio));
                foreach ($chunk as $p) {
                    static::registrar($p['dest'], 'enviado');
                }
            } catch (Throwable $e) {
                $motivo = mb_substr($e->getMessage(), 0, 250);
                foreach ($chunk as $p) {
                    static::registrar($p['dest'], 'fallido', $motivo);
                }
            }
        }
    }

    /**
     * Procesa filas pendientes creadas para un lote (cola asíncrona).
     *
     * @param  list<int>  $idsComMensajeEnvio
     */
    public static function procesarColaCorreoPendiente(array $idsComMensajeEnvio, int $idMensaje, string $nombreColegio = ''): void
    {
        $idsComMensajeEnvio = array_values(array_unique(array_map('intval', $idsComMensajeEnvio)));
        if ($idsComMensajeEnvio === []) {
            return;
        }

        $envios = ComMensajeEnvio::query()
            ->whereIn('id', $idsComMensajeEnvio)
            ->where('medio', 'email')
            ->where('estado', 'pendiente')
            ->orderBy('id')
            ->get();

        if ($envios->isEmpty()) {
            return;
        }

        $mensaje = ComMensaje::query()->find($idMensaje);
        if ($mensaje === null) {
            static::marcarEnviosFallido($envios, 'Mensaje inexistente');

            return;
        }
        $mensaje->load('hilo');
        MailInstitucionalConfig::aplicarParaNivel((int) ($mensaje->hilo?->id_nivel ?? 0) ?: null);

        $pairs = [];
        foreach ($envios as $envio) {
            $dest = ComMensajeDestinatario::query()->find($envio->id_mensaje_destinatario);
            if ($dest === null) {
                $envio->update([
                    'estado'     => 'fallido',
                    'motivo'     => 'Destinatario inexistente',
                    'enviado_at' => null,
                ]);

                continue;
            }
            $email = static::resolverEmail($dest);
            if ($email === null || trim($email) === '') {
                $envio->update([
                    'estado'     => 'no_aplicable',
                    'motivo'     => 'Sin dirección de correo disponible',
                    'enviado_at' => null,
                ]);

                continue;
            }
            $pairs[] = [
                'envio' => $envio,
                'dest'  => $dest,
                'email' => mb_strtolower(trim($email)),
            ];
        }

        if ($pairs === []) {
            return;
        }

        $agrupar = config('comunicaciones.mail_agrupar_bcc', true);

        if (! $agrupar) {
            foreach ($pairs as $p) {
                try {
                    Mail::to($p['email'])->send(new ComunicadoMail($mensaje, $p['dest'], $nombreColegio));
                    $p['envio']->update([
                        'estado'     => 'enviado',
                        'motivo'     => null,
                        'enviado_at' => now(),
                    ]);
                } catch (Throwable $e) {
                    $p['envio']->update([
                        'estado'     => 'fallido',
                        'motivo'     => mb_substr($e->getMessage(), 0, 250),
                        'enviado_at' => null,
                    ]);
                }
            }

            return;
        }

        $chunkSize = max(1, (int) config('comunicaciones.mail_bcc_chunk_destinatarios', 50));
        foreach (array_chunk($pairs, $chunkSize) as $chunk) {
            $emailsUnicos = [];
            foreach ($chunk as $p) {
                $emailsUnicos[$p['email']] = true;
            }
            $bcc = array_keys($emailsUnicos);
            if ($bcc === []) {
                continue;
            }
            try {
                Mail::bcc($bcc)->send(new ComunicadoMail($mensaje, null, $nombreColegio));
                foreach ($chunk as $p) {
                    $p['envio']->update([
                        'estado'     => 'enviado',
                        'motivo'     => null,
                        'enviado_at' => now(),
                    ]);
                }
            } catch (Throwable $e) {
                $motivo = mb_substr($e->getMessage(), 0, 250);
                foreach ($chunk as $p) {
                    $p['envio']->update([
                        'estado'     => 'fallido',
                        'motivo'     => $motivo,
                        'enviado_at' => null,
                    ]);
                }
            }
        }
    }

    /**
     * Dirección de correo usada para el envío (p. ej. para diagnóstico).
     */
    public static function resolverDireccionCorreo(ComMensajeDestinatario $destinatario): ?string
    {
        return static::resolverEmail($destinatario);
    }

    private static function correoPorCola(): bool
    {
        if (! config('comunicaciones.queue_mail', false)) {
            return false;
        }

        $driver = (string) config('queue.default', 'sync');

        return $driver !== 'sync' && $driver !== 'null';
    }

    /**
     * @param  iterable<ComMensajeEnvio>  $envios
     */
    private static function marcarEnviosFallido(iterable $envios, string $motivo): void
    {
        foreach ($envios as $envio) {
            $envio->update([
                'estado'     => 'fallido',
                'motivo'     => mb_substr($motivo, 0, 250),
                'enviado_at' => null,
            ]);
        }
    }

    /**
     * Determina el email de contacto según el tipo de destinatario.
     *
     * Para familias: madre → padre → tutor (primer mail válido del legajo).
     * Para profesores: profesores.email / emailInsti.
     */
    private static function resolverEmail(ComMensajeDestinatario $destinatario): ?string
    {
        if ($destinatario->tipo_destinatario === 'profesor' && $destinatario->id_profesor) {
            $prof = Profesor::find($destinatario->id_profesor);
            return static::primerEmail([$prof?->email ?? null, $prof?->emailInsti ?? null]);
        }

        if ($destinatario->tipo_destinatario === 'familia' && $destinatario->id_legajo) {
            $legajo = Legajo::find($destinatario->id_legajo);
            if ($legajo === null) {
                return null;
            }

            return static::primerEmail([
                $legajo->emailmad ?? null,
                $legajo->emailpad ?? null,
                $legajo->emailtut ?? null,
            ]);
        }

        return null;
    }

    /** Retorna el primer email no vacío de la lista */
    private static function primerEmail(array $candidatos): ?string
    {
        foreach ($candidatos as $email) {
            $e = trim((string) ($email ?? ''));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                return $e;
            }
        }

        return null;
    }

    private static function registrar(
        ComMensajeDestinatario $destinatario,
        string $estado,
        ?string $motivo = null
    ): ComMensajeEnvio {
        return ComMensajeEnvio::create([
            'id_mensaje_destinatario' => $destinatario->id,
            'medio'                   => 'email',
            'estado'                  => $estado,
            'motivo'                  => $motivo,
            'enviado_at'              => $estado === 'enviado' ? now() : null,
        ]);
    }
}
