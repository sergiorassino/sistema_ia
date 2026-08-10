<?php

namespace App\Livewire\Seguimiento\Disciplinario;

use App\Livewire\Seguimiento\Disciplinario\Concerns\RequiresPermisoSeguimientoDisciplinario;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Sancion;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\Seguimiento\NotificarFamiliaSancion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class DisciplinarioIndex extends Component
{
    use RequiresPermisoSeguimientoDisciplinario;

    public int|string $idCurso = '';
    public int|string $idMatricula = '';

    public function mount(): void
    {
        $ctx = ContextoEstudianteSesion::leer(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO);
        $this->idCurso = (string) ($ctx['curso'] ?? '');
        $this->idMatricula = (string) ($ctx['matricula'] ?? '');
    }

    private function persistirContextoEnSesion(): void
    {
        ContextoEstudianteSesion::fijar(ContextoEstudianteSesion::SEGUIMIENTO_DISCIPLINARIO, [
            'curso' => (int) $this->idCurso ?: null,
            'matricula' => (int) $this->idMatricula ?: null,
        ]);
    }

    public function updatedIdCurso(mixed $value): void
    {
        $this->idCurso = is_scalar($value) ? (string) $value : '';
        $this->idMatricula = '';
        $this->persistirContextoEnSesion();
    }

    public function updatedIdMatricula(mixed $value): void
    {
        $this->idMatricula = is_scalar($value) ? (string) $value : '';
        $this->persistirContextoEnSesion();
    }

    /** @return Collection<int, Curso> */
    private function cursosDelContexto(): Collection
    {
        return Curso::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idTurnoClase', 'c', 's']);
    }

    /** @return Collection<int, object> */
    private function alumnosDelCurso(int $idCurso): Collection
    {
        return Matricula::query()
            ->where('matricula.idNivel', schoolCtx()->idNivel)
            ->where('matricula.idTerlec', schoolCtx()->idTerlec)
            ->where('matricula.idCursos', $idCurso)
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->select([
                'matricula.id',
                'matricula.idLegajos',
                'legajos.apellido',
                'legajos.nombre',
                'legajos.dni',
            ])
            ->get();
    }

    private function matriculaSeleccionada(): ?Matricula
    {
        $id = (int) $this->idMatricula;
        if ($id <= 0) {
            return null;
        }

        return Matricula::query()
            ->with(['legajo', 'curso'])
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('idTerlec', schoolCtx()->idTerlec)
            ->find($id);
    }

    /** @return Collection<int, Sancion> */
    private function sancionesDeMatricula(int $idMatricula): Collection
    {
        return Sancion::query()
            ->with(['tipo', 'profesor'])
            ->where('idMatricula', $idMatricula)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    public function notificarPadres(int $id): void
    {
        $m = $this->matriculaSeleccionada();
        if (! $m) {
            $this->dispatch('se-swal-error', mensaje: 'Sin matrícula seleccionada.');
            return;
        }

        $key = 'sanciones:notif:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento.');
            return;
        }
        RateLimiter::hit($key, 60);

        $s = Sancion::query()
            ->with(['tipo.profesorNotif', 'profesor', 'matricula'])
            ->where('idMatricula', (int) $m->id)
            ->findOrFail($id);

        $tipo = $s->tipo;

        if (! $tipo || ! $tipo->idProfesorNotif) {
            $this->dispatch('se-swal-error', mensaje: 'Este tipo de sanción no tiene remitente configurado. Configuralo en Parametrización → Tipos de sanción.');
            return;
        }

        $resultado = NotificarFamiliaSancion::despachar($s, $m);

        if (! ($resultado['ok'] ?? false)) {
            $detalle = trim((string) ($resultado['motivo_fallo'] ?? ''));
            $mensaje = 'No se pudo enviar la notificación. Verificá que el canal del remitente → familia esté activo en Parametrización → Canales de Comunicación.';
            if ($detalle !== '') {
                $mensaje .= ' '.$detalle;
            }
            $this->dispatch('se-swal-error', mensaje: $mensaje);
            return;
        }

        // Marcar la sanción como comunicada
        $s->comunicadaPadres = true;
        $s->save();

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
                $this->dispatch(
                    'se-swal-aviso',
                    mensaje: 'El comunicado se creó en el cuaderno, pero el correo no salió por SMTP real (MAIL_MAILER='.$mailer.'). En el .env de producción debe ser MAIL_MAILER=smtp y la cuenta/contraseña se configuran en Parametrización → Correo institucional Gmail (no el campo «Mail» de datos del colegio / ento).',
                    titulo: 'Correo no enviado'
                );

                return;
            }

            $smtpUser = trim((string) ($resultado['email_smtp_user'] ?? ''));
            if ($smtpUser === '') {
                $this->dispatch(
                    'se-swal-aviso',
                    mensaje: 'El comunicado se creó, pero no hay cuenta SMTP configurada. Cargá usuario y contraseña de aplicación en Parametrización → Correo institucional Gmail (se guarda en storage, no en la tabla ento).',
                    titulo: 'Correo no configurado'
                );

                return;
            }

            $estadoEmail = (string) ($resultado['email_estado'] ?? '');
            $motivoEmail = trim((string) ($resultado['email_motivo'] ?? ''));

            if ($estadoEmail === 'enviado') {
                $this->dispatch(
                    'se-swal-exito',
                    mensaje: 'Notificación enviada a la familia. Correo de refuerzo aceptado por SMTP ('.$smtpUser.').',
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
            mensaje: 'Notificación enviada a la familia (cuaderno / push). Para incluir correo, activá «Refuerzo por correo» en el tipo de sanción.',
            titulo: 'Notificación enviada'
        );
    }

    public function render()
    {
        $cursos = $this->cursosDelContexto();

        $alumnos = collect();
        $cursoId = (int) $this->idCurso;
        if ($cursoId > 0) {
            $alumnos = $this->alumnosDelCurso($cursoId);
        }

        $matricula = $this->matriculaSeleccionada();
        $sanciones = collect();

        if ($matricula) {
            $sanciones = $this->sancionesDeMatricula((int) $matricula->id);
        } else {
            // si hay matrícula inválida para el curso/contexto, limpiar selección
            if ((int) $this->idMatricula > 0) {
                $this->idMatricula = '';
            }
        }

        return view('livewire.seguimiento.disciplinario.index', compact('cursos', 'alumnos', 'matricula', 'sanciones'))
            ->layout(layoutMenuStaff(), ['pageTitle' => 'Seguimiento disciplinario']);
    }
}

