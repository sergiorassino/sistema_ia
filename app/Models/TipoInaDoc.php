<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoInaDoc extends Model
{
    protected $table = 'tipoinadoc';

    public $timestamps = false;

    protected $fillable = [
        'motivo',
        'ord',
    ];

    protected $casts = [
        'ord' => 'integer',
    ];

    public function inasistencias(): HasMany
    {
        return $this->hasMany(InasDocente::class, 'idTipoInaDoc');
    }
}
