<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtActividad extends Model
{
    protected $table = 'ext_actividades';

    protected $fillable = [
        'id_tipo_registro',
        'id_nivel',
        'id_terlec',
        'id_profesor_proponente',
        'nombre',
        'lugar',
        'horario',
        'descripcion',
        'evaluacion',
        'tipo_grupo',
        'estado',
        'aprobado_por',
        'aprobado_at',
        'comunicado_at',
    ];

    protected $casts = [
        'aprobado_at' => 'datetime',
        'comunicado_at' => 'datetime',
    ];

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_APROBADO = 'aprobado';

    public const TIPO_GRUPO_CURSOS = 'cursos';

    public const TIPO_GRUPO_ALUMNOS = 'alumnos';

    public const ROL_A_CARGO = 'a_cargo';

    public const ROL_OTRO = 'otro';

    public function tipoRegistro(): BelongsTo
    {
        return $this->belongsTo(ExtTipoRegistro::class, 'id_tipo_registro');
    }

    public function proponente(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'id_profesor_proponente');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'aprobado_por');
    }

    public function fechas(): HasMany
    {
        return $this->hasMany(ExtFecha::class, 'id_actividad')->orderBy('fecha')->orderBy('hora_inicio');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(ExtActividadCurso::class, 'id_actividad');
    }

    public function alumnos(): HasMany
    {
        return $this->hasMany(ExtActividadAlumno::class, 'id_actividad');
    }

    public function docentes(): HasMany
    {
        return $this->hasMany(ExtActividadDocente::class, 'id_actividad');
    }

    public function estaAprobada(): bool
    {
        return $this->estado === self::ESTADO_APROBADO;
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }
}
