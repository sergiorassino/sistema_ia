<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GabineteTipo extends Model
{
    protected $table = 'gabinetetipo';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'tipo',
    ];

    public function registros()
    {
        return $this->hasMany(Gabinete::class, 'idTipoSancion');
    }
}
