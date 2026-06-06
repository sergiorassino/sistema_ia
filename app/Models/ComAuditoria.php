<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComAuditoria extends Model
{
    public const ACCION_MARCAR_LEIDO = 'marcar_leido';

    public const ACCION_MARCAR_NO_LEIDO = 'marcar_no_leido';

    public const ACCION_BORRAR_MENSAJE = 'borrar_mensaje';

    public const ACCION_BORRAR_HILO = 'borrar_hilo';

    protected $table = 'com_auditoria';

    public $timestamps = false;

    protected $fillable = [
        'accion',
        'portal',
        'tipo_actor',
        'actor_categoria',
        'id_profesor_actor',
        'id_legajo_actor',
        'nombre_actor_snapshot',
        'dni_actor_snapshot',
        'id_hilo',
        'hilo_asunto_snapshot',
        'id_mensaje',
        'mensaje_contenido_snapshot',
        'mensaje_fecha_snapshot',
        'mensaje_remitente_snapshot',
        'mensaje_destinatario_snapshot',
        'id_nivel',
        'id_terlec',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'mensaje_fecha_snapshot' => 'date',
        'created_at'             => 'datetime',
    ];

    public static function etiquetaAccion(string $accion): string
    {
        return match ($accion) {
            self::ACCION_MARCAR_LEIDO     => 'Marcó como leído',
            self::ACCION_MARCAR_NO_LEIDO  => 'Marcó como no leído',
            self::ACCION_BORRAR_MENSAJE   => 'Borró mensaje',
            self::ACCION_BORRAR_HILO      => 'Borró comunicado (hilo completo)',
            default                       => $accion,
        };
    }

    public static function etiquetaPortal(string $portal): string
    {
        return match ($portal) {
            'secretaria' => 'Secretaría',
            'docente'    => 'Portal docente',
            'familia'    => 'Portal familias',
            default      => $portal,
        };
    }

    public static function etiquetaCategoria(string $categoria): string
    {
        return match ($categoria) {
            'estudiante' => 'Estudiante / familia',
            'profesor'   => 'Profesor/a',
            'personal'   => 'Personal',
            default      => $categoria,
        };
    }
}
