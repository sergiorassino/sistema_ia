<?php

namespace App\Support\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega profesores.emailPass si falta (contraseña de aplicación Gmail para correo masivo).
 * Referencia: ia_colegiofader — varchar(19) NULL.
 * Idempotente: no hace nada si la tabla o la columna ya existen.
 */
final class EnsureProfesoresEmailPassColumn
{
    public const COLUMNA = 'emailPass';

    public static function aplicar(): bool
    {
        if (! Schema::hasTable('profesores')) {
            return false;
        }

        if (Schema::hasColumn('profesores', self::COLUMNA)) {
            return false;
        }

        Schema::table('profesores', function (Blueprint $table) {
            $column = $table->string(self::COLUMNA, 19)->nullable();
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
