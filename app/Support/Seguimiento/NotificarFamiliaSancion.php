<?php

namespace App\Support\Seguimiento;

use App\Comunicaciones\Adapters\MailAdapter;
use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComunicacionesRepository;
use App\Models\ComHilo;
use App\Models\ComMensajeDestinatario;
use App\Models\ComMensajeEnvio;
use App\Models\Matricula;
use App\Models\Profesor;
use App\Models\Sancion;
use App\Support\Comunicaciones\ComCanalRolCatalog;

/**
 * Envía un comunicado institucional a la familia del alumno
 * al registrar una sanción disciplinaria (botón «Notif. Padres»).
 *
 * El remitente es el profesor configurado en sanciontipo.idProfesorNotif.
 * Los medios incluyen siempre push (si el canal lo permite) y también email
 * cuando sanciontipo.refuerzoMail está activo y el canal lo permite.
 */
final class NotificarFamiliaSancion
{
    /**
     * @return array{
     *     ok: bool,
     *     medios: list<string>,
     *     email_incluido: bool,
     *     email_estado: ?string,
     *     email_motivo: ?string,
     *     email_destino: ?string,
     *     email_mailer: ?string,
     *     email_smtp_user: ?string,
     *     refuerzo_mail_pedido: bool,
     *     motivo_fallo: ?string
     * }
     */
    public static function despachar(Sancion $sancion, Matricula $matricula): array
    {
        $fallo = static fn (?string $motivo = null): array => [
            'ok'                   => false,
            'medios'               => [],
            'email_incluido'       => false,
            'email_estado'         => null,
            'email_motivo'         => null,
            'email_destino'        => null,
            'email_mailer'         => null,
            'email_smtp_user'      => null,
            'refuerzo_mail_pedido' => false,
            'motivo_fallo'         => $motivo,
        ];

        $ctx = schoolCtx();
        $idNivel  = (int) ($ctx->idNivel  ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        if ($idNivel < 1 || $idTerlec < 1) {
            return $fallo('Sin contexto de nivel o ciclo lectivo.');
        }

        // La sanción debe pertenecer al contexto actual
        if ((int) ($matricula->idNivel ?? 0)  !== $idNivel
            || (int) ($matricula->idTerlec ?? 0) !== $idTerlec) {
            return $fallo('La matrícula no pertenece al contexto actual.');
        }

        $sancion->loadMissing(['tipo.profesorNotif', 'profesor']);
        $tipo = $sancion->tipo;

        if (! $tipo) {
            return $fallo('La sanción no tiene tipo asociado.');
        }

        $refuerzoMailPedido = (bool) ($tipo->refuerzoMail ?? false);

        // Verificar que este tipo permite notificar a padres
        if (isset($tipo->permiteNotifPadres) && ! $tipo->permiteNotifPadres) {
            return $fallo('Este tipo de sanción no permite notificar a padres.');
        }

        // Remitente fijo configurado en el tipo
        $idProfesorNotif = (int) ($tipo->idProfesorNotif ?? 0);
        if ($idProfesorNotif < 1) {
            return $fallo('Este tipo de sanción no tiene remitente configurado.');
        }

        $profesorNotif = Profesor::query()->find($idProfesorNotif);
        if ($profesorNotif === null) {
            return $fallo('El remitente configurado no existe.');
        }

        $rolEmisor = CanalesPolicy::claveRolDeProfesor($profesorNotif);

        if (! CanalesPolicy::puedeIniciar($rolEmisor, ComCanalRolCatalog::CLAVE_FAMILIA, $idNivel)) {
            return $fallo('El canal del remitente hacia familia no está activo.');
        }

        $mediosCanal = CanalesPolicy::mediosPermitidos($rolEmisor, ComCanalRolCatalog::CLAVE_FAMILIA, $idNivel);
        if ($mediosCanal === []) {
            return $fallo('El canal del remitente hacia familia no tiene medios habilitados.');
        }

        // Filtrar medios: push siempre incluido si el canal lo permite;
        // email solo si refuerzoMail está activo para este tipo.
        $mediosEfectivos = array_values(array_filter($mediosCanal, function (string $medio) use ($tipo): bool {
            if ($medio === 'push') {
                return true;
            }
            if ($medio === 'email') {
                return (bool) ($tipo->refuerzoMail ?? false);
            }
            // whatsapp y otros: no incluir en notificaciones automáticas disciplinarias
            return false;
        }));

        if ($mediosEfectivos === []) {
            return $fallo('No hay medios efectivos para notificar (revisá el canal y el refuerzo por correo).');
        }

        $idLegajo = (int) ($matricula->idLegajos ?? 0);
        if ($idLegajo < 1) {
            return $fallo('La matrícula no tiene legajo asociado.');
        }

        $matricula->loadMissing(['legajo', 'curso']);

        $alumno  = trim(($matricula->legajo?->apellido ?? '').', '.($matricula->legajo?->nombre ?? ''));
        $curso   = $matricula->curso?->nombreParaListado() ?? '';
        $tipoNombre = trim((string) ($tipo->tipo ?? ''));
        $fecha   = $sancion->fecha ? $sancion->fecha->format('d/m/Y') : '—';
        $cantidad = isset($sancion->cantidad) && $sancion->cantidad !== null ? (string) $sancion->cantidad : '—';
        $motivo  = trim((string) ($sancion->motivo ?? ''));
        $solipor = trim((string) ($sancion->solipor ?? ''));
        if ($solipor === '') {
            $solipor = $sancion->profesor?->nombre_completo ?? '—';
        }

        // Prefijo del mensaje: texto configurado en el tipo o texto default
        $prefijo = trim((string) ($tipo->textoNotifPadres ?? ''));
        if ($prefijo === '') {
            $prefijo = 'Le informamos que se ha registrado la siguiente sanción disciplinaria en el Cuaderno de Seguimiento.';
        }

        $lineas = [$prefijo, ''];
        if ($alumno !== ', ') {
            $lineas[] = 'Alumno/a: '.$alumno;
        }
        if ($curso !== '') {
            $lineas[] = 'Curso: '.$curso;
        }
        $lineas[] = 'Fecha: '.$fecha;
        $lineas[] = 'Tipo de sanción: '.$tipoNombre;
        $lineas[] = 'Cantidad: '.$cantidad;
        if ($motivo !== '') {
            $lineas[] = 'Motivo: '.$motivo;
        }
        $lineas[] = 'Solicitada por: '.$solipor;

        $actaTexto = SancionActaHtmlSanitizer::aTextoPlanoMultilinea($sancion->acta ?? null);
        if ($actaTexto !== '') {
            $lineas[] = '';
            $lineas[] = 'Acta:';
            $lineas[] = $actaTexto;
        }

        $asunto = 'Sanción disciplinaria — '.$alumno;

        $hilo = ComunicacionesRepository::crearHiloConMensaje([
            'asunto'                   => $asunto,
            'contenido'                => implode("\n", $lineas),
            'scope'                    => 'alumno',
            'id_legajos'               => [$idLegajo],
            'id_curso'                 => (int) ($matricula->idCursos ?? 0) ?: null,
            'cursos_envio'             => null,
            'id_nivel'                 => $idNivel,
            'id_terlec'                => $idTerlec,
            'creado_por_tipo'          => 'profesor',
            'creado_por_id'            => $idProfesorNotif,
            'creado_por_rol'           => $rolEmisor,
            'rol_receptor'             => ComCanalRolCatalog::CLAVE_FAMILIA,
            'vinculo_familiar'         => null,
            'nombre_remitente'         => $profesorNotif->nombre_completo,
            'dni_remitente'            => (string) ($profesorNotif->dni ?? ''),
            'destinatarios_profesores' => [],
            'familia_puede_responder'  => false,
        ], $mediosEfectivos);

        $emailIncluido = in_array('email', $mediosEfectivos, true);
        $resumenEmail = $emailIncluido
            ? self::resumenEnvioEmail($hilo)
            : ['estado' => null, 'motivo' => null, 'destino' => null];

        return [
            'ok'                   => true,
            'medios'               => $mediosEfectivos,
            'email_incluido'       => $emailIncluido,
            'email_estado'         => $resumenEmail['estado'],
            'email_motivo'         => $resumenEmail['motivo'],
            'email_destino'        => $resumenEmail['destino'],
            'email_mailer'         => (string) config('mail.default'),
            'email_smtp_user'      => trim((string) config('mail.mailers.smtp.username', '')),
            'refuerzo_mail_pedido' => $refuerzoMailPedido,
            'motivo_fallo'         => null,
        ];
    }

    /**
     * @return array{estado: ?string, motivo: ?string, destino: ?string}
     */
    private static function resumenEnvioEmail(ComHilo $hilo): array
    {
        $idMensaje = (int) ($hilo->cuerpo_inicial_id ?? 0);
        if ($idMensaje < 1) {
            return ['estado' => null, 'motivo' => 'Sin mensaje inicial del hilo.', 'destino' => null];
        }

        $destinatarios = ComMensajeDestinatario::query()
            ->where('id_mensaje', $idMensaje)
            ->get();

        if ($destinatarios->isEmpty()) {
            return ['estado' => null, 'motivo' => 'Sin destinatarios del mensaje.', 'destino' => null];
        }

        $idsDest = $destinatarios->pluck('id')->all();

        $envio = ComMensajeEnvio::query()
            ->where('medio', 'email')
            ->whereIn('id_mensaje_destinatario', $idsDest)
            ->orderByDesc('id')
            ->first();

        if ($envio === null) {
            return ['estado' => null, 'motivo' => 'No se registró intento de correo.', 'destino' => null];
        }

        $dest = $destinatarios->firstWhere('id', (int) $envio->id_mensaje_destinatario)
            ?? $destinatarios->first();

        $destino = null;
        if ($dest instanceof ComMensajeDestinatario) {
            $destino = MailAdapter::resolverDireccionCorreo($dest);
            $destino = $destino !== null ? mb_strtolower(trim($destino)) : null;
            if ($destino === '') {
                $destino = null;
            }
        }

        return [
            'estado'  => (string) ($envio->estado ?? ''),
            'motivo'  => ($m = trim((string) ($envio->motivo ?? ''))) !== '' ? $m : null,
            'destino' => $destino,
        ];
    }
}
