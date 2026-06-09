<?php

namespace App\Livewire\Abm\LegajosProfesor;

use App\Models\CampoProfesor;
use App\Models\EstadoCivil;
use App\Models\Profesor;
use App\Models\ProfesorTipo;
use App\Models\Sexo;
use App\Models\SolapaLegajoProfesor;
use App\Support\PermisosIaCatalog;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class LegajoProfesorForm extends Component
{
    private const CORE_COLUMNS = ['apellido', 'nombre', 'dni', 'IdTipoProf'];

    public ?int $id = null;

    public string $activeTab = 'docente';

    public string $apellido = '';

    public string $nombre = '';

    public string $dni = '';

    public int|string $IdTipoProf = '';

    public string $cuil = '';

    public int|string $sexo = 0;

    public string $email = '';

    public string $emailInsti = '';

    public string $callenum = '';

    public string $barrio = '';

    public string $telefono = '';

    public string $celular = '';

    public string $nacion = '';

    public int|string $estacivi = '';

    public string $legJunta = '';

    public string $legEscuela = '';

    public string $fechnaci = '';

    public string $titulo = '';

    public string $numreg = '';

    public string $apto = '';

    public string $incapac = '';

    public string $escalafonD = '';

    public string $escalafonE = '';

    public string $cargo = '';

    public string $obs = '';

    public array $profesorExtras = [];

    /**
     * @var list<string>
     */
    private const COLUMNAS_FORMULARIO_GESTIONADAS = [
        'IdTipoProf', 'apellido', 'nombre', 'dni', 'cuil', 'sexo', 'email', 'emailInsti',
        'callenum', 'barrio', 'telefono', 'celular', 'nacion', 'estacivi', 'legJunta', 'legEscuela',
        'fechnaci', 'titulo', 'numreg', 'apto', 'incapac', 'escalafonD', 'escalafonE', 'cargo', 'obs',
    ];

    private const COLUMNAS_SISTEMA_NO_EXTRAS = [
        'id', 'pwrd', 'permisos', 'ult_idNivel', 'ult_idTerlec', 'nivel',
    ];

    public function mount(?int $id = null): void
    {
        if (! $id && ! tienePermiso(PermisosIaCatalog::LEGAJOS_DOCENTES)) {
            abort(403, 'Sin permiso para crear legajos de docentes.');
        }

        $this->id = $id;
        if ($id) {
            $this->loadProfesor($id);
        }
    }

    private function requireModificarLegajoDocente(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::LEGAJOS_DOCENTES), 403, 'Sin permiso para modificar legajos de docentes.');
    }

    protected function rules(): array
    {
        $idNivel = (int) (SchoolAlcancePedagogico::idNivelLegajosDocente() ?? 0);
        $dniUnique = Rule::unique('profesores', 'dni')
            ->where(fn ($q) => $q->where('nivel', $idNivel));
        if ($this->id) {
            $dniUnique = $dniUnique->ignore($this->id);
        }

        $r = [
            'apellido' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:50'],
            'dni' => ['required', 'digits_between:7,11', $dniUnique],
            'IdTipoProf' => ['required', 'integer', 'min:1'],
        ];

        $set = $this->camposActivosSet();
        if ($set === null || isset($set['email'])) {
            $r['email'] = ['nullable', 'email', 'max:100'];
        }
        if ($set === null || isset($set['emailInsti'])) {
            $r['emailInsti'] = ['nullable', 'email', 'max:100'];
        }
        if ($set === null || isset($set['fechnaci'])) {
            $r['fechnaci'] = ['nullable', 'date'];
        }
        if ($set === null || isset($set['apto'])) {
            $r['apto'] = ['nullable', 'date'];
        }
        if ($set === null || isset($set['escalafonD'])) {
            $r['escalafonD'] = ['nullable', 'date'];
        }
        if ($set === null || isset($set['escalafonE'])) {
            $r['escalafonE'] = ['nullable', 'date'];
        }

        $r['profesorExtras'] = ['array'];
        $r['profesorExtras.*'] = ['nullable', 'string', 'max:4000'];

        return $r;
    }

    protected function messages(): array
    {
        return [
            'apellido.required' => 'El apellido es obligatorio.',
            'nombre.required' => 'El nombre es obligatorio.',
            'dni.required' => 'El DNI es obligatorio.',
            'dni.digits_between' => 'El DNI debe tener entre 7 y 11 dígitos.',
            'dni.unique' => 'Ya existe un docente con ese DNI en este nivel.',
            'IdTipoProf.required' => 'El rol es obligatorio.',
            'IdTipoProf.min' => 'Seleccione un rol válido.',
            'email.email' => 'El email personal no es válido.',
            'emailInsti.email' => 'El email institucional no es válido.',
        ];
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function save(): mixed
    {
        $this->requireModificarLegajoDocente();

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->focusTabForValidationErrors(array_keys($e->errors()));
            throw $e;
        }

        $allData = $this->formData();
        $set = $this->camposActivosSet();

        if ($set !== null) {
            $allData = array_filter($allData, fn ($col) => isset($set[$col]), ARRAY_FILTER_USE_KEY);
        }

        $idNivel = (int) (SchoolAlcancePedagogico::idNivelLegajosDocente() ?? 0);
        if ($idNivel < 1) {
            session()->flash('warning', 'No hay nivel activo en el contexto. Seleccione nivel en el login.');

            return null;
        }

        if ($this->id) {
            $prof = $this->scopedProfesorOrFail($this->id);
            $prof->update($allData);
            session()->flash('success', "Legajo de {$allData['apellido']}, {$allData['nombre']} actualizado.");
        } else {
            $prof = Profesor::create($allData);
            $prof->nivel = $idNivel;
            // Texto plano legacy: ver docs/03-autenticacion-y-permisos.md §2.1
            $prof->pwrd = '1234';
            if ($prof->permisos === null || $prof->permisos === '') {
                $prof->permisos = str_repeat('0', 100);
            }
            $prof->save();
            $this->id = (int) $prof->id;
            session()->flash('success', "Legajo de {$allData['apellido']}, {$allData['nombre']} creado.");
        }

        return redirect()->route('abm.legajos-profesor', [
            'page' => $this->pageForProfesor((int) $this->id, 25),
            'focus' => (int) $this->id,
        ]);
    }

    public function cancel(): mixed
    {
        return redirect()->route('abm.legajos-profesor', ['focus' => $this->id]);
    }

    protected function scopedProfesorOrFail(int $id): Profesor
    {
        return Profesor::query()
            ->delNivel(SchoolAlcancePedagogico::idNivelLegajosDocente())
            ->whereKey($id)
            ->firstOrFail();
    }

    private function loadProfesor(int $id): void
    {
        $p = $this->scopedProfesorOrFail($id);

        $this->apellido = $p->apellido ?? '';
        $this->nombre = $p->nombre ?? '';
        $this->dni = (string) ($p->dni ?? '');
        $this->IdTipoProf = (int) ($p->IdTipoProf ?? 0);
        $this->cuil = $p->cuil ?? '';
        $this->sexo = $p->sexo !== null && $p->sexo !== '' ? (int) $p->sexo : '';
        $this->email = $p->email ?? '';
        $this->emailInsti = $p->emailInsti ?? '';
        $this->callenum = $p->callenum ?? '';
        $this->barrio = $p->barrio ?? '';
        $this->telefono = $p->telefono ?? '';
        $this->celular = $p->celular ?? '';
        $this->nacion = $p->nacion ?? '';
        $this->estacivi = $p->estacivi !== null && $p->estacivi !== '' ? (int) $p->estacivi : '';
        $this->legJunta = $p->legJunta ?? '';
        $this->legEscuela = $p->legEscuela ?? '';
        $this->fechnaci = $p->fechnaci ? $p->fechnaci->format('Y-m-d') : '';
        $this->titulo = $p->titulo ?? '';
        $this->numreg = $p->numreg ?? '';
        $this->apto = $p->apto ? $p->apto->format('Y-m-d') : '';
        $this->incapac = $p->incapac ?? '';
        $this->escalafonD = $p->escalafonD ? $p->escalafonD->format('Y-m-d') : '';
        $this->escalafonE = $p->escalafonE ? $p->escalafonE->format('Y-m-d') : '';
        $this->cargo = $p->cargo ?? '';
        $this->obs = $p->obs ?? '';

        $this->rellenarProfesorExtrasDesdeModelo($p);
    }

    private function rellenarProfesorExtrasDesdeModelo(Profesor $p): void
    {
        $this->profesorExtras = [];
        $managed = array_flip(self::COLUMNAS_FORMULARIO_GESTIONADAS);
        $skip = array_merge(self::COLUMNAS_SISTEMA_NO_EXTRAS, CampoProfesor::COLUMNAS_EXCLUIDAS);

        foreach ($p->getAttributes() as $key => $val) {
            if (isset($managed[$key]) || in_array($key, $skip, true)) {
                continue;
            }
            if ($val === null) {
                $this->profesorExtras[$key] = '';
            } elseif ($val instanceof \DateTimeInterface) {
                $this->profesorExtras[$key] = $val->format('Y-m-d');
            } elseif (is_bool($val)) {
                $this->profesorExtras[$key] = $val ? '1' : '0';
            } else {
                $this->profesorExtras[$key] = (string) $val;
            }
        }
    }

    private function formData(): array
    {
        $data = [
            'apellido' => strtoupper(trim($this->apellido)),
            'nombre' => ucwords(strtolower(trim($this->nombre))),
            'dni' => $this->dni !== '' ? (int) $this->dni : null,
            'IdTipoProf' => (int) $this->IdTipoProf,
            'cuil' => trim($this->cuil),
            'sexo' => $this->sexo !== '' ? (int) $this->sexo : 0,
            'email' => trim($this->email) ?: null,
            'emailInsti' => trim($this->emailInsti) ?: null,
            'callenum' => $this->callenum,
            'barrio' => $this->barrio,
            'telefono' => $this->telefono,
            'celular' => $this->celular,
            'nacion' => $this->nacion,
            'estacivi' => $this->estacivi !== '' ? (int) $this->estacivi : null,
            'legJunta' => $this->legJunta,
            'legEscuela' => $this->legEscuela,
            'fechnaci' => $this->fechnaci ?: null,
            'titulo' => $this->titulo,
            'numreg' => $this->numreg,
            'apto' => $this->apto ?: null,
            'incapac' => $this->incapac,
            'escalafonD' => $this->escalafonD ?: null,
            'escalafonE' => $this->escalafonE ?: null,
            'cargo' => $this->cargo,
            'obs' => $this->obs,
        ];

        $managedFlip = array_flip(self::COLUMNAS_FORMULARIO_GESTIONADAS);
        foreach ($this->profesorExtras as $k => $v) {
            if (isset($managedFlip[$k])) {
                continue;
            }
            $data[$k] = is_string($v) ? trim($v) : $v;
        }

        return $data;
    }

    /**
     * @return array<string, int>|null
     */
    private function camposActivosSet(): ?array
    {
        $visibles = CampoProfesor::columnasActivasParaLegajo();
        if ($visibles === null) {
            return null;
        }

        return array_flip(array_unique(array_merge(self::CORE_COLUMNS, $visibles)));
    }

    /**
     * @param  array<string, list<array{columna: string, etiqueta: ?string}>>  $camposPorSlug
     * @return array<string, string>
     */
    private function resolverTabsParametrizados(array $camposPorSlug): array
    {
        $rest = [];
        foreach (SolapaLegajoProfesor::query()->orderBy('orden')->get(['slug', 'nombre']) as $s) {
            if ($s->slug === 'docente') {
                continue;
            }
            if (! empty($camposPorSlug[$s->slug] ?? [])) {
                $rest[$s->slug] = $s->nombre;
            }
        }

        $docenteNombre = SolapaLegajoProfesor::where('slug', 'docente')->value('nombre') ?? 'DOCENTE';

        return array_merge(['docente' => $docenteNombre], $rest);
    }

    /**
     * Muestra el error en la solapa donde está el campo (p. ej. email en CONTACTO).
     *
     * @param  list<string>  $errorKeys
     */
    private function focusTabForValidationErrors(array $errorKeys): void
    {
        if ($errorKeys === []) {
            return;
        }

        $firstKey = $errorKeys[0];
        $columna = str_starts_with($firstKey, 'profesorExtras.')
            ? substr($firstKey, strlen('profesorExtras.'))
            : $firstKey;

        $slug = 'docente';
        if (! in_array($columna, self::CORE_COLUMNS, true)) {
            foreach (CampoProfesor::camposPorSolapaSlugOrdenados() as $tabSlug => $campos) {
                foreach ($campos as $campo) {
                    if ($campo['columna'] === $columna) {
                        $slug = $tabSlug;
                        break 2;
                    }
                }
            }
        }

        $this->activeTab = $slug;
    }

    private function pageForProfesor(int $id, int $perPage): int
    {
        $idNivel = (int) (SchoolAlcancePedagogico::idNivelLegajosDocente() ?? 0);
        $p = Profesor::query()->delNivel($idNivel)->find($id);
        if (! $p) {
            return 1;
        }

        $countBefore = Profesor::query()
            ->delNivel($idNivel)
            ->where(function ($q) use ($p) {
                $q->where('apellido', '<', $p->apellido)
                    ->orWhere(function ($q2) use ($p) {
                        $q2->where('apellido', $p->apellido)
                            ->where('nombre', '<', $p->nombre);
                    });
            })->count();

        return (int) floor($countBefore / $perPage) + 1;
    }

    public function render()
    {
        $roles = ProfesorTipo::query()->orderBy('tipo')->get(['id', 'tipo']);
        $sexosOpciones = Sexo::opcionesParaSelect();
        $estadosCivilesOpciones = EstadoCivil::opcionesParaSelect();

        $camposActivos = $this->camposActivosSet();
        $modoParametrizado = $camposActivos !== null;
        $columnasPorSolapaSlug = [];

        if ($modoParametrizado) {
            $columnasPorSolapaSlug = CampoProfesor::camposPorSolapaSlugOrdenados();
            $tabsVisibles = $this->resolverTabsParametrizados($columnasPorSolapaSlug);
        } else {
            $tabsVisibles = ['docente' => 'DOCENTE'];
            if (Schema::hasTable('solapas_legajo_profesor')) {
                foreach (SolapaLegajoProfesor::query()->orderBy('orden')->get(['slug', 'nombre']) as $s) {
                    if ($s->slug !== 'docente') {
                        $tabsVisibles[$s->slug] = $s->nombre;
                    }
                }
            }
        }

        if (! isset($tabsVisibles[$this->activeTab])) {
            $this->activeTab = array_key_first($tabsVisibles) ?? 'docente';
        }

        $puedeEditar = tienePermiso(PermisosIaCatalog::LEGAJOS_DOCENTES);
        $pageTitle = $this->id
            ? ($puedeEditar ? 'Editar legajo docente' : 'Consultar legajo docente')
            : 'Nuevo legajo docente';

        return view('livewire.abm.legajos-profesor.form', compact(
            'roles', 'sexosOpciones', 'estadosCivilesOpciones', 'tabsVisibles', 'modoParametrizado', 'columnasPorSolapaSlug', 'puedeEditar',
        ))->layout(layoutMenuStaff(), ['pageTitle' => $pageTitle]);
    }
}
