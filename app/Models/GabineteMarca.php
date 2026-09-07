<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GabineteMarca extends Model
{
    protected $table = 'gabinetemarca';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'color',
    ];

    public function legajo()
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }
}
