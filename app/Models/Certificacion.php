<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Período de servicio docente (tabla legacy `certificacion`).
 * Años/meses/días se calculan en runtime; no se persisten.
 */
class Certificacion extends Model
{
    protected $table = 'certificacion';

    protected $primaryKey = 'idcertificacion';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'idpersonal',
        'cargo',
        'titularSuplente',
        'nroResolucion',
        'fechaAlta',
        'FechaBaja', // legacy: F mayúscula (no fechaBaja)
        'hsCatedra',
    ];
}
