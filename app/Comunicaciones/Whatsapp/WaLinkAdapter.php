<?php

namespace App\Comunicaciones\Whatsapp;

use App\Models\Legajo;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\ComPreferencia;

class WaLinkAdapter
{
    /**
     * Genera el enlace para envío manual (WhatsApp Web en navegador o wa.me según configuración).
     *
     * Estado: 'enviado' (link generado) o 'no_aplicable' (sin teléfono).
     * El link se guarda en proveedor_msgid para mostrarlo en la UI.
     *
     * @return array{estado:string, motivo:?string, link:?string}
     */
    public static function generar(
        ComMensajeDestinatario $destinatario,
        ComMensaje $mensaje
    ): array {
        $telefono = static::resolverTelefono($destinatario);

        if ($telefono === null) {
            return ['estado' => 'no_aplicable', 'motivo' => 'Sin número de WhatsApp disponible', 'link' => null];
        }

        $asunto    = $mensaje->hilo?->asunto ?? 'Comunicado';
        $contenido = mb_substr((string) $mensaje->contenido, 0, 500);
        $texto     = "{$asunto}\n\n{$contenido}";
        // Sin prefijo + (wa.me y web.whatsapp.com/send lo aceptan así)
        $waNum = ltrim($telefono, '+');
        $textoEncoded = rawurlencode($texto);
        $estilo       = (string) config('comunicaciones.whatsapp_manual_link_style', 'web');
        $link         = $estilo === 'wa_me'
            ? 'https://wa.me/' . $waNum . '?text=' . $textoEncoded
            : 'https://web.whatsapp.com/send?phone=' . rawurlencode($waNum) . '&text=' . $textoEncoded;

        return ['estado' => 'enviado', 'motivo' => null, 'link' => $link];
    }

    private static function resolverTelefono(ComMensajeDestinatario $destinatario): ?string
    {
        if ($destinatario->tipo_destinatario !== 'familia' || ! $destinatario->id_legajo) {
            return null;
        }

        $legajo = Legajo::find($destinatario->id_legajo);
        if ($legajo === null) {
            return null;
        }

        $pref     = ComPreferencia::paraLegajo($destinatario->id_legajo);
        $vinculos = $pref->exists ? $pref->vinculosContactoResolucion() : null;

        $candidatos = [];
        if ($vinculos === null) {
            $candidatos[] = $legajo->telemad ?? null;
            $candidatos[] = $legajo->telepad ?? null;
        } else {
            foreach ($vinculos as $v) {
                if ($v === 'madre') {
                    $candidatos[] = $legajo->telemad ?? null;
                } elseif ($v === 'padre') {
                    $candidatos[] = $legajo->telepad ?? null;
                } elseif ($v === 'tutor') {
                    $candidatos[] = $legajo->teletut ?? null;
                }
            }
        }

        foreach ($candidatos as $tel) {
            $t = static::limpiarTelefono((string) ($tel ?? ''));
            if ($t !== '') {
                return $t;
            }
        }

        return null;
    }

    private static function limpiarTelefono(string $tel): string
    {
        $limpio = preg_replace('/[^0-9+]/', '', $tel) ?? '';
        return strlen($limpio) >= 7 ? $limpio : '';
    }
}
