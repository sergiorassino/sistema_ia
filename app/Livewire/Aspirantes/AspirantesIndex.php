<?php

namespace App\Livewire\Aspirantes;

use App\Models\AspiCursoModelo;
use App\Models\Aspirante;
use App\Models\Aspiento;
use Illuminate\Support\Facades\DB;
use App\Support\PermisosIaCatalog;
use Livewire\Component;
use Livewire\WithPagination;

class AspirantesIndex extends Component
{
    use WithPagination;

    public string $buscar = '';

    /** Id de aspiento (instancia de registro) a listar; si no viene, se usa la más reciente del ciclo activo. */
    public ?int $instanciaId = null;

    /** @var array<string, array{except?: mixed, as?: string}> */
    protected $queryString = [
        'instanciaId' => ['except' => null, 'as' => 'instancia'],
        'buscar'      => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::ASPIRANTES_GESTION), 403);
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $ctx = schoolCtx();

        $instancia = null;
        if ($this->instanciaId !== null && $this->instanciaId > 0) {
            $instancia = Aspiento::query()
                ->whereKey($this->instanciaId)
                ->where('idNivel', $ctx->idNivel)
                ->first();
        }
        if ($instancia === null) {
            $instancia = Aspiento::query()
                ->where('idNivel', $ctx->idNivel)
                ->where('idTerlec', $ctx->idTerlec)
                ->orderByDesc('Id')
                ->first();
        }

        $columnas = collect();

        if (\Illuminate\Support\Facades\Schema::hasTable('campos_aspirantes_nivel')) {
            $select = [
                'campos_aspirantes.columna as columna',
                'campos_aspirantes.orden as orden',
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('campos_aspirantes_nivel', 'etiqueta')) {
                $select[] = 'campos_aspirantes_nivel.etiqueta as etiqueta';
            }

            $filas = DB::table('campos_aspirantes')
                ->join('campos_aspirantes_nivel', 'campos_aspirantes_nivel.campo_aspirante_id', '=', 'campos_aspirantes.id')
                ->where('campos_aspirantes_nivel.idNivel', (int) $ctx->idNivel)
                ->where('campos_aspirantes_nivel.visible', 1)
                ->orderBy('campos_aspirantes.orden')
                ->orderBy('campos_aspirantes.columna')
                ->get($select);

            $columnas = $filas->mapWithKeys(static function ($r) {
                $col = (string) $r->columna;
                $etiqueta = property_exists($r, 'etiqueta') && $r->etiqueta !== null && $r->etiqueta !== ''
                    ? (string) $r->etiqueta
                    : $col;

                return [$etiqueta => $col];
            });
        }

        $cursosModelo = AspiCursoModelo::query()
            ->where('idNivel', $ctx->idNivel)
            ->get()
            ->keyBy('id');

        $aspirantes = collect();
        if ($instancia) {
            $q = Aspirante::query()->where('idAspiento', (int) $instancia->getKey());
            $buscar = trim($this->buscar);
            if ($buscar !== '') {
                $like = '%'.$buscar.'%';
                $q->where(function ($w) use ($like) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('aspirantes', 'apellido')) {
                        $w->orWhere('apellido', 'like', $like);
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('aspirantes', 'nombre')) {
                        $w->orWhere('nombre', 'like', $like);
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('aspirantes', 'dni')) {
                        $w->orWhere('dni', 'like', $like);
                    }
                });
            }
            $aspirantes = $q->orderByDesc('id')->paginate(25);
        }

        return view('livewire.aspirantes.aspirantes-index', [
            'instancia'    => $instancia,
            'aspirantes'   => $aspirantes,
            'columnas'     => $columnas,
            'cursosModelo' => $cursosModelo,
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Aspirantes registrados']);
    }
}
