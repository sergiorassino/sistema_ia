<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de aspirante (lo que carga la familia en el form público).
 *
 * Tabla legacy `aspirantes`. Las columnas se manejan de forma dinámica
 * (Schema::getColumnListing) para que cada colegio active solo las que necesita.
 */
class Aspirante extends Model
{
    protected $table = 'aspirantes';

    public $timestamps = false;

    /** Por seguridad usamos $guarded en lugar de un $fillable estático; las columnas se conocen dinámicamente. */
    protected $guarded = ['id'];

    /**
     * Columnas reservadas (no las maneja el padre en el form público).
     *
     * @var list<string>
     */
    public const COLUMNAS_RESERVADAS = [
        'id',
        'idAspiento',
        'idCursos',
        'idCursoModelo',
        'idNivel',
        'ip_origen',
        'user_agent',
        'created_at',
        'updated_at',
    ];

    /**
     * Columnas reales de la tabla, sin contar las reservadas.
     *
     * @return list<string>
     */
    public static function columnasDisponibles(): array
    {
        if (! Schema::hasTable('aspirantes')) {
            return [];
        }
        $cols = Schema::getColumnListing('aspirantes');

        return array_values(array_filter($cols, static fn ($c) => ! in_array($c, self::COLUMNAS_RESERVADAS, true)));
    }

    public function instancia()
    {
        return $this->belongsTo(Aspiento::class, 'idAspiento');
    }

    public function cursoModelo()
    {
        return $this->belongsTo(AspiCursoModelo::class, 'idCursoModelo');
    }
}
