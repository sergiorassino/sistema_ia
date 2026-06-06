<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';

    public $timestamps = false;

    protected $fillable = [
        'idNivel',
        'plan',
        'abrev',
    ];

    public function curplanes()
    {
        return $this->hasMany(Curplan::class, 'idPlan');
    }

    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'idNivel');
    }
}

