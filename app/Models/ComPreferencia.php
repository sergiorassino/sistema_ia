<?php

namespace App\Models;

use App\Models\Legajo;
use App\Models\Profesor;
use Illuminate\Database\Eloquent\Model;

class ComPreferencia extends Model
{
    protected $table = 'com_preferencias';
    public $timestamps = false;

    protected $fillable = [
        'tipo_usuario', 'id_legajo', 'id_profesor',
        'vinculo_contacto', 'vinculos_contacto', 'push', 'email', 'whatsapp',
    ];

    protected $casts = [
        'push'               => 'boolean',
        'email'              => 'boolean',
        'whatsapp'           => 'boolean',
        'vinculos_contacto'  => 'array',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function legajo()
    {
        return $this->belongsTo(Legajo::class, 'id_legajo');
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'id_profesor');
    }

    /** Retorna las preferencias de un legajo, o valores por defecto si no existe registro */
    public static function paraLegajo(int $idLegajo): static
    {
        return static::firstOrNew(
            ['tipo_usuario' => 'familia', 'id_legajo' => $idLegajo],
            ['push' => true, 'email' => true, 'whatsapp' => true]
        );
    }

    /** Retorna las preferencias de un profesor, o valores por defecto si no existe registro */
    public static function paraProfesor(int $idProfesor): static
    {
        return static::firstOrNew(
            ['tipo_usuario' => 'profesor', 'id_profesor' => $idProfesor],
            ['push' => true, 'email' => true, 'whatsapp' => true]
        );
    }

    /** Lista de medios activos según las preferencias */
    public function mediosActivos(): array
    {
        $medios = [];
        if ($this->push)     $medios[] = 'push';
        if ($this->email)    $medios[] = 'email';
        if ($this->whatsapp) $medios[] = 'whatsapp';

        return $medios;
    }

    /**
     * Responsables de contacto para email/WhatsApp: null = sin preferencia (comportamiento legacy).
     *
     * @return list<string>|null listado de claves entre madre, padre, tutor (orden de preferencia en UI)
     */
    public function vinculosContactoResolucion(): ?array
    {
        $permitidos = ['padre', 'madre', 'tutor'];
        $raw        = $this->vinculos_contacto;
        if (is_array($raw) && $raw !== []) {
            $out = [];
            foreach ($raw as $v) {
                $v = (string) $v;
                if (in_array($v, $permitidos, true) && ! in_array($v, $out, true)) {
                    $out[] = $v;
                }
            }

            return $out === [] ? null : $out;
        }

        $single = (string) ($this->vinculo_contacto ?? '');
        if ($single !== '' && in_array($single, $permitidos, true)) {
            return [$single];
        }

        return null;
    }
}
