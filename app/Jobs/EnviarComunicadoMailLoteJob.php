<?php

namespace App\Jobs;

use App\Comunicaciones\Adapters\MailAdapter;
use App\Models\ComMensajeEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class EnviarComunicadoMailLoteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * @param  list<int>  $idsComMensajeEnvio  Filas pendientes en com_mensajes_envios (medio email).
     */
    public function __construct(
        public array $idsComMensajeEnvio,
        public int $idMensaje,
        public string $nombreColegio = '',
    ) {}

    public function handle(): void
    {
        MailAdapter::procesarColaCorreoPendiente($this->idsComMensajeEnvio, $this->idMensaje, $this->nombreColegio);
    }

    public function failed(?Throwable $exception): void
    {
        ComMensajeEnvio::query()
            ->whereIn('id', $this->idsComMensajeEnvio)
            ->where('medio', 'email')
            ->where('estado', 'pendiente')
            ->update([
                'estado'     => 'fallido',
                'motivo'     => mb_substr($exception?->getMessage() ?? 'Error al procesar el lote de correo', 0, 250),
                'enviado_at' => null,
            ]);
    }
}
