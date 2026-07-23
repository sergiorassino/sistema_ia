<?php

namespace App\Support\EmailsMasivos;

use App\Mail\CorreoMasivoEstudiantesMail;
use App\Models\EmailEnviado;
use App\Models\EmailEscrito;
use App\Models\Profesor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class EnvioCorreoMasivo
{
    /**
     * Envía un mensaje ya guardado en emails_escritos (sin crear otro registro escrito).
     *
     * @param  list<array{
     *     email:string,
     *     tipo:string,
     *     idLegajo:int,
     *     idCurso:int,
     *     alumnoLabel:string,
     *     cursoLabel:string
     * }>  $destinatarios
     * @return array{ok:bool,mensaje:string,destinatarios:list<string>,idEmailEscrito:?int}
     */
    public static function ejecutarDesdeEscrito(
        EmailEscrito $escrito,
        Profesor $profesor,
        int $idNivel,
        int $idTerlec,
        array $destinatarios,
    ): array {
        $emailRemitente = trim((string) ($profesor->email ?? ''));
        $pass = trim((string) ($profesor->emailPass ?? ''));

        if ($emailRemitente === '' || ! filter_var($emailRemitente, FILTER_VALIDATE_EMAIL)) {
            return self::error('Configure su email en el legajo docente antes de enviar.');
        }
        if ($pass === '') {
            return self::error('Configure la contraseña de aplicación Gmail (emailPass) en su legajo docente.');
        }
        if ($destinatarios === []) {
            return self::error('No hay destinatarios con email para enviar.');
        }

        $n = count($destinatarios);
        if ($n > EmailsMasivosConfig::maxDestinatariosPorEnvio()) {
            return self::error(
                'La selección supera el máximo de ' . EmailsMasivosConfig::maxDestinatariosPorEnvio()
                . ' destinatarios por envío. Reduzca el alcance o realice varios envíos.'
            );
        }

        $asunto = mb_substr(trim((string) $escrito->subject), 0, 254);
        $html = (string) $escrito->text;
        $attached = (string) ($escrito->attached ?? '');
        $nombreRemitente = self::nombreColegio($idNivel);

        $emailsBcc = array_map(static fn (array $d) => $d['email'], $destinatarios);
        $pathsAbs = EmailsMasivosAdjuntosStorage::pathsAbsolutosCampana($idTerlec, (int) $escrito->id, $attached);

        if (EmailsMasivosConfig::simulado()) {
            return self::persistirEnviosSinSmtp(
                $escrito,
                $profesor,
                $idNivel,
                $idTerlec,
                $destinatarios,
                $emailsBcc,
            );
        }

        try {
            return DB::transaction(function () use (
                $escrito,
                $profesor,
                $idNivel,
                $idTerlec,
                $destinatarios,
                $emailsBcc,
                $asunto,
                $html,
                $attached,
                $emailRemitente,
                $pass,
                $nombreRemitente,
                $pathsAbs,
            ) {
                self::configurarMailerProfesor($emailRemitente, $pass);
                self::enviarBccChunks(
                    $asunto,
                    $html,
                    $emailRemitente,
                    $nombreRemitente,
                    $emailsBcc,
                    $pathsAbs,
                );

                self::insertarEnvios($profesor, $idNivel, $idTerlec, $destinatarios, $asunto, $html, $attached);

                return [
                    'ok' => true,
                    'mensaje' => 'Correo enviado con éxito.',
                    'destinatarios' => $emailsBcc,
                    'idEmailEscrito' => (int) $escrito->id,
                ];
            });
        } catch (Throwable $e) {
            Log::error('Envío correo masivo falló', [
                'profesor' => $profesor->id,
                'id_escrito' => $escrito->id,
                'error' => $e->getMessage(),
            ]);

            return self::error('ERROR AL ENVIAR EL CORREO: ' . mb_substr($e->getMessage(), 0, 500));
        }
    }

    /**
     * @param  list<string>  $bcc
     * @param  list<string>  $pathsAbs
     */
    private static function enviarBccChunks(
        string $asunto,
        string $html,
        string $emailRemitente,
        string $nombreRemitente,
        array $bcc,
        array $pathsAbs,
    ): void {
        $chunkSize = EmailsMasivosConfig::mailBccChunk();
        foreach (array_chunk($bcc, $chunkSize) as $chunk) {
            Mail::mailer('profesor_emails_masivos')->send(new CorreoMasivoEstudiantesMail(
                asunto: $asunto,
                htmlCuerpo: $html,
                emailRemitente: $emailRemitente,
                nombreRemitente: $nombreRemitente,
                bccDestinatarios: $chunk,
                adjuntosAbsolutos: $pathsAbs,
            ));
        }
    }

    private static function configurarMailerProfesor(string $email, string $pass): void
    {
        config([
            'mail.mailers.profesor_emails_masivos' => [
                'transport' => 'smtp',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => $email,
                'password' => $pass,
                'timeout' => null,
                'local_domain' => config('mail.mailers.smtp.local_domain'),
            ],
        ]);
    }

    /**
     * @param  list<array<string,mixed>>  $destinatarios
     * @param  list<string>  $listaOk
     * @return array{ok:bool,mensaje:string,destinatarios:list<string>,idEmailEscrito:?int}
     */
    private static function persistirEnviosSinSmtp(
        EmailEscrito $escrito,
        Profesor $profesor,
        int $idNivel,
        int $idTerlec,
        array $destinatarios,
        array $listaOk,
    ): array {
        try {
            return DB::transaction(function () use (
                $escrito,
                $profesor,
                $idNivel,
                $idTerlec,
                $destinatarios,
                $listaOk,
            ) {
                self::insertarEnvios(
                    $profesor,
                    $idNivel,
                    $idTerlec,
                    $destinatarios,
                    (string) $escrito->subject,
                    (string) $escrito->text,
                    (string) ($escrito->attached ?? ''),
                );

                Log::info('Correo masivo SIMULADO', [
                    'id_emails_escritos' => $escrito->id,
                    'destinatarios' => count($destinatarios),
                ]);

                return [
                    'ok' => true,
                    'mensaje' => 'Modo simulado: registros guardados sin envío SMTP real.',
                    'destinatarios' => $listaOk,
                    'idEmailEscrito' => (int) $escrito->id,
                ];
            });
        } catch (Throwable $e) {
            return self::error('ERROR AL REGISTRAR EL ENVÍO: ' . mb_substr($e->getMessage(), 0, 500));
        }
    }

    /**
     * @param  list<array<string,mixed>>  $destinatarios
     */
    private static function insertarEnvios(
        Profesor $profesor,
        int $idNivel,
        int $idTerlec,
        array $destinatarios,
        string $asunto,
        string $html,
        string $attached,
    ): void {
        $fechhora = now();
        foreach ($destinatarios as $d) {
            $idNivelEnvio = (int) ($d['idNivel'] ?? 0);
            if ($idNivelEnvio < 1) {
                $idNivelEnvio = $idNivel;
            }

            EmailEnviado::query()->create([
                'mailDestino' => $d['email'],
                'fechhora' => $fechhora,
                'idProfesores' => (int) $profesor->id,
                'idLegajos' => $d['idLegajo'],
                'idCursos' => $d['idCurso'],
                'idNiveles' => $idNivelEnvio,
                'idTerlec' => $idTerlec,
                'subject' => mb_substr(trim($asunto), 0, 254),
                'texto' => $html,
                'attached' => $attached,
            ]);
        }
    }

    /**
     * Nombre visible en el From del correo (institución del nivel, no el usuario SMTP).
     */
    private static function nombreColegio(int $idNivel): string
    {
        if ($idNivel > 0) {
            $insti = trim((string) (DB::table('ento')->where('idNivel', $idNivel)->value('insti') ?? ''));
            if ($insti !== '') {
                return $insti;
            }
        }

        $fallback = trim((string) schoolNombre());

        return $fallback !== '' ? $fallback : 'Institución';
    }

    /**
     * @return array{ok:bool,mensaje:string,destinatarios:list<string>,idEmailEscrito:?int}
     */
    private static function error(string $mensaje): array
    {
        return [
            'ok' => false,
            'mensaje' => $mensaje,
            'destinatarios' => [],
            'idEmailEscrito' => null,
        ];
    }
}
