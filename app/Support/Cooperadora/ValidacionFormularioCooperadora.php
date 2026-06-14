<?php

namespace App\Support\Cooperadora;

use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class ValidacionFormularioCooperadora
{
    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $atributos
     * @return array<string, mixed>
     */
    public static function validar(Component $component, array $rules, array $atributos): array
    {
        $mensajes = [
            'required' => 'Debe completar :attribute.',
            'integer' => 'Debe seleccionar un :attribute válido.',
            'numeric' => 'Debe ingresar un :attribute válido.',
            'min' => 'El campo :attribute no cumple el valor mínimo.',
            'in' => 'Debe seleccionar un :attribute válido.',
            'date' => 'Debe ingresar una :attribute válida.',
            'string' => 'Debe completar :attribute.',
        ];

        try {
            return $component->validate($rules, $mensajes, $atributos);
        } catch (ValidationException $e) {
            $etiquetas = collect($e->validator->errors()->keys())
                ->map(fn (string $campo) => $atributos[$campo] ?? $campo)
                ->unique()
                ->values();

            $detalle = $etiquetas->map(fn (string $etiqueta) => '• '.$etiqueta)->implode("\n");
            $mensaje = "Faltan completar los siguientes campos:\n\n".$detalle;

            $component->dispatch('se-swal-aviso', mensaje: $mensaje, titulo: 'Datos incompletos');

            throw $e;
        }
    }
}
