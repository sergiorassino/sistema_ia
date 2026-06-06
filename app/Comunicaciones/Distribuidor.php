<?php

namespace App\Comunicaciones;

use App\Comunicaciones\Adapters\MailAdapter;
use App\Comunicaciones\Adapters\PushAdapter;
use App\Comunicaciones\Adapters\WhatsappAdapter;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\ComPreferencia;
use Illuminate\Support\Facades\DB;

class Distribuidor
{
    /**
     * Envía un mensaje a todos sus destinatarios, por los medios que
     * la combinación canal + preferencias del usuario permita.
     *
     * @param list<string> $mediosCanal Medios permitidos por el canal
     */
    public static function distribuir(ComMensaje $mensaje, array $mediosCanal): void
    {
        $maxSeconds = (int) config('comunicaciones.distribuir_max_seconds', 300);
        if ($maxSeconds > 0) {
            @set_time_limit($maxSeconds);
        }

        $mensaje->load(['hilo', 'destinatarios']);
        $nombreColegio = static::nombreColegio($mensaje);

        $destinatariosCorreo = [];

        foreach ($mensaje->destinatarios as $destinatario) {
            $mediosUsuario = static::mediosActivosParaDestinatario($destinatario);
            $medios        = array_intersect($mediosCanal, $mediosUsuario);

            if (in_array('email', $medios, true)) {
                $destinatariosCorreo[] = $destinatario;
            }

            foreach ($medios as $medio) {
                if ($medio === 'email') {
                    continue;
                }
                match ($medio) {
                    'push'      => PushAdapter::enviar($destinatario, $mensaje, $nombreColegio),
                    'whatsapp'  => WhatsappAdapter::enviar($destinatario, $mensaje),
                    default     => null,
                };
            }
        }

        if ($destinatariosCorreo !== []) {
            MailAdapter::enviarCorreoParaVariosDestinatarios($mensaje, $destinatariosCorreo, $nombreColegio);
        }
    }

    /**
     * Medios activos según las preferencias del destinatario.
     * Si no hay fila en com_preferencias, se usan los mismos valores por defecto
     * que en firstOrNew (push, email y whatsapp activos hasta que la familia/docente los ajuste).
     *
     * @return list<string>
     */
    private static function mediosActivosParaDestinatario(ComMensajeDestinatario $dest): array
    {
        if ($dest->tipo_destinatario === 'familia' && $dest->id_legajo) {
            return ComPreferencia::paraLegajo($dest->id_legajo)->mediosActivos();
        }

        if ($dest->tipo_destinatario === 'profesor' && $dest->id_profesor) {
            return ComPreferencia::paraProfesor($dest->id_profesor)->mediosActivos();
        }

        return ['push', 'email', 'whatsapp'];
    }

    private static function nombreColegio(ComMensaje $mensaje): string
    {
        $idNivel = $mensaje->hilo?->id_nivel;
        if (! $idNivel) {
            return '';
        }
        $insti = DB::table('ento')->where('idNivel', $idNivel)->value('insti');

        return is_string($insti) ? trim($insti) : '';
    }
}
