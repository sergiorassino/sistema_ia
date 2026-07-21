<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReincoRegistro extends Model
{
    /** Tabla legacy única; el nombre no implica el ciclo lectivo activo. */
    protected $table = 'reinco2025';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idMatricula',
        'idReinco_tipo',
        'fecha',
        'obs',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public static function onTable(string $table): self
    {
        $model = new self;
        $model->setTable($table);

        return $model;
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'idMatricula');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(ReincoTipo::class, 'idReinco_tipo', 'id');
    }

    public function etiquetaTipo(): string
    {
        $etiqueta = $this->tipo?->etiqueta();
        if ($etiqueta !== null && $etiqueta !== '—') {
            return $etiqueta;
        }

        $id = (int) ($this->idReinco_tipo ?? 0);

        return $id > 0 ? 'Tipo #'.$id : '—';
    }
}
