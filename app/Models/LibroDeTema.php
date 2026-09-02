<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibroDeTema extends Model
{
    protected $table = 'librodetemas';

    public $timestamps = false;

    protected $fillable = [
        'idMateria',
        'fecha',
        'claseNro',
        'unidad',
        'caracter',
        'temas',
        'actividades',
        'observaciones',
    ];

    protected $casts = [
        'idMateria' => 'integer',
        'fecha' => 'date',
        'claseNro' => 'integer',
        'unidad' => 'integer',
    ];

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'idMateria', 'id');
    }
}
