<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapacitacionDocente extends Model
{
    protected $table = 'capacitacion_docente';

    protected $fillable = [
        'id_profesor',
        'id_nivel',
        'fecha',
        'nombre',
        'entidad_otorgante',
        'duracion',
        'modalidad',
        'certificado_archivo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'id_profesor' => 'integer',
        'id_nivel' => 'integer',
    ];

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'id_profesor');
    }

    public function etiquetaModalidad(): string
    {
        return match ((string) $this->modalidad) {
            'presencial' => 'Presencial',
            'virtual' => 'Virtual',
            'hibrida' => 'Híbrida',
            default => (string) $this->modalidad,
        };
    }

    public function tieneCertificado(): bool
    {
        return trim((string) ($this->certificado_archivo ?? '')) !== '';
    }
}
