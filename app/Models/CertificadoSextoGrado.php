<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Datos comunes del certificado de sexto grado (un registro, `id` = 1).
 */
class CertificadoSextoGrado extends Model
{
    protected $table = 'certificadosextogrado';

    public $timestamps = false;

    protected $fillable = [
        'serie',
        'mesApro',
        'anoApro',
        'diaEmision',
        'mesEmision',
        'anoEmision',
        'ppi',
    ];
}
