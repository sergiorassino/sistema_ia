<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Tabla de solapas del legajo ─────────────────────────────────────
        if (! Schema::hasTable('solapas_legajo')) {
            Schema::create('solapas_legajo', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 60);
                $table->string('slug', 30)->unique();
                $table->unsignedSmallInteger('orden')->default(0);
            });
        }

        $solapasPorDefecto = [
            ['nombre' => 'Alumno',      'slug' => 'alumno',    'orden' => 1],
            ['nombre' => 'Domicilio',   'slug' => 'domicilio', 'orden' => 2],
            ['nombre' => 'Madre',       'slug' => 'madre',     'orden' => 3],
            ['nombre' => 'Padre',       'slug' => 'padre',     'orden' => 4],
            ['nombre' => 'Tutor',       'slug' => 'tutor',     'orden' => 5],
            ['nombre' => 'Escolaridad', 'slug' => 'escolar',   'orden' => 6],
        ];

        foreach ($solapasPorDefecto as $solapa) {
            if (! DB::table('solapas_legajo')->where('slug', $solapa['slug'])->exists()) {
                DB::table('solapas_legajo')->insert($solapa);
            }
        }

        // ── 2. Renombrar campos_listado_alumnos → campos_legajo ────────────────
        if (Schema::hasTable('campos_listado_alumnos') && ! Schema::hasTable('campos_legajo')) {
            Schema::rename('campos_listado_alumnos', 'campos_legajo');
        } elseif (! Schema::hasTable('campos_legajo')) {
            Schema::create('campos_legajo', function (Blueprint $table) {
                $table->id();
                $table->string('columna', 80);
                $table->string('etiqueta', 100)->nullable();
                $table->boolean('visible_listado')->default(true);
                $table->unsignedInteger('orden')->default(0);
            });
        }

        if (! Schema::hasTable('campos_legajo')) {
            return;
        }

        // ── 3. Nuevas columnas: solapa + orden dentro de la solapa ─────────────
        if (! Schema::hasColumn('campos_legajo', 'solapa_legajo_id')) {
            Schema::table('campos_legajo', function (Blueprint $table) {
                $table->foreignId('solapa_legajo_id')
                    ->nullable()
                    ->after('orden')
                    ->constrained('solapas_legajo')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('campos_legajo', 'orden_en_solapa')) {
            Schema::table('campos_legajo', function (Blueprint $table) {
                $table->unsignedSmallInteger('orden_en_solapa')
                    ->default(0)
                    ->after('solapa_legajo_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campos_legajo')) {
            Schema::table('campos_legajo', function (Blueprint $table) {
                $table->dropForeign(['solapa_legajo_id']);
                $table->dropColumn(['solapa_legajo_id', 'orden_en_solapa']);
            });

            if (! Schema::hasTable('campos_listado_alumnos')) {
                Schema::rename('campos_legajo', 'campos_listado_alumnos');
            }
        }

        Schema::dropIfExists('solapas_legajo');
    }
};
