<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SancionTipo extends Model
{
    protected $table = 'sanciontipo';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'textoNotifPadres',
        'idProfesorNotif',
        'refuerzoMail',
        'permiteNotifPadres',
        'enResumenComunicado',
    ];

    protected $casts = [
        'refuerzoMail'         => 'boolean',
        'permiteNotifPadres'   => 'boolean',
        'enResumenComunicado'  => 'boolean',
    ];

    public function profesorNotif()
    {
        return $this->belongsTo(Profesor::class, 'idProfesorNotif');
    }

    /** Si el tipo emite comunicado PDF (botón y ruta). Sin columna, se mantiene el comportamiento anterior. */
    public function permiteComunicadoPdf(): bool
    {
        if (! Schema::hasColumn($this->getTable(), 'enResumenComunicado')) {
            return true;
        }

        return (bool) $this->enResumenComunicado;
    }
}

