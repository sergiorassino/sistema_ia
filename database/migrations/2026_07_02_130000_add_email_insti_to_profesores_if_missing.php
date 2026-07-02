<?php

use App\Support\Migrations\EnsureProfesoresEmailInstiColumn;
use Illuminate\Database\Migrations\Migration;

/**
 * Email institucional en legajo docente (profesores.emailInsti).
 * Necesario para «Olvidé mi contraseña» y ABM de legajos docentes.
 * Idempotente: solo agrega la columna si falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        EnsureProfesoresEmailInstiColumn::aplicar();
    }

    public function down(): void
    {
        // No eliminar columnas legacy de profesores.
    }
};
