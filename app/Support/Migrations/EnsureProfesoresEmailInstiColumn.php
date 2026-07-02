<?php

namespace App\Support\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega profesores.emailInsti si falta (colegios legacy sin esa columna).
 * Idempotente: no hace nada si la tabla o la columna ya existen.
 */
final class EnsureProfesoresEmailInstiColumn
{
    public const COLUMNA = 'emailInsti';

    public static function aplicar(): bool
    {
        if (! Schema::hasTable('profesores')) {
            return false;
        }

        if (Schema::hasColumn('profesores', self::COLUMNA)) {
            return false;
        }

        Schema::table('profesores', function (Blueprint $table) {
            $column = $table->string(self::COLUMNA, 100)->nullable();
            if (Schema::hasColumn('profesores', 'email')) {
                $column->after('email');
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
