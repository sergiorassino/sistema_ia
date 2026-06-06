<?php

namespace App\Livewire\Docentes\Inasistencias;

use App\Support\InasistenciasDocentes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class CargosDocenteIndex extends Component
{
    public int $idProfesor;

    public ?int $idCxp = null;

    public int|string $idCargos = '';

    public int $idNiveles = 0;

    public int $cant = 0;

    public function mount(int $idProfesor, int|string|null $idCxp = null): void
    {
        abort_unless(tienePermiso(InasistenciasDocentes::PERMISO_ORDEN), 403);
        abort_unless(InasistenciasDocentes::tieneCargos(), 503, 'La tabla de cargos por docente no está disponible.');

        $this->idProfesor = $idProfesor;
        $profesor = InasistenciasDocentes::profesorDelContexto($idProfesor);
        $this->idNiveles = (int) ($profesor->nivel ?? schoolCtx()->idNivel);
        $this->idCxp = $idCxp !== null && $idCxp !== '' ? (int) $idCxp : null;

        if ($this->idCxp) {
            $row = DB::table('cargosxprofesor')
                ->where('id', $this->idCxp)
                ->where('idProfesores', $idProfesor)
                ->first();
            abort_unless($row, 404);
            $this->idCargos = (string) (int) $row->idCargos;
            $this->idNiveles = (int) $row->idNiveles;
            $this->cant = (int) $row->cant;
        }
    }

    protected function rules(): array
    {
        return [
            'idCargos' => ['required', 'integer', 'min:1'],
            'cant' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function save(): mixed
    {
        $key = 'cargos-docente:save:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('idCargos', 'Demasiados intentos. Espere un momento.');

            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate();
        InasistenciasDocentes::profesorDelContexto($this->idProfesor);

        $payload = [
            'idCargos' => (int) $this->idCargos,
            'idNiveles' => $this->idNiveles,
            'cant' => (int) $this->cant,
        ];

        if ($this->idCxp) {
            DB::table('cargosxprofesor')
                ->where('id', $this->idCxp)
                ->where('idProfesores', $this->idProfesor)
                ->update($payload);
            session()->flash('success', 'Cargo actualizado.');
        } else {
            $payload['idProfesores'] = $this->idProfesor;
            DB::table('cargosxprofesor')->insert($payload);
            session()->flash('success', 'Cargo agregado.');
        }

        return redirect()->route('docentes.inasistencias.cargos', $this->idProfesor);
    }

    public function delete(): mixed
    {
        abort_unless($this->idCxp, 404);

        DB::table('cargosxprofesor')
            ->where('id', $this->idCxp)
            ->where('idProfesores', $this->idProfesor)
            ->delete();

        session()->flash('success', 'Cargo eliminado.');

        return redirect()->route('docentes.inasistencias.cargos', $this->idProfesor);
    }

    public function render()
    {
        $profesor = InasistenciasDocentes::profesorDelContexto($this->idProfesor);

        $listado = DB::table('cargosxprofesor as cxp')
            ->join('cargos as c', 'c.id', '=', 'cxp.idCargos')
            ->join('niveles as n', 'n.id', '=', 'cxp.idNiveles')
            ->where('cxp.idProfesores', $this->idProfesor)
            ->orderBy('n.nivel')
            ->orderBy('c.cargo')
            ->get(['cxp.id', 'c.cargo', 'n.nivel as nivel_nombre', 'cxp.cant']);

        $catalogoCargos = DB::table('cargos')->orderBy('cargo')->get(['id', 'cargo']);

        return view('livewire.docentes.inasistencias.cargos', [
            'profesor' => $profesor,
            'listado' => $listado,
            'catalogoCargos' => $catalogoCargos,
            'nivelNombre' => schoolCtx()->nivelNombre(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Cargos del docente']);
    }
}
