<?php

namespace App\Livewire\Docentes\CertificacionServicios;

use App\Support\CertificacionServicios\AntiguedadServiciosCalculator;
use App\Support\CertificacionServicios\CertificacionServicios;
use App\Support\PermisosIaCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class CertificacionServiciosForm extends Component
{
    public int $idPersonal = 0;

    public string $profesorEtiqueta = '';

    /**
     * Filas indexadas por id de BD (evita corrimientos de índice al insertar/borrar).
     *
     * @var array<int|string, array<string, mixed>>
     */
    public array $servicios = [];

    /**
     * @var array<int|string, array<string, mixed>>
     */
    public array $licencias = [];

    public string $buscarServicios = '';

    public string $buscarLicencias = '';

    public bool $modalImprimir = false;

    public string $fechaEmision = '';

    public string $paraPresentar = '';

    /** Evita solapar blur-guardar con insertar/borrar. */
    public bool $bloqueado = false;

    public function mount(int $idPersonal): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CERTIFICACION_SERVICIOS), 403, 'Sin permiso para certificación de servicios.');

        if (! CertificacionServicios::tablasDisponibles()) {
            session()->flash('error', CertificacionServicios::mensajeTablasFaltantes());
            $this->redirect(route('docentes.certificacion-servicios'), navigate: true);

            return;
        }

        $profesor = CertificacionServicios::scopedProfesorOrFail($idPersonal);
        $this->idPersonal = (int) $profesor->id;
        $this->profesorEtiqueta = trim($profesor->apellido.', '.$profesor->nombre);
        if (trim((string) ($profesor->dni ?? '')) !== '') {
            $this->profesorEtiqueta .= ' — DNI '.$profesor->dni;
        }

        $this->cargarFilas();
        $this->fechaEmision = Carbon::today()->format('Y-m-d');
        $this->paraPresentar = '';
    }

    public function cargarFilas(): void
    {
        $servicios = [];
        foreach (CertificacionServicios::listarServicios($this->idPersonal) as $fila) {
            $id = (int) ($fila['id'] ?? 0);
            if ($id > 0) {
                $servicios[$id] = $fila;
            }
        }
        $this->servicios = $servicios;

        $licencias = [];
        foreach (CertificacionServicios::listarLicencias($this->idPersonal) as $fila) {
            $id = (int) ($fila['id'] ?? 0);
            if ($id > 0) {
                $licencias[$id] = $fila;
            }
        }
        $this->licencias = $licencias;
    }

    public function insertarServicio(): void
    {
        $this->assertPermiso();
        if ($this->bloqueado) {
            return;
        }
        $this->bloqueado = true;

        try {
            $resultado = CertificacionServicios::guardarServicio($this->idPersonal, null, [
                'cargo' => '',
                'titularSuplente' => '',
                'nroResolucion' => '',
                'fechaAlta' => '',
                'fechaBaja' => '',
                'hsCatedra' => '',
            ]);
            if (! $resultado['ok']) {
                $this->dispatch('se-swal-error', mensaje: $resultado['error'] ?? 'No se pudo insertar el servicio.');

                return;
            }
            $this->cargarFilas();
        } finally {
            $this->bloqueado = false;
        }
    }

    public function guardarServicioFila(int $id): void
    {
        $this->assertPermiso();
        if ($this->bloqueado || $id < 1) {
            return;
        }

        $fila = $this->servicios[$id] ?? null;
        if (! is_array($fila) || (int) ($fila['id'] ?? 0) !== $id) {
            return;
        }

        $this->validate([
            "servicios.{$id}.cargo" => ['nullable', 'string', 'max:100'],
            "servicios.{$id}.titularSuplente" => ['nullable', 'string', 'max:50'],
            "servicios.{$id}.nroResolucion" => ['nullable', 'string', 'max:100'],
            "servicios.{$id}.fechaAlta" => ['nullable', 'date'],
            "servicios.{$id}.fechaBaja" => ['nullable', 'date'],
            "servicios.{$id}.hsCatedra" => ['nullable', 'string', 'max:20'],
        ], [], [
            "servicios.{$id}.cargo" => 'cargo',
            "servicios.{$id}.titularSuplente" => 'titular/suplente',
            "servicios.{$id}.fechaAlta" => 'fecha alta',
            "servicios.{$id}.fechaBaja" => 'fecha baja',
        ]);

        $resultado = CertificacionServicios::guardarServicio($this->idPersonal, $id, $fila);
        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['error'] ?? 'No se pudo guardar el servicio.');
            $this->cargarFilas();
        }
    }

    public function eliminarServicio(int $id): void
    {
        $this->assertPermiso();
        if ($this->bloqueado || $id < 1) {
            return;
        }
        $this->bloqueado = true;

        try {
            CertificacionServicios::eliminarServicio($this->idPersonal, $id);
            unset($this->servicios[$id]);
            $this->cargarFilas();
        } finally {
            $this->bloqueado = false;
        }
    }

    public function insertarLicencia(): void
    {
        $this->assertPermiso();
        if ($this->bloqueado) {
            return;
        }
        $this->bloqueado = true;

        try {
        $resultado = CertificacionServicios::guardarLicencia($this->idPersonal, null, [
            'fechaInicio' => '',
            'fechaFin' => '',
            'parcial' => '0',
        ]);
            if (! $resultado['ok']) {
                $this->dispatch('se-swal-error', mensaje: $resultado['error'] ?? 'No se pudo insertar la licencia.');

                return;
            }
            $this->cargarFilas();
        } finally {
            $this->bloqueado = false;
        }
    }

    public function guardarLicenciaFila(int $id): void
    {
        $this->assertPermiso();
        if ($this->bloqueado || $id < 1) {
            return;
        }

        $fila = $this->licencias[$id] ?? null;
        if (! is_array($fila) || (int) ($fila['id'] ?? 0) !== $id) {
            return;
        }

        $this->validate([
            "licencias.{$id}.fechaInicio" => ['nullable', 'date'],
            "licencias.{$id}.fechaFin" => ['nullable', 'date'],
            "licencias.{$id}.parcial" => ['nullable', 'in:0,1'],
        ], [], [
            "licencias.{$id}.fechaInicio" => 'fecha inicio',
            "licencias.{$id}.fechaFin" => 'fecha fin',
            "licencias.{$id}.parcial" => 'parcial',
        ]);

        $resultado = CertificacionServicios::guardarLicencia($this->idPersonal, $id, $fila);
        if (! $resultado['ok']) {
            $this->dispatch('se-swal-error', mensaje: $resultado['error'] ?? 'No se pudo guardar la licencia.');
            $this->cargarFilas();
        }
    }

    public function eliminarLicencia(int $id): void
    {
        $this->assertPermiso();
        if ($this->bloqueado || $id < 1) {
            return;
        }
        $this->bloqueado = true;

        try {
            CertificacionServicios::eliminarLicencia($this->idPersonal, $id);
            unset($this->licencias[$id]);
            $this->cargarFilas();
        } finally {
            $this->bloqueado = false;
        }
    }

    public function abrirModalImprimir(): void
    {
        $this->assertPermiso();
        if ($this->fechaEmision === '') {
            $this->fechaEmision = Carbon::today()->format('Y-m-d');
        }
        $this->resetValidation();
        $this->modalImprimir = true;
    }

    public function cerrarModalImprimir(): void
    {
        $this->modalImprimir = false;
    }

    public function emitirPdf(): void
    {
        $this->assertPermiso();

        $validated = $this->validate([
            'fechaEmision' => ['required', 'date'],
            'paraPresentar' => ['nullable', 'string', 'max:300'],
        ], [], [
            'fechaEmision' => 'fecha de emisión',
            'paraPresentar' => 'presentar ante',
        ]);

        $key = 'cert-servicios-pdf:'.(auth()->id() ?? 'guest').':'.$this->idPersonal;
        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->dispatch('se-swal-error', mensaje: 'Demasiadas solicitudes. Intente nuevamente en breve.');

            return;
        }
        RateLimiter::hit($key, 60);

        try {
            CertificacionServicios::armarDatosPdf(
                $this->idPersonal,
                $validated['fechaEmision'],
                (string) ($validated['paraPresentar'] ?? '')
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'No se puede emitir el PDF.';
            $this->dispatch('se-swal-error', mensaje: $msg);

            return;
        }

        $this->modalImprimir = false;
        $this->dispatch(
            'abrir-pdf-post',
            ...CertificacionServicios::pdfPost(
                $this->idPersonal,
                $validated['fechaEmision'],
                (string) ($validated['paraPresentar'] ?? '')
            )
        );
    }

    /**
     * @return array{anios: int, meses: int, dias: int}
     */
    public function duracionServicio(int $id): array
    {
        $fila = $this->servicios[$id] ?? null;
        if (! is_array($fila) || ($fila['fechaAlta'] ?? '') === '') {
            return ['anios' => 0, 'meses' => 0, 'dias' => 0];
        }
        $fin = ($fila['fechaBaja'] ?? '') !== ''
            ? $fila['fechaBaja']
            : $this->fechaReferenciaCalculo();

        return AntiguedadServiciosCalculator::diffYmd($fila['fechaAlta'], $fin);
    }

    /**
     * @return array{anios: int, meses: int, dias: int}
     */
    public function duracionLicencia(int $id): array
    {
        $fila = $this->licencias[$id] ?? null;
        if (! is_array($fila) || ($fila['fechaInicio'] ?? '') === '' || ($fila['fechaFin'] ?? '') === '') {
            return ['anios' => 0, 'meses' => 0, 'dias' => 0];
        }

        return AntiguedadServiciosCalculator::diffYmd($fila['fechaInicio'], $fila['fechaFin']);
    }

    /**
     * @return array{
     *     subtotal: array{anios: int, meses: int, dias: int},
     *     licencias: array{anios: int, meses: int, dias: int},
     *     antiguedad: array{ok: bool, anios: int, meses: int, dias: int}
     * }
     */
    public function resumenAntiguedad(): array
    {
        $ref = $this->fechaReferenciaCalculo();
        $calc = AntiguedadServiciosCalculator::calcular(
            array_map(static fn (array $s): array => [
                'fechaAlta' => $s['fechaAlta'] ?? '',
                'fechaBaja' => ($s['fechaBaja'] ?? '') !== '' ? $s['fechaBaja'] : null,
            ], array_values($this->servicios)),
            array_map(static fn (array $l): array => [
                'fechaInicio' => $l['fechaInicio'] ?? '',
                'fechaFin' => $l['fechaFin'] ?? '',
                'parcial' => $l['parcial'] ?? '',
            ], array_values($this->licencias)),
            $ref
        );

        return [
            'subtotal' => $calc['subtotal'],
            'licencias' => $calc['descuentoLicencias'],
            'antiguedad' => $calc['antiguedad'],
        ];
    }

    private function fechaReferenciaCalculo(): string
    {
        if ($this->fechaEmision !== '') {
            return $this->fechaEmision;
        }

        return Carbon::today()->format('Y-m-d');
    }

    public function render()
    {
        return view('livewire.docentes.certificacion-servicios.form', [
            'serviciosVisibles' => $this->filtrarServicios(),
            'licenciasVisibles' => $this->filtrarLicencias(),
            'resumen' => $this->resumenAntiguedad(),
            'serviciosGridKey' => implode('-', array_map('strval', array_keys($this->servicios))),
            'licenciasGridKey' => implode('-', array_map('strval', array_keys($this->licencias))),
        ])->layout(layoutMenuStaff(), ['pageTitle' => 'Certificación de servicios']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filtrarServicios(): array
    {
        $q = mb_strtolower(trim($this->buscarServicios));
        $out = [];
        foreach ($this->servicios as $id => $fila) {
            if ($q !== '') {
                $hay = mb_strtolower(
                    ($fila['cargo'] ?? '').' '.($fila['titularSuplente'] ?? '').' '.($fila['nroResolucion'] ?? '')
                );
                if (! str_contains($hay, $q)) {
                    continue;
                }
            }
            $out[(int) $id] = $fila;
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filtrarLicencias(): array
    {
        $q = mb_strtolower(trim($this->buscarLicencias));
        $out = [];
        foreach ($this->licencias as $id => $fila) {
            if ($q !== '') {
                $parcialTxt = match ((string) ($fila['parcial'] ?? '')) {
                    '1' => 'si',
                    '0' => 'no',
                    default => '',
                };
                $hay = mb_strtolower(
                    ($fila['fechaInicio'] ?? '').' '.($fila['fechaFin'] ?? '').' '.$parcialTxt
                );
                if (! str_contains($hay, $q)) {
                    continue;
                }
            }
            $out[(int) $id] = $fila;
        }

        return $out;
    }

    private function assertPermiso(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::CERTIFICACION_SERVICIOS), 403, 'Sin permiso para certificación de servicios.');
    }
}
