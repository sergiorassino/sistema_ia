<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('itemsboletin')) {
            return;
        }

        Schema::create('itemsboletin', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('etiqueta', 160);
            /** Origen: `inasistencias`, `sanciones`, `conducta1` o `conducta2`. */
            $table->string('fuente', 32);
            /**
             * Fragmento SQL AND-adicional (sin `WHERE` inicial). Solo columnas de la tabla `fuente`.
             * Ej.: `tipo <> 5 AND just = 'J'` o `idTipoSancion = 1 AND publicada = 1`
             * Para `conducta1` / `conducta2` no se usa (el valor sale de `matricula`).
             */
            $table->string('condicion_where', 500);
            /** Si es null, el ítem aplica a todos los ciclos lectivos de la base. */
            $table->unsignedInteger('idTerlec')->nullable();
            $table->boolean('activo')->default(true);
            $table->index(['activo', 'orden']);
            $table->index(['idTerlec', 'activo']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('itemsboletin')) {
            return;
        }

        Schema::drop('itemsboletin');
    }
};
