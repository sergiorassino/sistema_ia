<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnaliticoDato extends Model
{
    protected $table = 'analiticodatos';

    public $timestamps = false;

    protected $fillable = [
        'idLegajos',
        'analCohorte',
        'analObservaciones',
        'analParaCompletar',
        'analValidez',
        'serie',
        'numero',
        'analLibroFolio',
        'analFechaEmision',
        'analParaPre',
    ];

    protected $casts = [
        'analFechaEmision' => 'date',
    ];

    public function legajo(): BelongsTo
    {
        return $this->belongsTo(Legajo::class, 'idLegajos');
    }

    /**
     * Registro efectivo del legajo.
     *
     * Legacy (ScriptCase) a veces inserta filas vacías nuevas en lugar de
     * actualizar: prioriza la más reciente con contenido; si ninguna tiene
     * datos, la más reciente.
     */
    public static function paraLegajo(int $idLegajos): ?self
    {
        if ($idLegajos < 1) {
            return null;
        }

        $rows = static::query()
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows->first(fn (self $row) => $row->tieneContenido()) ?? $rows->first();
    }

    public function tieneContenido(): bool
    {
        $cohorte = trim((string) ($this->analCohorte ?? ''));
        if ($cohorte !== '' && $cohorte !== '0') {
            return true;
        }

        foreach (['analObservaciones', 'analParaCompletar', 'analValidez', 'serie', 'numero', 'analLibroFolio', 'analParaPre'] as $campo) {
            if (trim((string) ($this->{$campo} ?? '')) !== '') {
                return true;
            }
        }

        return $this->analFechaEmision !== null;
    }
}
