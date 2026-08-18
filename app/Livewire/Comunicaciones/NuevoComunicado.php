<?php

namespace App\Livewire\Comunicaciones;

use App\Comunicaciones\CanalesPolicy;
use App\Comunicaciones\ComGruposRepository;
use App\Comunicaciones\ComunicacionesRepository;
use App\Push\DestinatariosRepository;
use App\Support\Comunicaciones\ComCanalRolCatalog;
use App\Support\Comunicaciones\NuevoComunicadoDocenteDestino;
use App\Support\ComunicacionesRutasGestion;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

class NuevoComunicado extends Component
{
    /** `familia` o `tipo:{id}` de profesortipo — vacío hasta elegir destinatario */
    public string $destinatarioTipo = '';

    /**
     * Opciones del selector (canales con «Iniciar conversación» para el rol del usuario).
     *
     * @var list<array{value:string,label:string,es_familia:bool,id_tipo_prof:?int}>
     */
    public array $opcionesDestinatarios = [];

    /** alumnos: uno o varios · cursos: uno o varios · colegio · grupos */
    public string $tipoDestino = 'alumnos';

    public string $asunto    = '';
    public string $contenido = '';

    /** Si la familia podrá responder en el cuaderno (solo a envíos desde la escuela). */
    public bool $familiaPuedeResponder = true;

    /** Si los docentes destinatarios podrán responder en el hilo (solo scope docentes; columna com_hilos.docentes_permite_respuestas). */
    public bool $docentesDestinatariosPuedenResponder = true;

    public array $alumnosSeleccionados = []; // [{id, label}]

    public array $cursosSeleccionados = []; // [{id, label}]

    public array $docentesSeleccionados = []; // [{id, label}]

    public array $gruposSeleccionados = []; // [{id, label, miembros}]

    /** Nivel del docente prefijado (p. ej. desde reserva de material didáctico cross-nivel). */
    public ?int $idNivelDestinatarioDocente = null;

    // —— Modal alumnos ——
    public bool $modalAlumnosAbierto = false;

    public string $modalAlumnosFiltro = '';

    /** @var list<array{id:int,label:string,dni:?string}> */
    public array $modalAlumnosLista = [];

    /** @var list<int|string> */
    public array $modalAlumnosMarcados = [];

    // —— Modal cursos ——
    public bool $modalCursosAbierto = false;

    public string $modalCursosFiltro = '';

    /** @var list<array{id:int,label:string}> */
    public array $modalCursosLista = [];

    /** @var list<int|string> */
    public array $modalCursosMarcados = [];

    // —— Modal docentes ——
    public bool $modalDocentesAbierto = false;

    public string $modalDocentesFiltro = '';

    /** @var list<array{id:int,label:string,dni:?string}> */
    public array $modalDocentesLista = [];

    /** @var list<int|string> */
    public array $modalDocentesMarcados = [];

    // —— Modal grupos ——
    public bool $modalGruposAbierto = false;

    public string $modalGruposFiltro = '';

    /** @var list<array{id:int,label:string,miembros:int}> */
    public array $modalGruposLista = [];

    /** @var list<int|string> */
    public array $modalGruposMarcados = [];

    public function mount(): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoNuevoComunicado(), 403, 'Sin permiso para iniciar comunicados.');

        $ctx = schoolCtx();
        $profesor = $ctx->profesor();
        if ($profesor !== null) {
            $this->opcionesDestinatarios = CanalesPolicy::opcionesDestinatarioNuevoComunicado(
                CanalesPolicy::claveRolDeProfesor($profesor),
                (int) $ctx->idNivel
            );
        }
        array_unshift($this->opcionesDestinatarios, [
            'value'        => 'grupos',
            'label'        => 'Mis grupos',
            'es_familia'   => false,
            'id_tipo_prof' => null,
        ]);

        $idDestinatario = (int) request()->query('destinatario', 0);
        if ($idDestinatario > 0) {
            $prefill = NuevoComunicadoDocenteDestino::datosDestinatarioProfesor($idDestinatario);
            if ($prefill !== null) {
                $this->destinatarioTipo              = $prefill['destinatario_tipo'];
                $this->docentesSeleccionados         = [$prefill['docente']];
                $this->idNivelDestinatarioDocente    = (int) ($prefill['id_nivel_destinatario'] ?? 0) ?: null;
                $this->asegurarOpcionDestinatarioPrefill();
            }
        }
    }

    private function asegurarOpcionDestinatarioPrefill(): void
    {
        if ($this->destinatarioTipo === '') {
            return;
        }

        foreach ($this->opcionesDestinatarios as $op) {
            if (($op['value'] ?? '') === $this->destinatarioTipo) {
                return;
            }
        }

        $catalogo = ComCanalRolCatalog::catalogo();
        if (! isset($catalogo[$this->destinatarioTipo])) {
            return;
        }

        $parsed = ComCanalRolCatalog::parseClave($this->destinatarioTipo);
        $this->opcionesDestinatarios[] = [
            'value'        => $this->destinatarioTipo,
            'label'        => $catalogo[$this->destinatarioTipo],
            'es_familia'   => $parsed['familia'],
            'id_tipo_prof' => $parsed['id_tipo_prof'],
        ];

        usort(
            $this->opcionesDestinatarios,
            static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label'])
        );
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

    public function updatedModalDocentesFiltro(): void
    {
        if ($this->modalDocentesAbierto) {
            $this->recargarModalDocentesLista();
        }
    }

    public function updatedModalGruposFiltro(): void
    {
        if ($this->modalGruposAbierto) {
            $this->recargarModalGruposLista();
        }
    }

    public function abrirModalAlumnos(): void
    {
        $this->modalAlumnosAbierto   = true;
        $this->modalAlumnosFiltro    = '';
        $this->modalAlumnosMarcados  = array_map(fn ($a) => (int) $a['id'], $this->alumnosSeleccionados);
        $this->recargarModalAlumnosLista();
    }

    public function cerrarModalAlumnos(): void
    {
        $this->modalAlumnosAbierto = false;
    }

    public function aplicarModalAlumnos(): void
    {
        $labelsPorId = collect($this->modalAlumnosLista)->keyBy('id');
        $prev        = collect($this->alumnosSeleccionados)->keyBy('id');
        $out         = [];
        foreach (array_unique(array_map('intval', $this->modalAlumnosMarcados)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $fromLista = $labelsPorId->get($id);
            if ($fromLista !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromLista['label']];

                continue;
            }
            $fromPrev = $prev->get($id);
            if ($fromPrev !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromPrev['label']];
            }
        }
        $this->alumnosSeleccionados = $out;
        $this->modalAlumnosAbierto  = false;
    }

    public function modalAlumnosSeleccionarTodosVisibles(): void
    {
        $ids = array_map(fn ($r) => (int) $r['id'], $this->modalAlumnosLista);
        $this->modalAlumnosMarcados = array_values(array_unique(array_merge(
            array_map('intval', $this->modalAlumnosMarcados),
            $ids
        )));
    }

    public function modalAlumnosQuitarVisibles(): void
    {
        $vis = array_flip(array_map(fn ($r) => (int) $r['id'], $this->modalAlumnosLista));
        $this->modalAlumnosMarcados = array_values(array_filter(
            array_map('intval', $this->modalAlumnosMarcados),
            fn (int $id) => ! isset($vis[$id])
        ));
    }

    public function abrirModalCursos(): void
    {
        $this->modalCursosAbierto  = true;
        $this->modalCursosFiltro   = '';
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
        $prev        = collect($this->cursosSeleccionados)->keyBy('id');
        $out         = [];
        foreach (array_unique(array_map('intval', $this->modalCursosMarcados)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $fromLista = $labelsPorId->get($id);
            if ($fromLista !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromLista['label']];

                continue;
            }
            $fromPrev = $prev->get($id);
            if ($fromPrev !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromPrev['label']];
            }
        }
        $this->cursosSeleccionados = $out;
        $this->modalCursosAbierto = false;
    }

    public function modalCursosSeleccionarTodosVisibles(): void
    {
        $ids = array_map(fn ($r) => (int) $r['id'], $this->modalCursosLista);
        $this->modalCursosMarcados = array_values(array_unique(array_merge(
            array_map('intval', $this->modalCursosMarcados),
            $ids
        )));
    }

    public function modalCursosQuitarVisibles(): void
    {
        $vis = array_flip(array_map(fn ($r) => (int) $r['id'], $this->modalCursosLista));
        $this->modalCursosMarcados = array_values(array_filter(
            array_map('intval', $this->modalCursosMarcados),
            fn (int $id) => ! isset($vis[$id])
        ));
    }

    public function abrirModalDocentes(): void
    {
        $this->modalDocentesAbierto  = true;
        $this->modalDocentesFiltro   = '';
        $this->modalDocentesMarcados = array_map(fn ($d) => (int) $d['id'], $this->docentesSeleccionados);
        $this->recargarModalDocentesLista();
    }

    public function cerrarModalDocentes(): void
    {
        $this->modalDocentesAbierto = false;
    }

    public function aplicarModalDocentes(): void
    {
        $labelsPorId = collect($this->modalDocentesLista)->keyBy('id');
        $prev        = collect($this->docentesSeleccionados)->keyBy('id');
        $out         = [];
        foreach (array_unique(array_map('intval', $this->modalDocentesMarcados)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $fromLista = $labelsPorId->get($id);
            if ($fromLista !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromLista['label']];

                continue;
            }
            $fromPrev = $prev->get($id);
            if ($fromPrev !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromPrev['label']];
            }
        }
        $this->docentesSeleccionados = $out;
        $this->modalDocentesAbierto  = false;
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

    public function abrirModalGrupos(): void
    {
        $this->modalGruposAbierto  = true;
        $this->modalGruposFiltro   = '';
        $this->modalGruposMarcados = array_map(fn ($g) => (int) $g['id'], $this->gruposSeleccionados);
        $this->recargarModalGruposLista();
    }

    public function cerrarModalGrupos(): void
    {
        $this->modalGruposAbierto = false;
    }

    public function aplicarModalGrupos(): void
    {
        $labelsPorId = collect($this->modalGruposLista)->keyBy('id');
        $prev        = collect($this->gruposSeleccionados)->keyBy('id');
        $out         = [];
        foreach (array_unique(array_map('intval', $this->modalGruposMarcados)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $fromLista = $labelsPorId->get($id);
            if ($fromLista !== null) {
                $out[] = [
                    'id'       => $id,
                    'label'    => (string) $fromLista['label'],
                    'miembros' => (int) ($fromLista['miembros'] ?? 0),
                ];

                continue;
            }
            $fromPrev = $prev->get($id);
            if ($fromPrev !== null) {
                $out[] = [
                    'id'       => $id,
                    'label'    => (string) $fromPrev['label'],
                    'miembros' => (int) ($fromPrev['miembros'] ?? 0),
                ];
            }
        }
        $this->gruposSeleccionados = $out;
        $this->modalGruposAbierto  = false;
    }

    public function modalGruposSeleccionarTodosVisibles(): void
    {
        $ids = array_map(fn ($r) => (int) $r['id'], $this->modalGruposLista);
        $this->modalGruposMarcados = array_values(array_unique(array_merge(
            array_map('intval', $this->modalGruposMarcados),
            $ids
        )));
    }

    public function modalGruposQuitarVisibles(): void
    {
        $vis = array_flip(array_map(fn ($r) => (int) $r['id'], $this->modalGruposLista));
        $this->modalGruposMarcados = array_values(array_filter(
            array_map('intval', $this->modalGruposMarcados),
            fn (int $id) => ! isset($vis[$id])
        ));
    }

    public function removeAlumno(int $id): void
    {
        $this->alumnosSeleccionados = array_values(
            array_filter($this->alumnosSeleccionados, fn ($a) => (int) $a['id'] !== $id)
        );
    }

    public function removeCurso(int $id): void
    {
        $this->cursosSeleccionados = array_values(
            array_filter($this->cursosSeleccionados, fn ($c) => (int) $c['id'] !== $id)
        );
    }

    public function removeDocente(int $id): void
    {
        $this->docentesSeleccionados = array_values(
            array_filter($this->docentesSeleccionados, fn ($d) => (int) $d['id'] !== $id)
        );
    }

    public function removeGrupo(int $id): void
    {
        $this->gruposSeleccionados = array_values(
            array_filter($this->gruposSeleccionados, fn ($g) => (int) $g['id'] !== $id)
        );
    }

    public function updatedDestinatarioTipo(): void
    {
        if ($this->esDestinatarioFamilia()) {
            $this->docentesSeleccionados = [];
            $this->idNivelDestinatarioDocente = null;
            $this->cerrarModalDocentes();
        } elseif ($this->esDestinatarioGrupos()) {
            $this->alumnosSeleccionados = [];
            $this->cursosSeleccionados = [];
            $this->docentesSeleccionados = [];
            $this->idNivelDestinatarioDocente = null;
            $this->tipoDestino = 'alumnos';
            $this->cerrarModalAlumnos();
            $this->cerrarModalCursos();
            $this->cerrarModalDocentes();
        } else {
            $this->alumnosSeleccionados = [];
            $this->cursosSeleccionados  = [];
            $this->tipoDestino          = 'alumnos';
            $this->cerrarModalAlumnos();
            $this->cerrarModalCursos();
            $this->docentesSeleccionados = [];
            $this->idNivelDestinatarioDocente = null;
            $this->cerrarModalDocentes();
        }
        $this->gruposSeleccionados = [];
        $this->cerrarModalGrupos();
    }

    public function updatedTipoDestino(): void
    {
        $this->alumnosSeleccionados = [];
        $this->cursosSeleccionados  = [];
        $this->gruposSeleccionados  = [];
        $this->cerrarModalAlumnos();
        $this->cerrarModalCursos();
        $this->cerrarModalGrupos();
    }

    public function enviar(): void
    {
        abort_unless(ComunicacionesRutasGestion::accesoNuevoComunicado(), 403);

        $key = 'com:nuevo:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, config('comunicaciones.rate_limit_max', 20))) {
            $this->addError('contenido', 'Demasiados envíos. Espere un momento.');

            return;
        }
        RateLimiter::hit($key, config('comunicaciones.rate_limit_decay', 60));

        $ctx      = schoolCtx();
        $profesor = $ctx->profesor();
        $rolEmisor = $profesor !== null ? CanalesPolicy::claveRolDeProfesor($profesor) : '';
        $valoresDest = CanalesPolicy::valoresDestinatarioNuevoComunicado($rolEmisor, (int) $ctx->idNivel);
        $valoresDest[] = 'grupos';
        if ($this->idNivelDestinatarioDocente !== null && $this->idNivelDestinatarioDocente !== (int) $ctx->idNivel) {
            $valoresDest = array_values(array_unique(array_merge(
                $valoresDest,
                CanalesPolicy::valoresDestinatarioNuevoComunicado($rolEmisor, $this->idNivelDestinatarioDocente)
            )));
        }

        $rules = [
            'destinatarioTipo' => ['required', 'string', Rule::in($valoresDest)],
            'asunto'           => 'required|string|max:' . config('comunicaciones.max_asunto', 200),
            'contenido'        => 'required|string|max:' . config('comunicaciones.max_contenido', 2000),
        ];
        if ($this->esDestinatarioFamilia()) {
            $rules['tipoDestino']           = 'required|in:alumnos,cursos,colegio';
            $rules['familiaPuedeResponder'] = 'boolean';
        } elseif ($this->esDestinatarioGrupos()) {
            $rules['familiaPuedeResponder'] = 'boolean';
            $rules['docentesDestinatariosPuedenResponder'] = 'boolean';
        } else {
            $rules['docentesDestinatariosPuedenResponder'] = 'boolean';
        }
        $this->validate($rules);

        $idNivel  = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;
        $idProf   = (int) $ctx->idProfesor;

        if ($profesor === null) {
            $this->addError('contenido', 'No se pudo identificar al usuario.');

            return;
        }

        $nombreProfesor = trim("{$profesor->apellido}, {$profesor->nombre}");

        if ($this->esDestinatarioGrupos()) {
            $this->enviarDesdeGrupos(
                $rolEmisor,
                $nombreProfesor,
                (string) ($profesor->dni ?? ''),
                $idNivel,
                $idTerlec,
                $idProf
            );

            return;
        }

        if ($this->esDestinatarioFamilia()) {
            if (! CanalesPolicy::puedeIniciar($rolEmisor, ComCanalRolCatalog::CLAVE_FAMILIA)) {
                $this->addError('contenido', 'Su rol no tiene permiso para iniciar comunicados a familias.');

                return;
            }

            $scopePersistido = null;
            $idCursoGuardar  = null;
            $cursosEnvio     = null;

            $idLegajos = match ($this->tipoDestino) {
                'alumnos' => $this->variasAlumnoIds(),
                'cursos'  => $this->cursosLegajosIds($idNivel, $idTerlec),
                'colegio' => $this->colegioIds($idNivel, $idTerlec),
                default   => [],
            };

            if ($this->tipoDestino === 'alumnos') {
                if (empty($idLegajos)) {
                    $this->addError('tipoDestino', 'Seleccione al menos un alumno.');

                    return;
                }
                $scopePersistido = count($idLegajos) === 1 ? 'alumno' : 'varios_alumnos';
            } elseif ($this->tipoDestino === 'cursos') {
                if (empty($this->cursosSeleccionados)) {
                    $this->addError('tipoDestino', 'Seleccione al menos un curso.');

                    return;
                }
                if (empty($idLegajos)) {
                    $this->addError('tipoDestino', 'No hay alumnos matriculados en los cursos elegidos.');

                    return;
                }
                $idsCursos       = array_map(fn ($c) => (int) $c['id'], $this->cursosSeleccionados);
                $scopePersistido = count($idsCursos) === 1 ? 'curso' : 'varios_cursos';
                $idCursoGuardar  = $idsCursos[0];
                $cursosEnvio     = array_values(array_map(
                    fn (array $c) => ['id' => (int) $c['id'], 'label' => trim((string) ($c['label'] ?? ''))],
                    $this->cursosSeleccionados
                ));
            } else {
                $scopePersistido = 'colegio';
                $idCursoGuardar  = null;
            }

            if (empty($idLegajos)) {
                $this->addError('tipoDestino', 'No hay destinatarios para enviar.');

                return;
            }

            $mediosCanal = CanalesPolicy::mediosPermitidos($rolEmisor, 'familia');

            $hilo = ComunicacionesRepository::crearHiloConMensaje([
                'asunto'                   => $this->asunto,
                'contenido'                => $this->contenido,
                'scope'                    => $scopePersistido,
                'id_legajos'               => $idLegajos,
                'id_curso'                 => $idCursoGuardar,
                'cursos_envio'             => $cursosEnvio,
                'id_nivel'                 => $idNivel,
                'id_terlec'                => $idTerlec,
                'creado_por_tipo'          => 'profesor',
                'creado_por_id'            => $idProf,
                'creado_por_rol'           => $rolEmisor,
                'rol_receptor'             => ComCanalRolCatalog::CLAVE_FAMILIA,
                'vinculo_familiar'         => null,
                'nombre_remitente'         => $nombreProfesor,
                'dni_remitente'            => (string) ($profesor->dni ?? ''),
                'destinatarios_profesores' => [],
                'familia_puede_responder'  => $this->familiaPuedeResponder,
            ], $mediosCanal);
        } else {
            $idTipoProf = $this->idTipoProfDestinatario();
            if ($idTipoProf === null) {
                $this->addError('destinatarioTipo', 'Seleccione un tipo de destinatario válido.');

                return;
            }

            $claveReceptor = $this->destinatarioTipo;
            $idNivelCanal  = $this->idNivelDestinatarioDocente ?? $idNivel;
            if (! CanalesPolicy::puedeIniciar($rolEmisor, $claveReceptor, $idNivelCanal)) {
                $this->addError('destinatarioTipo', 'Su rol no tiene permiso para iniciar comunicados a este tipo de destinatario según los canales del nivel.');

                return;
            }

            $idsPedidos = array_map(fn ($d) => (int) $d['id'], $this->docentesSeleccionados);
            $idsProf    = ComunicacionesRepository::filtrarIdsProfesoresPorIdTipoProf($idsPedidos, $idNivelCanal, $idTipoProf);
            $idsProf    = array_values(array_diff($idsProf, [$idProf]));

            if ($idsProf === []) {
                $this->addError('destinatarioTipo', 'Seleccione al menos un destinatario válido. No puede incluirse a usted mismo.');

                return;
            }

            $mediosCanal = CanalesPolicy::mediosPermitidos($rolEmisor, $claveReceptor, $idNivelCanal);
            if ($mediosCanal === []) {
                $this->addError('contenido', 'No hay medios habilitados para este tipo de envío. Revise la parametrización de canales.');

                return;
            }

            $hilo = ComunicacionesRepository::crearHiloConMensaje([
                'asunto'                   => $this->asunto,
                'contenido'                => $this->contenido,
                'scope'                    => 'docentes',
                'id_legajos'               => [],
                'id_curso'                 => null,
                'cursos_envio'             => null,
                'id_nivel'                 => $idNivel,
                'id_terlec'                => $idTerlec,
                'creado_por_tipo'          => 'profesor',
                'creado_por_id'            => $idProf,
                'creado_por_rol'           => $rolEmisor,
                'rol_receptor'             => $claveReceptor,
                'vinculo_familiar'         => null,
                'nombre_remitente'         => $nombreProfesor,
                'dni_remitente'            => (string) ($profesor->dni ?? ''),
                'destinatarios_profesores' => $idsProf,
                'familia_puede_responder'  => true,
                'docentes_permite_respuestas' => $this->docentesDestinatariosPuedenResponder,
            ], $mediosCanal);
        }

        $idPrimerMensaje = (int) ($hilo->cuerpo_inicial_id ?? 0);
        if ($idPrimerMensaje > 0) {
            $waLinks = ComunicacionesRepository::enlacesWhatsappWaMeDelMensaje($idPrimerMensaje);
            if ($waLinks !== []) {
                session()->flash('whatsapp_wa_links', [
                    'hilo_id' => (int) $hilo->id,
                    'links'   => $waLinks,
                ]);
            }
        }

        session()->flash('success', 'Comunicado registrado. A continuación el detalle de cada envío por medio.');
        $this->redirectRoute(ComunicacionesRutasGestion::nombreRuta('informe-envio'), ['id' => $hilo->id]);
    }

    private function enviarDesdeGrupos(
        string $rolEmisor,
        string $nombreProfesor,
        string $dniProfesor,
        int $idNivel,
        int $idTerlec,
        int $idProf
    ): void {
        if ($this->gruposSeleccionados === []) {
            $this->addError('destinatarioTipo', 'Seleccione al menos un grupo.');

            return;
        }

        $idsGrupos = array_map(fn ($g) => (int) $g['id'], $this->gruposSeleccionados);
        $exp = ComGruposRepository::expandirParaEnvio($idsGrupos, $idProf, $idNivel, $idTerlec);

        $idLegajos = [];
        if ($exp['legajos'] !== [] && CanalesPolicy::puedeIniciar($rolEmisor, ComCanalRolCatalog::CLAVE_FAMILIA, $idNivel)) {
            $idLegajos = $exp['legajos'];
        }

        $idsProf = [];
        $clavesProf = ComGruposRepository::clavesCanalPorIdsProfesores($exp['profesores'], $idNivel);
        foreach ($clavesProf as $idP => $clave) {
            if ((int) $idP === $idProf) {
                continue;
            }
            if (CanalesPolicy::puedeIniciar($rolEmisor, $clave, $idNivel)) {
                $idsProf[] = (int) $idP;
            }
        }
        $idsProf = array_values(array_unique($idsProf));

        if ($idLegajos === [] && $idsProf === []) {
            $this->addError('destinatarioTipo', 'Los grupos no tienen destinatarios vigentes a los que usted pueda enviar según los canales del nivel.');

            return;
        }

        $rolesMedios = [];
        if ($idLegajos !== []) {
            $rolesMedios[] = ComCanalRolCatalog::CLAVE_FAMILIA;
        }
        foreach ($idsProf as $idP) {
            if (isset($clavesProf[$idP])) {
                $rolesMedios[] = $clavesProf[$idP];
            }
        }
        $mediosCanal = [];
        foreach (array_unique($rolesMedios) as $rolRec) {
            $mediosCanal = array_merge($mediosCanal, CanalesPolicy::mediosPermitidos($rolEmisor, $rolRec, $idNivel));
        }
        $mediosCanal = array_values(array_unique($mediosCanal));
        if ($mediosCanal === []) {
            $this->addError('contenido', 'No hay medios habilitados para este tipo de envío. Revise la parametrización de canales.');

            return;
        }

        if ($idLegajos !== []) {
            $scope = count($idLegajos) === 1 && $idsProf === [] ? 'alumno' : 'varios_alumnos';
            $rolReceptor = ComCanalRolCatalog::CLAVE_FAMILIA;
        } else {
            $scope = 'docentes';
            $rolReceptor = $rolesMedios[0] ?? 'profesor';
        }

        $hilo = ComunicacionesRepository::crearHiloConMensaje([
            'asunto'                      => $this->asunto,
            'contenido'                   => $this->contenido,
            'scope'                       => $scope,
            'id_legajos'                  => $idLegajos,
            'id_curso'                    => null,
            'cursos_envio'                => null,
            'id_nivel'                    => $idNivel,
            'id_terlec'                   => $idTerlec,
            'creado_por_tipo'             => 'profesor',
            'creado_por_id'               => $idProf,
            'creado_por_rol'              => $rolEmisor,
            'rol_receptor'                => $rolReceptor,
            'vinculo_familiar'            => null,
            'nombre_remitente'            => $nombreProfesor,
            'dni_remitente'               => $dniProfesor,
            'destinatarios_profesores'    => $idsProf,
            'familia_puede_responder'     => $idLegajos !== [] ? $this->familiaPuedeResponder : true,
            'docentes_permite_respuestas' => $idsProf !== [] ? $this->docentesDestinatariosPuedenResponder : true,
        ], $mediosCanal);

        $idPrimerMensaje = (int) ($hilo->cuerpo_inicial_id ?? 0);
        if ($idPrimerMensaje > 0) {
            $waLinks = ComunicacionesRepository::enlacesWhatsappWaMeDelMensaje($idPrimerMensaje);
            if ($waLinks !== []) {
                session()->flash('whatsapp_wa_links', [
                    'hilo_id' => (int) $hilo->id,
                    'links'   => $waLinks,
                ]);
            }
        }

        session()->flash('success', 'Comunicado registrado. A continuación el detalle de cada envío por medio.');
        $this->redirectRoute(ComunicacionesRutasGestion::nombreRuta('informe-envio'), ['id' => $hilo->id]);
    }

    private function recargarModalAlumnosLista(): void
    {
        $ctx = schoolCtx();
        if (! $ctx->idNivel || ! $ctx->idTerlec) {
            $this->modalAlumnosLista = [];

            return;
        }
        $this->modalAlumnosLista = DestinatariosRepository::alumnosMatriculadosParaSelector(
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec,
            $this->modalAlumnosFiltro,
            2500
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
        $f   = mb_strtolower(trim($this->modalCursosFiltro));
        if ($f !== '') {
            $all = array_values(array_filter(
                $all,
                fn (array $c) => str_contains(mb_strtolower((string) ($c['label'] ?? '')), $f)
            ));
        }
        $this->modalCursosLista = $all;
    }

    private function recargarModalDocentesLista(): void
    {
        $ctx = schoolCtx();
        $idTipoProf = $this->idTipoProfDestinatario();
        if (! $ctx->idNivel || $idTipoProf === null) {
            $this->modalDocentesLista = [];

            return;
        }
        $this->modalDocentesLista = ComunicacionesRepository::profesoresDelNivelParaSelectorPorIdTipoProf(
            (int) $ctx->idNivel,
            $idTipoProf,
            $this->modalDocentesFiltro,
            800
        );
    }

    private function recargarModalGruposLista(): void
    {
        $ctx = schoolCtx();
        if (! $ctx->idNivel || ! $ctx->idProfesor) {
            $this->modalGruposLista = [];

            return;
        }
        $all = ComGruposRepository::paraSelector((int) $ctx->idProfesor, (int) $ctx->idNivel);
        $f = mb_strtolower(trim($this->modalGruposFiltro));
        if ($f !== '') {
            $all = array_values(array_filter(
                $all,
                fn (array $g) => str_contains(mb_strtolower((string) ($g['label'] ?? '')), $f)
            ));
        }
        $this->modalGruposLista = $all;
    }

    public function esDestinatarioFamilia(): bool
    {
        return $this->destinatarioTipo === 'familia';
    }

    public function esDestinatarioGrupos(): bool
    {
        return $this->destinatarioTipo === 'grupos';
    }

    public function idTipoProfDestinatario(): ?int
    {
        if (! str_starts_with($this->destinatarioTipo, 'tipo:')) {
            return null;
        }

        $id = (int) substr($this->destinatarioTipo, 5);

        return $id > 0 ? $id : null;
    }

    public function etiquetaDestinatarioSeleccionado(): string
    {
        foreach ($this->opcionesDestinatarios as $op) {
            if (($op['value'] ?? '') === $this->destinatarioTipo) {
                return (string) ($op['label'] ?? '');
            }
        }

        return 'destinatarios';
    }

    private function variasAlumnoIds(): array
    {
        return array_map(fn ($a) => (int) $a['id'], $this->alumnosSeleccionados);
    }

    /**
     * @return list<int>
     */
    private function cursosLegajosIds(int $idNivel, int $idTerlec): array
    {
        if (empty($this->cursosSeleccionados)) {
            return [];
        }
        $ids = [];
        foreach ($this->cursosSeleccionados as $c) {
            $porCurso = DestinatariosRepository::alumnosPorCurso($idNivel, $idTerlec, (int) $c['id']);
            foreach ($porCurso as $lk) {
                $ids[] = (int) $lk;
            }
        }

        return array_values(array_unique($ids));
    }

    private function colegioIds(int $idNivel, int $idTerlec): array
    {
        return array_map('intval', DestinatariosRepository::alumnosDelColegio($idNivel, $idTerlec));
    }

    public function render()
    {
        $ctx = schoolCtx();
        $cursos = ($ctx->idNivel && $ctx->idTerlec)
            ? DestinatariosRepository::cursosDelContexto((int) $ctx->idNivel, (int) $ctx->idTerlec)
            : [];

        return view('comunicaciones::livewire.comunicaciones.nuevo-comunicado', [
            'cursos'       => $cursos,
            'maxContenido' => config('comunicaciones.max_contenido', 2000),
            'maxAsunto'    => config('comunicaciones.max_asunto', 200),
        ])->layout(ComunicacionesRutasGestion::layout(), ['pageTitle' => 'Nuevo Comunicado']);
    }
}
