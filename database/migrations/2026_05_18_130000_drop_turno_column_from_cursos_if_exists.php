<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Política: cursos.turno se conserva siempre (texto legacy / ScriptCase).
     * idTurnoClase convive en la misma tabla (ver EnsureCursosTurnoColumns).
     *
     * Migración histórica: no eliminar turno.
     */
    public function up(): void
    {
        // Sin cambios.
    }

    public function down(): void
    {
        // Sin cambios.
    }
};
