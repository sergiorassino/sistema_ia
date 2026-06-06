<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de cursos modelo para inscripción de aspirantes (sin sección).
 *
 * Una fila por nivel y nombre. Ej.: "Sala de 4" (inicial), "Primero" (primario / secundario).
 * Se carga en la pantalla ABM "Cursos modelo (Aspirantes)" y se elige por instancia (aspicursos).
 */
class AspiCursoModelo extends Model
{
    protected $table = 'aspicursosmodelo';

    public $timestamps = false;

    protected $fillable = [
        'idNivel',
        'nombre',
        'orden',
        'activo',
    ];

    protected $casts = [
        'idNivel' => 'integer',
        'orden'   => 'integer',
        'activo'  => 'boolean',
    ];

    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'idNivel');
    }
}
