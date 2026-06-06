<?php

namespace App\Livewire\Concerns;

trait DetalleLecturaDestinatariosModal
{
    public bool $modalLecturaAbierto = false;

    /** @var list<array{nombre:string,tipo_etiqueta:string,leido:bool,fecha_lectura:string}> */
    public array $modalLecturaFilas = [];

    public string $modalLecturaTitulo = '';

    public string $modalLecturaResumen = '';

    public function cerrarDetalleLectura(): void
    {
        $this->modalLecturaAbierto = false;
        $this->modalLecturaFilas   = [];
        $this->modalLecturaTitulo  = '';
        $this->modalLecturaResumen = '';
    }

    /**
     * @param  array{resumen:array{etiqueta:string},filas:list<array{nombre:string,tipo_etiqueta:string,leido:bool,fecha_lectura:string}>,titulo:string}|null  $payload
     */
    protected function mostrarDetalleLectura(?array $payload): void
    {
        if ($payload === null || ($payload['filas'] ?? []) === []) {
            return;
        }

        $this->modalLecturaFilas   = $payload['filas'];
        $this->modalLecturaTitulo  = (string) ($payload['titulo'] ?? 'Confirmación de lectura');
        $this->modalLecturaResumen = (string) ($payload['resumen']['etiqueta'] ?? '');
        $this->modalLecturaAbierto = true;
    }
}
