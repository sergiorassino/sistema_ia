<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Indicadores de evaluación por materia (nivel inicial).
 *
 * Esquema habitual en este proyecto: `id`, `idMaterias`, `indicador1`, `indicador2` (texto multilínea por período).
 */
class Indicador extends Model
{
    protected $table = 'indicadores';

    public $timestamps = false;

    protected $fillable = [
        'idMaterias',
        'indicador1',
        'indicador2',
        'indicador3',
        'etapa',
        'ord',
        'indicador',
    ];

    protected function casts(): array
    {
        return [
            'idMaterias' => 'integer',
            'etapa' => 'integer',
            'ord' => 'integer',
        ];
    }
}
