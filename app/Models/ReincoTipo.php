<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReincoTipo extends Model
{
    /** Catálogo legacy único de situaciones TEA (5 tipos). */
    protected $table = 'reinco2025_tipo';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'orden',
        'tipo',
    ];

    public static function onTable(string $table): self
    {
        $model = new self;
        $model->setTable($table);

        return $model;
    }

    public function etiqueta(): string
    {
        $tipo = trim((string) ($this->tipo ?? ''));

        return $tipo !== '' ? $tipo : '—';
    }
}
