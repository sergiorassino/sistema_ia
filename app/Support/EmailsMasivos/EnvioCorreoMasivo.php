<?php

namespace App\Support\EmailsMasivos;

use App\Mail\CorreoMasivoEstudiantesMail;
use App\Models\EmailEnviado;
use App\Models\EmailEscrito;
use App\Models\Profesor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class EnvioCorreoMasivo
{
    /**
     * @param  list<array{
     *     email:string,
     *     tipo:string,
     *     idLegajo:int,
     *     idCurso:int,
     *     alumnoLabel:string,
     *     cursoLabel:string
     * }>  $destinatarios
     * @param  list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile>  $archivosTemp
     * @return array{ok:bool,mensaje:string,destinatarios:list<string>,idEmailEscrito:?int}
     */
    public static function ejecutar(
        Profesor $profesor,
        int $idNivel,
        int $idTerlec,
        string $asunto,
        string $html,
        array $destinatarios,
        array $archivosTemp = [],
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

        $nombresPreview = array_map(
            static fn ($f) => EmailsMasivosAdjuntosStorage::nombreSeguro($f->getClientOriginalName()),
            $archivosTemp,
        );
        if ($errAdj = EmailsMasivosAdjuntosStorage::validarListaNombres($nombresPreview)) {
            return self::error($errAdj);
        }

        $asunto = mb_substr(trim($asunto), 0, 254);
        $attachedStr = implode('|', $nombresPreview);
        $nombreRemitente = trim(($profesor->apellido ?? '') . ', ' . ($profesor->nombre ?? ''));
        if ($nombreRemitente === ',') {
            $nombreRemitente = 'Institución';
        }

        $emailsBcc = array_map(static fn (array $d) => $d['email'], $destinatarios);
        $listaOk = $emailsBcc;

        if (EmailsMasivosConfig::simulado()) {
            return self::persistirSinSmtp(
                $profesor,
                $idNivel,
                $idTerlec,
                $asunto,
                $html,
                $attachedStr,
                $destinatarios,
                $archivosTemp,
                $listaOk,
            );
        }

        try {
            return DB::transaction(function () use (
                $profesor,
                $idNivel,
                $idTerlec,
                $asunto,
                $html,
                $attachedStr,
                $destinatarios,
                $archivosTemp,
                $emailsBcc,
                $listaOk,
                $emailRemitente,
                $pass,
                $nombreRemitente,
            ) {
                $escrito = EmailEscrito::query()->create([
                    'subject' => $asunto,
                    'text' => $html,
                    'attached' => $attachedStr,
                ]);

                $adjuntos = EmailsMasivosAdjuntosStorage::guardarParaCampana(
                    $idTerlec,
                    (int) $escrito->id,
                    $archivosTemp,
                );

                $pathsAbs = [];
                $disk = Storage::disk(EmailsMasivosAdjuntosStorage::DISK);
                foreach ($adjuntos['paths'] as $rel) {
                    $pathsAbs[] = $disk->path($rel);
                }

                self::configurarMailerProfesor($emailRemitente, $pass);
                self::enviarBccChunks(
                    $asunto,
                    $html,
                    $emailRemitente,
                    $nombreRemitente,
                    $emailsBcc,
                    $pathsAbs,
                );

                $fechhora = now();
                foreach ($destinatarios as $d) {
                    EmailEnviado::query()->create([
                        'mailDestino' => $d['email'],
                        'fechhora' => $fechhora,
                        'idProfesores' => (int) $profesor->id,
                        'idLegajos' => $d['idLegajo'],
                        'idCursos' => $d['idCurso'],
                        'idNiveles' => $idNivel,
                        'idTerlec' => $idTerlec,
                        'subject' => $asunto,
                        'texto' => $html,
                        'attached' => $adjuntos['attached'],
                    ]);
                }

                return [
                    'ok' => true,
                    'mensaje' => 'Correo enviado con éxito.',
                    'destinatarios' => $listaOk,
                    'idEmailEscrito' => (int) $escrito->id,
                ];
            });
        } catch (Throwable $e) {
            Log::error('Envío correo masivo falló', [
                'profesor' => $profesor->id,
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
     * @param  list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile>  $archivosTemp
     * @param  list<string>  $listaOk
     * @return array{ok:bool,mensaje:string,destinatarios:list<string>,idEmailEscrito:?int}
     */
    private static function persistirSinSmtp(
        Profesor $profesor,
        int $idNivel,
        int $idTerlec,
        string $asunto,
        string $html,
        string $attachedStr,
        array $destinatarios,
        array $archivosTemp,
        array $listaOk,
    ): array {
        try {
            return DB::transaction(function () use (
                $profesor,
                $idNivel,
                $idTerlec,
                $asunto,
                $html,
                $attachedStr,
                $destinatarios,
                $archivosTemp,
                $listaOk,
            ) {
                $escrito = EmailEscrito::query()->create([
                    'subject' => $asunto,
                    'text' => $html,
                    'attached' => $attachedStr,
                ]);

                $adjuntos = EmailsMasivosAdjuntosStorage::guardarParaCampana(
                    $idTerlec,
                    (int) $escrito->id,
                    $archivosTemp,
                );

                $fechhora = now();
                foreach ($destinatarios as $d) {
                    EmailEnviado::query()->create([
                        'mailDestino' => $d['email'],
                        'fechhora' => $fechhora,
                        'idProfesores' => (int) $profesor->id,
                        'idLegajos' => $d['idLegajo'],
                        'idCursos' => $d['idCurso'],
                        'idNiveles' => $idNivel,
                        'idTerlec' => $idTerlec,
                        'subject' => $asunto,
                        'texto' => $html,
                        'attached' => $adjuntos['attached'],
                    ]);
                }

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
