<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolapaLegajo extends Model
{
    protected $table = 'solapas_legajo';

    public $timestamps = false;

    protected $fillable = ['nombre', 'slug', 'orden'];

    protected $casts = ['orden' => 'integer'];

    public function campos(): HasMany
    {
        return $this->hasMany(CampoLegajo::class, 'solapa_legajo_id');
    }
}
