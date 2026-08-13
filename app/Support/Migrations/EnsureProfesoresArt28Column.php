<?php

namespace App\Support\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega profesores.art28 si falta (declaración art. 28 / incompatibilidades).
 * Referencia: ia_iess — varchar(50) NULL, después de incapac.
 * Idempotente: no hace nada si la tabla o la columna ya existen.
 */
final class EnsureProfesoresArt28Column
{
    public const COLUMNA = 'art28';

    public static function aplicar(): bool
    {
        if (! Schema::hasTable('profesores')) {
            return false;
        }

        if (Schema::hasColumn('profesores', self::COLUMNA)) {
            return false;
        }

        Schema::table('profesores', function (Blueprint $table) {
            $column = $table->string(self::COLUMNA, 50)->nullable();
            if (Schema::hasColumn('profesores', 'incapac')) {
                $column->after('incapac');
            }
        });

        return true;
    }

    public static function estado(): string
    {
        if (! Schema::hasTable('profesores')) {
            return 'sin_tabla_profesores';
        }

        if (Schema::hasColumn('profesores', self::COLUMNA)) {
            return 'ya_existe';
        }

        return 'pendiente';
    }
}
