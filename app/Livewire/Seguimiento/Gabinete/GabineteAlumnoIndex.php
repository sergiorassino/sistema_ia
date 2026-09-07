<?php

namespace App\Livewire\Seguimiento\Gabinete;

use App\Livewire\Seguimiento\Gabinete\Concerns\RequiresPermisoSeguimientoGabinete;
use App\Models\Matricula;
use App\Support\Mail\MailDesarrollo;
use App\Support\Navegacion\ContextoEstudianteSesion;
use App\Support\Seguimiento\GabineteOrientacion;
use App\Support\Seguimiento\GabineteSemaforo;
use App\Support\Seguimiento\NotificarDocentesGabinete;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class GabineteAlumnoIndex extends Component
{
    use RequiresPermisoSeguimientoGabinete;

    public int $idMatricula = 0;

    public bool $modalCompartirAbierto = false;

    public int $idRegistroCompartir = 0;

    public string $modalDocentesFiltro = '';

    /** @var list<array{id:int,label:string,dni:?string,rol:string,rol_label:string}> */
    public array $modalDocentesLista = [];

    /** @var list<int|string> */
    public array $modalDocentesMarcados = [];

    public int $idCursoMarcar = 0;

    /** @var list<array{id:int,label:string}> */
    public array $cursosMarcar = [];

    public function mount(): void
    {
        abort_unless(GabineteOrientacion::tablasDisponibles(), 404, 'No hay tablas de gabinete en esta base.');

        $id = ContextoEstudianteSesion::matricula(ContextoEstudianteSesion::SEGUIMIENTO_GABINETE);
        abort_if($id === null, 404);
        $this->idMatricula = $id;
    }

    private function matricula(): Matricula
    {
        return GabineteOrientacion::matriculaEnContexto($this->idMatricula);
    }

    public function borrar(int $id): void
    {
        $key = 'gabinete:delete:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $m = $this->matricula();
        $reg = GabineteOrientacion::registroEnAlcance($id);
        if ((int) ($reg->matricula?->idLegajos ?? 0) !== (int) $m->idLegajos) {
            abort(404);
        }

        $reg->delete();

        $this->dispatch('se-swal-exito', mensaje: 'Registro de gabinete borrado.');
    }

    public function abrirCompartir(int $id): void
    {
        $m = $this->matricula();
        $reg = GabineteOrientacion::registroEnAlcance($id);
        if ((int) ($reg->matricula?->idLegajos ?? 0) !== (int) $m->idLegajos) {
            abort(404);
        }

        $this->idRegistroCompartir = $id;
        $this->modalCompartirAbierto = true;
        $this->modalDocentesFiltro = '';
        $this->modalDocentesMarcados = [];
        $this->cursosMarcar = GabineteOrientacion::cursosDelContexto()
            ->map(static fn ($c) => ['id' => (int) $c->Id, 'label' => $c->nombreParaListado()])
            ->values()
            ->all();
        $this->idCursoMarcar = (int) ($m->idCursos ?? 0);
        $this->recargarModalDocentesLista();
    }

    public function cerrarCompartir(): void
    {
        $this->modalCompartirAbierto = false;
        $this->idRegistroCompartir = 0;
        $this->modalDocentesFiltro = '';
        $this->modalDocentesLista = [];
        $this->modalDocentesMarcados = [];
        $this->idCursoMarcar = 0;
        $this->cursosMarcar = [];
    }

    public function updatedModalDocentesFiltro(): void
    {
        if ($this->modalCompartirAbierto) {
            $this->recargarModalDocentesLista();
        }
    }

    public function modalDocentesSeleccionarTodosVisibles(): void
    {
        $ids = array_map(fn ($r) => (int) $r['id'], $this->modalDocentesLista);
        $this->modalDocentesMarcados = array_values(array_unique(array_merge(
            array_map('intval', $this->modalDocentesMarcados),
            $ids
        )));
    }

    public function modalDocentesQuitarVisibles(): void
    {
        $vis = array_flip(array_map(fn ($r) => (int) $r['id'], $this->modalDocentesLista));
        $this->modalDocentesMarcados = array_values(array_filter(
            array_map('intval', $this->modalDocentesMarcados),
            fn (int $id) => ! isset($vis[$id])
        ));
    }

    public function marcarDocentesDelCurso(): void
    {
        if (! $this->modalCompartirAbierto) {
            return;
        }

        $idCurso = (int) $this->idCursoMarcar;
        $labelCurso = '';
        foreach ($this->cursosMarcar as $c) {
            if ((int) $c['id'] === $idCurso) {
                $labelCurso = (string) $c['label'];
                break;
            }
        }
        if ($idCurso < 1 || $labelCurso === '') {
            $this->dispatch('se-swal-error', mensaje: 'Elija un curso del ciclo actual.');

            return;
        }

        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $idTerlec = (int) ($ctx->idTerlec ?? 0);
        $excluirId = (int) ($ctx->idProfesor ?? 0);
        $idsPpc = NotificarDocentesGabinete::idsDocentesPpcDelCurso($idCurso, $idNivel, $idTerlec);
        $permitidos = [];
        foreach (NotificarDocentesGabinete::personalParaSelector($idNivel, '', 2000, $excluirId > 0 ? $excluirId : null) as $row) {
            $permitidos[(int) $row['id']] = true;
        }
        $ids = array_values(array_filter(
            $idsPpc,
            static fn (int $id): bool => $id !== $excluirId && isset($permitidos[$id])
        ));
        if ($ids === []) {
            $this->dispatch(
                'se-swal-aviso',
                mensaje: 'No hay docentes asignados a '.$labelCurso.' en este ciclo (tabla ppc), o no figuran en la lista de destinatarios.',
                titulo: 'Sin docentes del curso'
            );

            return;
        }

        $this->modalDocentesMarcados = array_values(array_unique(array_merge(
            array_map('intval', $this->modalDocentesMarcados),
            $ids
        )));
        $this->modalDocentesFiltro = '';
        $this->recargarModalDocentesLista();
        $n = count($ids);
        $this->dispatch(
            'se-swal-aviso',
            mensaje: 'Se marcaron '.$n.' docente'.($n === 1 ? '' : 's').' de '.$labelCurso.'. Puede sumar o quitar personas en la lista y luego enviar.',
            titulo: 'Curso marcado'
        );
    }

    public function compartir(): void
    {
        $key = 'gabinete:compartir:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiados intentos. Espere un momento e intente nuevamente.');

            return;
        }
        RateLimiter::hit($key, 60);

        $idReg = (int) $this->idRegistroCompartir;
        if ($idReg < 1 || ! $this->modalCompartirAbierto) {
            $this->dispatch('se-swal-error', mensaje: 'No hay un registro seleccionado para compartir.');

            return;
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $this->modalDocentesMarcados),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            $this->dispatch('se-swal-error', mensaje: 'Seleccione al menos un destinatario.');

            return;
        }

        $m = $this->matricula();
        $reg = GabineteOrientacion::registroEnAlcance($idReg);
        if ((int) ($reg->matricula?->idLegajos ?? 0) !== (int) $m->idLegajos) {
            abort(404);
        }

        $resultado = NotificarDocentesGabinete::despachar($reg, $m, $ids);

        if (! ($resultado['ok'] ?? false)) {
            $detalle = trim((string) ($resultado['motivo_fallo'] ?? ''));
            $mensaje = 'No se pudo compartir el registro.';
            if ($detalle !== '') {
                $mensaje .= ' '.$detalle;
            }
            $this->dispatch('se-swal-error', mensaje: $mensaje);

            return;
        }

        $this->cerrarCompartir();
        $this->avisarResultadoCompartir($resultado);
    }

    /**
     * @param  array{
     *     cantidad: int,
     *     omitidos_canal: int,
     *     email_incluido: bool,
     *     email_estado: ?string,
     *     email_motivo: ?string,
     *     email_destino: ?string,
     *     email_mailer: ?string,
     *     email_smtp_user: ?string
     * }  $resultado
     */
    private function avisarResultadoCompartir(array $resultado): void
    {
        $n = (int) ($resultado['cantidad'] ?? 0);
        $omitidos = (int) ($resultado['omitidos_canal'] ?? 0);
        $base = 'Se compartió el registro con '.$n.' destinatario'.($n === 1 ? '' : 's').'.';
        if ($omitidos > 0) {
            $base .= ' '.$omitidos.' destinatario(s) no recibieron el aviso por falta de canal.';
        }

        if (! ($resultado['email_incluido'] ?? false)) {
            $this->dispatch(
                'se-swal-exito',
                mensaje: $base.' El canal no tiene correo de refuerzo habilitado; el aviso quedó en el cuaderno de comunicaciones.',
                titulo: 'Registro compartido'
            );

            return;
        }

        $mailer = strtolower(trim((string) ($resultado['email_mailer'] ?? '')));
        if ($mailer !== '' && $mailer !== 'smtp') {
            $mensajeMailer = MailDesarrollo::bloquearSmtp()
                ? $base.' En desarrollo (APP_ENV=local) el correo no sale por SMTP: queda en storage/logs. Para una prueba real: MAIL_FORCE_REAL=true y MAIL_MAILER=smtp.'
                : $base.' El correo no salió por SMTP real (MAIL_MAILER='.$mailer.'). En producción debe ser MAIL_MAILER=smtp.';
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
                mensaje: $base.' No hay cuenta SMTP configurada. Cargá usuario y contraseña en Parametrización → Correo institucional.',
                titulo: 'Correo no configurado'
            );

            return;
        }

        $estadoEmail = (string) ($resultado['email_estado'] ?? '');
        $motivoEmail = trim((string) ($resultado['email_motivo'] ?? ''));
        $emailDestino = trim((string) ($resultado['email_destino'] ?? ''));

        if ($estadoEmail === 'enviado') {
            $detalleDestino = $emailDestino !== ''
                ? ' Destinatario de referencia: '.$emailDestino.'.'
                : '';
            $this->dispatch(
                'se-swal-exito',
                mensaje: $base.' Correo de refuerzo enviado.'.$detalleDestino,
                titulo: 'Registro compartido'
            );

            return;
        }

        if ($estadoEmail === 'fallido') {
            $this->dispatch(
                'se-swal-aviso',
                mensaje: $base.' El correo falló'.($motivoEmail !== '' ? ': '.$motivoEmail : '.')
                    .' Revisá SMTP del servidor / contraseña de aplicación Gmail.',
                titulo: 'Correo fallido'
            );

            return;
        }

        if ($estadoEmail === 'no_aplicable') {
            $this->dispatch(
                'se-swal-aviso',
                mensaje: $base.' No hay correo usable en el legajo docente'
                    .($motivoEmail !== '' ? ' ('.$motivoEmail.')' : '.')
                    .' Completá email / emailInsti en el personal.',
                titulo: 'Sin dirección de correo'
            );

            return;
        }

        $this->dispatch(
            'se-swal-exito',
            mensaje: $base,
            titulo: 'Registro compartido'
        );
    }

    private function recargarModalDocentesLista(): void
    {
        $ctx = schoolCtx();
        $idNivel = (int) ($ctx->idNivel ?? 0);
        $excluirId = (int) ($ctx->idProfesor ?? 0);
        if ($idNivel < 1) {
            $this->modalDocentesLista = [];

            return;
        }

        $this->modalDocentesLista = NotificarDocentesGabinete::personalParaSelector(
            $idNivel,
            $this->modalDocentesFiltro,
            800,
            $excluirId > 0 ? $excluirId : null
        );
    }

    public function render()
    {
        $matricula = $this->matricula();
        $registros = GabineteOrientacion::registrosDelLegajo((int) $matricula->idLegajos);
        $color = GabineteOrientacion::colorDelLegajo((int) $matricula->idLegajos);
        $claseNombre = GabineteSemaforo::claseFondoNombre($color);

        return view('livewire.seguimiento.gabinete.alumno', compact(
            'matricula',
            'registros',
            'color',
            'claseNombre',
        ))->layout(layoutMenuStaff(), ['pageTitle' => 'Seguimiento de gabinete']);
    }
}
