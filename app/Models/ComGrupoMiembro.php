<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComGrupoMiembro extends Model
{
    public const TIPO_LEGAJO = 'legajo';

    public const TIPO_PROFESOR = 'profesor';

    protected $table = 'com_grupos_miembros';

    public $timestamps = false;

    protected $fillable = [
        'id_grupo',
        'tipo_miembro',
        'id_legajo',
        'id_profesor',
        'nombre_snapshot',
    ];

    protected $casts = [
        'id_grupo'    => 'integer',
        'id_legajo'   => 'integer',
        'id_profesor' => 'integer',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(ComGrupo::class, 'id_grupo');
    }

    public function idDestino(): int
    {
        if ($this->tipo_miembro === self::TIPO_PROFESOR) {
            return (int) $this->id_profesor;
        }

        return (int) $this->id_legajo;
    }
}
