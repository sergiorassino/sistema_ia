<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Curso modelo habilitado para una instancia de registro de aspirantes.
 *
 * Esta tabla apunta a `aspicursosmodelo` (catálogo por nivel, sin sección).
 * Se mantiene `idCursos` en la tabla legacy por compatibilidad histórica,
 * pero el módulo nuevo solo usa `idCursoModelo`.
 */
class Aspicurso extends Model
{
    protected $table = 'aspicursos';

    public $timestamps = false;

    protected $fillable = [
        'idAspiento',
        'idCursoModelo',
        'idCursos',
        'idNivel',
        'activo',
        'cursoaspi',
        'habilitado',
    ];

    protected $casts = [
        'idAspiento'    => 'integer',
        'idCursoModelo' => 'integer',
        'idCursos'      => 'integer',
        'idNivel'       => 'integer',
        'activo'        => 'boolean',
        'habilitado'    => 'boolean',
    ];

    public function cursoModelo()
    {
        return $this->belongsTo(AspiCursoModelo::class, 'idCursoModelo');
    }

    public function instancia()
    {
        return $this->belongsTo(Aspiento::class, 'idAspiento');
    }
}
