<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas legacy de datos comunes de certificados de finalización.
 * Si el tenant ya las tiene (ScriptCase), no se tocan.
 * Equivalente a database/sql/certificado_jardin_sexto_tablas_idempotente.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificadojardin')) {
            Schema::create('certificadojardin', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('serie', 50)->default('');
                $table->string('mesApro', 40)->default('');
                $table->string('anoApro', 20)->default('');
                $table->string('diaEmision', 40)->default('');
                $table->string('mesEmision', 40)->default('');
                $table->string('anoEmision', 20)->default('');
                $table->string('ppi', 500)->default('');
            });
        }

        if (! Schema::hasTable('certificadosextogrado')) {
            Schema::create('certificadosextogrado', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('serie', 50)->default('');
                $table->string('mesApro', 40)->default('');
                $table->string('anoApro', 20)->default('');
                $table->string('diaEmision', 40)->default('');
                $table->string('mesEmision', 40)->default('');
                $table->string('anoEmision', 20)->default('');
                $table->string('ppi', 500)->default('');
            });
        }
    }

    public function down(): void
    {
        // No eliminar tablas legacy (pueden tener datos de ScriptCase).
    }
};
