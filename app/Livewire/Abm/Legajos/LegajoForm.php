<?php

namespace App\Livewire\Abm\Legajos;

use App\Models\CampoLegajo;
use App\Models\Condicion;
use App\Models\Curso;
use App\Models\Familia;
use App\Models\Legajo;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Sexo;
use App\Models\SolapaLegajo;
use App\Models\Terlec;
use App\Support\Abm\LegajoDependenciasEliminacion;
use App\Support\Alumnos\FotoCarnetLegajo;
use App\Support\Database\PersistenciaColumnas;
use App\Support\PermisosIaCatalog;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class LegajoForm extends Component
{
    use WithFileUploads;

    /** Columnas siempre visibles y persistidas (no pueden desactivarse). */
    private const CORE_COLUMNS = ['apellido', 'nombre', 'dni'];

    /** Slugs canónicos del formulario (plantillas Blade por panel). */
    private const PANEL_SLUGS = ['alumno', 'domicilio', 'madre', 'padre', 'tutor', 'escolar'];

    /** Columnas opcionales de la solapa «Alumno» en el Blade (el trío core va aparte). */
    private const ALUMNO_TAB_COLUMNS = ['cuil', 'fechnaci', 'sexo', 'nacion', 'idFamilias', 'tipoalumno', 'legajo', 'libro', 'folio', 'pwrd', 'fotoCarnet'];

    /** Columnas que pertenecen a cada plantilla de pestaña del formulario. */
    private const TAB_COLUMNS = [
        'domicilio' => ['callenum', 'barrio', 'localidad', 'codpos', 'ln_ciudad', 'ln_depto', 'ln_provincia', 'ln_pais', 'telefono', 'email'],
        'madre' => ['nombremad', 'dnimad', 'fechnacmad', 'nacionmad', 'estacivimad', 'vivemad', 'ocupacmad', 'domimad', 'telemad', 'telecelmad', 'emailmad'],
        'padre' => ['nombrepad', 'dnipad', 'fechnacpad', 'nacionpad', 'estacivipad', 'vivepad', 'ocupacpad', 'domipad', 'telepad', 'telecelpad', 'emailpad'],
        'tutor' => ['nombretut', 'dnitut', 'teletut', 'emailtut', 'respAdmiNom', 'respAdmiDni'],
        'escolar' => ['escori', 'destino', 'parroquia', 'ec_padres', 'vivecon', 'hermanos', 'needes', 'needes_detalle', 'certDisc', 'identif', 'retira', 'emeravis', 'obs'],
    ];

    public ?int $id = null;

    public string $activeTab = 'alumno';

    // ─── Alumno ───────────────────────────────────────────────────────────────
    public string $apellido = '';

    public string $nombre = '';

    public string $dni = '';

    public string $cuil = '';

    public string $fechnaci = '';

    public string $sexo = '';

    public string $nacion = '';

    public int $idFamilias = 1;

    public int|string $tipoalumno = 0;

    public string $legajo = '';

    public string $libro = '';

    public string $folio = '';

    public string $pwrd = '';

    /** Path relativo guardado en `legajos.fotoCarnet` (disco privado). */
    public string $fotoCarnetPath = '';

    /** @var TemporaryUploadedFile|null */
    public $fotoCarnetUpload = null;

    public bool $removeFotoCarnet = false;

    // ─── Domicilio ────────────────────────────────────────────────────────────
    public string $callenum = '';

    public string $barrio = '';

    public string $localidad = '';

    public string $codpos = '';

    public string $ln_ciudad = '';

    public string $ln_depto = '';

    public string $ln_provincia = '';

    public string $ln_pais = '';

    public string $telefono = '';

    public string $email = '';

    // ─── Madre ────────────────────────────────────────────────────────────────
    public string $nombremad = '';

    public string $dnimad = '';

    public string $fechnacmad = '';

    public string $nacionmad = '';

    public string $estacivimad = '';

    public string $domimad = '';

    public string $ocupacmad = '';

    public string $telemad = '';

    public string $telecelmad = '';

    public string $emailmad = '';

    public string $vivemad = '';

    // ─── Padre ────────────────────────────────────────────────────────────────
    public string $nombrepad = '';

    public string $dnipad = '';

    public string $fechnacpad = '';

    public string $nacionpad = '';

    public string $estacivipad = '';

    public string $domipad = '';

    public string $ocupacpad = '';

    public string $telepad = '';

    public string $telecelpad = '';

    public string $emailpad = '';

    public string $vivepad = '';

    // ─── Tutor / Responsable ──────────────────────────────────────────────────
    public string $nombretut = '';

    public string $dnitut = '';

    public string $teletut = '';

    public string $emailtut = '';

    public string $respAdmiNom = '';

    public string $respAdmiDni = '';

    // ─── Escolaridad / Obs ────────────────────────────────────────────────────
    public string $escori = '';

    public string $destino = '';

    public string $obs = '';

    public string $identif = '';

    public string $vivecon = '';

    public string $hermanos = '';

    public string $ec_padres = '';

    public string $parroquia = '';

    public string $needes = '';

    public string $needes_detalle = '';

    public string $certDisc = '';

    public string $emeravis = '';

    public string $retira = '';

    // ─── Matrículas (modales) ────────────────────────────────────────────────
    /** Modal abierto desde el listado (`?matriculas=1`); al cerrar, volver al listado. */
    public bool $matriculasDesdeListado = false;

    public bool $showMatriculasModal = false;

    public bool $showMatriculaForm = false;

    public ?int $matriculaEditId = null;

    public ?int $matriculaDeleteId = null;

    public bool $showMatriculaConfirm = false;

    public string $matriculaDeleteInfo = '';

    public bool $matriculaPuedeEliminar = true;

    public bool $showMatriculaPlanConfirm = false;

    public string $matriculaPlanConfirmInfo = '';

    /** Curso al abrir edición; base para comparar plan y revertir cancelación. */
    public int $m_idCursosAlEditar = 0;

    /** Curso elegido que dispara confirmación por cambio de plan. */
    public int $m_idCursosPendiente = 0;

    /** Curso destino ya aceptado en el modal de cambio de plan. */
    public ?int $matriculaPlanConfirmadoParaCurso = null;

    // Matrícula form fields
    public int|string $m_idCursos = '';

    public int|string $m_idCondiciones = '';

    public int|string $m_idTerlec = '';

    public int|string $m_idNivel = '';

    public string $m_terlec_ano = '';

    public string $m_nivel_nombre = '';

    public string $m_nroMatricula = '';

    public string $m_fechaMatricula = '';

    public string $m_fechaBaja = '';

    public bool $m_bloqmatr = false;

    public bool $m_bloqadmi = false;

    // ─── Cambio de curso (matrícula del año activo) ───────────────────────────
    public bool $showCambioCursoModal = false;

    public ?int $cambioCursoMatriculaId = null;

    public int $cambioCursoOrigenId = 0;

    public string $cambioCursoOrigenLabel = '';

    public int|string $cambioCursoDestinoId = '';

    public bool $showCambioCursoPlanConfirm = false;

    public string $cambioCursoPlanConfirmInfo = '';

    /** Curso destino ya aceptado en el modal de cambio de plan (flujo cambio de curso). */
    public ?int $cambioCursoPlanConfirmadoParaCurso = null;

    /**
     * Columnas de `legajos` con control dedicado en el formulario (no van en extras).
     *
     * @var list<string>
     */
    private const COLUMNAS_FORMULARIO_GESTIONADAS = [
        'apellido', 'nombre', 'dni', 'cuil', 'fechnaci', 'sexo', 'nacion', 'idFamilias', 'tipoalumno', 'legajo', 'libro', 'folio',
        'callenum', 'barrio', 'localidad', 'codpos', 'ln_ciudad', 'ln_depto', 'ln_provincia', 'ln_pais', 'telefono', 'email',
        'nombremad', 'dnimad', 'fechnacmad', 'nacionmad', 'estacivimad', 'domimad', 'ocupacmad', 'telemad', 'telecelmad', 'emailmad', 'vivemad',
        'nombrepad', 'dnipad', 'fechnacpad', 'nacionpad', 'estacivipad', 'domipad', 'ocupacpad', 'telepad', 'telecelpad', 'emailpad', 'vivepad',
        'nombretut', 'dnitut', 'teletut', 'emailtut', 'respAdmiNom', 'respAdmiDni',
        'escori', 'destino', 'obs', 'identif', 'vivecon', 'hermanos', 'ec_padres', 'parroquia',
        'needes', 'needes_detalle', 'certDisc', 'emeravis', 'retira', 'pwrd', 'fotoCarnet',
    ];

    /** No cargar ni persistir vía extras (sistema / seguridad). */
    private const COLUMNAS_SISTEMA_NO_EXTRAS = [
        'id', 'pwrd', 'fechhora', 'fechActDatos', 'bloqmatr', 'bloqadmi', 'fotoCarnet',
    ];

    /** Columnas extra de `legajos` (p. ej. telealte1_nom) sin control dedicado en el Blade. */
    public array $legajoExtras = [];

    public function mount(): void
    {
        $id = \App\Support\Navegacion\ContextoEstudianteSesion::legajo(
            \App\Support\Navegacion\ContextoEstudianteSesion::LEGAJO_ABM,
        );

        if (! $id && ! puedeModificarLegajosEstudiantes()) {
            abort(403, 'Sin permiso para crear legajos de estudiantes.');
        }

        $this->id = $id;
        if ($id) {
            $this->loadLegajo($id);

            if (session()->pull('legajo_abrir_matriculas', false)) {
                $this->matriculasDesdeListado = true;
                $this->openMatriculas();
            }
        }
    }

    private function requireModificarLegajo(): void
    {
        abort_unless(puedeModificarLegajosEstudiantes(), 403, 'Sin permiso para modificar legajos de estudiantes.');
    }

    protected function rules(): array
    {
        $dniUnique = 'unique:legajos,dni'.($this->id ? ",{$this->id}" : '');
        $set = $this->camposActivosSet();

        $r = [
            'apellido' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:50'],
            'dni' => ['required', 'digits_between:7,11', $dniUnique],
        ];

        if ($set === null || isset($set['idFamilias'])) {
            $r['idFamilias'] = ['nullable', 'integer', 'min:1'];
        }
        if ($set === null || isset($set['fechnaci'])) {
            $r['fechnaci'] = ['nullable', 'date'];
        }
        if ($set === null || isset($set['cuil'])) {
            $r['cuil'] = ['nullable', 'string', 'max:13'];
        }
        if ($set === null || isset($set['email'])) {
            $r['email'] = ['nullable', 'string', 'max:100'];
        }
        if ($set === null || isset($set['emailmad'])) {
            $r['emailmad'] = ['nullable', 'string', 'max:50'];
        }
        if ($set === null || isset($set['emailpad'])) {
            $r['emailpad'] = ['nullable', 'string', 'max:50'];
        }
        if ($set === null || isset($set['emailtut'])) {
            $r['emailtut'] = ['nullable', 'string', 'max:50'];
        }
        if ($set === null || isset($set['pwrd'])) {
            $r['pwrd'] = ['nullable', 'string', 'max:50'];
        }

        $r['legajoExtras'] = ['array'];
        $r['legajoExtras.*'] = ['nullable', 'string', 'max:4000'];

        return $r;
    }

    protected function messages(): array
    {
        return [
            'apellido.required' => 'El apellido es obligatorio.',
            'nombre.required' => 'El nombre es obligatorio.',
            'dni.required' => 'El DNI es obligatorio.',
            'dni.digits_between' => 'El DNI debe tener entre 7 y 11 dígitos.',
            'dni.unique' => 'Ya existe un legajo con ese DNI.',
            'fechnaci.date' => 'Fecha de nacimiento inválida.',
        ];
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function updatedFotoCarnetUpload(): void
    {
        $this->resetValidation('fotoCarnetUpload');

        if ($this->fotoCarnetUpload === null) {
            return;
        }

        if (! $this->fotoCarnetUpload instanceof TemporaryUploadedFile) {
            return;
        }

        $this->removeFotoCarnet = false;

        $error = FotoCarnetLegajo::validarUpload($this->fotoCarnetUpload);
        if ($error !== null) {
            $this->addError('fotoCarnetUpload', $error);
            $this->fotoCarnetUpload = null;
        }
    }

    public function onFotoCarnetUploadFailed(): void
    {
        $this->addError(
            'fotoCarnetUpload',
            'No se pudo subir la foto. Compruebe tamaño (máx. 8 MB al subir), formato JPG/PNG y que la sesión siga activa.'
        );
    }

    /** Marca la foto para borrarla al guardar (o descarta una selección pendiente). */
    public function marcarQuitarFotoCarnet(): void
    {
        $this->requireModificarLegajo();
        $this->fotoCarnetUpload = null;
        $this->resetValidation('fotoCarnetUpload');

        if (trim($this->fotoCarnetPath) !== '') {
            $this->removeFotoCarnet = true;
        } else {
            $this->removeFotoCarnet = false;
        }

        $this->dispatch('legajo-foto-carnet-cleared');
    }

    public function deshacerQuitarFotoCarnet(): void
    {
        $this->requireModificarLegajo();
        $this->removeFotoCarnet = false;
    }

    public function save(): mixed
    {
        $this->requireModificarLegajo();

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->focusTabForValidationErrors(array_keys($e->errors()));
            throw $e;
        }

        $set = $this->camposActivosSet();
        $fotoActiva = $set === null || isset($set['fotoCarnet']);

        if ($fotoActiva && $this->fotoCarnetUpload !== null && ! ($this->fotoCarnetUpload instanceof TemporaryUploadedFile)) {
            $this->addError(
                'fotoCarnetUpload',
                'La subida de la foto no finalizó. Espere a que desaparezca «Subiendo archivo…» y vuelva a pulsar Guardar.'
            );
            $this->focusTabForValidationErrors(['fotoCarnetUpload']);
            $this->dispatch('se-swal-aviso', mensaje: 'La foto todavía se está subiendo. Espere y vuelva a guardar.');

            return null;
        }

        if ($fotoActiva && $this->fotoCarnetUpload instanceof TemporaryUploadedFile) {
            $errorFoto = FotoCarnetLegajo::validarUpload($this->fotoCarnetUpload);
            if ($errorFoto !== null) {
                $this->addError('fotoCarnetUpload', $errorFoto);
                $this->focusTabForValidationErrors(['fotoCarnetUpload']);

                return null;
            }

            if (! FotoCarnetLegajo::columnaDisponible()) {
                $this->addError(
                    'fotoCarnetUpload',
                    'La columna legajos.fotoCarnet no existe en esta base. Ejecute la migración o el SQL idempotente antes de subir fotos.'
                );
                $this->focusTabForValidationErrors(['fotoCarnetUpload']);

                return null;
            }
        }

        if ($fotoActiva && $this->removeFotoCarnet && ! FotoCarnetLegajo::columnaDisponible() && trim($this->fotoCarnetPath) !== '') {
            $this->addError(
                'fotoCarnetUpload',
                'La columna legajos.fotoCarnet no existe en esta base. No se puede quitar la foto.'
            );
            $this->focusTabForValidationErrors(['fotoCarnetUpload']);

            return null;
        }

        $allData = $this->formData();

        // Restrict payload to active columns; core trio always included.
        if ($set !== null) {
            $allData = array_filter($allData, fn ($col) => isset($set[$col]), ARRAY_FILTER_USE_KEY);
        }

        $data = $allData;

        if (! tienePermiso(PermisosIaCatalog::LEGAJOS_FAMILIAS_GESTION)) {
            unset($data['idFamilias']);
        }

        unset($data['fotoCarnet']);

        $persistPwrd = $set === null || isset($set['pwrd']);
        $esAlta = $this->id === null;

        if ($this->id) {
            // update() only touches the given keys, preserving hidden-column values in the DB.
            $legajo = Legajo::findOrFail($this->id);
            $legajo->update($data);
            if ($persistPwrd) {
                $nuevaPwrd = trim($this->pwrd);
                if ($nuevaPwrd !== '') {
                    $legajo->pwrd = $nuevaPwrd;
                    $legajo->save();
                }
            }
        } else {
            $data['fechhora'] = now();
            $legajo = Legajo::create($data);
            if ($persistPwrd) {
                $legajo->pwrd = trim($this->pwrd);
                $legajo->save();
            }
            $this->id = (int) $legajo->id;
        }

        if ($fotoActiva && ! $this->persistirFotoCarnet((int) $this->id)) {
            return null;
        }

        $apellidoFlash = (string) ($data['apellido'] ?? $this->apellido);
        $nombreFlash = (string) ($data['nombre'] ?? $this->nombre);
        session()->flash(
            'success',
            $esAlta
                ? "Legajo de {$apellidoFlash}, {$nombreFlash} creado."
                : "Legajo de {$apellidoFlash}, {$nombreFlash} actualizado."
        );

        $focusId = (int) $this->id;
        $page = $this->pageForLegajo($focusId, 25);

        session()->flash('legajo_listado_focus', $focusId);

        return redirect()->to(LegajosIndex::urlIndiceConFiltrosGuardados([
            'page' => $page,
            'focus' => $focusId,
        ]));
    }

    /**
     * Persiste o elimina la foto carnet tras guardar el legajo.
     */
    private function persistirFotoCarnet(int $idLegajo): bool
    {
        $pathAnterior = trim($this->fotoCarnetPath);

        if ($this->removeFotoCarnet && ! ($this->fotoCarnetUpload instanceof TemporaryUploadedFile)) {
            $payload = [FotoCarnetLegajo::COLUMNA => null];
            $preparado = PersistenciaColumnas::prepararPayload('legajos', $payload);
            if ($preparado['columnas_con_valor_sin_columna'] !== []) {
                // null no cuenta como valor con columna faltante en prepararPayload normalmente;
                // si la columna no existe, columnasInexistentes lo detecta vía update fallido.
            }

            if (! FotoCarnetLegajo::columnaDisponible()) {
                $this->addError(
                    'fotoCarnetUpload',
                    PersistenciaColumnas::mensajeColumnasInexistentes('legajos', [FotoCarnetLegajo::COLUMNA])
                );
                $this->focusTabForValidationErrors(['fotoCarnetUpload']);

                return false;
            }

            try {
                Legajo::where('id', $idLegajo)->update([FotoCarnetLegajo::COLUMNA => null]);
            } catch (QueryException $e) {
                $this->addError('fotoCarnetUpload', PersistenciaColumnas::mensajeDesdeQueryException($e));
                $this->focusTabForValidationErrors(['fotoCarnetUpload']);

                return false;
            }

            FotoCarnetLegajo::eliminarArchivo($pathAnterior);
            $this->fotoCarnetPath = '';

            return true;
        }

        if (! ($this->fotoCarnetUpload instanceof TemporaryUploadedFile)) {
            return true;
        }

        $resultado = FotoCarnetLegajo::guardarDesdeUpload(
            $idLegajo,
            $this->dni,
            $this->fotoCarnetUpload,
            $pathAnterior,
        );
        if (! $resultado['ok']) {
            $this->addError('fotoCarnetUpload', $resultado['error']);
            $this->focusTabForValidationErrors(['fotoCarnetUpload']);

            return false;
        }

        $payload = [FotoCarnetLegajo::COLUMNA => $resultado['path']];
        $preparado = PersistenciaColumnas::prepararPayload('legajos', $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            FotoCarnetLegajo::eliminarArchivo($resultado['path']);
            $this->addError(
                'fotoCarnetUpload',
                PersistenciaColumnas::mensajeColumnasInexistentes('legajos', $preparado['columnas_con_valor_sin_columna'])
            );
            $this->focusTabForValidationErrors(['fotoCarnetUpload']);

            return false;
        }

        try {
            Legajo::where('id', $idLegajo)->update($preparado['payload']);
        } catch (QueryException $e) {
            FotoCarnetLegajo::eliminarArchivo($resultado['path']);
            $this->addError('fotoCarnetUpload', PersistenciaColumnas::mensajeDesdeQueryException($e));
            $this->focusTabForValidationErrors(['fotoCarnetUpload']);

            return false;
        }

        $noPersistidas = PersistenciaColumnas::columnasNoPersistidas(
            'legajos',
            ['id' => $idLegajo],
            $preparado['payload']
        );
        if ($noPersistidas !== []) {
            FotoCarnetLegajo::eliminarArchivo($resultado['path']);
            $this->addError(
                'fotoCarnetUpload',
                PersistenciaColumnas::mensajeColumnasNoPersistidas('legajos', $noPersistidas)
            );
            $this->focusTabForValidationErrors(['fotoCarnetUpload']);

            return false;
        }

        $this->fotoCarnetPath = $resultado['path'];
        $this->fotoCarnetUpload = null;
        $this->removeFotoCarnet = false;

        return true;
    }

    public function cancel(): mixed
    {
        $extra = [];
        if ($this->id) {
            $focusId = (int) $this->id;
            session()->flash('legajo_listado_focus', $focusId);
            $extra['focus'] = $focusId;
        }

        return redirect()->to(LegajosIndex::urlIndiceConFiltrosGuardados($extra));
    }

    // ─── Matrículas ───────────────────────────────────────────────────────────
    public function openMatriculas(): void
    {
        if (! $this->id) {
            return;
        }

        $this->showMatriculasModal = true;
        $this->showMatriculaForm = false;
        $this->resetMatriculaForm();
    }

    public function closeMatriculas(): mixed
    {
        $this->showMatriculasModal = false;
        $this->showMatriculaForm = false;
        $this->resetMatriculaForm();
        $this->resetCambioCursoState();

        if ($this->matriculasDesdeListado && $this->id) {
            $focusId = (int) $this->id;
            $page = $this->pageForLegajo($focusId, 25);

            session()->flash('legajo_listado_focus', $focusId);

            return redirect()->to(LegajosIndex::urlIndiceConFiltrosGuardados([
                'page' => $page,
                'focus' => $focusId,
            ]));
        }

        return null;
    }

    public function cancelMatriculaForm(): void
    {
        $this->showMatriculaForm = false;
        $this->resetMatriculaForm();
        $this->resetValidation();
    }

    /** Cerrar modal de matrículas o volver al listado si el formulario está abierto. */
    public function dismissMatriculasModal(): mixed
    {
        if ($this->showMatriculaForm) {
            $this->cancelMatriculaForm();

            return null;
        }

        return $this->closeMatriculas();
    }

    public function openNuevaMatricula(): void
    {
        $this->requireModificarLegajo();

        $this->matriculaEditId = null;
        $this->resetMatriculaForm();

        $this->m_idTerlec = (int) (schoolCtx()->idTerlec ?? 0);
        $this->m_idNivel = '';
        $this->fillMatriculaReadonlyLabels();
        $this->m_fechaMatricula = now()->format('Y-m-d');

        $this->resetValidation();
        $this->showMatriculaForm = true;
    }

    public function openEditMatricula(int $id): void
    {
        $this->requireModificarLegajo();

        $m = Matricula::where('idLegajos', $this->id)->findOrFail($id);
        $this->matriculaEditId = $id;

        $this->m_idCursos = (int) ($m->idCursos ?? 0);
        $this->m_idCursosAlEditar = (int) ($m->idCursos ?? 0);
        $this->resetMatriculaPlanConfirmState();
        $this->m_idCondiciones = (int) ($m->idCondiciones ?? 0);
        $this->m_idTerlec = (int) ($m->idTerlec ?? 0);
        $this->m_idNivel = (int) ($m->idNivel ?? 0);
        $this->fillMatriculaReadonlyLabels();
        $this->m_nroMatricula = (string) ($m->nroMatricula ?? '');
        $this->m_fechaMatricula = $m->fechaMatricula ? $m->fechaMatricula->format('Y-m-d') : '';
        $this->m_fechaBaja = $m->fechaBaja ? $m->fechaBaja->format('Y-m-d') : '';
        $this->m_bloqmatr = (bool) ($m->bloqmatr ?? false);
        $this->m_bloqadmi = (bool) ($m->bloqadmi ?? false);

        $this->resetValidation();
        $this->showMatriculaForm = true;
    }

    public function updated($property, $value = null): void
    {
        if ($property === 'm_idCursos') {
            $idCurso = (int) ($value ?? $this->m_idCursos);
            if (! $this->matriculaEditId) {
                $this->sincronizarNivelMatriculaDesdeCurso($idCurso);
            }
            $this->evaluarCambioCursoMatricula($idCurso);
        }
    }

    private function sincronizarNivelMatriculaDesdeCurso(int $idCurso): void
    {
        if ($idCurso < 1) {
            $this->m_idNivel = '';

            return;
        }

        $idNivel = (int) (Curso::query()->whereKey($idCurso)->value('idNivel') ?? 0);
        $this->m_idNivel = $idNivel > 0 ? $idNivel : '';
        $this->fillMatriculaReadonlyLabels();
    }

    public function evaluarCambioCursoMatriculaDesdeUi(): void
    {
        $this->evaluarCambioCursoMatricula((int) $this->m_idCursos);
    }

    private function evaluarCambioCursoMatricula(int $nuevoId): void
    {
        if (! $this->matriculaEditId) {
            return;
        }

        $originalId = (int) $this->m_idCursosAlEditar;

        if ($nuevoId < 1 || $nuevoId === $originalId) {
            $this->resetMatriculaPlanConfirmState();

            return;
        }

        if (! $this->matriculaCambioDePlanDistinto($originalId, $nuevoId)) {
            $this->resetMatriculaPlanConfirmState();

            return;
        }

        if ((int) $this->matriculaPlanConfirmadoParaCurso === $nuevoId) {
            $this->showMatriculaPlanConfirm = false;

            return;
        }

        $this->m_idCursosPendiente = $nuevoId;
        $this->matriculaPlanConfirmInfo = $this->buildMatriculaPlanConfirmMessage($originalId, $nuevoId);
        $this->matriculaPlanConfirmadoParaCurso = null;
        $this->showMatriculaPlanConfirm = true;
    }

    public function confirmMatriculaPlanChange(): void
    {
        $this->requireModificarLegajo();

        $destino = (int) ($this->m_idCursosPendiente ?: $this->m_idCursos);
        if ($destino < 1) {
            $this->showMatriculaPlanConfirm = false;

            return;
        }

        $this->matriculaPlanConfirmadoParaCurso = $destino;
        $this->showMatriculaPlanConfirm = false;

        if ((int) $this->m_idCursos !== $destino) {
            $this->m_idCursos = $destino;
        }
    }

    public function cancelMatriculaPlanChange(): void
    {
        $this->m_idCursos = (int) $this->m_idCursosAlEditar;
        $this->resetMatriculaPlanConfirmState();
    }

    public function openCambioCurso(int $idMatricula): void
    {
        $this->requireModificarLegajo();

        if (! $this->id) {
            return;
        }

        $matricula = Matricula::where('idLegajos', $this->id)
            ->with('curso')
            ->findOrFail($idMatricula);

        $error = $this->validarMatriculaParaCambioCurso($matricula);
        if ($error !== null) {
            $this->dispatch('se-swal-error', mensaje: $error);

            return;
        }

        $this->cambioCursoMatriculaId = (int) $matricula->id;
        $this->cambioCursoOrigenId = (int) ($matricula->idCursos ?? 0);
        $this->cambioCursoOrigenLabel = trim((string) ($matricula->curso?->cursec ?? '')) ?: '—';
        $this->cambioCursoDestinoId = '';
        $this->resetCambioCursoPlanConfirmState();
        $this->resetValidation();
        $this->showCambioCursoModal = true;
    }

    public function closeCambioCurso(): void
    {
        $this->resetCambioCursoState();
        $this->resetValidation();
    }

    public function confirmCambioCurso(): void
    {
        $this->requireModificarLegajo();

        $destino = (int) $this->cambioCursoDestinoId;
        $this->validate([
            'cambioCursoDestinoId' => ['required', 'integer', 'min:1'],
        ], [
            'cambioCursoDestinoId.required' => 'Debe especificar un curso de destino.',
            'cambioCursoDestinoId.min' => 'Debe especificar un curso de destino.',
        ]);

        $this->ejecutarCambioCurso($destino, false);
    }

    public function confirmCambioCursoPlan(): void
    {
        $this->requireModificarLegajo();

        $destino = (int) ($this->cambioCursoDestinoId ?: 0);
        if ($destino < 1) {
            $this->showCambioCursoPlanConfirm = false;

            return;
        }

        $this->cambioCursoPlanConfirmadoParaCurso = $destino;
        $this->showCambioCursoPlanConfirm = false;
        $this->ejecutarCambioCurso($destino, true);
    }

    public function cancelCambioCursoPlan(): void
    {
        $this->resetCambioCursoPlanConfirmState();
    }

    /**
     * Cambio de curso del año activo: actualiza la matrícula in-place,
     * reasigna cuotasgeneradas y calificaciones. Inasistencias/sanciones
     * quedan vinculadas por idMatricula (mismo efecto que el legacy sin recrear filas).
     */
    private function ejecutarCambioCurso(int $idCursoDestino, bool $planYaConfirmado): void
    {
        if (! $this->id || ! $this->cambioCursoMatriculaId) {
            return;
        }

        $matricula = Matricula::where('idLegajos', $this->id)
            ->findOrFail($this->cambioCursoMatriculaId);

        $error = $this->validarMatriculaParaCambioCurso($matricula);
        if ($error !== null) {
            $this->dispatch('se-swal-error', mensaje: $error);

            return;
        }

        $idCursosOrigen = (int) ($matricula->idCursos ?? 0);
        if ($idCursoDestino < 1) {
            $this->addError('cambioCursoDestinoId', 'Debe especificar un curso de destino.');

            return;
        }

        if ($idCursoDestino === $idCursosOrigen) {
            $this->addError('cambioCursoDestinoId', 'El curso de origen y destino es el mismo.');

            return;
        }

        $cursoDestino = $this->cursoDestinoPermitido($idCursoDestino);
        if ($cursoDestino === null) {
            $this->addError('cambioCursoDestinoId', 'El curso de destino no es válido para el ciclo y nivel actuales.');

            return;
        }

        $nuevoNivel = (int) ($cursoDestino->idNivel ?? 0);
        if ($nuevoNivel < 1) {
            $this->dispatch('se-swal-error', mensaje: 'No se pudo determinar el nivel del curso de destino.');

            return;
        }

        $planDistinto = $this->matriculaCambioDePlanDistinto($idCursosOrigen, $idCursoDestino);
        if ($planDistinto
            && ! $planYaConfirmado
            && (int) $this->cambioCursoPlanConfirmadoParaCurso !== $idCursoDestino
        ) {
            $this->cambioCursoPlanConfirmInfo = $this->buildMatriculaPlanConfirmMessage(
                $idCursosOrigen,
                $idCursoDestino,
            );
            $this->cambioCursoPlanConfirmadoParaCurso = null;
            $this->showCambioCursoPlanConfirm = true;

            return;
        }

        $idLegajos = (int) $this->id;
        $idMatricula = (int) $matricula->id;
        $idTerlec = (int) ($matricula->idTerlec ?? 0);

        try {
            DB::transaction(function () use (
                $matricula,
                $idCursoDestino,
                $nuevoNivel,
                $planDistinto,
                $idLegajos,
                $idMatricula,
                $idTerlec,
            ) {
                $matricula->update([
                    'idCursos' => $idCursoDestino,
                    'idNivel' => $nuevoNivel,
                ]);

                $this->reasignarCuotasGeneradasAlCambioCurso(
                    $idTerlec,
                    $idLegajos,
                    $idMatricula,
                    $idCursoDestino,
                );

                // Inasistencias y sanciones: solo idMatricula → siguen al curso nuevo.

                if ($planDistinto) {
                    if (Schema::hasTable('calificaciones')) {
                        DB::table('calificaciones')
                            ->where('idLegajos', $idLegajos)
                            ->where('idMatricula', $idMatricula)
                            ->delete();
                    }

                    $this->seedCalificacionesForMatricula(
                        $idLegajos,
                        $idMatricula,
                        $nuevoNivel,
                        $idTerlec,
                        $idCursoDestino,
                    );
                } else {
                    $this->relocateCalificacionesMismoPlan(
                        $idLegajos,
                        $idMatricula,
                        $nuevoNivel,
                        $idTerlec,
                        $idCursoDestino,
                    );
                }
            });
        } catch (QueryException $e) {
            report($e);
            $this->dispatch(
                'se-swal-error',
                mensaje: 'No se pudo completar el cambio de curso. Intente nuevamente o consulte con el administrador.',
            );

            return;
        }

        $mensaje = $planDistinto
            ? 'Curso actualizado. Se regeneraron las calificaciones según el nuevo plan; cuotas del año reasignadas.'
            : 'Curso actualizado. Calificaciones y cuotas del año reasignadas al nuevo curso.';

        $this->resetCambioCursoState();
        $this->dispatch('se-swal-exito', mensaje: $mensaje);
    }

    private function validarMatriculaParaCambioCurso(Matricula $matricula): ?string
    {
        $idTerlecActivo = (int) (schoolCtx()->idTerlec ?? 0);
        $idTerlecMatricula = (int) ($matricula->idTerlec ?? 0);

        if ($idTerlecActivo < 1 || $idTerlecMatricula !== $idTerlecActivo) {
            return 'Solo se puede cambiar el curso de la matrícula del año lectivo activo.';
        }

        $idNivelFiltro = SchoolAlcancePedagogico::idNivelFiltroUnico();
        if ($idNivelFiltro !== null && (int) ($matricula->idNivel ?? 0) !== $idNivelFiltro) {
            return 'No puede cambiar de curso a un estudiante que no pertenece a su nivel.';
        }

        return null;
    }

    private function cursoDestinoPermitido(int $idCursoDestino): ?Curso
    {
        $idTerlec = (int) (schoolCtx()->idTerlec ?? 0);
        if ($idCursoDestino < 1 || $idTerlec < 1) {
            return null;
        }

        $query = Curso::query()
            ->whereKey($idCursoDestino)
            ->where('idTerlec', $idTerlec);
        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'idNivel');

        return $query->first(['Id', 'cursec', 'idNivel', 'idCurPlan']);
    }

    private function reasignarCuotasGeneradasAlCambioCurso(
        int $idTerlec,
        int $idLegajos,
        int $idMatricula,
        int $idCursoDestino,
    ): void {
        if (! Schema::hasTable('cuotasgeneradas') || $idTerlec < 1 || $idLegajos < 1) {
            return;
        }

        $payload = ['idCursos' => $idCursoDestino];
        if (Schema::hasColumn('cuotasgeneradas', 'idMatricula')) {
            $payload['idMatricula'] = $idMatricula;
        }

        DB::table('cuotasgeneradas')
            ->where('idTerlec', $idTerlec)
            ->where('idLegajos', $idLegajos)
            ->update($payload);
    }

    private function resetCambioCursoPlanConfirmState(): void
    {
        $this->showCambioCursoPlanConfirm = false;
        $this->cambioCursoPlanConfirmInfo = '';
        $this->cambioCursoPlanConfirmadoParaCurso = null;
    }

    private function resetCambioCursoState(): void
    {
        $this->showCambioCursoModal = false;
        $this->cambioCursoMatriculaId = null;
        $this->cambioCursoOrigenId = 0;
        $this->cambioCursoOrigenLabel = '';
        $this->cambioCursoDestinoId = '';
        $this->resetCambioCursoPlanConfirmState();
    }

    public function saveMatricula(): void
    {
        $this->requireModificarLegajo();

        if ($this->matriculaEditFueraDeAnioActivo()) {
            session()->flash(
                'warning',
                'Las matrículas deben editarse con el sistema en el año de la matrícula a editar.'
            );

            return;
        }

        $this->validate([
            'm_idCursos' => ['required', 'integer', 'min:1'],
            'm_idCondiciones' => ['required', 'integer', 'min:1'],
            'm_idTerlec' => ['required', 'integer', 'min:1'],
            'm_idNivel' => ['required', 'integer', 'min:1'],
            'm_nroMatricula' => ['nullable', 'string', 'max:20'],
            'm_fechaMatricula' => ['nullable', 'date'],
            'm_fechaBaja' => ['nullable', 'date'],
        ], [
            'm_idCursos.required' => 'Seleccione curso y sección.',
            'm_idCondiciones.required' => 'Seleccione condición.',
        ]);

        if (! $this->id) {
            return;
        }

        $idCurso = (int) $this->m_idCursos;
        $idNivelMatricula = (int) (Curso::query()->whereKey($idCurso)->value('idNivel') ?? 0);
        if ($idNivelMatricula < 1) {
            $this->addError('m_idCursos', 'No se pudo determinar el nivel del curso seleccionado.');

            return;
        }

        $data = [
            'idLegajos' => (int) $this->id,
            'idCursos' => $idCurso,
            'idCondiciones' => (int) $this->m_idCondiciones,
            'idTerlec' => (int) $this->m_idTerlec,
            'idNivel' => $idNivelMatricula,
            'nroMatricula' => trim($this->m_nroMatricula) !== '' ? trim($this->m_nroMatricula) : null,
            'fechaMatricula' => $this->m_fechaMatricula ?: null,
            'fechaBaja' => $this->m_fechaBaja ?: null,
            'bloqmatr' => $this->m_bloqmatr ? 1 : 0,
            'bloqadmi' => $this->m_bloqadmi ? 1 : 0,
        ];

        if ($this->matriculaEditId) {
            $existente = Matricula::where('idLegajos', $this->id)
                ->findOrFail($this->matriculaEditId);

            $idCursosAnterior = (int) ($existente->idCursos ?? 0);
            $idCursosNuevo = (int) $data['idCursos'];
            $cursoCambio = $idCursosAnterior !== $idCursosNuevo;
            $planDistinto = $cursoCambio
                && $this->matriculaCambioDePlanDistinto($idCursosAnterior, $idCursosNuevo);

            if ($planDistinto && (int) $this->matriculaPlanConfirmadoParaCurso !== $idCursosNuevo) {
                $this->m_idCursosPendiente = $idCursosNuevo;
                $this->matriculaPlanConfirmInfo = $this->buildMatriculaPlanConfirmMessage(
                    $idCursosAnterior,
                    $idCursosNuevo,
                );
                $this->matriculaPlanConfirmadoParaCurso = null;
                $this->showMatriculaPlanConfirm = true;
                session()->flash(
                    'warning',
                    'Debe confirmar el cambio de plan de estudio antes de guardar la matrícula.'
                );

                return;
            }

            DB::transaction(function () use ($existente, $data, $cursoCambio, $planDistinto, $idCursosAnterior) {
                $existente->update($data);

                if ($cursoCambio && $planDistinto) {
                    DB::table('calificaciones')
                        ->where('idLegajos', (int) $data['idLegajos'])
                        ->where('idMatricula', (int) $existente->id)
                        ->delete();

                    $this->seedCalificacionesForMatricula(
                        (int) $data['idLegajos'],
                        (int) $existente->id,
                        (int) $data['idNivel'],
                        (int) $data['idTerlec'],
                        (int) $data['idCursos'],
                    );
                } elseif ($cursoCambio) {
                    $this->relocateCalificacionesMismoPlan(
                        (int) $data['idLegajos'],
                        (int) $existente->id,
                        (int) $data['idNivel'],
                        (int) $data['idTerlec'],
                        (int) $data['idCursos'],
                    );
                }
            });

            session()->flash(
                'success',
                $planDistinto
                    ? 'Matrícula actualizada. Las calificaciones se regeneraron según el nuevo curso.'
                    : 'Matrícula actualizada.'
            );

            $this->resetMatriculaPlanConfirmState();
        } else {
            DB::transaction(function () use ($data) {
                $matricula = Matricula::create($data);

                $this->seedCalificacionesForMatricula(
                    (int) $data['idLegajos'],
                    (int) $matricula->id,
                    (int) $data['idNivel'],
                    (int) $data['idTerlec'],
                    (int) $data['idCursos'],
                );
            });
            session()->flash('success', 'Matrícula creada.');
        }

        $this->showMatriculaForm = false;
        $this->resetMatriculaForm();
    }

    public function confirmDeleteMatricula(int $id): void
    {
        $this->requireModificarLegajo();

        $m = Matricula::where('idLegajos', $this->id)->with(['terlec', 'curso'])->findOrFail($id);

        $this->matriculaDeleteId = $id;
        $descAno = $m->terlec?->ano ?? '—';
        $descCurso = $m->curso?->cursec ? trim($m->curso->cursec) : '—';

        $dependencias = LegajoDependenciasEliminacion::paraMatricula($id);
        if ($dependencias !== []) {
            $modulos = LegajoDependenciasEliminacion::resumen($dependencias);

            $this->matriculaPuedeEliminar = false;
            $this->matriculaDeleteInfo = "La matrícula que intenta borrar ({$descAno} · {$descCurso}) tiene registros relacionados en: {$modulos}.";
        } else {
            $this->matriculaPuedeEliminar = true;
            $this->matriculaDeleteInfo = "¿Confirma eliminar la matrícula {$descAno} · {$descCurso}?";
        }

        $this->showMatriculaConfirm = true;
    }

    public function deleteMatricula(): void
    {
        $this->requireModificarLegajo();

        if ($this->matriculaDeleteId && $this->id && $this->matriculaPuedeEliminar) {
            $dependencias = LegajoDependenciasEliminacion::paraMatricula((int) $this->matriculaDeleteId);
            if ($dependencias !== []) {
                $modulos = LegajoDependenciasEliminacion::resumen($dependencias);
                $this->matriculaPuedeEliminar = false;
                $this->matriculaDeleteInfo = "La matrícula que intenta borrar tiene registros relacionados en: {$modulos}.";
                $this->showMatriculaConfirm = true;

                return;
            }

            $idMatricula = (int) $this->matriculaDeleteId;
            $idLegajos = (int) $this->id;

            try {
                DB::transaction(function () use ($idMatricula, $idLegajos) {
                    // Garantiza limpieza aunque el tenant no tenga FK ON DELETE CASCADE.
                    if (Schema::hasTable('calificaciones')) {
                        DB::table('calificaciones')
                            ->where('idLegajos', $idLegajos)
                            ->where('idMatricula', $idMatricula)
                            ->delete();
                    }

                    Matricula::where('idLegajos', $idLegajos)->findOrFail($idMatricula)->delete();
                });
            } catch (QueryException $e) {
                report($e);
                $msg = LegajoDependenciasEliminacion::mensajeDesdeQueryException($e, 'la matrícula')
                    ?? 'No se puede eliminar la matrícula porque tiene registros relacionados en otros módulos.';
                $this->matriculaPuedeEliminar = false;
                $this->matriculaDeleteInfo = $msg;
                $this->showMatriculaConfirm = true;
                $this->dispatch('se-swal-error', mensaje: $msg);

                return;
            }

            session()->flash('success', 'Matrícula eliminada.');
        }

        $this->showMatriculaConfirm = false;
        $this->reset('matriculaDeleteId', 'matriculaDeleteInfo', 'matriculaPuedeEliminar');
        $this->matriculaPuedeEliminar = true;
    }

    /**
     * Inserta filas en calificaciones según las materias definidas para nivel / ciclo / curso.
     */
    private function seedCalificacionesForMatricula(
        int $idLegajos,
        int $idMatricula,
        int $idNivel,
        int $idTerlec,
        int $idCursos,
    ): void {
        $materias = DB::table('materias')
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('idCursos', $idCursos)
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'ord', 'idMatPlan']);

        if ($materias->isEmpty()) {
            return;
        }

        $rows = $materias->map(function ($m) use ($idLegajos, $idMatricula, $idTerlec, $idCursos) {
            return [
                'idLegajos' => $idLegajos,
                'idMatricula' => $idMatricula,
                'ord' => (int) ($m->ord ?? 0),
                'idTerlec' => $idTerlec,
                'idCursos' => $idCursos,
                'idMaterias' => (int) $m->id,
                'idMatPlan' => (int) ($m->idMatPlan ?? 0),
            ];
        })->values()->all();

        DB::table('calificaciones')->insert($rows);
    }

    /**
     * Cambio de sección/curso con el mismo plan: conserva notas y actualiza vínculos.
     */
    private function relocateCalificacionesMismoPlan(
        int $idLegajos,
        int $idMatricula,
        int $idNivel,
        int $idTerlec,
        int $idCursosNuevo,
    ): void {
        $mapaMaterias = DB::table('materias')
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('idCursos', $idCursosNuevo)
            ->pluck('id', 'idMatPlan')
            ->mapWithKeys(fn ($id, $idMatPlan) => [(int) $idMatPlan => (int) $id]);

        $calificaciones = DB::table('calificaciones')
            ->where('idLegajos', $idLegajos)
            ->where('idMatricula', $idMatricula)
            ->get(['id', 'idMatPlan']);

        foreach ($calificaciones as $calificacion) {
            $update = ['idCursos' => $idCursosNuevo];
            $idMatPlan = (int) ($calificacion->idMatPlan ?? 0);
            if ($idMatPlan > 0 && $mapaMaterias->has($idMatPlan)) {
                $update['idMaterias'] = $mapaMaterias[$idMatPlan];
            }

            DB::table('calificaciones')
                ->where('id', (int) $calificacion->id)
                ->update($update);
        }
    }

    private function matriculaCambioDePlanDistinto(int $idCursosAnterior, int $idCursosNuevo): bool
    {
        if ($idCursosAnterior === $idCursosNuevo) {
            return false;
        }

        $cursos = Curso::query()
            ->whereIn('Id', [$idCursosAnterior, $idCursosNuevo])
            ->get(['Id', 'idCurPlan']);

        $planAnterior = (int) optional($cursos->firstWhere('Id', $idCursosAnterior))->idCurPlan;
        $planNuevo = (int) optional($cursos->firstWhere('Id', $idCursosNuevo))->idCurPlan;

        return $planAnterior !== $planNuevo;
    }

    private function buildMatriculaPlanConfirmMessage(int $idCursosAnterior, int $idCursosNuevo): string
    {
        $cursos = Curso::query()
            ->whereIn('Id', [$idCursosAnterior, $idCursosNuevo])
            ->get(['Id', 'cursec']);

        $de = trim((string) optional($cursos->firstWhere('Id', $idCursosAnterior))->cursec ?: 'curso actual');
        $a = trim((string) optional($cursos->firstWhere('Id', $idCursosNuevo))->cursec ?: 'nuevo curso');

        return "El curso «{$de}» y «{$a}» tienen planes de estudio distintos. "
            .'Al continuar se eliminarán las calificaciones cargadas para esta matrícula '
            .'y se crearán filas nuevas según las materias del nuevo curso. '
            .'¿Desea continuar?';
    }

    private function resetMatriculaPlanConfirmState(): void
    {
        $this->showMatriculaPlanConfirm = false;
        $this->matriculaPlanConfirmInfo = '';
        $this->m_idCursosPendiente = 0;
        $this->matriculaPlanConfirmadoParaCurso = null;
    }

    private function resetMatriculaForm(): void
    {
        $this->reset([
            'matriculaEditId',
            'm_idCursos', 'm_idCondiciones', 'm_idTerlec', 'm_idNivel',
            'm_terlec_ano', 'm_nivel_nombre',
            'm_nroMatricula', 'm_fechaMatricula', 'm_fechaBaja',
            'm_bloqmatr', 'm_bloqadmi',
            'm_idCursosAlEditar',
        ]);
        $this->resetMatriculaPlanConfirmState();
    }

    /** La matrícula en edición pertenece a un ciclo lectivo distinto al activo en sesión. */
    private function matriculaEditFueraDeAnioActivo(): bool
    {
        if (! $this->matriculaEditId) {
            return false;
        }

        $idTerlecMatricula = (int) $this->m_idTerlec;
        $idTerlecActivo = (int) (schoolCtx()->idTerlec ?? 0);

        return $idTerlecMatricula > 0
            && $idTerlecActivo > 0
            && $idTerlecMatricula !== $idTerlecActivo;
    }

    private function matriculaCursoEtiquetaLectura(): string
    {
        $idCurso = (int) $this->m_idCursos;
        if ($idCurso < 1) {
            return '—';
        }

        $cursec = Curso::query()->whereKey($idCurso)->value('cursec');

        return $cursec ? trim((string) $cursec) : '—';
    }

    private function fillMatriculaReadonlyLabels(): void
    {
        $terlec = null;
        $nivel = null;

        $idTerlec = (int) ($this->m_idTerlec ?: 0);
        $idNivel = (int) ($this->m_idNivel ?: 0);

        if ($idTerlec > 0) {
            $terlec = Terlec::query()->find($idTerlec);
        }
        if ($idNivel > 0) {
            $nivel = Nivel::query()->find($idNivel);
        }

        $this->m_terlec_ano = $terlec?->ano !== null ? (string) $terlec->ano : '';
        $this->m_nivel_nombre = $nivel?->nivel ? (string) $nivel->nivel : '';
    }

    private function loadLegajo(int $id): void
    {
        $l = Legajo::findOrFail($id);

        $this->apellido = $l->apellido ?? '';
        $this->nombre = $l->nombre ?? '';
        $this->dni = (string) ($l->dni ?? '');
        $this->cuil = $l->cuil ?? '';
        $this->fechnaci = $l->fechnaci ? $l->fechnaci->format('Y-m-d') : '';
        $this->sexo = $l->sexo ?? '';
        $this->nacion = $l->nacion ?? '';
        $this->idFamilias = $l->idFamilias > 0 ? (int) $l->idFamilias : 1;
        $this->tipoalumno = $l->tipoalumno ?? 0;
        $this->legajo = $l->legajo ?? '';
        $this->libro = $l->libro ?? '';
        $this->folio = $l->folio ?? '';
        $this->pwrd = (string) ($l->pwrd ?? '');
        $this->fotoCarnetPath = Schema::hasColumn('legajos', FotoCarnetLegajo::COLUMNA)
            ? trim((string) ($l->fotoCarnet ?? ''))
            : '';
        $this->fotoCarnetUpload = null;
        $this->removeFotoCarnet = false;

        $this->callenum = $l->callenum ?? '';
        $this->barrio = $l->barrio ?? '';
        $this->localidad = $l->localidad ?? '';
        $this->codpos = $l->codpos ?? '';
        $this->ln_ciudad = $l->ln_ciudad ?? '';
        $this->ln_depto = $l->ln_depto ?? '';
        $this->ln_provincia = $l->ln_provincia ?? '';
        $this->ln_pais = $l->ln_pais ?? '';
        $this->telefono = $l->telefono ?? '';
        $this->email = $l->email ?? '';

        $this->nombremad = $l->nombremad ?? '';
        $this->dnimad = $l->dnimad ?? '';
        $this->fechnacmad = $l->fechnacmad ? $l->fechnacmad->format('Y-m-d') : '';
        $this->nacionmad = $l->nacionmad ?? '';
        $this->estacivimad = $l->estacivimad ?? '';
        $this->domimad = $l->domimad ?? '';
        $this->ocupacmad = $l->ocupacmad ?? '';
        $this->telemad = $l->telemad ?? '';
        $this->telecelmad = $l->telecelmad ?? '';
        $this->emailmad = $l->emailmad ?? '';
        $this->vivemad = $l->vivemad ?? '';

        $this->nombrepad = $l->nombrepad ?? '';
        $this->dnipad = $l->dnipad ?? '';
        $this->fechnacpad = $l->fechnacpad ? $l->fechnacpad->format('Y-m-d') : '';
        $this->nacionpad = $l->nacionpad ?? '';
        $this->estacivipad = $l->estacivipad ?? '';
        $this->domipad = $l->domipad ?? '';
        $this->ocupacpad = $l->ocupacpad ?? '';
        $this->telepad = $l->telepad ?? '';
        $this->telecelpad = $l->telecelpad ?? '';
        $this->emailpad = $l->emailpad ?? '';
        $this->vivepad = $l->vivepad ?? '';

        $this->nombretut = $l->nombretut ?? '';
        $this->dnitut = (string) ($l->dnitut ?? '');
        $this->teletut = $l->teletut ?? '';
        $this->emailtut = $l->emailtut ?? '';
        $this->respAdmiNom = $l->respAdmiNom ?? '';
        $this->respAdmiDni = (string) ($l->respAdmiDni ?? '');

        $this->escori = $l->escori ?? '';
        $this->destino = $l->destino ?? '';
        $this->obs = $l->obs ?? '';
        $this->identif = $l->identif ?? '';
        $this->vivecon = $l->vivecon ?? '';
        $this->hermanos = $l->hermanos ?? '';
        $this->ec_padres = $l->ec_padres ?? '';
        $this->parroquia = $l->parroquia ?? '';
        $this->needes = $l->needes ?? '';
        $this->needes_detalle = $l->needes_detalle ?? '';
        $this->certDisc = $l->certDisc ?? '';
        $this->emeravis = $l->emeravis ?? '';
        $this->retira = $l->retira ?? '';

        $this->rellenarLegajoExtrasDesdeModelo($l);
    }

    private function rellenarLegajoExtrasDesdeModelo(Legajo $l): void
    {
        $this->legajoExtras = [];
        $managed = array_flip(self::COLUMNAS_FORMULARIO_GESTIONADAS);
        $skip = array_merge(self::COLUMNAS_SISTEMA_NO_EXTRAS, CampoLegajo::COLUMNAS_EXCLUIDAS);

        foreach ($l->getAttributes() as $key => $val) {
            if (isset($managed[$key]) || in_array($key, $skip, true)) {
                continue;
            }
            if ($val === null) {
                $this->legajoExtras[$key] = '';
            } elseif ($val instanceof \DateTimeInterface) {
                $this->legajoExtras[$key] = $val->format('Y-m-d');
            } elseif (is_bool($val)) {
                $this->legajoExtras[$key] = $val ? '1' : '0';
            } else {
                $this->legajoExtras[$key] = (string) $val;
            }
        }
    }

    private function formData(): array
    {
        $data = [
            'apellido' => strtoupper(trim($this->apellido)),
            'nombre' => ucwords(strtolower(trim($this->nombre))),
            'dni' => $this->dni !== '' ? (int) $this->dni : null,
            'cuil' => $this->cuil,
            'fechnaci' => $this->fechnaci ?: null,
            'sexo' => $this->sexo,
            'nacion' => $this->nacion,
            'idFamilias' => $this->idFamilias > 0 ? $this->idFamilias : 1,
            'tipoalumno' => (int) $this->tipoalumno,
            'legajo' => $this->legajo,
            'libro' => $this->libro,
            'folio' => $this->folio,
            'callenum' => $this->callenum,
            'barrio' => $this->barrio,
            'localidad' => $this->localidad,
            'codpos' => $this->codpos,
            'ln_ciudad' => $this->ln_ciudad,
            'ln_depto' => $this->ln_depto,
            'ln_provincia' => $this->ln_provincia,
            'ln_pais' => $this->ln_pais,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'nombremad' => $this->nombremad,
            'dnimad' => $this->dnimad,
            'fechnacmad' => $this->fechnacmad ?: null,
            'nacionmad' => $this->nacionmad,
            'estacivimad' => $this->estacivimad,
            'domimad' => $this->domimad,
            'ocupacmad' => $this->ocupacmad,
            'telemad' => $this->telemad,
            'telecelmad' => $this->telecelmad,
            'emailmad' => $this->emailmad,
            'vivemad' => $this->vivemad,
            'nombrepad' => $this->nombrepad,
            'dnipad' => $this->dnipad,
            'fechnacpad' => $this->fechnacpad ?: null,
            'nacionpad' => $this->nacionpad,
            'estacivipad' => $this->estacivipad,
            'domipad' => $this->domipad,
            'ocupacpad' => $this->ocupacpad,
            'telepad' => $this->telepad,
            'telecelpad' => $this->telecelpad,
            'emailpad' => $this->emailpad,
            'vivepad' => $this->vivepad,
            'nombretut' => $this->nombretut,
            'dnitut' => $this->dnitut !== '' ? (int) $this->dnitut : null,
            'teletut' => $this->teletut,
            'emailtut' => $this->emailtut,
            'respAdmiNom' => $this->respAdmiNom,
            'respAdmiDni' => $this->respAdmiDni !== '' ? (int) $this->respAdmiDni : 0,
            'escori' => $this->escori,
            'destino' => $this->destino,
            'obs' => $this->obs,
            'identif' => $this->identif,
            'vivecon' => $this->vivecon,
            'hermanos' => $this->hermanos,
            'ec_padres' => $this->ec_padres,
            'parroquia' => $this->parroquia,
            'needes' => $this->needes,
            'needes_detalle' => $this->needes_detalle,
            'certDisc' => $this->certDisc,
            'emeravis' => $this->emeravis,
            'retira' => $this->retira,
        ];

        $managedFlip = array_flip(self::COLUMNAS_FORMULARIO_GESTIONADAS);
        foreach ($this->legajoExtras as $k => $v) {
            if (isset($managedFlip[$k]) || $k === FotoCarnetLegajo::COLUMNA) {
                continue;
            }
            $data[$k] = is_string($v) ? trim($v) : $v;
        }

        return $data;
    }

    /**
     * Slugs de solapa que no coinciden con plantillas fijas pero deben usar la
     * plantilla «escolar» cuando solo hay columnas nuevas (no mapeadas).
     *
     * @var list<string>
     */
    private const SLUGS_MISC_PANEL_ESCOLAR = ['otros', 'misc', 'varios', 'adicional', 'general', 'extras'];

    /**
     * @return array<string, string>
     */
    private static function columnToPanelMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        foreach (self::ALUMNO_TAB_COLUMNS as $col) {
            $map[$col] = 'alumno';
        }
        foreach (self::TAB_COLUMNS as $panel => $cols) {
            foreach ($cols as $col) {
                $map[$col] = $panel;
            }
        }

        return $map;
    }

    /**
     * Decide qué plantilla Blade usar para una solapa según las columnas asignadas.
     * Las columnas sin entrada en el mapa no votan (nunca se infiere «alumno» por error).
     * Si solo hay columnas nuevas, se usa plantilla escolar u otras reglas por slug.
     */
    private static function inferPanelForSolapa(int $solapaId, string $slug): string
    {
        $slugLower = strtolower($slug);
        $canonical = array_flip(self::PANEL_SLUGS);
        if (isset($canonical[$slugLower])) {
            return $slugLower;
        }

        $columns = CampoLegajo::query()
            ->where('solapa_legajo_id', $solapaId)
            ->pluck('columna')
            ->map(fn ($c) => (string) $c)
            ->all();

        if ($columns === []) {
            return 'escolar';
        }

        $map = self::columnToPanelMap();
        $votes = [];
        foreach ($columns as $col) {
            if (! isset($map[$col])) {
                continue;
            }
            $panel = $map[$col];
            // Nunca usar la plantilla «alumno» para una solapa que no sea slug «alumno»
            // (evita legajo/libro/folio en «Otros» con plantilla equivocada).
            if ($slugLower !== 'alumno' && $panel === 'alumno') {
                continue;
            }
            $votes[$panel] = ($votes[$panel] ?? 0) + 1;
        }

        if ($votes !== []) {
            arsort($votes);

            return array_key_first($votes) ?? 'escolar';
        }

        // Solo columnas nuevas (no listadas en plantillas): nunca «alumno» (evita DNI/apellido/nombre duplicados).
        if (in_array($slugLower, self::SLUGS_MISC_PANEL_ESCOLAR, true)) {
            return 'escolar';
        }

        return 'escolar';
    }

    /**
     * @param  array<string,int>|null  $camposActivos
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function resolverTabsVisibles(?array $camposActivos): array
    {
        $canonical = array_flip(self::PANEL_SLUGS);

        if (Schema::hasTable('solapas_legajo')) {
            $solapasConCampos = SolapaLegajo::query()
                ->whereHas('campos')
                ->orderBy('orden')
                ->get(['id', 'nombre', 'slug']);

            if ($solapasConCampos->isNotEmpty()) {
                $tabs = [];
                $tabSlugToPanel = [];

                $alumnoNombre = SolapaLegajo::where('slug', 'alumno')->value('nombre') ?? 'Alumno';
                $tabs['alumno'] = $alumnoNombre;
                $tabSlugToPanel['alumno'] = 'alumno';

                foreach ($solapasConCampos as $s) {
                    if ($s->slug === 'alumno') {
                        $tabs['alumno'] = $s->nombre;

                        continue;
                    }
                    $tabs[$s->slug] = $s->nombre;
                    $tabSlugToPanel[$s->slug] = isset($canonical[$s->slug])
                        ? $s->slug
                        : self::inferPanelForSolapa((int) $s->id, (string) $s->slug);
                }

                return [$tabs, $tabSlugToPanel];
            }
        }

        // ── Fallback: TAB_COLUMNS ────────────────────────────────────────────
        $tabLabels = [
            'domicilio' => 'Domicilio',
            'madre' => 'Madre',
            'padre' => 'Padre',
            'tutor' => 'Tutor',
            'escolar' => 'Escolaridad',
        ];
        $tabs = ['alumno' => 'Alumno'];
        $tabSlugToPanel = ['alumno' => 'alumno'];

        foreach (self::TAB_COLUMNS as $tabId => $cols) {
            if ($camposActivos === null) {
                $tabs[$tabId] = $tabLabels[$tabId];
            } else {
                foreach ($cols as $col) {
                    if (isset($camposActivos[$col])) {
                        $tabs[$tabId] = $tabLabels[$tabId];
                        break;
                    }
                }
            }
        }

        foreach (array_keys($tabs) as $slug) {
            if (! isset($tabSlugToPanel[$slug])) {
                $tabSlugToPanel[$slug] = $slug;
            }
        }

        return [$tabs, $tabSlugToPanel];
    }

    /**
     * Pestañas con parametrización: orden de `solapas_legajo`; «alumno» siempre primero.
     *
     * @param  array<string, list<array{columna: string, etiqueta: ?string}>>  $camposPorSlug
     * @return array<string, string>
     */
    private function resolverTabsParametrizados(array $camposPorSlug): array
    {
        $rest = [];
        foreach (SolapaLegajo::query()->orderBy('orden')->get(['slug', 'nombre']) as $s) {
            if ($s->slug === 'alumno') {
                continue;
            }
            if (! empty($camposPorSlug[$s->slug] ?? [])) {
                $rest[$s->slug] = $s->nombre;
            }
        }

        $alumnoNombre = SolapaLegajo::where('slug', 'alumno')->value('nombre') ?? 'Alumno';

        return array_merge(['alumno' => $alumnoNombre], $rest);
    }

    /**
     * Devuelve null si no hay parametrización activa (mostrar todo como siempre).
     * Si hay columnas con solapa asignada, devuelve array_flip del conjunto unido
     * con el trío obligatorio.
     *
     * @return array<string, int>|null
     */
    private function camposActivosSet(): ?array
    {
        $visibles = CampoLegajo::columnasActivasParaLegajo();
        if ($visibles === null) {
            return null;
        }

        return array_flip(array_unique(array_merge(self::CORE_COLUMNS, $visibles)));
    }

    /**
     * Muestra el error en la solapa donde está el campo (p. ej. email en Domicilio).
     *
     * @param  list<string>  $errorKeys
     */
    private function focusTabForValidationErrors(array $errorKeys): void
    {
        if ($errorKeys === []) {
            return;
        }

        $firstKey = $errorKeys[0];
        $columna = str_starts_with($firstKey, 'legajoExtras.')
            ? substr($firstKey, strlen('legajoExtras.'))
            : $firstKey;

        if ($columna === 'fotoCarnetUpload') {
            $columna = 'fotoCarnet';
        }

        $this->activeTab = $this->slugForColumn($columna);
    }

    private function slugForColumn(string $columna): string
    {
        if (in_array($columna, self::CORE_COLUMNS, true)) {
            return 'alumno';
        }

        if (Schema::hasTable('campos_legajo') && Schema::hasTable('solapas_legajo')) {
            $slug = CampoLegajo::query()
                ->where('campos_legajo.columna', $columna)
                ->whereNotNull('campos_legajo.solapa_legajo_id')
                ->join('solapas_legajo', 'solapas_legajo.id', '=', 'campos_legajo.solapa_legajo_id')
                ->value('solapas_legajo.slug');
            if ($slug) {
                return (string) $slug;
            }
        }

        return self::columnToPanelMap()[$columna] ?? 'alumno';
    }

    private function pageForLegajo(int $id, int $perPage): int
    {
        $l = Legajo::find($id);
        if (! $l) {
            return 1;
        }

        $filtros = LegajosIndex::sessionFiltros();
        $idTerlec = schoolCtx()->idTerlec;

        $query = Legajo::query();

        if ($filtros['search'] !== '') {
            $query->buscar($filtros['search']);
        }

        if ($filtros['soloMatricula']) {
            $query->whereHas('matriculas', fn ($q) => $q->where('idTerlec', $idTerlec));
        }

        if ($filtros['soloMiNivel']) {
            $query->whereHas('matriculas', function ($q) use ($idTerlec) {
                $q->where('idTerlec', $idTerlec);
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($q, 'idNivel');
            });
        }

        $countBefore = $query->where(function ($q) use ($l) {
            $q->where('apellido', '<', $l->apellido)
                ->orWhere(function ($q2) use ($l) {
                    $q2->where('apellido', $l->apellido)
                        ->where('nombre', '<', $l->nombre);
                });
        })->count();

        return (int) floor($countBefore / $perPage) + 1;
    }

    public function render()
    {
        $idTerlec = schoolCtx()->idTerlec;

        $familias = Familia::orderBy('id')->orderBy('apellido')->get(['id', 'apellido', 'responsable']);

        $cursosQuery = Curso::query();
        if ($idTerlec) {
            $cursosQuery->where('idTerlec', $idTerlec);
        }
        \App\Support\SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($cursosQuery, 'idNivel');
        $cursos = $cursosQuery->orderBy('Id')->get(['Id', 'cursec', 'idNivel']);

        $condiciones = Condicion::query()
            ->orderBy('id')
            ->get(['id', 'condicion']);

        $sexosOpciones = Sexo::opcionesParaSelect();

        $matriculasAlumno = collect();
        if ($this->id) {
            $matriculasAlumno = Matricula::where('idLegajos', $this->id)
                ->with(['terlec', 'curso', 'condicion'])
                ->leftJoin('terlec', 'terlec.id', '=', 'matricula.idTerlec')
                ->orderByDesc('terlec.ano')
                ->orderByDesc('matricula.id')
                ->select('matricula.*')
                ->get();
        }

        $idTerlecActivo = (int) ($idTerlec ?? 0);
        $matriculaAnioActivo = $matriculasAlumno->first(
            fn ($m) => (int) ($m->idTerlec ?? 0) === $idTerlecActivo
        );

        // null = sin restricción (mostrar todo, comportamiento original).
        // array = set de columnas activas (array_flip de nombres de columna).
        $camposActivos = $this->camposActivosSet();

        $showField = fn (string $col): bool => $camposActivos === null || isset($camposActivos[$col]);

        $modoParametrizadoLegajo = $camposActivos !== null;
        $columnasPorSolapaSlug = [];

        if ($modoParametrizadoLegajo) {
            $columnasPorSolapaSlug = CampoLegajo::camposPorSolapaSlugOrdenados();
            $tabsVisibles = $this->resolverTabsParametrizados($columnasPorSolapaSlug);
            $tabSlugToPanel = [];
            $showFieldEnTab = static fn (string $col): bool => false;
        } else {
            $columnsPorSlugTab = [];
            if (Schema::hasTable('solapas_legajo') && Schema::hasTable('campos_legajo')) {
                foreach (SolapaLegajo::query()->orderBy('orden')->get(['id', 'slug']) as $solapa) {
                    $columnsPorSlugTab[$solapa->slug] = CampoLegajo::query()
                        ->where('solapa_legajo_id', $solapa->id)
                        ->orderBy('orden_en_solapa')
                        ->orderBy('columna')
                        ->pluck('columna')
                        ->map(fn ($c) => (string) $c)
                        ->values()
                        ->all();
                }
            }

            $activeTabKey = $this->activeTab;
            $showFieldEnTab = function (string $col) use ($showField, $camposActivos, $columnsPorSlugTab, $activeTabKey): bool {
                if (! $showField($col)) {
                    return false;
                }
                if ($camposActivos === null || $columnsPorSlugTab === []) {
                    return true;
                }

                return in_array($col, $columnsPorSlugTab[$activeTabKey] ?? [], true);
            };

            [$tabsVisibles, $tabSlugToPanel] = $this->resolverTabsVisibles($camposActivos);
        }

        if (! isset($tabsVisibles[$this->activeTab])) {
            $this->activeTab = array_key_first($tabsVisibles) ?? 'alumno';
        }

        $puedeEditar = puedeModificarLegajosEstudiantes();
        $puedeGestionarFamilias = tienePermiso(PermisosIaCatalog::LEGAJOS_FAMILIAS_GESTION);
        $pageTitle = $this->id
            ? ($puedeEditar ? 'Editar legajo' : 'Consultar legajo')
            : 'Nuevo legajo';

        $matriculaEditFueraDeAnioActivo = $this->matriculaEditFueraDeAnioActivo();
        $matriculaCursoEtiqueta = $matriculaEditFueraDeAnioActivo
            ? $this->matriculaCursoEtiquetaLectura()
            : null;

        $fotoColumnaOk = FotoCarnetLegajo::columnaDisponible();
        $fotoCarnetActivo = $fotoColumnaOk && $showField('fotoCarnet');
        $fotoCarnetUrl = FotoCarnetLegajo::dataUrlPreview(
            $this->fotoCarnetPath !== '' ? $this->fotoCarnetPath : null
        );
        if ($fotoCarnetUrl === null) {
            $fotoCarnetUrl = FotoCarnetLegajo::urlVer(
                $this->id ? (int) $this->id : null,
                $this->fotoCarnetPath !== '' ? $this->fotoCarnetPath : null
            );
        }
        $mostrarFotoSticky = $fotoCarnetUrl !== null
            || $fotoCarnetActivo
            || ($fotoColumnaOk && $this->fotoCarnetUpload instanceof TemporaryUploadedFile)
            || ($this->removeFotoCarnet && trim($this->fotoCarnetPath) !== '');

        $etiquetaFotoCarnet = 'Foto carnet';
        if ($modoParametrizadoLegajo) {
            foreach ($columnasPorSolapaSlug as $camposSolapa) {
                foreach ($camposSolapa as $campoSolapa) {
                    if (($campoSolapa['columna'] ?? '') === 'fotoCarnet' && ! empty($campoSolapa['etiqueta'])) {
                        $etiquetaFotoCarnet = (string) $campoSolapa['etiqueta'];
                        break 2;
                    }
                }
            }
        }

        return view('livewire.abm.legajos.form', compact(
            'familias', 'cursos', 'condiciones', 'sexosOpciones', 'matriculasAlumno',
            'matriculaAnioActivo',
            'camposActivos', 'showField', 'showFieldEnTab', 'tabsVisibles', 'tabSlugToPanel',
            'modoParametrizadoLegajo', 'columnasPorSolapaSlug', 'puedeEditar', 'puedeGestionarFamilias',
            'matriculaEditFueraDeAnioActivo', 'matriculaCursoEtiqueta',
            'fotoCarnetActivo', 'fotoCarnetUrl', 'mostrarFotoSticky', 'etiquetaFotoCarnet',
        ))->layout(layoutMenuStaff(), ['pageTitle' => $pageTitle]);
    }
}
