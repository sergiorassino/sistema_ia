<?php

namespace App\Livewire\Comunicaciones;

use App\Comunicaciones\ComunicacionesRepository;
use App\Models\ComMensajeEnvio;
use App\Support\ComunicacionesRutasGestion;
use Livewire\Component;

class InformeEnvioComunicado extends Component
{
    public int $idHilo = 0;

    /**
     * @var array{
     *   id_hilo:int,
     *   id_mensaje:int,
     *   asunto:string,
     *   contenido_preview:string,
     *   filas:list<array<string,mixed>>,
     *   totales:array{enviado:int,fallido:int,no_aplicable:int,pendiente:int}
     * }|null
     */
    public ?array $informe = null;

    public function mount(int $id): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoNuevoComunicado(), 403);

        $this->idHilo = $id;

        $ctx      = schoolCtx();
        $idNivel  = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;
        $idProf   = (int) $ctx->idProfesor;

        abort_unless(
            ComunicacionesRepository::profesorPuedeVerHilo($id, $idProf, $idNivel, $idTerlec),
            403
        );

        $this->informe = ComunicacionesRepository::informeEnviosPrimerMensajeDelHilo($id, $idNivel, $idTerlec);
        abort_if($this->informe === null, 404);
    }

    public static function medioEtiqueta(string $medio): string
    {
        return match ($medio) {
            'push'      => 'Push',
            'email'     => 'Correo',
            'whatsapp'  => 'WhatsApp',
            default     => $medio,
        };
    }

    public static function estadoEtiqueta(string $estado, string $medio = '', mixed $proveedorMsgId = null): string
    {
        if (ComMensajeEnvio::esWhatsappEnvioManualWaMe($medio, $estado, $proveedorMsgId)) {
            return '(Envío manual)';
        }

        return match ($estado) {
            'enviado'      => 'Enviado',
            'fallido'      => 'Fallido',
            'pendiente'    => 'Pendiente',
            'no_aplicable' => 'No aplica',
            default        => ucfirst($estado),
        };
    }

    public function render()
    {
        return view('comunicaciones::livewire.comunicaciones.informe-envio-comunicado', [
            'waLinks' => session('whatsapp_wa_links'),
        ])->layout(ComunicacionesRutasGestion::layout(), ['pageTitle' => 'Informe de envío']);
    }
}
