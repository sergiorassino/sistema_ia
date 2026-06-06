<?php

namespace App\Models;

use App\Support\Comunicaciones\ComCanalRolCatalog;
use Illuminate\Database\Eloquent\Model;

class ComCanal extends Model
{
    protected $table = 'com_canales';
    public $timestamps = false;

    protected $fillable = [
        'id_nivel', 'rol_emisor', 'rol_receptor', 'puede_iniciar', 'puede_responder',
        'medios_permitidos', 'activo',
    ];

    protected $casts = [
        'puede_iniciar'     => 'boolean',
        'puede_responder'   => 'boolean',
        'activo'            => 'boolean',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /**
     * @param  array<int, string>|string|null  $value
     */
    public function setMediosPermitidosAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['medios_permitidos'] = null;

            return;
        }

        if (is_string($value)) {
            $this->attributes['medios_permitidos'] = $value;

            return;
        }

        $lista = array_values(array_unique(array_filter(
            is_array($value) ? $value : [],
            static fn ($m) => is_string($m) && $m !== ''
        )));

        $this->attributes['medios_permitidos'] = json_encode($lista, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    public function getMediosPermitidosAttribute(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @return array<string, string> clave => etiqueta (profesortipo + familias) */
    public static function etiquetasRoles(): array
    {
        return ComCanalRolCatalog::catalogo();
    }

    /** @return list<string> */
    public static function rolesClave(): array
    {
        return ComCanalRolCatalog::claves();
    }

    public static function etiquetaRol(string $rol): string
    {
        return ComCanalRolCatalog::etiqueta($rol);
    }

    /** Medios disponibles en el sistema */
    public static function mediosDisponibles(): array
    {
        return ['push', 'email', 'whatsapp'];
    }
}
