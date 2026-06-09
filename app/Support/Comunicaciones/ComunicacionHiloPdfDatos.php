<?php

namespace App\Support\Comunicaciones;

use App\Models\ComAuditoria;
use App\Models\ComHilo;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\ComMensajeEnvio;
use App\Models\Terlec;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arma los datos para el PDF de un hilo de comunicados (gestión / secretaría).
 */
final class ComunicacionHiloPdfDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function paraHilo(int $idHilo, int $idNivel, int $idTerlec): ?array
    {
        $hilo = ComHilo::with([
            'mensajes' => function ($q) {
                $q->with(['destinatarios.envios'])->orderBy('created_at');
            },
        ])
            ->where('id', $idHilo)
            ->where('id_nivel', $idNivel)
            ->where('id_terlec', $idTerlec)
            ->first();

        if ($hilo === null) {
            return null;
        }

        $profesor = schoolCtx()->profesor();
        $impresoPor = '—';
        if ($profesor !== null) {
            $impresoPor = trim((string) $profesor->apellido.', '.(string) $profesor->nombre);
            $dni = trim((string) ($profesor->dni ?? ''));
            if ($dni !== '') {
                $impresoPor .= ' · DNI '.$dni;
            }
        }

        $terlec = Terlec::query()->find($idTerlec);
        $cicloAno = $terlec !== null ? (string) ($terlec->ano ?? '') : '';

        $auditoriaPorMensaje = self::auditoriaAgrupadaPorMensaje($idHilo, $idNivel, $idTerlec);

        $mensajes = [];
        foreach ($hilo->mensajes as $msg) {
            $mensajes[] = self::filaMensaje($msg, $auditoriaPorMensaje);
        }

        $informativo = $hilo->esComunicadoInformativoEscuela()
            ? 'Solo informativo · sin respuesta familia'
            : (! $hilo->docentesDestinatariosPuedenResponder() && $hilo->esComunicacionInternaDocentes()
                ? 'Solo informativo · sin respuesta en hilo'
                : null);

        return [
            'asunto'           => (string) $hilo->asunto,
            'scopeLabel'       => $hilo->scopeLabel(),
            'estadoLabel'      => $hilo->estadoLabel(),
            'iniciado'         => $hilo->created_at?->format('d/m/Y H:i') ?? '—',
            'paraCompleto'     => self::paraCompleto($hilo),
            'cicloAno'         => $cicloAno,
            'informativo'      => $informativo,
            'generado'         => now()->format('d/m/Y H:i'),
            'impresoPor'       => $impresoPor,
            'mensajes'         => $mensajes,
        ];
    }

    /**
     * @param  Collection<int, Collection<int, ComAuditoria>>  $auditoriaPorMensaje
     * @return array<string, mixed>
     */
    private static function filaMensaje(ComMensaje $msg, Collection $auditoriaPorMensaje): array
    {
        $fecha = $msg->created_at?->format('d/m/Y') ?? ($msg->fecha?->format('d/m/Y') ?? '—');
        $hora = $msg->hora ? substr((string) $msg->hora, 0, 5) : ($msg->created_at?->format('H:i') ?? '');

        $remitente = trim((string) ($msg->nombre_remitente_snapshot ?? ''));
        if ($remitente === '') {
            $remitente = $msg->tipo_remitente === 'profesor' ? 'Personal escolar' : 'Familia';
        }

        $vinculo = $msg->vinculo_familiar ? $msg->vinculoLabel() : null;

        $lectura = $msg->resumenLecturaDestinatarios();
        $lecturaResumen = $lectura['etiqueta'] !== '' ? $lectura['etiqueta'] : null;

        $destinatarios = [];
        foreach ($msg->destinatarios as $d) {
            $nombre = trim((string) ($d->nombre_snapshot ?? ''));
            if ($nombre === '') {
                $nombre = $d->tipo_destinatario === 'familia' ? 'Familia' : 'Personal escolar';
            }
            $destinatarios[] = [
                'nombre'         => $nombre,
                'tipo'           => $d->tipo_destinatario === 'familia' ? 'Familia' : 'Personal',
                'leido'          => $d->leido_at !== null,
                'fecha_lectura'  => $d->leido_at !== null ? $d->leido_at->format('d/m/Y H:i') : 'Sin leer',
            ];
        }

        $envios = [];
        foreach ($msg->destinatarios as $d) {
            $nombreDest = trim((string) ($d->nombre_snapshot ?? ''));
            if ($nombreDest === '') {
                $nombreDest = $d->tipo_destinatario === 'familia' ? 'Familia' : 'Personal';
            }
            foreach ($d->envios as $envio) {
                $envios[] = [
                    'destinatario' => $nombreDest,
                    'medio'        => self::etiquetaMedio((string) $envio->medio),
                    'estado'       => self::etiquetaEstadoEnvio($envio),
                    'motivo'       => trim((string) ($envio->motivo ?? '')),
                ];
            }
        }

        $auditoria = [];
        $regs = $auditoriaPorMensaje->get((int) $msg->id, collect());
        foreach ($regs as $r) {
            $auditoria[] = [
                'fecha'      => $r->created_at?->format('d/m/Y H:i:s') ?? '—',
                'actor'      => (string) ($r->nombre_actor_snapshot ?? '—'),
                'dni'        => trim((string) ($r->dni_actor_snapshot ?? '')),
                'categoria'  => ComAuditoria::etiquetaCategoria((string) $r->actor_categoria),
                'accion'     => ComAuditoria::etiquetaAccion((string) $r->accion),
                'portal'     => ComAuditoria::etiquetaPortal((string) $r->portal),
                'remitente'  => trim((string) ($r->mensaje_remitente_snapshot ?? '')),
                'destinatario' => trim((string) ($r->mensaje_destinatario_snapshot ?? '')),
                'ip'         => trim((string) ($r->ip_address ?? '')),
            ];
        }

        $contenido = preg_replace('/\A[\p{Z}\s]+/u', '', (string) $msg->contenido) ?? '';
        $contenido = preg_replace('/[\p{Z}\s]+\z/u', '', $contenido) ?? '';

        return [
            'numero'           => (int) $msg->id,
            'remitente'        => $remitente,
            'vinculo'          => $vinculo,
            'fechaHora'        => trim($fecha.' '.$hora),
            'contenido'        => $contenido,
            'lecturaResumen'   => $lecturaResumen,
            'destinatarios'    => $destinatarios,
            'envios'           => $envios,
            'auditoria'        => $auditoria,
        ];
    }

    /**
     * @return Collection<int, Collection<int, ComAuditoria>>
     */
    private static function auditoriaAgrupadaPorMensaje(int $idHilo, int $idNivel, int $idTerlec): Collection
    {
        if (! Schema::hasTable('com_auditoria')) {
            return collect();
        }

        return ComAuditoria::query()
            ->where('id_hilo', $idHilo)
            ->where('id_nivel', $idNivel)
            ->where('id_terlec', $idTerlec)
            ->whereNotNull('id_mensaje')
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (ComAuditoria $r) => (int) $r->id_mensaje);
    }

    private static function etiquetaMedio(string $medio): string
    {
        return match ($medio) {
            'push'     => 'Push',
            'email'    => 'Correo',
            'whatsapp' => 'WhatsApp',
            default    => $medio,
        };
    }

    private static function etiquetaEstadoEnvio(ComMensajeEnvio $envio): string
    {
        if (ComMensajeEnvio::esWhatsappEnvioManualWaMe(
            (string) $envio->medio,
            (string) $envio->estado,
            $envio->proveedor_msgid
        )) {
            return 'Envío manual';
        }

        return $envio->estadoLabel();
    }

    private static function paraCompleto(ComHilo $hilo): ?string
    {
        if ($hilo->creado_por_tipo !== 'profesor') {
            return null;
        }

        if ($hilo->esComunicacionInternaDocentes()) {
            $nombres = ComMensajeDestinatario::query()
                ->where('id_mensaje', (int) $hilo->cuerpo_inicial_id)
                ->where('tipo_destinatario', 'profesor')
                ->whereNotNull('nombre_snapshot')
                ->orderBy('id_profesor')
                ->pluck('nombre_snapshot')
                ->map(fn ($s) => trim((string) $s))
                ->filter(fn ($s) => $s !== '')
                ->unique()
                ->values()
                ->all();

            return count($nombres) ? implode(' · ', $nombres) : 'Docentes';
        }

        if ($hilo->scope === 'colegio') {
            return 'Todo el colegio';
        }

        if (in_array($hilo->scope, ['curso', 'varios_cursos'], true)) {
            $labels = [];
            if (is_array($hilo->cursos_envio)) {
                foreach ($hilo->cursos_envio as $row) {
                    if (is_array($row) && isset($row['label']) && trim((string) $row['label']) !== '') {
                        $labels[] = trim((string) $row['label']);
                    }
                }
            }
            if (count($labels) === 0 && $hilo->id_curso) {
                $cursoLabel = DB::table('cursos as c')
                    ->leftJoin('curplan as cp', 'cp.id', '=', 'c.idCurPlan')
                    ->where('c.Id', (int) $hilo->id_curso)
                    ->value(DB::raw("CASE WHEN TRIM(COALESCE(c.cursec, '')) <> '' THEN TRIM(c.cursec) ELSE TRIM(COALESCE(cp.curPlanCurso, 'Curso')) END"));
                if ($cursoLabel !== null && trim((string) $cursoLabel) !== '') {
                    $labels[] = trim((string) $cursoLabel);
                }
            }

            return count($labels) ? implode(' · ', $labels) : 'Cursos';
        }

        $nombres = ComMensajeDestinatario::query()
            ->where('id_mensaje', (int) $hilo->cuerpo_inicial_id)
            ->where('tipo_destinatario', 'familia')
            ->whereNotNull('nombre_snapshot')
            ->orderBy('id_legajo')
            ->pluck('nombre_snapshot')
            ->map(fn ($s) => trim((string) $s))
            ->filter(fn ($s) => $s !== '')
            ->unique()
            ->values()
            ->all();

        return count($nombres) ? implode(' · ', $nombres) : '—';
    }
}
