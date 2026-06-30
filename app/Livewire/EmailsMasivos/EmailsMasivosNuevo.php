<?php

namespace App\Livewire\EmailsMasivos;

use App\Models\Profesor;
use App\Push\DestinatariosRepository;
use App\Support\EmailsMasivos\DestinatariosEmailsMasivos;
use App\Support\EmailsMasivos\EmailsMasivosAdjuntosStorage;
use App\Support\EmailsMasivos\EmailsMasivosConfig;
use App\Support\EmailsMasivos\EnvioCorreoMasivo;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;

class EmailsMasivosNuevo extends Component
{
    use WithFileUploads;

    /** redactar | destinatarios | confirmar | resultado */
    public string $paso = 'redactar';

    public string $asunto = '';

    public string $contenidoHtml = '';

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $adjuntosArchivos = [];

    /** alumnos | cursos */
    public string $tipoDestino = 'cursos';

    /** multiple | prioridad */
    public string $modoContacto = 'multiple';

    public bool $incluirMadre = true;

    public bool $incluirPadre = true;

    public bool $incluirTutor = true;

    /** @var list<array{id:int,label:string}> */
    public array $alumnosSeleccionados = [];

    /** @var list<array{id:int,label:string}> */
    public array $cursosSeleccionados = [];

    /**
     * @var list<array{key:string,idLegajo:int,idCurso:int,label:string,cursoLabel:string,marcado:bool}>
     */
    public array $lineasAlumnos = [];

    public bool $modalAlumnosAbierto = false;

    public string $modalAlumnosFiltro = '';

    /** @var list<array{id:int,label:string,dni:?string,idCurso:int}> */
    public array $modalAlumnosLista = [];

    /** @var list<int> */
    public array $modalAlumnosMarcados = [];

    public bool $modalCursosAbierto = false;

    public string $modalCursosFiltro = '';

    /** @var list<array{id:int,label:string}> */
    public array $modalCursosLista = [];

    /** @var list<int> */
    public array $modalCursosMarcados = [];

    /** @var list<string> */
    public array $resultadoDestinatarios = [];

    public string $resultadoMensaje = '';

    public ?int $resultadoIdEscrito = null;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::EMAILS_MASIVOS_ESTUDIANTES), 403);
        abort_unless(Schema::hasTable('emails_escritos') && Schema::hasTable('emails_enviados'), 404);
    }

    public function updatedModalAlumnosFiltro(): void
    {
        if ($this->modalAlumnosAbierto) {
            $this->recargarModalAlumnosLista();
        }
    }

    public function updatedModalCursosFiltro(): void
    {
        if ($this->modalCursosAbierto) {
            $this->recargarModalCursosLista();
        }
    }

    public function updatedTipoDestino(): void
    {
        $this->lineasAlumnos = [];
        $this->alumnosSeleccionados = [];
        $this->cursosSeleccionados = [];
    }

    public function irADestinatarios(): void
    {
        $this->validate([
            'asunto' => ['required', 'string', 'max:254'],
            'contenidoHtml' => ['required', 'string', 'min:3'],
        ], [
            'asunto.required' => 'Ingrese el asunto.',
            'contenidoHtml.required' => 'Redacte el cuerpo del mensaje.',
        ]);

        $this->validarAdjuntosPendientes();
        $this->paso = 'destinatarios';
    }

    public function irAConfirmacion(): void
    {
        $this->validarSeleccionAlumnos();
        $this->paso = 'confirmar';
    }

    public function volverARedactar(): void
    {
        $this->paso = 'redactar';
    }

    public function volverADestinatarios(): void
    {
        $this->paso = 'destinatarios';
    }

    public function confirmarYEnviar(): void
    {
        $this->validarSeleccionAlumnos();
        $destinatarios = $this->calcularDestinatariosEnvio();
        $n = count($destinatarios);

        if ($n > EmailsMasivosConfig::maxDestinatariosPorEnvio()) {
            $this->dispatch('se-swal-error', mensaje: 'La selección supera el máximo de '
                . EmailsMasivosConfig::maxDestinatariosPorEnvio()
                . ' destinatarios. Reduzca el alcance antes de enviar.');

            return;
        }

        $ctx = schoolCtx();
        $profesor = $ctx->profesor();
        abort_if($profesor === null, 403);

        $resultado = EnvioCorreoMasivo::ejecutar(
            $profesor,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $this->asunto,
            $this->contenidoHtml,
            $destinatarios,
            $this->adjuntosArchivos,
        );

        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['mensaje']);

            return;
        }

        $this->resultadoDestinatarios = $resultado['destinatarios'];
        $this->resultadoMensaje = $resultado['mensaje'];
        $this->resultadoIdEscrito = $resultado['idEmailEscrito'];
        $this->paso = 'resultado';
        $this->dispatch('se-swal-exito', mensaje: $resultado['mensaje']);
    }

    public function abrirModalAlumnos(): void
    {
        $this->modalAlumnosAbierto = true;
        $this->modalAlumnosFiltro = '';
        $this->modalAlumnosMarcados = array_map(fn ($a) => (int) $a['id'], $this->alumnosSeleccionados);
        $this->recargarModalAlumnosLista();
    }

    public function cerrarModalAlumnos(): void
    {
        $this->modalAlumnosAbierto = false;
    }

    public function aplicarModalAlumnos(): void
    {
        $ctx = schoolCtx();
        $labelsPorId = collect($this->modalAlumnosLista)->keyBy('id');
        $prev = collect($this->alumnosSeleccionados)->keyBy('id');
        $out = [];

        foreach (array_unique(array_map('intval', $this->modalAlumnosMarcados)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $fromLista = $labelsPorId->get($id);
            if ($fromLista !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromLista['label']];
            } elseif ($prev->has($id)) {
                $out[] = ['id' => $id, 'label' => (string) $prev->get($id)['label']];
            }
        }

        $this->alumnosSeleccionados = $out;
        $this->modalAlumnosAbierto = false;
        $this->reconstruirLineasDesdeAlumnos((int) $ctx->idNivel, (int) $ctx->idTerlec);
    }

    public function abrirModalCursos(): void
    {
        $this->modalCursosAbierto = true;
        $this->modalCursosFiltro = '';
        $this->modalCursosMarcados = array_map(fn ($c) => (int) $c['id'], $this->cursosSeleccionados);
        $this->recargarModalCursosLista();
    }

    public function cerrarModalCursos(): void
    {
        $this->modalCursosAbierto = false;
    }

    public function aplicarModalCursos(): void
    {
        $labelsPorId = collect($this->modalCursosLista)->keyBy('id');
        $prev = collect($this->cursosSeleccionados)->keyBy('id');
        $out = [];

        foreach (array_unique(array_map('intval', $this->modalCursosMarcados)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $fromLista = $labelsPorId->get($id);
            if ($fromLista !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromLista['label']];
            } elseif ($prev->has($id)) {
                $out[] = ['id' => $id, 'label' => (string) $prev->get($id)['label']];
            }
        }

        $this->cursosSeleccionados = $out;
        $this->modalCursosAbierto = false;

        $ctx = schoolCtx();
        $this->reconstruirLineasDesdeCursos((int) $ctx->idNivel, (int) $ctx->idTerlec);
    }

    public function modalCursosSeleccionarTodos(): void
    {
        $this->modalCursosMarcados = array_map(fn ($c) => (int) $c['id'], $this->modalCursosLista);
    }

    public function toggleLineaAlumno(string $key): void
    {
        foreach ($this->lineasAlumnos as $i => $linea) {
            if (($linea['key'] ?? '') === $key) {
                $this->lineasAlumnos[$i]['marcado'] = ! ($linea['marcado'] ?? false);
                break;
            }
        }
    }

    public function marcarTodosLineasCurso(int $idCurso, bool $marcado): void
    {
        foreach ($this->lineasAlumnos as $i => $linea) {
            if ((int) ($linea['idCurso'] ?? 0) === $idCurso) {
                $this->lineasAlumnos[$i]['marcado'] = $marcado;
            }
        }
    }

    public function removeAdjunto(int $index): void
    {
        unset($this->adjuntosArchivos[$index]);
        $this->adjuntosArchivos = array_values($this->adjuntosArchivos);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function calcularDestinatariosEnvio(): array
    {
        return DestinatariosEmailsMasivos::resolverDestinatariosEnvio(
            $this->lineasAlumnosMarcadas(),
            $this->modoContacto,
            $this->incluirMadre,
            $this->incluirPadre,
            $this->incluirTutor,
        );
    }

    public function render()
    {
        $ctx = schoolCtx();
        $profesor = $ctx->profesor();
        $credencialesOk = $profesor !== null
            && trim((string) ($profesor->email ?? '')) !== ''
            && trim((string) ($profesor->emailPass ?? '')) !== '';

        $destinatarios = $this->paso === 'confirmar' || $this->paso === 'destinatarios'
            ? $this->calcularDestinatariosEnvio()
            : [];

        $lineasMarcadas = $this->lineasAlumnosMarcadas();
        $totalAlumnosMarcados = count($lineasMarcadas);
        $nEnvios = count($destinatarios);
        $maxEnvio = EmailsMasivosConfig::maxDestinatariosPorEnvio();
        $avisoEnvio = EmailsMasivosConfig::maxDestinatariosAviso();

        $omitidos = max(0, $totalAlumnosMarcados - count(array_unique(array_map(
            fn ($l) => (int) $l['idLegajo'],
            $lineasMarcadas,
        ))));

        return view('livewire.emails-masivos.emails-masivos-nuevo', [
            'credencialesOk' => $credencialesOk,
            'profesor' => $profesor,
            'destinatariosPreview' => $destinatarios,
            'nEnvios' => $nEnvios,
            'maxEnvio' => $maxEnvio,
            'avisoEnvio' => $avisoEnvio,
            'superaTope' => $nEnvios > $maxEnvio,
            'superaAviso' => $nEnvios > $avisoEnvio && $nEnvios <= $maxEnvio,
            'simulado' => EmailsMasivosConfig::simulado(),
            'lineasPorCurso' => collect($this->lineasAlumnos)->groupBy('idCurso'),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Nuevo correo masivo']);
    }

    private function validarAdjuntosPendientes(): void
    {
        if ($this->adjuntosArchivos === []) {
            return;
        }

        $maxBytes = EmailsMasivosConfig::adjuntoMaxBytes();
        $maxCount = EmailsMasivosConfig::adjuntosMaxCount();
        if (count($this->adjuntosArchivos) > $maxCount) {
            $this->addError('adjuntosArchivos', 'Máximo ' . $maxCount . ' adjuntos.');

            throw \Illuminate\Validation\ValidationException::withMessages([
                'adjuntosArchivos' => 'Máximo ' . $maxCount . ' adjuntos.',
            ]);
        }

        $nombres = [];
        foreach ($this->adjuntosArchivos as $file) {
            if ($file->getSize() > $maxBytes) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'adjuntosArchivos' => 'Cada adjunto debe pesar como máximo '
                        . (int) config('emails_masivos.adjunto_max_mb', 10) . ' MB.',
                ]);
            }
            $nombres[] = EmailsMasivosAdjuntosStorage::nombreSeguro($file->getClientOriginalName());
        }

        if ($err = EmailsMasivosAdjuntosStorage::validarListaNombres($nombres)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'adjuntosArchivos' => $err,
            ]);
        }
    }

    private function validarSeleccionAlumnos(): void
    {
        if ($this->lineasAlumnosMarcadas() === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lineasAlumnos' => 'Seleccione al menos un alumno matriculado regular.',
            ]);
        }

        if ($this->modoContacto === 'multiple' && ! $this->incluirMadre && ! $this->incluirPadre && ! $this->incluirTutor) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'modoContacto' => 'Seleccione al menos un tipo de contacto (madre, padre o tutor).',
            ]);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function lineasAlumnosMarcadas(): array
    {
        return array_values(array_filter(
            $this->lineasAlumnos,
            fn (array $l) => ! empty($l['marcado']),
        ));
    }

    private function reconstruirLineasDesdeAlumnos(int $idNivel, int $idTerlec): void
    {
        $lineas = [];
        foreach ($this->alumnosSeleccionados as $alumno) {
            $idLegajo = (int) $alumno['id'];
            $mat = DestinatariosEmailsMasivos::matriculaRegularDeLegajo($idNivel, $idTerlec, $idLegajo);
            if ($mat === null) {
                continue;
            }
            $idCurso = (int) $mat['idCurso'];
            $lineas[] = [
                'key' => 'a-' . $idLegajo,
                'idLegajo' => $idLegajo,
                'idCurso' => $idCurso,
                'label' => (string) $alumno['label'],
                'cursoLabel' => '',
                'marcado' => true,
            ];
        }
        $this->lineasAlumnos = $lineas;
    }

    private function reconstruirLineasDesdeCursos(int $idNivel, int $idTerlec): void
    {
        $lineas = [];
        foreach ($this->cursosSeleccionados as $curso) {
            $idCurso = (int) $curso['id'];
            $alumnos = DestinatariosEmailsMasivos::alumnosRegularesPorCurso($idNivel, $idTerlec, $idCurso);
            foreach ($alumnos as $a) {
                $lineas[] = [
                    'key' => $idCurso . '-' . $a['id'],
                    'idLegajo' => (int) $a['id'],
                    'idCurso' => $idCurso,
                    'label' => (string) $a['label'],
                    'cursoLabel' => (string) $curso['label'],
                    'marcado' => true,
                ];
            }
        }
        $this->lineasAlumnos = $lineas;
    }

    private function recargarModalAlumnosLista(): void
    {
        $ctx = schoolCtx();
        if (! $ctx->idNivel || ! $ctx->idTerlec) {
            $this->modalAlumnosLista = [];

            return;
        }

        $t = trim($this->modalAlumnosFiltro);
        if ($t === '') {
            $this->modalAlumnosLista = [];

            return;
        }

        $this->modalAlumnosLista = DestinatariosEmailsMasivos::buscarAlumnosRegulares(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $t,
            50,
        );
    }

    private function recargarModalCursosLista(): void
    {
        $ctx = schoolCtx();
        if (! $ctx->idNivel || ! $ctx->idTerlec) {
            $this->modalCursosLista = [];

            return;
        }

        $all = DestinatariosRepository::cursosDelContexto((int) $ctx->idNivel, (int) $ctx->idTerlec);
        $f = mb_strtolower(trim($this->modalCursosFiltro));
        if ($f !== '') {
            $all = array_values(array_filter(
                $all,
                fn (array $c) => str_contains(mb_strtolower((string) ($c['label'] ?? '')), $f),
            ));
        }
        $this->modalCursosLista = $all;
    }
}
