<?php

namespace App\Livewire\Alumnos\Comunicaciones;

use Livewire\Component;
use App\Models\ComPreferencia;

class PreferenciasMedios extends Component
{
    /** @var list<string> */
    public array $vinculosContacto = [];

    public bool $push     = true;
    public bool $email    = true;
    public bool $whatsapp = true;

    /** Orden de opciones: Padre, Madre, Tutor/a */
    public array $vinculos = [
        'padre' => 'Padre',
        'madre' => 'Madre',
        'tutor' => 'Tutor/a',
    ];

    public function mount(): void
    {
        $idLegajo = (int) studentCtx()->idLegajo;
        $pref     = ComPreferencia::paraLegajo($idLegajo);

        $this->push     = (bool) $pref->push;
        $this->email    = (bool) $pref->email;
        $this->whatsapp = (bool) $pref->whatsapp;

        $json = $pref->vinculos_contacto;
        if (is_array($json) && $json !== []) {
            $this->vinculosContacto = $this->normalizarVinculosSeleccion($json);
        } else {
            $single = (string) ($pref->vinculo_contacto ?? '');
            $this->vinculosContacto = in_array($single, ['padre', 'madre', 'tutor'], true)
                ? [$single]
                : [];
        }
    }

    public function guardar(): void
    {
        $rules = [
            'vinculosContacto'   => ['nullable', 'array'],
            'vinculosContacto.*' => ['in:padre,madre,tutor'],
        ];
        if (config('comunicaciones.alumno_ui_medios_preferencia')) {
            $rules['push']     = 'boolean';
            $rules['email']    = 'boolean';
            $rules['whatsapp'] = 'boolean';
        }
        $this->validate($rules);

        $idLegajo = (int) studentCtx()->idLegajo;

        $normalizados = $this->normalizarVinculosSeleccion($this->vinculosContacto);

        $attrs = [
            'vinculos_contacto' => $normalizados === [] ? null : $normalizados,
            'vinculo_contacto'  => null,
            'updated_at'        => now(),
        ];
        if (config('comunicaciones.alumno_ui_medios_preferencia')) {
            $attrs['push']     = $this->push;
            $attrs['email']    = $this->email;
            $attrs['whatsapp'] = $this->whatsapp;
        }

        ComPreferencia::updateOrCreate(
            ['tipo_usuario' => 'familia', 'id_legajo' => $idLegajo],
            $attrs
        );

        $this->vinculosContacto = $normalizados;

        session()->flash('success', 'Preferencias guardadas correctamente.');
    }

    /**
     * @param  array<int, mixed>  $entrada
     * @return list<string>
     */
    private function normalizarVinculosSeleccion(array $entrada): array
    {
        $ordenUi = ['padre', 'madre', 'tutor'];
        $out     = [];
        foreach ($ordenUi as $clave) {
            if (in_array($clave, $entrada, true) && ! in_array($clave, $out, true)) {
                $out[] = $clave;
            }
        }

        return $out;
    }

    public function render()
    {
        return view('comunicaciones::livewire.alumnos.comunicaciones.preferencias-medios')
            ->layout('layouts.alumno', ['pageTitle' => 'Preferencias de comunicación']);
    }
}
