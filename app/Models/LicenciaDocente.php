<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Licencia del docente (tabla legacy `licencias`).
 * Años/meses/días se calculan en runtime; no se persisten.
 * `parcial`: 0 = No (descuenta), 1 = Sí (no descuenta).
 */
class LicenciaDocente extends Model
{
    protected $table = 'licencias';

    protected $primaryKey = 'idlicencias';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'idPersonal',
        'fechaInicio',
        'fechaFin',
        'parcial',
    ];
}
