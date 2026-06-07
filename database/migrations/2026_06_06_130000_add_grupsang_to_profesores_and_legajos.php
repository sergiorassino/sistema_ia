<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grupo sanguíneo en legajos y profesores (columna legacy `grupsang`).
 * Idempotente: solo agrega la columna si falta (Schema::hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legajos') && ! Schema::hasColumn('legajos', 'grupsang')) {
            Schema::table('legajos', function (Blueprint $table) {
                $table->string('grupsang', 20)->nullable();
            });
        }

        if (Schema::hasTable('profesores') && ! Schema::hasColumn('profesores', 'grupsang')) {
            Schema::table('profesores', function (Blueprint $table) {
                $table->string('grupsang', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('legajos') && Schema::hasColumn('legajos', 'grupsang')) {
            Schema::table('legajos', function (Blueprint $table) {
                $table->dropColumn('grupsang');
            });
        }

        if (Schema::hasTable('profesores') && Schema::hasColumn('profesores', 'grupsang')) {
            Schema::table('profesores', function (Blueprint $table) {
                $table->dropColumn('grupsang');
            });
        }
    }
};
