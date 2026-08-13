<?php

namespace App\Support\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega profesores.fichaIncompat si falta (ficha de incompatibilidad docente).
 * Tipo alineado con art28: varchar(50) NULL.
 * Idempotente: no hace nada si la tabla o la columna ya existen.
 */
final class EnsureProfesoresFichaIncompatColumn
{
    public const COLUMNA = 'fichaIncompat';

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
            if (Schema::hasColumn('profesores', 'art28')) {
                $column->after('art28');
            } elseif (Schema::hasColumn('profesores', 'incapac')) {
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
