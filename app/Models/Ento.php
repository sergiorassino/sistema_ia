<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ento extends Model
{
    protected $table = 'ento';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'idNivel',

        // Institucional (legacy)
        'insti',
        'cue',
        'ee',
        'cuit',
        'categoria',
        'direccion',
        'localidad',
        'departamento',
        'provincia',
        'telefono',
        'mail',
        'replegal',
        'siroIniPrim',
        'siroSecu',

        // Logo (nuevo)
        'logo_path',
        'logo_original_name',

        // Matrícula web — nombre del PDF vigente por documento (legacy)
        'documAcept1',
        'documAcept2',
        'documAcept3',
        'documAcept4',
    ];
}

