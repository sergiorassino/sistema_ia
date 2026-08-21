<?php

namespace App\Livewire\MatriculaWeb;

use App\Livewire\Concerns\RequiresPermisoMatriculaWeb;
use App\Models\Matricula;
use App\Support\Mail\MailDesarrollo;
use App\Support\MatriculaWeb\BloqueosMatriculaConsulta;
use App\Support\MatriculaWeb\BloqueosMatriculaService;
use App\Support\MatriculaWeb\NotificarFamiliaBloqueoMatricula;
use App\Support\PermisosMatriculaWeb;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithPagination;

class BloqueosMatriculaIndex extends Component
{
    use RequiresPermisoMatriculaWeb;
    use WithPagination;

    /** 0 = todos los cursos (alfabético); >0 = filtrar por curso. */
    public int $idCurso = 0;

    /** Apellido, nombre o DNI (mismo criterio que legajos). */
    public string $busqueda = '';

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'idCurso' => ['except' => 0, 'as' => 'curso'],
        'busqueda' => ['except' => '', 'as' => 'buscar'],
    ];

    protected function permisoMatriculaWebOrden(): int
    {
        return PermisosMatriculaWeb::BLOQUEOS_MATRICULA;
    }

    public function mount(): void
    {
        $ctx = schoolCtx();
        if ($ctx->idNivel < 1 || $ctx->idTerlec < 1) {
            abort(403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
        }

        $this->idCurso = BloqueosMatriculaConsulta::opcionesCurso()
            ->pluck('id')
            ->contains($this->idCurso)
            ? $this->idCurso
            : 0;
    }

    public function updatedIdCurso(): void
    {
        $this->resetPage();
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function alternarBloqueo(int $idMatricula, string $campo): void
    {
        abort_unless(PermisosMatriculaWeb::tiene(PermisosMatriculaWeb::BLOQUEOS_MATRICULA), 403);

        $rateKey = 'matricula-web:bloqueos:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 60)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados cambios seguidos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $resultado = BloqueosMatriculaService::alternar($idMatricula, $campo);

        if (! $resultado['exito']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }
    }

    public function aplicarBloqueoMasivo(string $campo, bool $bloquear): void
    {
        abort_unless(PermisosMatriculaWeb::tiene(PermisosMatriculaWeb::BLOQUEOS_MATRICULA), 403);

        $rateKey = 'matricula-web:bloqueos-masivo:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados cambios masivos seguidos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $resultado = BloqueosMatriculaService::aplicarMasivo($this->idCurso, $campo, $bloquear, $this->busqueda);

        if (! $resultado['exito']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }

        $this->dispatch('se-swal-exito', mensaje: $resultado['mensaje']);
    }

    public function notificarFamilia(int $idMatricula): void
    {
        abort_unless(PermisosMatriculaWeb::tiene(PermisosMatriculaWeb::BLOQUEOS_MATRICULA), 403);

        $rateKey = 'matricula-web:bloqueos-notif:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');

            return;
        }
        RateLimiter::hit($rateKey, 60);

        $enAlcance = BloqueosMatriculaConsulta::matriculaEnAlcance($idMatricula);
        if ($enAlcance === null) {
            $this->dispatch('se-swal-error', mensaje: 'Matrícula no encontrada en el listado actual.');

            return;
        }

        if (! (bool) ($enAlcance->bloqmatr ?? false) && ! (bool) ($enAlcance->bloqadmi ?? false)) {
            $this->dispatch('se-swal-error', mensaje: 'El alumno no tiene bloqueo activo para notificar.');

            return;
        }

        $matricula = Matricula::query()
            ->with(['legajo', 'curso', 'nivel'])
            ->find($idMatricula);

        if ($matricula === null) {
            $this->dispatch('se-swal-error', mensaje: 'No se encontró la matrícula.');

            return;
        }

        $resultado = NotificarFamiliaBloqueoMatricula::despachar($matricula);

        if (! ($resultado['ok'] ?? false)) {
            $detalle = trim((string) ($resultado['motivo_fallo'] ?? ''));
            $mensaje = 'No se pudo enviar la notificación. Verificá que el canal del remitente → familia esté activo en Parametrización → Canales de Comunicación.';
            if ($detalle !== '') {
                $mensaje .= ' '.$detalle;
            }
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        }

        if (($resultado['refuerzo_mail_pedido'] ?? false) && ! ($resultado['email_incluido'] ?? false)) {
            $this->dispatch(
                'se-swal-aviso',
                mensaje: 'El comunicado se creó en el cuaderno, pero no se envió correo: el canal del remitente hacia Familia no tiene el medio «email» habilitado. Activá correo en Parametrización → Canales de Comunicación (rol del remitente → Estudiantes/Familias) y volvé a notificar.',
                titulo: 'Sin correo'
            );

            return;
        }

        if ($resultado['email_incluido'] ?? false) {
            $mailer = strtolower(trim((string) ($resultado['email_mailer'] ?? '')));
            if ($mailer !== '' && $mailer !== 'smtp') {
                $mensajeMailer = MailDesarrollo::bloquearSmtp()
                    ? 'El comunicado se creó. En desarrollo (APP_ENV=local) el correo no sale por SMTP: queda en storage/logs. Para una prueba real: MAIL_FORCE_REAL=true y MAIL_MAILER=smtp.'
                    : 'El comunicado se creó en el cuaderno, pero el correo no salió por SMTP real (MAIL_MAILER='.$mailer.'). En el .env de producción debe ser MAIL_MAILER=smtp y la cuenta/contraseña se configuran en Parametrización → Correo institucional (ento del nivel).';
                $this->dispatch(
                    'se-swal-aviso',
                    mensaje: $mensajeMailer,
                    titulo: MailDesarrollo::bloquearSmtp() ? 'Correo en log (desarrollo)' : 'Correo no enviado'
                );

                return;
            }

            $smtpUser = trim((string) ($resultado['email_smtp_user'] ?? ''));
            if ($smtpUser === '') {
                $this->dispatch(
                    'se-swal-aviso',
                    mensaje: 'El comunicado se creó, pero no hay cuenta SMTP configurada. Cargá usuario y contraseña de aplicación en Parametrización → Correo institucional Gmail (se guarda en ento.ctaEnvioMail / passEnvioMail del nivel).',
                    titulo: 'Correo no configurado'
                );

                return;
            }

            $estadoEmail = (string) ($resultado['email_estado'] ?? '');
            $motivoEmail = trim((string) ($resultado['email_motivo'] ?? ''));
            $emailDestino = trim((string) ($resultado['email_destino'] ?? ''));

            if ($estadoEmail === 'enviado') {
                $detalleDestino = $emailDestino !== ''
                    ? ' Destinatarios: '.$emailDestino.'.'
                    : '';
                $avisoParcial = trim((string) ($resultado['email_motivo'] ?? ''));
                $mensajeExito = 'Notificación enviada a la familia. Correo de refuerzo enviado.'.$detalleDestino;
                if ($avisoParcial !== '') {
                    $mensajeExito .= ' '.$avisoParcial;
                }
                $this->dispatch(
                    'se-swal-exito',
                    mensaje: $mensajeExito,
                    titulo: 'Correo enviado'
                );

                return;
            }

            if ($estadoEmail === 'fallido') {
                $this->dispatch(
                    'se-swal-aviso',
                    mensaje: 'El comunicado se creó en el cuaderno, pero el correo falló'
                        .($motivoEmail !== '' ? ': '.$motivoEmail : '.')
                        .' Revisá SMTP del servidor / contraseña de aplicación Gmail.',
                    titulo: 'Correo fallido'
                );

                return;
            }

            if ($estadoEmail === 'no_aplicable') {
                $this->dispatch(
                    'se-swal-aviso',
                    mensaje: 'El comunicado se creó, pero no hay correo de familia usable'
                        .($motivoEmail !== '' ? ' ('.$motivoEmail.')' : '.')
                        .' Completá emailmad / emailpad / emailtut en el legajo.',
                    titulo: 'Sin dirección de correo'
                );

                return;
            }

            if ($estadoEmail === 'pendiente') {
                $this->dispatch(
                    'se-swal-aviso',
                    mensaje: 'El correo quedó pendiente en cola. Verificá que el worker de colas esté corriendo en el servidor.',
                    titulo: 'Correo pendiente'
                );

                return;
            }

            $this->dispatch(
                'se-swal-aviso',
                mensaje: 'El comunicado se creó, pero no se pudo confirmar el envío de correo'
                    .($motivoEmail !== '' ? ': '.$motivoEmail : '.')
                    .' Revisá MAIL_MAILER=smtp y la configuración de Correo institucional.',
                titulo: 'Correo no confirmado'
            );

            return;
        }

        $this->dispatch(
            'se-swal-exito',
            mensaje: 'Notificación enviada a la familia (cuaderno / push).',
            titulo: 'Notificación enviada'
        );
    }

    public function render()
    {
        $ctx = schoolCtx();
        $opcionesCurso = BloqueosMatriculaConsulta::opcionesCurso();
        $alumnos = BloqueosMatriculaConsulta::paginar($this->idCurso, $this->busqueda);

        return view('livewire.matricula-web.bloqueos-matricula-index', [
            'opcionesCurso' => $opcionesCurso,
            'alumnos' => $alumnos,
            'totalAlumnos' => $alumnos->total(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Bloqueos de matrícula']);
    }
}
