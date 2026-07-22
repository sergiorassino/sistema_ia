<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas legacy del módulo Correo masivo a estudiantes.
 * Referencia: ia_colegiofader (`emails_escritos`, `emails_enviados`).
 * Equivalente a database/sql/emails_masivos_tablas_idempotente.sql.
 * Se aplica con php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('emails_escritos')) {
            Schema::create('emails_escritos', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('subject', 254)->default('');
                $table->text('text');
                $table->string('attached', 150)->default('');
            });
        }

        if (! Schema::hasTable('emails_enviados')) {
            Schema::create('emails_enviados', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('mailDestino', 100)->default('');
                $table->dateTime('fechhora');
                $table->integer('idProfesores');
                $table->integer('idLegajos');
                $table->integer('idCursos');
                $table->integer('idNiveles');
                $table->integer('idTerlec');
                $table->string('subject', 254)->default('');
                $table->text('texto');
                $table->string('attached', 150)->default('');
            });
        }
    }

    public function down(): void
    {
        // No eliminar tablas (pueden tener historial de envíos / datos de negocio).
    }
};
