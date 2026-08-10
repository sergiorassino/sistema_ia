<?php

namespace App\Support\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega ento.ctaEnvioMail y ento.passEnvioMail si faltan
 * (cuenta y contraseña de aplicación para correo institucional SMTP).
 * Idempotente: no hace nada si la tabla o las columnas ya existen.
 */
final class EnsureEntoCtaEnvioMailColumns
{
    public const COLUMNA_CTA = 'ctaEnvioMail';

    public const COLUMNA_PASS = 'passEnvioMail';

    public static function aplicar(): bool
    {
        if (! Schema::hasTable('ento')) {
            return false;
        }

        $agrego = false;

        if (! Schema::hasColumn('ento', self::COLUMNA_CTA)) {
            Schema::table('ento', function (Blueprint $table) {
                $column = $table->string(self::COLUMNA_CTA, 120)->nullable();
                if (Schema::hasColumn('ento', 'mail')) {
                    $column->after('mail');
                }
            });
            $agrego = true;
        }

        if (! Schema::hasColumn('ento', self::COLUMNA_PASS)) {
            Schema::table('ento', function (Blueprint $table) {
                $column = $table->string(self::COLUMNA_PASS, 40)->nullable();
                if (Schema::hasColumn('ento', self::COLUMNA_CTA)) {
                    $column->after(self::COLUMNA_CTA);
                } elseif (Schema::hasColumn('ento', 'mail')) {
                    $column->after('mail');
                }
            });
            $agrego = true;
        }

        return $agrego;
    }

    public static function estado(): string
    {
        if (! Schema::hasTable('ento')) {
            return 'sin_tabla_ento';
        }

        $tieneCta = Schema::hasColumn('ento', self::COLUMNA_CTA);
        $tienePass = Schema::hasColumn('ento', self::COLUMNA_PASS);

        if ($tieneCta && $tienePass) {
            return 'ya_existe';
        }

        if ($tieneCta || $tienePass) {
            return 'parcial';
        }

        return 'pendiente';
    }
}
