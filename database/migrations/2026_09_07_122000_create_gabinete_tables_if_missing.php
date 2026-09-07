<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de seguimiento de gabinete para tenants que no las tienen.
 *
 * Equivalente: database/sql/gabinete_tablas_idempotente.sql
 * Se aplica con php artisan se:migrate-legacy --force
 *
 * No recrea tablas existentes. Solo agrega columnas/índices faltantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gabinetetipo')) {
            Schema::create('gabinetetipo', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('tipo', 100)->default('');
            });
        } elseif (! Schema::hasColumn('gabinetetipo', 'tipo')) {
            Schema::table('gabinetetipo', function (Blueprint $table) {
                $table->string('tipo', 100)->default('');
            });
        }

        if (! Schema::hasTable('gabinete')) {
            Schema::create('gabinete', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('idMatricula');
                $table->integer('idTipoSancion');
                $table->date('fecha')->nullable();
                $table->integer('cantidad')->nullable();
                $table->string('solipor', 500)->nullable();
                $table->text('motivo')->nullable();
                $table->text('asistentes')->nullable();
                $table->text('conclusion')->nullable();
                $table->index('idMatricula', 'idx_gabinete_matricula');
                $table->index('idTipoSancion', 'idx_gabinete_tipo');
                $table->index('fecha', 'idx_gabinete_fecha');
            });
        } else {
            $this->agregarColumnaSiFalta('gabinete', 'idMatricula', fn (Blueprint $t) => $t->integer('idMatricula')->default(0));
            $this->agregarColumnaSiFalta('gabinete', 'idTipoSancion', fn (Blueprint $t) => $t->integer('idTipoSancion')->default(0));
            $this->agregarColumnaSiFalta('gabinete', 'fecha', fn (Blueprint $t) => $t->date('fecha')->nullable());
            $this->agregarColumnaSiFalta('gabinete', 'cantidad', fn (Blueprint $t) => $t->integer('cantidad')->nullable());
            $this->agregarColumnaSiFalta('gabinete', 'solipor', fn (Blueprint $t) => $t->string('solipor', 500)->nullable());
            $this->agregarColumnaSiFalta('gabinete', 'motivo', fn (Blueprint $t) => $t->text('motivo')->nullable());
            $this->agregarColumnaSiFalta('gabinete', 'asistentes', fn (Blueprint $t) => $t->text('asistentes')->nullable());
            $this->agregarColumnaSiFalta('gabinete', 'conclusion', fn (Blueprint $t) => $t->text('conclusion')->nullable());
            $this->agregarIndiceSiFalta('gabinete', 'idx_gabinete_matricula', 'idMatricula');
            $this->agregarIndiceSiFalta('gabinete', 'idx_gabinete_tipo', 'idTipoSancion');
            $this->agregarIndiceSiFalta('gabinete', 'idx_gabinete_fecha', 'fecha');
        }

        if (! Schema::hasTable('gabinetemarca')) {
            Schema::create('gabinetemarca', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('idLegajos');
                $table->unsignedTinyInteger('color')->default(0);
                $table->unique('idLegajos', 'uk_gabinetemarca_legajo');
            });
        } else {
            $this->agregarColumnaSiFalta('gabinetemarca', 'idLegajos', fn (Blueprint $t) => $t->unsignedInteger('idLegajos'));
            $this->agregarColumnaSiFalta('gabinetemarca', 'color', fn (Blueprint $t) => $t->unsignedTinyInteger('color')->default(0));
            $this->agregarIndiceUnicoSiFalta('gabinetemarca', 'uk_gabinetemarca_legajo', 'idLegajos');
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

    private function agregarIndiceSiFalta(string $tabla, string $nombre, string $columna): void
    {
        if (! Schema::hasColumn($tabla, $columna) || Schema::hasIndex($tabla, $nombre)) {
            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($nombre, $columna) {
            $table->index($columna, $nombre);
        });
    }

    private function agregarIndiceUnicoSiFalta(string $tabla, string $nombre, string $columna): void
    {
        if (! Schema::hasColumn($tabla, $columna) || Schema::hasIndex($tabla, $nombre)) {
            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($nombre, $columna) {
            $table->unique($columna, $nombre);
        });
    }
};
