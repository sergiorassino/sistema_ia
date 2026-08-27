<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtTipoRegistro extends Model
{
    protected $table = 'ext_tipo_registro';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'id',
        'nombre',
    ];

    public function actividades(): HasMany
    {
        return $this->hasMany(ExtActividad::class, 'id_tipo_registro');
    }
}
