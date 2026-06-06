<?php

namespace App\Livewire\Aspirantes;

use App\Models\AspiCursoModelo;
use App\Models\Aspicurso;
use App\Models\Aspiento;
use App\Models\Ento;
use App\Models\Terlec;
use App\Support\Aspirantes\AspirantesTokenService;
use App\Support\PermisosIaCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class InstanciaForm extends Component
{
    public ?int $instanciaId = null;

    public ?int $idTerlec = null;

    public ?string $insti = '';

    public ?string $titulo = '';

    public ?string $titulo3 = '';

    public ?string $fechdesde = null;

    public ?string $fechhasta = null;

    public ?string $mensaje_publico = '';

    public bool $activo = false;

    public ?string $token = null;

    /** @var array<int, bool> idCursoModelo => activo */
    public array $cursosSeleccionados = [];

    public function mount(?int $id = null): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASPIRANTES_GESTION), 403);

        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            abort(403, 'Contexto incompleto.');
        }

        if ($id !== null) {
            $instancia = Aspiento::query()
                ->whereKey($id)
                ->where('idNivel', $ctx->idNivel)
                ->firstOrFail();
            $this->cargarDesdeInstancia($instancia);
        } else {
            $this->idTerlec = $ctx->idTerlec;
            if (Schema::hasColumn('aspiento', 'insti')) {
                $this->insti = trim((string) (Ento::query()
                    ->where('idNivel', $ctx->idNivel)
                    ->value('insti') ?? ''));
            }
            $this->cursosSeleccionados = $this->cursosModeloDelNivel()
                ->mapWithKeys(static fn ($m) => [$m->id => false])
                ->all();
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, AspiCursoModelo>
     */
    public function cursosModeloDelNivel()
    {
        return AspiCursoModelo::query()
            ->where('idNivel', schoolCtx()->idNivel)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    protected function cargarDesdeInstancia(Aspiento $i): void
    {
        $this->instanciaId     = (int) $i->getKey();
        $this->idTerlec        = (int) $i->idTerlec;
        $this->insti           = Schema::hasColumn('aspiento', 'insti')
            ? (string) ($i->insti ?? '')
            : '';
        $this->titulo          = (string) ($i->titulo ?? '');
        $this->titulo3         = Schema::hasColumn('aspiento', 'titulo3')
            ? (string) ($i->titulo3 ?? '')
            : '';
        $this->fechdesde       = $i->fechdesde?->format('Y-m-d');
        $this->fechhasta       = $i->fechhasta?->format('Y-m-d');
        $this->mensaje_publico = (string) ($i->mensaje_publico ?? '');
        $this->activo          = (bool) $i->activo;
        $this->token           = (string) ($i->token ?? '');

        $cursosModelo = $this->cursosModeloDelNivel();
        $habilitados  = Aspicurso::query()
            ->where('idAspiento', (int) $i->getKey())
            ->where('activo', 1)
            ->whereNotNull('idCursoModelo')
            ->pluck('idCursoModelo')
            ->map(fn ($v) => (int) $v)
            ->all();
        $flip = array_flip($habilitados);

        $this->cursosSeleccionados = $cursosModelo
            ->mapWithKeys(static fn ($m) => [$m->id => isset($flip[$m->id])])
            ->all();
    }

    public function guardar(AspirantesTokenService $tokenSvc): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASPIRANTES_GESTION), 403);

        $reglas = [
            'idTerlec'        => ['required', 'integer', 'exists:terlec,id'],
            'titulo'          => ['nullable', 'string', 'max:150'],
            'fechdesde'       => ['required', 'date'],
            'fechhasta'       => ['required', 'date', 'after_or_equal:fechdesde'],
            'mensaje_publico' => ['nullable', 'string', 'max:2000'],
        ];
        if (Schema::hasColumn('aspiento', 'insti')) {
            $reglas['insti'] = ['nullable', 'string', 'max:120'];
        }
        if (Schema::hasColumn('aspiento', 'titulo3')) {
            $reglas['titulo3'] = ['nullable', 'string', 'max:150'];
        }

        $this->validate($reglas, [
            'idTerlec.required' => 'Seleccioná el ciclo lectivo.',
            'fechdesde.required' => 'Indicá la fecha de inicio.',
            'fechhasta.required' => 'Indicá la fecha de cierre.',
            'fechhasta.after_or_equal' => 'La fecha de cierre debe ser igual o posterior a la de inicio.',
        ]);

        $ctx = schoolCtx();

        DB::transaction(function () use ($tokenSvc, $ctx) {
            if ($this->instanciaId) {
                $i = Aspiento::query()
                    ->whereKey($this->instanciaId)
                    ->where('idNivel', $ctx->idNivel)
                    ->firstOrFail();
            } else {
                $i = new Aspiento();
            }

            if (! $i->exists) {
                $i->idNivel  = $ctx->idNivel;
                $i->idTerlec = (int) $this->idTerlec;
                $i->token    = $tokenSvc->generarUnico();
            } else {
                $i->idTerlec = (int) $this->idTerlec;
            }

            if (empty($i->token)) {
                $i->token = $tokenSvc->generarUnico();
            }

            if (Schema::hasColumn('aspiento', 'insti')) {
                $i->insti = $this->insti !== null && trim($this->insti) !== ''
                    ? mb_substr(trim($this->insti), 0, 120)
                    : null;
            }
            $i->titulo = $this->titulo !== null && trim($this->titulo) !== ''
                ? mb_substr(trim($this->titulo), 0, 150)
                : null;
            if (Schema::hasColumn('aspiento', 'titulo3')) {
                $i->titulo3 = $this->titulo3 !== null && trim($this->titulo3) !== ''
                    ? mb_substr(trim($this->titulo3), 0, 150)
                    : null;
            }
            $i->fechdesde       = $this->fechdesde;
            $i->fechhasta       = $this->fechhasta;
            $i->mensaje_publico = $this->mensaje_publico !== null && trim($this->mensaje_publico) !== ''
                ? trim($this->mensaje_publico)
                : null;
            $i->activo          = $this->activo;
            $i->save();

            $this->sincronizarCursos($i);

            $this->cargarDesdeInstancia($i->refresh());
        });

        session()->flash('status', 'Instancia guardada correctamente.');

        $this->redirect(
            route('aspirantes.instancia.edit', ['id' => $this->instanciaId]),
            navigate: true,
        );
    }

    /**
     * Sincroniza la tabla aspicursos según los modelos marcados:
     * - Inserta/actualiza una fila por cada modelo marcado, completando
     *   también las columnas legacy (`cursoaspi`, `habilitado`).
     * - Borra cualquier fila previa del mismo aspiento cuyo modelo
     *   pertenezca al nivel pero ya no esté marcado.
     */
    protected function sincronizarCursos(Aspiento $instancia): void
    {
        $cursosModelo = $this->cursosModeloDelNivel()->keyBy('id');
        $idsNivel     = $cursosModelo->keys()->map(fn ($v) => (int) $v)->all();

        $seleccionados = collect($this->cursosSeleccionados)
            ->filter(static fn ($v) => (bool) $v)
            ->keys()
            ->map(fn ($v) => (int) $v)
            ->all();

        $noSeleccionados = array_values(array_diff($idsNivel, $seleccionados));
        if ($noSeleccionados !== []) {
            Aspicurso::query()
                ->where('idAspiento', (int) $instancia->getKey())
                ->whereIn('idCursoModelo', $noSeleccionados)
                ->delete();
        }

        $tieneCursoAspi  = Schema::hasColumn('aspicursos', 'cursoaspi');
        $tieneHabilitado = Schema::hasColumn('aspicursos', 'habilitado');
        $tieneActivo     = Schema::hasColumn('aspicursos', 'activo');
        $tieneIdNivel    = Schema::hasColumn('aspicursos', 'idNivel');

        foreach ($seleccionados as $idCursoModelo) {
            $modelo = $cursosModelo->get($idCursoModelo);
            if (! $modelo) {
                continue;
            }

            $valores = [];
            if ($tieneActivo) {
                $valores['activo'] = true;
            }
            if ($tieneHabilitado) {
                $valores['habilitado'] = true;
            }
            if ($tieneCursoAspi) {
                $valores['cursoaspi'] = mb_substr((string) $modelo->nombre, 0, 80);
            }
            if ($tieneIdNivel) {
                $valores['idNivel'] = (int) $modelo->idNivel;
            }

            Aspicurso::query()->updateOrCreate(
                ['idAspiento' => (int) $instancia->getKey(), 'idCursoModelo' => $idCursoModelo],
                $valores,
            );
        }
    }

    public function getUrlPublicaProperty(): ?string
    {
        if (empty($this->token)) {
            return null;
        }

        return route('aspirantes.publico.registro', ['token' => $this->token]);
    }

    public function render()
    {
        $cursosModelo = $this->cursosModeloDelNivel();

        $tituloPagina = $this->instanciaId
            ? 'Aspirantes — Editar instancia'
            : 'Aspirantes — Nueva instancia';

        $terlecActual = $this->idTerlec
            ? Terlec::query()->find($this->idTerlec)
            : null;

        return view('livewire.aspirantes.instancia-form', [
            'cursosModelo'   => $cursosModelo,
            'esNueva'        => $this->instanciaId === null,
            'terlecs'        => Terlec::paraSelector(),
            'terlecActual'   => $terlecActual,
            'nivelNombre'    => schoolCtx()->nivelNombre(),
            'tieneInsti'     => Schema::hasColumn('aspiento', 'insti'),
            'tieneTitulo3'   => Schema::hasColumn('aspiento', 'titulo3'),
        ])->layout(layoutMenuStaff(), ['pageTitle' => $tituloPagina]);
    }
}
