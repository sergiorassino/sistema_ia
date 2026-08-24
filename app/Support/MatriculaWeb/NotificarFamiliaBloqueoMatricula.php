<?php

namespace App\Support\MatriculaWeb;

use App\Comunicaciones\Adapters\MailAdapter;
use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComunicacionesRepository;
use App\Mail\ComunicadoMail;
use App\Models\ComHilo;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\ComMensajeEnvio;
use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Profesor;
use App\Support\Comunicaciones\ComCanalRolCatalog;
use App\Support\Mail\MailInstitucionalConfig;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía un comunicado institucional a la familia avisando el bloqueo o desbloqueo
 * de matrícula (botones «Notif. Bloqueo» / «Notif. Desbloqueo»).
 *
 * Remitente: profesor logueado. Medios: push (si el canal lo permite) + email de refuerzo.
 * Correo de refuerzo: a diferencia del resto del módulo de comunicaciones (un solo mail
 * madre→padre→tutor), esta notificación envía a los tres contactos del legajo que tengan
 * correo válido, sin alterar MailAdapter ni el flujo general.
 */
final class NotificarFamiliaBloqueoMatricula
{
    public const TIPO_BLOQUEO = 'bloqueo';

    public const TIPO_DESBLOQUEO = 'desbloqueo';

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
    public static function despachar(Matricula $matricula, string $tipo = self::TIPO_BLOQUEO): array
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
            'refuerzo_mail_pedido' => true,
            'motivo_fallo'         => $motivo,
        ];

        if ($tipo !== self::TIPO_BLOQUEO && $tipo !== self::TIPO_DESBLOQUEO) {
            return $fallo('Tipo de notificación inválido.');
        }

        $ctx = schoolCtx();
        $idProfesor = (int) ($ctx->idProfesor ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);

        if ($idProfesor < 1 || $idTerlec < 1) {
            return $fallo('Sin contexto de usuario o ciclo lectivo.');
        }

        if ((int) ($matricula->idTerlec ?? 0) !== $idTerlec) {
            return $fallo('La matrícula no pertenece al ciclo lectivo activo.');
        }

        $idNivelAlumno = (int) ($matricula->idNivel ?? 0);
        if ($idNivelAlumno < 1) {
            return $fallo('La matrícula no tiene nivel asociado.');
        }

        $idNivelFiltro = SchoolAlcancePedagogico::idNivelFiltroUnico();
        if ($idNivelFiltro !== null && $idNivelAlumno !== $idNivelFiltro) {
            return $fallo('La matrícula no pertenece al nivel activo.');
        }

        $bloqPeda = (bool) ($matricula->bloqmatr ?? false);
        $bloqAdmi = (bool) ($matricula->bloqadmi ?? false);

        if ($tipo === self::TIPO_BLOQUEO && ! $bloqPeda && ! $bloqAdmi) {
            return $fallo('El alumno no tiene bloqueo de matrícula activo.');
        }

        if ($tipo === self::TIPO_DESBLOQUEO && ($bloqPeda || $bloqAdmi)) {
            return $fallo('El alumno aún tiene bloqueo de matrícula activo.');
        }

        $profesor = Profesor::query()->find($idProfesor);
        if ($profesor === null) {
            return $fallo('No se encontró el usuario remitente.');
        }

        $rolEmisor = CanalesPolicy::claveRolDeProfesor($profesor);

        if (! CanalesPolicy::puedeIniciar($rolEmisor, ComCanalRolCatalog::CLAVE_FAMILIA, $idNivelAlumno)) {
            return $fallo('El canal del remitente hacia familia no está activo.');
        }

        $mediosCanal = CanalesPolicy::mediosPermitidos($rolEmisor, ComCanalRolCatalog::CLAVE_FAMILIA, $idNivelAlumno);
        if ($mediosCanal === []) {
            return $fallo('El canal del remitente hacia familia no tiene medios habilitados.');
        }

        // Push si el canal lo permite; email siempre pedido (refuerzo de mail).
        $mediosEfectivos = array_values(array_filter($mediosCanal, static function (string $medio): bool {
            return $medio === 'push' || $medio === 'email';
        }));

        if ($mediosEfectivos === []) {
            return $fallo('No hay medios efectivos para notificar (revisá push/email en el canal).');
        }

        $idLegajo = (int) ($matricula->idLegajos ?? 0);
        if ($idLegajo < 1) {
            return $fallo('La matrícula no tiene legajo asociado.');
        }

        $matricula->loadMissing(['legajo', 'curso', 'nivel']);

        $alumno = trim(($matricula->legajo?->apellido ?? '').', '.($matricula->legajo?->nombre ?? ''));
        $curso = $matricula->curso?->nombreParaListado() ?? '';
        $nombreNivel = trim((string) ($matricula->nivel?->nivel ?? ''));
        if ($nombreNivel === '') {
            $nombreNivel = trim((string) schoolCtx()->nivelNombre());
        }

        $cuerpo = $tipo === self::TIPO_DESBLOQUEO
            ? self::armarCuerpoDesbloqueo($bloqPeda, $bloqAdmi)
            : self::armarCuerpo($bloqPeda, $bloqAdmi, $nombreNivel);

        $lineas = [$cuerpo, ''];
        if ($alumno !== ', ') {
            $lineas[] = 'Alumno/a: '.$alumno;
        }
        if ($curso !== '') {
            $lineas[] = 'Curso: '.$curso;
        }
        if ($nombreNivel !== '') {
            $lineas[] = 'Nivel: '.$nombreNivel;
        }

        $asunto = ($tipo === self::TIPO_DESBLOQUEO ? 'Desbloqueo de matrícula — ' : 'Bloqueo de matrícula — ').$alumno;

        $hilo = ComunicacionesRepository::crearHiloConMensaje([
            'asunto'                   => $asunto,
            'contenido'                => implode("\n", $lineas),
            'scope'                    => 'alumno',
            'id_legajos'               => [$idLegajo],
            'id_curso'                 => (int) ($matricula->idCursos ?? 0) ?: null,
            'cursos_envio'             => null,
            'id_nivel'                 => $idNivelAlumno,
            'id_terlec'                => $idTerlec,
            'creado_por_tipo'          => 'profesor',
            'creado_por_id'            => $idProfesor,
            'creado_por_rol'           => $rolEmisor,
            'rol_receptor'             => ComCanalRolCatalog::CLAVE_FAMILIA,
            'vinculo_familiar'         => null,
            'nombre_remitente'         => $profesor->nombre_completo,
            'dni_remitente'            => (string) ($profesor->dni ?? ''),
            'destinatarios_profesores' => [],
            'familia_puede_responder'  => false,
        ], $mediosEfectivos);

        $emailIncluido = in_array('email', $mediosEfectivos, true);
        if ($emailIncluido) {
            self::enviarCorreosFamiliaRestantes($hilo, $matricula->legajo);
        }

        $resumenEmail = $emailIncluido
            ? self::resumenEnvioEmail($hilo, $matricula->legajo)
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
            'refuerzo_mail_pedido' => true,
            'motivo_fallo'         => null,
        ];
    }

    public static function armarCuerpo(bool $bloqPeda, bool $bloqAdmi, string $nombreNivel): string
    {
        $textoMotivos = match (true) {
            $bloqPeda && $bloqAdmi => 'PEDAGÓGICOS y/o ADMINISTRATIVOS',
            $bloqAdmi => 'ADMINISTRATIVOS',
            default => 'PEDAGÓGICOS',
        };

        $secretariaNivel = self::etiquetaSecretariaNivel($nombreNivel);
        $contactos = [];
        if ($bloqPeda) {
            $contactos[] = $secretariaNivel;
        }
        if ($bloqAdmi) {
            $contactos[] = 'Administración';
        }

        $textoContacto = match (count($contactos)) {
            2 => $contactos[0].' y '.$contactos[1],
            1 => $contactos[0],
            default => $secretariaNivel,
        };

        return "Estimada Familia:\n"
            .'Les informamos que la Matrícula para el año próximo del/la estudiante se encuentra bloqueada por motivos '
            .$textoMotivos
            .'. Por favor, comunicarse a la brevedad con '
            .$textoContacto
            .".\nAtte.\nEquipo Directivo";
    }

    /**
     * «Secretaría de Nivel Secundario» sin duplicar «Nivel» si el nombre del nivel ya lo trae.
     */
    public static function etiquetaSecretariaNivel(string $nombreNivel): string
    {
        $etiqueta = trim($nombreNivel);
        if ($etiqueta === '') {
            $etiqueta = 'Nivel';
        } elseif (! preg_match('/^nivel\b/iu', $etiqueta)) {
            $etiqueta = 'Nivel '.$etiqueta;
        }

        return 'Secretaría de '.$etiqueta;
    }

    /**
     * Texto de desbloqueo. Los flags indican el estado actual (deben estar en false
     * al notificar desbloqueo total); el tipo de requisitos refleja lo liberado.
     */
    public static function armarCuerpoDesbloqueo(bool $bloqPeda, bool $bloqAdmi): string
    {
        $librePeda = ! $bloqPeda;
        $libreAdmi = ! $bloqAdmi;

        $textoRequisitos = match (true) {
            $librePeda && $libreAdmi => 'administrativos y/o pedagógicos',
            $libreAdmi => 'administrativos',
            default => 'pedagógicos',
        };

        return "Estimada Familia:\n"
            .'Les informamos que, habiendo cumplimentado los requisitos '
            .$textoRequisitos
            .' pendientes, la matrícula para el próximo año lectivo del/la estudiante se encuentra desbloqueada.'
            ."\nPor lo tanto, ya están en condiciones de continuar con el trámite correspondiente de matriculación."
            ."\nAtte.\nEquipo Directivo";
    }

    /**
     * El módulo de comunicaciones ya envió (o encoló) el primer mail válido
     * (madre → padre → tutor). Aquí se reenvía el mismo comunicado a los otros
     * contactos del legajo con correo, solo para esta notificación.
     */
    private static function enviarCorreosFamiliaRestantes(ComHilo $hilo, ?Legajo $legajo): void
    {
        if ($legajo === null) {
            return;
        }

        $todos = self::emailsFamiliaValidos($legajo);
        if (count($todos) <= 1) {
            return;
        }

        $idMensaje = (int) ($hilo->cuerpo_inicial_id ?? 0);
        if ($idMensaje < 1) {
            return;
        }

        $mensaje = ComMensaje::query()->with('hilo')->find($idMensaje);
        if ($mensaje === null) {
            return;
        }

        $destinatario = ComMensajeDestinatario::query()
            ->where('id_mensaje', $idMensaje)
            ->where('tipo_destinatario', 'familia')
            ->first();

        if ($destinatario === null) {
            return;
        }

        $yaEnviado = MailAdapter::resolverDireccionCorreo($destinatario);
        $yaEnviado = $yaEnviado !== null ? mb_strtolower(trim($yaEnviado)) : null;

        $restantes = array_values(array_filter(
            $todos,
            static fn (string $email): bool => $yaEnviado === null || $email !== $yaEnviado
        ));

        if ($restantes === []) {
            return;
        }

        $idNivel = (int) ($hilo->id_nivel ?? 0);
        MailInstitucionalConfig::aplicarParaNivel($idNivel > 0 ? $idNivel : null);

        $nombreColegio = '';
        if ($idNivel > 0) {
            $insti = DB::table('ento')->where('idNivel', $idNivel)->value('insti');
            $nombreColegio = is_string($insti) ? trim($insti) : '';
        }

        foreach ($restantes as $email) {
            try {
                Mail::to($email)->send(new ComunicadoMail($mensaje, $destinatario, $nombreColegio));
                ComMensajeEnvio::create([
                    'id_mensaje_destinatario' => (int) $destinatario->id,
                    'medio'                   => 'email',
                    'estado'                  => 'enviado',
                    'motivo'                  => 'Refuerzo bloqueo matrícula (contacto adicional)',
                    'enviado_at'              => now(),
                ]);
            } catch (Throwable $e) {
                ComMensajeEnvio::create([
                    'id_mensaje_destinatario' => (int) $destinatario->id,
                    'medio'                   => 'email',
                    'estado'                  => 'fallido',
                    'motivo'                  => mb_substr($e->getMessage(), 0, 250),
                    'enviado_at'              => null,
                ]);
            }
        }
    }

    /**
     * Correos válidos del legajo (madre → padre → tutor), sin duplicados.
     * Misma selección que el envío de refuerzo.
     *
     * @return list<array{rol: string, email: string}>
     */
    public static function correosFamiliaParaEnvio(?Legajo $legajo): array
    {
        if ($legajo === null) {
            return [];
        }

        return self::correosFamiliaValidosDesdeCampos(
            $legajo->emailmad ?? null,
            $legajo->emailpad ?? null,
            $legajo->emailtut ?? null,
        );
    }

    /**
     * @return list<array{rol: string, email: string}>
     */
    public static function correosFamiliaValidosDesdeCampos(
        ?string $emailMad,
        ?string $emailPad,
        ?string $emailTut,
    ): array {
        $vistos = [];
        $validos = [];

        foreach ([
            ['Madre', $emailMad],
            ['Padre', $emailPad],
            ['Tutor', $emailTut],
        ] as [$rol, $candidato]) {
            $email = mb_strtolower(trim((string) ($candidato ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (isset($vistos[$email])) {
                continue;
            }
            $vistos[$email] = true;
            $validos[] = [
                'rol' => $rol,
                'email' => $email,
            ];
        }

        return $validos;
    }

    /**
     * @return list<string>
     */
    private static function emailsFamiliaValidos(Legajo $legajo): array
    {
        return array_map(
            static fn (array $item): string => $item['email'],
            self::correosFamiliaParaEnvio($legajo)
        );
    }

    /**
     * @return array{estado: ?string, motivo: ?string, destino: ?string}
     */
    private static function resumenEnvioEmail(ComHilo $hilo, ?Legajo $legajo): array
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

        $envios = ComMensajeEnvio::query()
            ->where('medio', 'email')
            ->whereIn('id_mensaje_destinatario', $idsDest)
            ->orderBy('id')
            ->get();

        if ($envios->isEmpty()) {
            return ['estado' => null, 'motivo' => 'No se registró intento de correo.', 'destino' => null];
        }

        $destino = null;
        if ($legajo !== null) {
            $emails = self::emailsFamiliaValidos($legajo);
            $destino = $emails !== [] ? implode(', ', $emails) : null;
        }

        if ($destino === null) {
            $dest = $destinatarios->first();
            if ($dest instanceof ComMensajeDestinatario) {
                $uno = MailAdapter::resolverDireccionCorreo($dest);
                $destino = $uno !== null ? mb_strtolower(trim($uno)) : null;
                if ($destino === '') {
                    $destino = null;
                }
            }
        }

        $estados = $envios->pluck('estado')->map(fn ($e) => (string) $e)->all();
        $huboEnviado = in_array('enviado', $estados, true);
        $huboFallido = in_array('fallido', $estados, true);
        $huboPendiente = in_array('pendiente', $estados, true);
        $soloNoAplicable = $estados !== [] && count(array_unique($estados)) === 1 && $estados[0] === 'no_aplicable';

        if ($soloNoAplicable) {
            return [
                'estado'  => 'no_aplicable',
                'motivo'  => trim((string) ($envios->first()->motivo ?? '')) ?: 'Sin dirección de correo disponible',
                'destino' => $destino,
            ];
        }

        if ($huboEnviado && ! $huboFallido && ! $huboPendiente) {
            return ['estado' => 'enviado', 'motivo' => null, 'destino' => $destino];
        }

        if ($huboEnviado && $huboFallido) {
            $motivoFallido = $envios
                ->first(fn (ComMensajeEnvio $e) => (string) ($e->estado ?? '') === 'fallido');

            return [
                'estado'  => 'enviado',
                'motivo'  => 'Algunos destinos fallaron'
                    .($motivoFallido && trim((string) ($motivoFallido->motivo ?? '')) !== ''
                        ? ': '.trim((string) $motivoFallido->motivo)
                        : '.'),
                'destino' => $destino,
            ];
        }

        if ($huboFallido && ! $huboEnviado) {
            $motivo = $envios
                ->first(fn (ComMensajeEnvio $e) => (string) ($e->estado ?? '') === 'fallido');

            return [
                'estado'  => 'fallido',
                'motivo'  => ($m = trim((string) ($motivo?->motivo ?? ''))) !== '' ? $m : null,
                'destino' => $destino,
            ];
        }

        if ($huboPendiente) {
            return ['estado' => 'pendiente', 'motivo' => null, 'destino' => $destino];
        }

        $ultimo = $envios->last();

        return [
            'estado'  => (string) ($ultimo->estado ?? ''),
            'motivo'  => ($m = trim((string) ($ultimo->motivo ?? ''))) !== '' ? $m : null,
            'destino' => $destino,
        ];
    }
}
