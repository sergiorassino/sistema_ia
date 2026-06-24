<?php

use App\Support\Migrations\EnsureCursosTurnoColumns;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Repara instalaciones que ejecutaron una versión anterior que eliminaba turno.
     * Idempotente: crea turno si falta, asegura idTurnoClase y sincroniza datos.
     */
    public function up(): void
    {
        EnsureCursosTurnoColumns::aplicar();
    }

    public function down(): void
    {
        // No eliminar turno ni idTurnoClase.
    }
};
