<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas de la variante San Francisco de Asís (autogestión / ficha de matrícula)
 * que algunos tenants (p. ej. EPQ) no tienen en `legajos`.
 *
 * Equivalente a database/sql/legajos_sfa_autogestion_columnas_idempotente.sql.
 * Se aplica con php artisan migrate
 *   o php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legajos')) {
            return;
        }

        $this->agregarSiFalta('reglamApenom', function (Blueprint $table): void {
            $table->string('reglamApenom', 100)->nullable();
        });
        $this->agregarSiFalta('reglamDni', function (Blueprint $table): void {
            $table->string('reglamDni', 20)->nullable();
        });
        $this->agregarSiFalta('reglamEmail', function (Blueprint $table): void {
            $table->string('reglamEmail', 120)->nullable();
        });
        $this->agregarSiFalta('ec_padres', function (Blueprint $table): void {
            $table->string('ec_padres', 200)->nullable();
        });
        $this->agregarSiFalta('contacto1', function (Blueprint $table): void {
            $table->string('contacto1', 200)->nullable();
        });
        $this->agregarSiFalta('contacto2', function (Blueprint $table): void {
            $table->string('contacto2', 200)->nullable();
        });
        $this->agregarSiFalta('contacto3', function (Blueprint $table): void {
            $table->string('contacto3', 200)->nullable();
        });
        $this->agregarSiFalta('retira1', function (Blueprint $table): void {
            $table->string('retira1', 200)->nullable();
        });
        $this->agregarSiFalta('obs_web', function (Blueprint $table): void {
            $table->text('obs_web')->nullable();
        });
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de legajos.
    }

    private function agregarSiFalta(string $columna, callable $definir): void
    {
        if (Schema::hasColumn('legajos', $columna)) {
            return;
        }

        Schema::table('legajos', $definir);
    }
};
