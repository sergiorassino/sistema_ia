<?php

use App\Support\Migrations\EnsureEntoCtaEnvioMailColumns;
use Illuminate\Database\Migrations\Migration;

/**
 * Cuenta y contraseña de aplicación Gmail para correo institucional (ento).
 * Equivalente a database/sql/ento_cta_pass_envio_mail_idempotente.sql.
 * Se aplica con: php artisan migrate
 *   o php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        EnsureEntoCtaEnvioMailColumns::aplicar();
    }

    public function down(): void
    {
        // No eliminar columnas de ento.
    }
};
