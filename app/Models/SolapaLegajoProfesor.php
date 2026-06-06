<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolapaLegajoProfesor extends Model
{
    protected $table = 'solapas_legajo_profesor';

    public $timestamps = false;

    protected $fillable = ['nombre', 'slug', 'orden'];

    protected $casts = ['orden' => 'integer'];

    public function campos(): HasMany
    {
        return $this->hasMany(CampoProfesor::class, 'solapa_legajo_profesor_id');
    }
}
