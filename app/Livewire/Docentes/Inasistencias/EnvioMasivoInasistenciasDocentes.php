<?php

namespace App\Livewire\Docentes\Inasistencias;

use App\Support\InasistenciasDocentes;
use App\Support\InasistenciasDocentes\EnvioMasivoInformes;
use Illuminate\Support\Str;
use Livewire\Component;

class EnvioMasivoInasistenciasDocentes extends Component
{
    public int $bimestre = 0;

    public int $anio;

    public bool $soloPrueba = false;

    public ?string $token = null;

    public bool $polling = false;

    /** @var array<string, mixed> */
    public array $progreso = [];

    public bool $revisionAbierta = false;

    public int $revisionIndice = 0;

    public function mount(): void
    {
        abort_unless(tienePermiso(InasistenciasDocentes::PERMISO_ORDEN), 403);
        abort_unless(InasistenciasDocentes::moduloDisponible(), 503);

        $this->anio = InasistenciasDocentes::anoLectivo();
    }

    public function enviar(): void
    {
        $this->validate([
            'bimestre' => 'required|integer|between:1,6',
            'anio' => 'required|integer|min:2020|max:2035',
        ], [
            'bimestre.required' => 'Elegí un bimestre.',
            'bimestre.between' => 'El bimestre no es válido.',
        ]);

        $this->revisionAbierta = false;
        $this->revisionIndice = 0;
        $this->token = Str::random(32);
        $this->polling = true;
        $this->progreso = [
            'total' => 0,
            'current' => 0,
            'nombre' => '',
            'done' => false,
            'enviados' => 0,
            'sinEmail' => 0,
            'errores' => 0,
            'mensaje' => '',
            'lista' => [],
            'bimestre' => $this->bimestre,
            'anio' => $this->anio,
            'soloPrueba' => $this->soloPrueba,
        ];

        EnvioMasivoInformes::guardarParams($this->token, [
            'bimestre' => $this->bimestre,
            'anio' => $this->anio,
            'soloPrueba' => $this->soloPrueba,
            'idNivel' => (int) (schoolCtx()->idNivel ?? 0),
        ]);

        EnvioMasivoInformes::escribirProgreso($this->token, $this->progreso);

        $token = $this->token;
        dispatch(function () use ($token) {
            EnvioMasivoInformes::procesar($token);
        })->afterResponse();
    }

    public function actualizarProgreso(): void
    {
        if ($this->token === null) {
            return;
        }

        $data = EnvioMasivoInformes::obtenerProgreso($this->token);
        if ($data !== null) {
            $this->progreso = $data;
        }

        if (! empty($this->progreso['done'])) {
            $this->polling = false;
        }
    }

    /**
     * @return list<array{nombre: string, idProfesor: int, estado: string}>
     */
    public function pdfsGeneradosParaRevision(): array
    {
        $lista = $this->progreso['lista'] ?? [];

        return array_values(array_filter($lista, function (array $r): bool {
            return ($r['estado'] ?? '') === 'generado' && ! empty($r['idProfesor']);
        }));
    }

    public function abrirRevision(int $indice = 0): void
    {
        $pdfs = $this->pdfsGeneradosParaRevision();
        if ($pdfs === []) {
            return;
        }

        $this->revisionIndice = max(0, min($indice, count($pdfs) - 1));
        $this->revisionAbierta = true;
    }

    public function abrirRevisionPorProfesor(int $idProfesor): void
    {
        foreach ($this->pdfsGeneradosParaRevision() as $i => $r) {
            if ((int) ($r['idProfesor'] ?? 0) === $idProfesor) {
                $this->abrirRevision($i);

                return;
            }
        }
    }

    public function cerrarRevision(): void
    {
        $this->revisionAbierta = false;
    }

    public function revisionAnterior(): void
    {
        if ($this->revisionIndice > 0) {
            $this->revisionIndice--;
        }
    }

    public function revisionSiguiente(): void
    {
        $total = count($this->pdfsGeneradosParaRevision());
        if ($this->revisionIndice < $total - 1) {
            $this->revisionIndice++;
        }
    }

    public function render()
    {
        return view('livewire.docentes.inasistencias.envio-masivo', [
            'bimestres' => InasistenciasDocentes::BIMESTRES,
            'pdfsRevision' => $this->pdfsGeneradosParaRevision(),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Envío masivo — Inasistencias docentes']);
    }
}
