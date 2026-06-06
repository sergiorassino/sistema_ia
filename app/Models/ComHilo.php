<?php

namespace App\Models;

use App\Models\Curso;
use App\Models\Legajo;
use App\Models\ComMensajeDestinatario;
use Illuminate\Database\Eloquent\Model;

class ComHilo extends Model
{
    protected $table = 'com_hilos';
    public $timestamps = false;

    protected $fillable = [
        'asunto', 'cuerpo_inicial_id', 'scope',
        'id_legajo', 'id_curso', 'cursos_envio', 'id_nivel', 'id_terlec',
        'creado_por_tipo', 'creado_por_id', 'creado_por_rol',
        'estado', 'familia_puede_responder', 'docentes_permite_respuestas', 'ultimo_mensaje_at',
    ];

    protected $casts = [
        'cursos_envio'            => 'array',
        'familia_puede_responder' => 'boolean',
        'ultimo_mensaje_at'       => 'datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public function mensajes()
    {
        return $this->hasMany(ComMensaje::class, 'id_hilo')->orderBy('created_at');
    }

    public function destinatarios()
    {
        return $this->hasMany(ComMensajeDestinatario::class, 'id_hilo');
    }

    public function legajo()
    {
        return $this->belongsTo(Legajo::class, 'id_legajo');
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'id_curso', 'Id');
    }

    /**
     * Comunicación interna entre docentes (scope explícito o inferido cuando `scope` quedó vacío en BD
     * pero el primer mensaje solo tiene destinatarios tipo profesor).
     */
    public static function inferirEsComunicacionInternaDocentesDesdeDatos(?string $scope, int $idMensajeInicial): bool
    {
        $sc = trim((string) ($scope ?? ''));
        if ($sc === 'docentes') {
            return true;
        }
        if ($sc !== '') {
            return false;
        }
        if ($idMensajeInicial <= 0) {
            return false;
        }

        return static::mensajeTieneSoloDestinatariosDocentes($idMensajeInicial);
    }

    private static function mensajeTieneSoloDestinatariosDocentes(int $idMensaje): bool
    {
        $hayProf = ComMensajeDestinatario::query()
            ->where('id_mensaje', $idMensaje)
            ->where('tipo_destinatario', 'profesor')
            ->exists();
        if (! $hayProf) {
            return false;
        }

        return ! ComMensajeDestinatario::query()
            ->where('id_mensaje', $idMensaje)
            ->where('tipo_destinatario', 'familia')
            ->exists();
    }

    public function esComunicacionInternaDocentes(): bool
    {
        return static::inferirEsComunicacionInternaDocentesDesdeDatos(
            $this->scope !== null ? (string) $this->scope : null,
            (int) ($this->cuerpo_inicial_id ?? 0)
        );
    }

    /** Etiqueta legible del scope */
    public function scopeLabel(): string
    {
        if ($this->esComunicacionInternaDocentes()) {
            return 'Docentes';
        }

        $sc = (string) ($this->scope ?? '');

        return match ($sc) {
            'alumno'         => 'Un alumno',
            'varios_alumnos' => 'Varios alumnos',
            'curso'          => 'Un curso',
            'varios_cursos'  => 'Varios cursos',
            'colegio'        => 'Todo el colegio',
            'docentes'       => 'Docentes',
            default          => $sc !== '' ? ucfirst($sc) : '—',
        };
    }

    /** Etiqueta del estado */
    public function estadoLabel(): string
    {
        return match($this->estado) {
            'abierto' => 'Abierto',
            'cerrado' => 'Cerrado',
            default   => ucfirst((string) $this->estado),
        };
    }

    /**
     * Si el hilo fue iniciado por la escuela y se marcó como solo informativo,
     * la familia no puede enviar respuestas en el cuaderno.
     */
    public function familiaPuedeEnviarRespuestas(): bool
    {
        if ($this->creado_por_tipo !== 'profesor') {
            return true;
        }

        return (bool) $this->familia_puede_responder;
    }

    /**
     * Hilos internos (scope docentes): si los destinatarios pueden responder en el hilo.
     *
     * Usa el atributo `docentes_permite_respuestas`: NULL (columna ausente o sin valor) = permitir,
     * para no heredar el falso histórico en `familia_puede_responder`. Solo bloquea con valor explícito 0/false.
     */
    public function docentesDestinatariosPuedenResponder(): bool
    {
        if (! $this->esComunicacionInternaDocentes()) {
            return true;
        }
        if (! array_key_exists('docentes_permite_respuestas', $this->getAttributes())) {
            return true;
        }
        $raw = $this->getAttributes()['docentes_permite_respuestas'];
        if ($raw === null) {
            return true;
        }
        if ($raw === false || $raw === 0 || $raw === '0' || $raw === 0.0) {
            return false;
        }

        return true;
    }

    public function esComunicadoInformativoEscuela(): bool
    {
        if ($this->esComunicacionInternaDocentes()) {
            return false;
        }

        return $this->creado_por_tipo === 'profesor' && ! $this->familiaPuedeEnviarRespuestas();
    }
}
