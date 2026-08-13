<?php

use App\Support\Migrations\EnsureProfesoresArt28Column;
use App\Support\Migrations\EnsureProfesoresFichaIncompatColumn;
use Illuminate\Database\Migrations\Migration;

/**
 * Columnas de incompatibilidad en legajo docente (profesores.art28, profesores.fichaIncompat).
 * Referencia art28: ia_iess — varchar(50) NULL.
 * Equivalente a database/sql/profesores_art28_ficha_incompat_idempotente.sql.
 * Se aplica con php artisan migrate o php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        EnsureProfesoresArt28Column::aplicar();
        EnsureProfesoresFichaIncompatColumn::aplicar();
    }

    public function down(): void
    {
        // No eliminar columnas legacy de profesores.
    }
};
