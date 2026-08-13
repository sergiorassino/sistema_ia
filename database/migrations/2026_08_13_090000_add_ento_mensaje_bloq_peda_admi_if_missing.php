<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes de bloqueo pedagógico/administrativo en autogestión (ficha y datos personales).
 * Equivalente a database/sql/ento_mensaje_bloq_peda_admi_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            if (! Schema::hasColumn('ento', 'mensajeBloqPeda')) {
                $column = $table->string('mensajeBloqPeda', 500)->nullable();
                if (Schema::hasColumn('ento', 'verDatosFicha')) {
                    $column->after('verDatosFicha');
                } elseif (Schema::hasColumn('ento', 'imprBoleOff')) {
                    $column->after('imprBoleOff');
                }
            }
            if (! Schema::hasColumn('ento', 'mensajeBloqAdmi')) {
                $column = $table->string('mensajeBloqAdmi', 500)->nullable();
                if (Schema::hasColumn('ento', 'mensajeBloqPeda')) {
                    $column->after('mensajeBloqPeda');
                } elseif (Schema::hasColumn('ento', 'verDatosFicha')) {
                    $column->after('verDatosFicha');
                }
            }
        });
    }

    public function down(): void
    {
        // No eliminar columnas legacy de ento.
    }
};
