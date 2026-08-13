<?php

namespace App\Livewire\Alumnos;

use App\Support\Alumnos\ActualizacionDatosPersonalesSanFranciscoAsis;
use App\Support\MatriculaWeb\MatriculaWebDocumentos;
use Livewire\Component;

class AceptacionDocumentoFamilia extends Component
{
    public string $tipo;

    public function mount(string $tipo): void
    {
        abort_unless(tenantAutogestionActualizacionDatosHabilitada(), 404);
        abort_unless(tenantAutogestionActualizacionDatosRequiereDocumentos(), 404);
        abort_unless(MatriculaWebDocumentos::claveValida($tipo), 404);

        $ctx = ActualizacionDatosPersonalesSanFranciscoAsis::contexto();
        if ($ctx === null) {
            abort(404);
        }

        if (ActualizacionDatosPersonalesSanFranciscoAsis::estaBloqueado($ctx['matricula'])) {
            $this->redirectRoute('alumnos.actualizacion-datos', navigate: true);

            return;
        }

        $this->tipo = $tipo;

        if (! ActualizacionDatosPersonalesSanFranciscoAsis::documentoDisponible($tipo)) {
            session()->flash('error', 'El documento no está disponible para su nivel. Contacte a la institución.');
            $this->redirectRoute('alumnos.actualizacion-datos', navigate: true);
        }
    }

    public function aceptar(): void
    {
        $ctx = ActualizacionDatosPersonalesSanFranciscoAsis::contexto();
        if ($ctx === null) {
            abort(404);
        }

        if (ActualizacionDatosPersonalesSanFranciscoAsis::estaBloqueado($ctx['matricula'])) {
            $this->redirectRoute('alumnos.actualizacion-datos', navigate: true);

            return;
        }

        ActualizacionDatosPersonalesSanFranciscoAsis::marcarAceptacion($ctx['matricula'], $this->tipo, true);

        session()->flash('success', 'Documento aceptado.');

        $this->redirectRoute('alumnos.actualizacion-datos', navigate: true);
    }

    public function render()
    {
        $def = MatriculaWebDocumentos::definicion($this->tipo);
        $pdfUrl = matriculaWebDocumentoUrl($this->tipo);

        return view('livewire.alumnos.aceptacion-documento-familia', [
            'def' => $def,
            'pdfUrl' => $pdfUrl,
            'textoCompromiso' => $this->tipo === MatriculaWebDocumentos::COMPROMISO
                ? ActualizacionDatosPersonalesSanFranciscoAsis::TEXTO_COMPROMISO_PARENTAL
                : null,
        ])->layout('layouts.alumno', ['pageTitle' => 'Aceptación — '.($def['titulo_corto'] ?? 'Documento')]);
    }
}
