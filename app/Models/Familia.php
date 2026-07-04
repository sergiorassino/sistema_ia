<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Familia extends Model
{
    protected $table = 'familias';
    public $timestamps = false;
    protected $fillable = [
        'apellido', 'responsable', 'dniResp', 'email',
    ];

    public function legajos()
    {
        return $this->hasMany(Legajo::class, 'idFamilias');
    }
}
