<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot de datos impresos en comprobantes AFIP (teléfono, aporte estatal, curso, doc. tipo)
 * y eliminación de domicilioAlumno (sin uso en PDF ni AFIP).
 * Equivalente a database/sql/comprobanteafip_snapshot_campos_idempotente.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comprobanteafip')) {
            return;
        }

        Schema::table('comprobanteafip', function (Blueprint $table) {
            if (! Schema::hasColumn('comprobanteafip', 'telefonoInstitucion')) {
                $table->string('telefonoInstitucion', 40)->nullable();
            }
            if (! Schema::hasColumn('comprobanteafip', 'aporteEstatal')) {
                $table->string('aporteEstatal', 80)->nullable();
            }
            if (! Schema::hasColumn('comprobanteafip', 'cursoAlumno')) {
                $table->string('cursoAlumno', 120)->nullable();
            }
            if (! Schema::hasColumn('comprobanteafip', 'docTipoAfip')) {
                $table->unsignedSmallInteger('docTipoAfip')->nullable();
            }
        });

        if (Schema::hasColumn('comprobanteafip', 'domicilioAlumno')) {
            Schema::table('comprobanteafip', function (Blueprint $table) {
                $table->dropColumn('domicilioAlumno');
            });
        }
    }

    public function down(): void
    {
        // No eliminar columnas legacy de comprobanteafip.
    }
};
