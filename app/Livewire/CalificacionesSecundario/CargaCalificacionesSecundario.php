<?php

namespace App\Livewire\CalificacionesSecundario;

use App\Models\Curso;
use App\Support\EntoCargaNotas;
use App\Support\PortalDocente\CalificacionesDocenteSecundario;
use App\Support\PromedioAnualCalificacionesSecundario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

/**
 * Módulo UI (nivel secundario): carga/edición masiva de calificaciones por curso + materia.
 *
 * Flujo:
 * 1) El usuario elige curso y materia (IDs reales de `materias.id`).
 * 2) Se listan filas de `calificaciones` para ese curso/materia/ciclo lectivo.
 * 3) Cada celda editable guarda con `saveCell()` (disparado desde Blade con `wire:blur` / `wire:change`).
 *    Las notas ingresadas en módulos (ic**) y coloquios (Dic/Feb) deben existir en `notaspermitidas`
 *    para el `idNivel` del contexto (si hay al menos una fila configurada para ese nivel).
 *    Tras guardar: se relee solo la fila tocada desde `calificaciones` (sin JOIN).
 *    Las notas no permitidas se filtran en el navegador (lista en la vista) para no disparar el servidor;
 *    el servidor igual valida por seguridad (lectura mínima de la celda si llegara un valor inválido).
 *
 * Seguridad:
 * - Todas las consultas/mutaciones se filtran por `schoolCtx()` (nivel + año lectivo) y por curso/materia elegidos.
 * - Antes de actualizar por `id`, se revalida que el registro pertenezca al alcance actual (anti-ID guessing).
 */
class CargaCalificacionesSecundario extends Component
{
    /** Curso seleccionado (`cursos.Id`) dentro del contexto de sesión. */
    public ?int $cursoId = null;

    /** Materia seleccionada (`materias.id`) dentro del curso/contexto de sesión. */
    public ?int $materiaId = null;

    /**
     * Filas renderizadas en la grilla.
     *
     * Clave: `calificaciones.id` (int). Esto permite updates O(1) y `wire:key` estable por fila.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /**
     * Lista de notas permitidas para `schoolCtx()->idNivel` (cargada con la grilla).
     *
     * Es un array **secuencial de strings** (no mapa por clave): en PHP, claves numéricas como `"10"`
     * se convierten a entero y rompen el JSON hacia el navegador (`10` vs `"10"` en `Set.has`).
     *
     * Debe ser `public` para que Livewire lo conserve entre requests al guardar por celda.
     *
     * @var list<string>
     */
    public array $notasPermitidasLista = [];

    /** Menú de Docentes: sin permiso de carga; alcance por ppc y `ento.cargaNotasOff`. */
    public bool $modoPortalDocente = false;

    public bool $cargaNotasSoloLectura = false;

    public bool $mostrarModalNotasOff = false;

    public string $mensajeNotasOff = '';

    /**
     * Secretaría: sin parámetros. Menú de Docentes: `{curso}` y `{materia}` en la ruta.
     */
    public function mount(?int $curso = null, ?int $materia = null): void
    {
        if ($curso !== null && $materia !== null) {
            $this->inicializarModoPortalDocente($curso, $materia);

            return;
        }

        abort_unless(tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA), 403, 'Sin permiso para cargar calificaciones.');

        // Entrada al módulo: forzar selección explícita de curso/materia.
        $this->cursoId = null;
        $this->materiaId = null;
        $this->rows = [];
        $this->resetNotasPermitidas();
    }

    protected function inicializarModoPortalDocente(int $curso, int $materia): void
    {
        CalificacionesDocenteSecundario::abortSiNoEsSecundario();

        $this->modoPortalDocente = true;
        $this->cursoId = $curso > 0 ? $curso : null;
        $this->materiaId = $materia > 0 ? $materia : null;
        $this->rows = [];
        $this->resetNotasPermitidas();

        CalificacionesDocenteSecundario::abortSiProfesorSinMateria($materia, $curso);

        $params = EntoCargaNotas::paraNivelActual();
        if ($params['bloqueada']) {
            $this->cargaNotasSoloLectura = true;
            $this->mensajeNotasOff = $params['mensaje'];
            $this->mostrarModalNotasOff = true;
        }

        if ($this->cursoId && $this->materiaId) {
            $this->loadGrid();
        }
    }

    public function updatedCursoId($value): void
    {
        // `wire:model.live` puede mandar string vacío: lo normalizamos a null.
        $this->cursoId = ((int) $value) > 0 ? (int) $value : null;

        // Al cambiar de curso, la materia deja de ser válida: reseteamos dependientes.
        $this->materiaId = null;
        $this->rows = [];
        $this->resetNotasPermitidas();
        $this->resetValidation();
    }

    public function updatedMateriaId($value): void
    {
        $this->materiaId = ((int) $value) > 0 ? (int) $value : null;
        $this->rows = [];
        $this->resetNotasPermitidas();
        $this->resetValidation();

        // Cuando ya hay curso+materia, cargamos la grilla (consulta única, orden estable).
        if ($this->cursoId && $this->materiaId) {
            $this->loadGrid();
        }
    }

    /**
     * Valida que curso/materia existan y pertenezcan al contexto institucional actual.
     *
     * Importante: si aún no hay selección completa, no hace nada (evita 404 en renders intermedios).
     */
    protected function ensureScopeOr404(): void
    {
        $ctx = schoolCtx();

        if (! $this->cursoId || ! $this->materiaId) {
            return;
        }

        $cursoOk = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', (int) $this->cursoId)
            ->exists();

        if (! $cursoOk) {
            abort(404);
        }

        $materiaOk = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', (int) $this->cursoId)
            ->where('id', (int) $this->materiaId)
            ->exists();

        if (! $materiaOk) {
            abort(404);
        }

        if ($this->modoPortalDocente) {
            CalificacionesDocenteSecundario::abortSiProfesorSinMateria(
                (int) $this->materiaId,
                (int) $this->cursoId,
            );
        }
    }

    /**
     * Construye `$this->rows` leyendo `calificaciones` + datos mínimos de alumno para la UI.
     *
     * Mapeo de columnas (legacy):
     * - Eval 1..8: `ic01..ic24` (cada evaluación: N, R1, R2)
     * - JIS 1..2: `ic25..ic28` (N/R por bloque)
     * - Coloquios: `dic`, `feb`
     * - Promedio final persistido: `calif`
     * - TEA: `tea` (checkbox en UI; en BD es entero 0/1 según migración del proyecto)
     */
    public function loadGrid(): void
    {
        $this->rows = $this->fetchRowsSnapshot();
        $this->cargarNotasPermitidasParaNivelActual();
    }

    /**
     * Columnas de `calificaciones` necesarias para sincronizar la grilla sin JOIN a `legajos`.
     *
     * @return list<string>
     */
    protected function columnasCalificacionSoloTabla(): array
    {
        return [
            'ord',
            'ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06', 'ic07', 'ic08', 'ic09', 'ic10',
            'ic11', 'ic12', 'ic13', 'ic14', 'ic15', 'ic16', 'ic17', 'ic18', 'ic19', 'ic20',
            'ic21', 'ic22', 'ic23', 'ic24', 'ic25', 'ic26', 'ic27', 'ic28',
            'dic', 'feb', 'calif', 'tea',
        ];
    }

    /**
     * Copia desde un registro `calificaciones` a `$this->rows[$id]` (mantiene `alumno` / `id` ya cargados en `loadGrid`).
     */
    protected function mergeCalificacionDbAFila(int $id, object $r): void
    {
        if (! isset($this->rows[$id])) {
            return;
        }

        $this->rows[$id]['ord'] = $r->ord;
        foreach ([
            'ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06', 'ic07', 'ic08', 'ic09', 'ic10',
            'ic11', 'ic12', 'ic13', 'ic14', 'ic15', 'ic16', 'ic17', 'ic18', 'ic19', 'ic20',
            'ic21', 'ic22', 'ic23', 'ic24', 'ic25', 'ic26', 'ic27', 'ic28',
        ] as $c) {
            $this->rows[$id][$c] = (string) ($r->{$c} ?? '');
        }
        $this->rows[$id]['dic'] = (string) ($r->dic ?? '');
        $this->rows[$id]['feb'] = (string) ($r->feb ?? '');
        $this->rows[$id]['calif'] = (string) ($r->calif ?? '');
        $this->rows[$id]['tea'] = ((int) ($r->tea ?? 0)) === 1;
    }

    /**
     * Una sola lectura por PK tras guardado válido o TEA (sin JOIN).
     */
    protected function refreshCalificacionRowFromDatabase(int $id): void
    {
        if (! $this->cursoId || ! $this->materiaId) {
            return;
        }

        $ctx = schoolCtx();
        $r = DB::table('calificaciones')
            ->where('id', $id)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', (int) $this->cursoId)
            ->where('idMaterias', (int) $this->materiaId)
            ->first($this->columnasCalificacionSoloTabla());

        if ($r) {
            $this->mergeCalificacionDbAFila($id, $r);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchRowsSnapshot(): array
    {
        $this->ensureScopeOr404();

        $ctx = schoolCtx();

        // Join con `legajos` solo para mostrar nombre (no se edita desde acá).
        $califs = DB::table('calificaciones as c')
            ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->where('c.idTerlec', (int) $ctx->idTerlec)
            ->where('c.idCursos', (int) $this->cursoId)
            ->where('c.idMaterias', (int) $this->materiaId)
            ->orderByRaw('COALESCE(c.ord, 9999) asc')
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->get([
                'c.id',
                'c.ord',
                'l.apellido',
                'l.nombre',
                'c.ic01', 'c.ic02', 'c.ic03',
                'c.ic04', 'c.ic05', 'c.ic06',
                'c.ic07', 'c.ic08', 'c.ic09',
                'c.ic10', 'c.ic11', 'c.ic12',
                'c.ic13', 'c.ic14', 'c.ic15',
                'c.ic16', 'c.ic17', 'c.ic18',
                'c.ic19', 'c.ic20', 'c.ic21',
                'c.ic22', 'c.ic23', 'c.ic24',
                'c.ic25', 'c.ic26', 'c.ic27', 'c.ic28',
                'c.dic', 'c.feb', 'c.tea', 'c.calif',
            ]);

        $out = [];
        foreach ($califs as $r) {
            $id = (int) $r->id;
            $out[$id] = [
                'id' => $id,
                'ord' => $r->ord,
                'alumno' => trim(((string) $r->apellido).', '.((string) $r->nombre)),
                'ic01' => (string) ($r->ic01 ?? ''),
                'ic02' => (string) ($r->ic02 ?? ''),
                'ic03' => (string) ($r->ic03 ?? ''),
                'ic04' => (string) ($r->ic04 ?? ''),
                'ic05' => (string) ($r->ic05 ?? ''),
                'ic06' => (string) ($r->ic06 ?? ''),
                'ic07' => (string) ($r->ic07 ?? ''),
                'ic08' => (string) ($r->ic08 ?? ''),
                'ic09' => (string) ($r->ic09 ?? ''),
                'ic10' => (string) ($r->ic10 ?? ''),
                'ic11' => (string) ($r->ic11 ?? ''),
                'ic12' => (string) ($r->ic12 ?? ''),
                'ic13' => (string) ($r->ic13 ?? ''),
                'ic14' => (string) ($r->ic14 ?? ''),
                'ic15' => (string) ($r->ic15 ?? ''),
                'ic16' => (string) ($r->ic16 ?? ''),
                'ic17' => (string) ($r->ic17 ?? ''),
                'ic18' => (string) ($r->ic18 ?? ''),
                'ic19' => (string) ($r->ic19 ?? ''),
                'ic20' => (string) ($r->ic20 ?? ''),
                'ic21' => (string) ($r->ic21 ?? ''),
                'ic22' => (string) ($r->ic22 ?? ''),
                'ic23' => (string) ($r->ic23 ?? ''),
                'ic24' => (string) ($r->ic24 ?? ''),
                'ic25' => (string) ($r->ic25 ?? ''),
                'ic26' => (string) ($r->ic26 ?? ''),
                'ic27' => (string) ($r->ic27 ?? ''),
                'ic28' => (string) ($r->ic28 ?? ''),
                'dic' => (string) ($r->dic ?? ''),
                'feb' => (string) ($r->feb ?? ''),
                'calif' => (string) ($r->calif ?? ''),
                'tea' => ((int) ($r->tea ?? 0)) === 1,
            ];
        }

        return $out;
    }

    protected function resetNotasPermitidas(): void
    {
        $this->notasPermitidasLista = [];
    }

    /**
     * Lee `notaspermitidas` filtrado por el nivel del contexto institucional.
     */
    protected function cargarNotasPermitidasParaNivelActual(): void
    {
        $this->resetNotasPermitidas();

        $ctx = schoolCtx();
        $notas = DB::table('notaspermitidas')
            ->where('idNivel', (int) $ctx->idNivel)
            ->pluck('nota');

        foreach ($notas as $n) {
            $clave = trim((string) $n);
            if ($clave === '') {
                continue;
            }
            if (! in_array($clave, $this->notasPermitidasLista, true)) {
                $this->notasPermitidasLista[] = $clave;
            }
        }
    }

    /** Si no hay filas en `notaspermitidas` para el nivel, no se aplica lista blanca (compatibilidad). */
    protected function notasPermitidasActiva(): bool
    {
        return $this->notasPermitidasLista !== [];
    }

    /** Nota vacía se acepta (celda sin dato); el resto debe coincidir con el catálogo (comparación estricta de string). */
    protected function notaPermitidaParaCatalogoActual(string $nota): bool
    {
        if ($nota === '') {
            return true;
        }

        return in_array($nota, $this->notasPermitidasLista, true);
    }

    /**
     * Campos de nota cuyo valor debe existir en `notaspermitidas` para el nivel actual.
     *
     * No incluye `calif`: puede ser un promedio calculado con decimales que no coincide con el catálogo de ingreso.
     */
    protected function campoSujetoANotasPermitidas(string $field): bool
    {
        if (preg_match('/^ic(0[1-9]|1[0-9]|2[0-8])$/', $field) === 1) {
            return true;
        }

        return $field === 'dic' || $field === 'feb';
    }

    /**
     * Lista blanca de campos que el cliente puede intentar editar vía Livewire.
     *
     * `calif` (Pr.Final / promedio anual) se calcula y persiste en servidor; la UI es solo lectura.
     *
     * @return list<string>
     */
    protected function editableFields(): array
    {
        return [
            'ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06', 'ic07', 'ic08', 'ic09', 'ic10',
            'ic11', 'ic12', 'ic13', 'ic14', 'ic15', 'ic16', 'ic17', 'ic18', 'ic19', 'ic20',
            'ic21', 'ic22', 'ic23', 'ic24', 'ic25', 'ic26', 'ic27', 'ic28',
            'dic', 'feb', 'tea',
        ];
    }

    /**
     * Guarda una celda puntual en `calificaciones`.
     *
     * - Se ejecuta desde la vista en `blur` para inputs (sale del campo) y en `change` para checkbox.
     * - Luego de guardar notas de módulos (`ic01..ic28`), recalcula/persiste `calif` vía `syncPromedioAnual()`.
     */
    public function saveCell(int $id, string $field, mixed $value): void
    {
        if ($this->modoPortalDocente) {
            if ($this->cargaNotasSoloLectura) {
                return;
            }
        } else {
            abort_unless(tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA), 403);
        }

        // Rate limit suave: evita bursts si el usuario navega rápido con teclado.
        $key = 'calificacionesSecundario:carga:cell:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 240)) {
            return;
        }
        RateLimiter::hit($key, 60);

        $this->ensureScopeOr404();

        $field = trim($field);
        if (! in_array($field, $this->editableFields(), true)) {
            abort(400);
        }

        $ctx = schoolCtx();

        // Revalidación de alcance: el `id` debe pertenecer al curso/materia/ciclo actual.
        $exists = DB::table('calificaciones')
            ->where('id', $id)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', (int) $this->cursoId)
            ->where('idMaterias', (int) $this->materiaId)
            ->exists();

        if (! $exists) {
            abort(404);
        }

        $value = is_string($value) ? trim($value) : $value;

        if ($field === 'tea') {
            // TEA: booleano persistido como 0/1 (coherente con checkbox).
            $payload = ['tea' => $value ? 1 : 0];
            DB::table('calificaciones')->where('id', $id)->update($payload);
            $this->refreshCalificacionRowFromDatabase($id);

            return;
        }

        // Validación por tipo de columna (límites coherentes con columnas VARCHAR legacy).
        $rules = [
            'value' => match ($field) {
                'dic', 'feb' => ['nullable', 'string', 'max:10'],
                default => ['nullable', 'string', 'max:15'],
            },
        ];
        Validator::make(
            ['value' => $value],
            $rules,
            [],
            ['value' => $field],
        )->validate();

        $strVal = (string) ($value ?? '');
        if ($this->notasPermitidasActiva() && $this->campoSujetoANotasPermitidas($field)) {
            if ($strVal !== '' && ! $this->notaPermitidaParaCatalogoActual($strVal)) {
                // No persistir (caso raro: bypass del chequeo en el navegador). Solo alinear la celda desde BD.
                $guardado = (string) (DB::table('calificaciones')
                    ->where('id', $id)
                    ->where('idTerlec', (int) $ctx->idTerlec)
                    ->where('idCursos', (int) $this->cursoId)
                    ->where('idMaterias', (int) $this->materiaId)
                    ->value($field) ?? '');
                if (isset($this->rows[$id])) {
                    $this->rows[$id][$field] = $guardado;
                }

                return;
            }
        }

        // Persistencia del campo editado (string); luego una lectura por PK (sin JOIN) para devolver estado real.
        DB::table('calificaciones')->where('id', $id)->update([$field => $strVal]);

        // Promedio anual: solo depende de módulos (Eval/JIS). No recalculamos al editar Dic/Feb/TEA.
        if ($this->debeRecalcularPromedioAnual($field)) {
            $this->syncPromedioAnual($id);
        }

        $this->refreshCalificacionRowFromDatabase($id);
        $this->resetErrorBag('cell.'.$id.'.'.$field);
    }

    /**
     * Define si un campo disparó cambios en módulos que impactan el promedio anual.
     */
    protected function debeRecalcularPromedioAnual(string $field): bool
    {
        // Solo módulos (Eval/JIS). No recalcula por TEA ni por campos finales (Dic/Feb/Pr.Final).
        return preg_match('/^ic(0[1-9]|1[0-9]|2[0-8])$/', $field) === 1;
    }

    /**
     * Recalcula y persiste `calif` en función de `ic01..ic28` ya guardados en BD.
     *
     * Único punto del sistema que invoca `PromedioAnualCalificacionesSecundario::calcular()` (docs/05 §7).
     * Importante: relee desde DB para evitar inconsistencias si hubiera más de un update encadenado.
     */
    protected function syncPromedioAnual(int $id): void
    {
        $ctx = schoolCtx();

        // Tomamos solo los campos necesarios para el cálculo (menos ruido y menos datos movidos).
        $row = DB::table('calificaciones')
            ->where('id', $id)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', (int) $this->cursoId)
            ->where('idMaterias', (int) $this->materiaId)
            ->first([
                'ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06', 'ic07', 'ic08', 'ic09', 'ic10',
                'ic11', 'ic12', 'ic13', 'ic14', 'ic15', 'ic16', 'ic17', 'ic18', 'ic19', 'ic20',
                'ic21', 'ic22', 'ic23', 'ic24', 'ic25', 'ic26', 'ic27', 'ic28',
            ]);

        if (! $row) {
            return;
        }

        $arr = [];
        foreach ([
            'ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06', 'ic07', 'ic08', 'ic09', 'ic10',
            'ic11', 'ic12', 'ic13', 'ic14', 'ic15', 'ic16', 'ic17', 'ic18', 'ic19', 'ic20',
            'ic21', 'ic22', 'ic23', 'ic24', 'ic25', 'ic26', 'ic27', 'ic28',
        ] as $k) {
            $arr[$k] = (string) ($row->{$k} ?? '');
        }

        $prom = PromedioAnualCalificacionesSecundario::calcular($arr);
        $calif = (string) ($prom['promedio'] ?? '');

        DB::table('calificaciones')->where('id', $id)->update(['calif' => $calif]);

        if (isset($this->rows[$id])) {
            $this->rows[$id]['calif'] = $calif;
        }
    }

    /**
     * Cursos disponibles para el nivel + ciclo lectivo activos en sesión.
     *
     * @return Collection<int, mixed>
     */
    public function cursos(): Collection
    {
        $ctx = schoolCtx();

        return Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->orderByRaw('COALESCE(orden, 9999) asc')
            ->orderBy('Id')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);
    }

    /**
     * Materias del curso seleccionado (tabla `materias`), filtradas por contexto.
     *
     * @return Collection<int, mixed>
     */
    public function materias(): Collection
    {
        $ctx = schoolCtx();

        if (! $this->cursoId) {
            return collect();
        }

        return DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', (int) $this->cursoId)
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'materia', 'abrev', 'ord']);
    }

    public function render()
    {
        $cursos = $this->cursos();
        $materias = $this->materias();

        // Textos auxiliares para el encabezado informativo (no son fuente de verdad; vienen de los mismos datasets).
        $cursoLabel = $this->cursoId
            ? optional($cursos->firstWhere('Id', (int) $this->cursoId))->cursec
            : null;

        $materiaLabel = $this->materiaId
            ? optional($materias->firstWhere('id', (int) $this->materiaId))->materia
            : null;

        $notasPermitidasLista = $this->notasPermitidasLista;
        $notasPermitidasActiva = $this->notasPermitidasActiva();

        $modoPortalDocente = $this->modoPortalDocente;
        $soloLectura = $this->modoPortalDocente && $this->cargaNotasSoloLectura;
        $mostrarModalNotasOff = $this->modoPortalDocente && $this->mostrarModalNotasOff;
        $mensajeNotasOff = $this->mensajeNotasOff;
        $pdfUrl = null;
        $urlLista = null;

        if ($this->modoPortalDocente && $this->cursoId && $this->materiaId) {
            $pdfUrl = route('portalDocente.calificaciones.pdf', [
                'curso' => $this->cursoId,
                'materia' => $this->materiaId,
            ]);
            $urlLista = route('portalDocente.calificaciones');
        }

        $viewData = compact(
            'cursos',
            'materias',
            'cursoLabel',
            'materiaLabel',
            'notasPermitidasLista',
            'notasPermitidasActiva',
            'modoPortalDocente',
            'soloLectura',
            'mostrarModalNotasOff',
            'mensajeNotasOff',
            'pdfUrl',
            'urlLista',
        );

        $layout = $this->modoPortalDocente ? 'layouts.docente' : 'layouts.app';
        $pageTitle = $this->modoPortalDocente
            ? 'Calificaciones'
            : 'Carga de calificaciones (secundario)';

        return view('livewire.calificaciones-secundario.carga-calificaciones-secundario', $viewData)
            ->layout($layout, ['pageTitle' => $pageTitle]);
    }

    public function aceptarAvisoCargaNotasOff(): void
    {
        $this->mostrarModalNotasOff = false;
    }
}
