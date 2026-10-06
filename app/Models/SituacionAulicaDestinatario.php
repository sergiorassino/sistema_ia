<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SituacionAulicaDestinatario extends Model
{
    public const TABLA = 'situacion_aulica_destinatarios';

    protected $table = self::TABLA;

    public $timestamps = false;

    protected $fillable = [
        'idNivel',
        'idProfesor',
    ];

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'idProfesor');
    }

    /**
     * @return list<int>
     */
    public static function idsDelNivel(int $idNivel): array
    {
        if ($idNivel < 1) {
            return [];
        }

        $out = [];
        foreach (self::query()->where('idNivel', $idNivel)->pluck('idProfesor') as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }
}
