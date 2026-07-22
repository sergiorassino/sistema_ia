<?php

use App\Support\Migrations\EnsureProfesoresEmailPassColumn;
use Illuminate\Database\Migrations\Migration;

/**
 * Contraseña de aplicación Gmail en legajo docente (profesores.emailPass).
 * Referencia: ia_colegiofader — usada por el módulo Correo masivo a estudiantes.
 * Equivalente a database/sql/profesores_email_pass_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        EnsureProfesoresEmailPassColumn::aplicar();
    }

    public function down(): void
    {
        // No eliminar columnas legacy de profesores.
    }
};
