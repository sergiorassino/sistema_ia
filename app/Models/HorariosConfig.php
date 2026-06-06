<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorariosConfig extends Model
{
    protected $table = 'horarios_config';

    protected $primaryKey = 'idNivel';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'idNivel',
        'turnos_activos',
        'dias_activos',
    ];
}
