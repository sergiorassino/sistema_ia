<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    protected $table = 'cursos';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = [
        'orden', 'idCurPlan', 'idTerlec', 'idNivel', 'cursec', 'c', 's', 'idTurnoClase',
    ];

    public function turnoClase()
    {
        return $this->belongsTo(TurnoClase::class, 'idTurnoClase', 'id');
    }

    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'idNivel');
    }

    public function terlec()
    {
        return $this->belongsTo(Terlec::class, 'idTerlec');
    }

    public function curplan()
    {
        return $this->belongsTo(Curplan::class, 'idCurPlan');
    }

    /**
     * Texto para listados / PDF: prioriza sección (`cursec`), si no hay datos del plan y turno.
     */
    public function nombreParaListado(): string
    {
        $sec = trim((string) $this->cursec);
        if ($sec !== '') {
            return $sec;
        }

        $nombrePlan = trim((string) ($this->curplan?->curPlanCurso ?? ''));

        $turnoLabel = null;
        if ((int) ($this->idTurnoClase ?? 0) > 0) {
            if (! $this->relationLoaded('turnoClase')) {
                $this->load('turnoClase');
            }
            $turnoLabel = trim((string) ($this->turnoClase?->nombre ?? ''));
        }
        if ($turnoLabel === '') {
            $turnoLabel = null;
        }

        $extras = collect([$turnoLabel, $this->c, $this->s])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        if ($nombrePlan !== '') {
            return $extras->isNotEmpty()
                ? $nombrePlan.' · '.$extras->implode(' · ')
                : $nombrePlan;
        }

        if ($extras->isNotEmpty()) {
            return $extras->implode(' · ');
        }

        return 'Curso';
    }

    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'idCursos', 'Id');
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class, 'idCursos', 'Id');
    }
}
