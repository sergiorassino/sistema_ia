<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComGrupo extends Model
{
    protected $table = 'com_grupos';

    public $timestamps = false;

    public const TIPO_MIXTO = 'mixto';

    protected $fillable = [
        'nombre',
        'id_profesor',
        'id_nivel',
        'tipo_destinatario',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id_profesor'        => 'integer',
        'id_nivel'           => 'integer',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function miembros(): HasMany
    {
        return $this->hasMany(ComGrupoMiembro::class, 'id_grupo');
    }
}
