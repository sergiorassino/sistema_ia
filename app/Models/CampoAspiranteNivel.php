<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampoAspiranteNivel extends Model
{
    protected $table = 'campos_aspirantes_nivel';

    public $timestamps = false;

    protected $fillable = [
        'campo_aspirante_id',
        'idNivel',
        'visible',
        'obligatorio',
        'etiqueta',
        'opciones',
        'ayuda',
    ];

    protected $casts = [
        'campo_aspirante_id' => 'integer',
        'idNivel'            => 'integer',
        'visible'            => 'boolean',
        'obligatorio'        => 'boolean',
    ];

    public function campo()
    {
        return $this->belongsTo(CampoAspirante::class, 'campo_aspirante_id');
    }
}

