<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Datos comunes del certificado de jardín (un registro, `id` = 1).
 */
class CertificadoJardin extends Model
{
    protected $table = 'certificadojardin';

    public $timestamps = false;

    protected $fillable = [
        'serie',
        'mesApro',
        'anoApro',
        'mesAprobacion',
        'anoAprobacion',
        'diaEmision',
        'mesEmision',
        'anoEmision',
        'ppi',
    ];
}
