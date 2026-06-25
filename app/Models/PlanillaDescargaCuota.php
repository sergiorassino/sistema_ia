<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanillaDescargaCuota extends Model
{
    protected $table = 'planillasdescargacuotas';

    public $timestamps = false;

    protected $fillable = [
        'nroPlanilla',
        'fecha',
        'desde',
        'hasta',
        'canalPago',
        'nombreArchivo',
        'impactado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'desde' => 'date',
        'hasta' => 'date',
        'impactado' => 'boolean',
    ];

    public function rendiciones(): HasMany
    {
        return $this->hasMany(RendicionRoela::class, 'nroPlanilla', 'nroPlanilla');
    }
}
