<?php

namespace App\Models;

use App\Models\Legajo;
use App\Models\Profesor;
use Illuminate\Database\Eloquent\Model;

class ComMensajeDestinatario extends Model
{
    protected $table = 'com_mensajes_destinatarios';
    public $timestamps = false;

    protected $fillable = [
        'id_mensaje', 'id_hilo', 'tipo_destinatario',
        'id_profesor', 'id_legajo', 'rol_destinatario',
        'nombre_snapshot', 'dni_snapshot',
        'leido_at', 'respondido_at', 'id_mensaje_respuesta',
    ];

    protected $casts = [
        'leido_at'      => 'datetime',
        'respondido_at' => 'datetime',
    ];

    public function mensaje()
    {
        return $this->belongsTo(ComMensaje::class, 'id_mensaje');
    }

    public function hilo()
    {
        return $this->belongsTo(ComHilo::class, 'id_hilo');
    }

    public function envios()
    {
        return $this->hasMany(ComMensajeEnvio::class, 'id_mensaje_destinatario');
    }

    public function legajo()
    {
        return $this->belongsTo(Legajo::class, 'id_legajo');
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'id_profesor');
    }

    /** Derivar estado visual del hilo para este destinatario */
    public function estadoHilo(): string
    {
        if ($this->respondido_at !== null) {
            return 'respondido';
        }
        if ($this->leido_at !== null) {
            return 'leido';
        }
        return 'no_leido';
    }
}
