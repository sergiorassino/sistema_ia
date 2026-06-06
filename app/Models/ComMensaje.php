<?php

namespace App\Models;

use App\Models\Legajo;
use App\Models\Profesor;
use Illuminate\Database\Eloquent\Model;

class ComMensaje extends Model
{
    protected $table = 'com_mensajes';
    public $timestamps = false;

    protected $fillable = [
        'id_hilo', 'id_mensaje_padre', 'tipo_remitente',
        'id_profesor', 'id_legajo', 'rol_remitente',
        'vinculo_familiar', 'nombre_remitente_snapshot', 'dni_remitente_snapshot',
        'contenido', 'fecha', 'hora',
    ];

    protected $casts = [
        'fecha'      => 'date',
        'created_at' => 'datetime',
    ];

    public function hilo()
    {
        return $this->belongsTo(ComHilo::class, 'id_hilo');
    }

    public function padre()
    {
        return $this->belongsTo(ComMensaje::class, 'id_mensaje_padre');
    }

    public function respuestas()
    {
        return $this->hasMany(ComMensaje::class, 'id_mensaje_padre')->orderBy('created_at');
    }

    public function destinatarios()
    {
        return $this->hasMany(ComMensajeDestinatario::class, 'id_mensaje');
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'id_profesor');
    }

    public function legajo()
    {
        return $this->belongsTo(Legajo::class, 'id_legajo');
    }

    /** Nombre para mostrar en la UI (snapshot) */
    public function nombreDisplay(): string
    {
        $nombre = trim((string) $this->nombre_remitente_snapshot);
        if ($nombre !== '') {
            return $nombre;
        }
        return $this->tipo_remitente === 'familia' ? 'Familia' : 'Personal escolar';
    }

    public static function etiquetasVinculo(): array
    {
        return [
            'madre'     => 'Madre',
            'padre'     => 'Padre',
            'tutor'     => 'Tutor/a',
            'resp_admin'=> 'Resp. Administrativo/a',
            'otro'      => 'Otro responsable',
        ];
    }

    public function vinculoLabel(): string
    {
        return static::etiquetasVinculo()[$this->vinculo_familiar ?? ''] ?? '';
    }

    /**
     * Estado de confirmación de lectura para mensajes enviados (destinatarios del mensaje).
     *
     * @return array{
     *   total:int,
     *   leidos:int,
     *   pendientes:int,
     *   estado:'sin_destinatarios'|'leido'|'parcial'|'no_leido',
     *   etiqueta:string,
     *   titulo:string
     * }
     */
    public function resumenLecturaDestinatarios(): array
    {
        $destinatarios = $this->relationLoaded('destinatarios')
            ? $this->destinatarios
            : $this->destinatarios()->get(['leido_at']);

        $total = $destinatarios->count();
        if ($total === 0) {
            return [
                'total'      => 0,
                'leidos'     => 0,
                'pendientes' => 0,
                'estado'     => 'sin_destinatarios',
                'etiqueta'   => '',
                'titulo'     => '',
            ];
        }

        $leidos     = $destinatarios->filter(fn ($d) => $d->leido_at !== null)->count();
        $pendientes = $total - $leidos;

        if ($pendientes === 0) {
            $estado = 'leido';
        } elseif ($leidos === 0) {
            $estado = 'no_leido';
        } else {
            $estado = 'parcial';
        }

        $sufijoClic = ' Clic para ver el detalle.';

        if ($total === 1) {
            $etiqueta = $estado === 'leido' ? 'Leído' : 'Sin leer';
            $titulo   = ($estado === 'leido'
                ? 'El destinatario abrió este mensaje.'
                : 'El destinatario aún no confirmó lectura.').$sufijoClic;
        } else {
            $etiqueta = match ($estado) {
                'leido'    => "Leído ({$leidos}/{$total})",
                'no_leido' => "Sin leer ({$pendientes}/{$total})",
                default    => "{$leidos}/{$total} leídos",
            };
            $titulo = (match ($estado) {
                'leido'    => "Los {$total} destinatarios confirmaron lectura.",
                'no_leido' => "Ninguno de los {$total} destinatarios confirmó lectura aún.",
                default    => "{$leidos} de {$total} destinatarios confirmaron lectura.",
            }).$sufijoClic;
        }

        return [
            'total'      => $total,
            'leidos'     => $leidos,
            'pendientes' => $pendientes,
            'estado'     => $estado,
            'etiqueta'   => $etiqueta,
            'titulo'     => $titulo,
        ];
    }

    /**
     * Filas para el modal de detalle (nombre + fecha de lectura).
     *
     * @return list<array{nombre:string,tipo_etiqueta:string,leido:bool,fecha_lectura:string}>
     */
    public function filasDetalleLecturaDestinatarios(): array
    {
        $destinatarios = $this->relationLoaded('destinatarios')
            ? $this->destinatarios
            : $this->destinatarios()->get([
                'nombre_snapshot', 'tipo_destinatario', 'leido_at',
            ]);

        $filas = [];
        foreach ($destinatarios as $d) {
            $nombre = trim((string) ($d->nombre_snapshot ?? ''));
            if ($nombre === '') {
                $nombre = $d->tipo_destinatario === 'familia' ? 'Familia' : 'Personal escolar';
            }

            $leido = $d->leido_at !== null;
            $filas[] = [
                'nombre'         => $nombre,
                'tipo_etiqueta'  => $d->tipo_destinatario === 'familia' ? 'Familia' : 'Personal',
                'leido'          => $leido,
                'fecha_lectura'  => $leido
                    ? $d->leido_at->format('d/m/Y H:i')
                    : 'Sin leer',
            ];
        }

        usort($filas, function (array $a, array $b): int {
            if ($a['leido'] !== $b['leido']) {
                return $b['leido'] <=> $a['leido'];
            }
            if ($a['leido']) {
                return strcmp($b['fecha_lectura'], $a['fecha_lectura']);
            }

            return strcasecmp($a['nombre'], $b['nombre']);
        });

        return $filas;
    }
}
