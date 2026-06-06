<?php

namespace App\Support\Examenes;

use Illuminate\Support\Facades\DB;

/**
 * Anula todas las inscripciones a examen: inscri = 0 donde inscri = 1 (tabla completa).
 */
final class BorrarInscripcionesExamen
{
    public static function contarInscriptos(): int
    {
        return (int) DB::table('calificaciones')
            ->where('inscri', 1)
            ->count();
    }

    /**
     * @return int Filas actualizadas
     */
    public static function ejecutar(): int
    {
        return DB::table('calificaciones')
            ->where('inscri', 1)
            ->update(['inscri' => 0]);
    }
}
