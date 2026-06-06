<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema del módulo Inasistencias docentes (referencia _miPhp/25demayo/sql/schema.sql).
 * Solo crea tablas/columnas que falten (aditivo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cargos')) {
            Schema::create('cargos', function (Blueprint $table) {
                $table->id();
                $table->string('cargo', 50)->nullable();
            });
        }

        if (! Schema::hasTable('cargosxprofesor')) {
            Schema::create('cargosxprofesor', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('idCargos');
                $table->unsignedInteger('idProfesores');
                $table->unsignedInteger('dniProfesor')->default(0);
                $table->unsignedInteger('idNiveles')->default(0);
                $table->unsignedInteger('cant')->default(0);
            });
        } else {
            Schema::table('cargosxprofesor', function (Blueprint $table) {
                if (! Schema::hasColumn('cargosxprofesor', 'dniProfesor')) {
                    $table->unsignedInteger('dniProfesor')->default(0)->after('idProfesores');
                }
                if (! Schema::hasColumn('cargosxprofesor', 'idNiveles')) {
                    $table->unsignedInteger('idNiveles')->default(0)->after('dniProfesor');
                }
                if (! Schema::hasColumn('cargosxprofesor', 'cant')) {
                    $table->unsignedInteger('cant')->default(0);
                }
            });
        }

        if (! Schema::hasTable('inasdocentes')) {
            return;
        }

        Schema::table('inasdocentes', function (Blueprint $table) {
            if (! Schema::hasColumn('inasdocentes', 'dniProfesor')) {
                $table->unsignedInteger('dniProfesor')->default(0)->after('idProfesores');
            }
            if (! Schema::hasColumn('inasdocentes', 'idNivel')) {
                $table->unsignedSmallInteger('idNivel')->default(0)->after('dniProfesor');
            }
            if (! Schema::hasColumn('inasdocentes', 'idCargosXProfesor')) {
                $table->unsignedInteger('idCargosXProfesor')->default(0)->after('idTipoInaDoc');
            }
        });

        if (Schema::hasColumn('inasdocentes', 'cantObligIna')) {
            // Legacy 25demayo usa float(5,2); no forzamos ALTER si ya existe como int.
        }

        if (! Schema::hasTable('inasdocentes_detalle')) {
            Schema::create('inasdocentes_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('idInasDocentes');
                $table->unsignedInteger('idMaterias');
                $table->unsignedInteger('idCursos');
                $table->decimal('cantidad', 5, 2)->default(0);
                $table->index('idInasDocentes');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inasdocentes_detalle');
        // No se eliminan cargos / columnas legacy en down (datos de negocio).
    }
};
