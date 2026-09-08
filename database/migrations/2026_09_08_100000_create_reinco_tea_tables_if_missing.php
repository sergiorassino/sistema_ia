<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de Gestión de TEA (reincorporación por inasistencias) para tenants que no las tienen.
 *
 * Equivalente: database/sql/reinco_tea_tablas_idempotente.sql
 * Se aplica con php artisan se:migrate-legacy --force
 *
 * Catálogo `reinco2025_tipo`: mismos 5 registros que ia_montecristo.
 * `reinco2025` se crea vacía. No se truncan registros existentes.
 */
return new class extends Migration
{
    /**
     * Catálogo Montecristo (`reinco2025_tipo`).
     *
     * @return list<array{id: int, orden: int, tipo: string}>
     */
    private function tiposMontecristo(): array
    {
        return [
            ['id' => 1, 'orden' => 1, 'tipo' => 'Informe del Preceptor a la Familia (3 inas)'],
            ['id' => 2, 'orden' => 2, 'tipo' => 'Citación Adultos Responsables (5 inas)'],
            ['id' => 3, 'orden' => 3, 'tipo' => 'Acta Compromiso con Estudiante y Responsables (10 inas)'],
            ['id' => 4, 'orden' => 4, 'tipo' => 'Informe de Situación de Riesgo y Definición de Acciones Pedag (20 inas)'],
            ['id' => 5, 'orden' => 5, 'tipo' => 'Situación de TEA (más de 25 inas)'],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('reinco2025_tipo')) {
            Schema::create('reinco2025_tipo', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('orden')->default(0);
                $table->string('tipo', 80);
            });
        } else {
            $this->agregarColumnaSiFalta('reinco2025_tipo', 'orden', fn (Blueprint $t) => $t->integer('orden')->default(0));
            $this->agregarColumnaSiFalta('reinco2025_tipo', 'tipo', fn (Blueprint $t) => $t->string('tipo', 80)->default(''));
        }

        if (Schema::hasTable('reinco2025_tipo')
            && Schema::hasColumn('reinco2025_tipo', 'tipo')
            && Schema::hasColumn('reinco2025_tipo', 'orden')) {
            foreach ($this->tiposMontecristo() as $tipo) {
                if (! DB::table('reinco2025_tipo')->where('id', $tipo['id'])->exists()) {
                    DB::table('reinco2025_tipo')->insert($tipo);
                }
            }
        }

        if (! Schema::hasTable('reinco2025')) {
            Schema::create('reinco2025', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('idMatricula');
                $table->integer('idReinco_tipo');
                $table->date('fecha');
                $table->text('obs')->nullable();
            });
        } else {
            $this->agregarColumnaSiFalta('reinco2025', 'idMatricula', fn (Blueprint $t) => $t->integer('idMatricula')->default(0));
            $this->agregarColumnaSiFalta('reinco2025', 'idReinco_tipo', fn (Blueprint $t) => $t->integer('idReinco_tipo')->default(0));
            $this->agregarColumnaSiFalta('reinco2025', 'fecha', fn (Blueprint $t) => $t->date('fecha')->nullable());
            $this->agregarColumnaSiFalta('reinco2025', 'obs', fn (Blueprint $t) => $t->text('obs')->nullable());
        }
    }

    public function down(): void
    {
        // No eliminar tablas con datos de negocio / posible legacy.
    }

    private function agregarColumnaSiFalta(string $tabla, string $columna, callable $definicion): void
    {
        if (Schema::hasColumn($tabla, $columna)) {
            return;
        }

        Schema::table($tabla, $definicion);
    }
};
