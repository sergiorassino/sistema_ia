<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas legacy de desempeño, inasistencias y conducta por etapa en `matricula`.
 * Idempotente: solo agrega columnas que falten (Schema::hasColumn).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNAS = [
        'obs1',
        'obs2',
        'obsAnual',
        'just1',
        'inju1',
        'just2',
        'inju2',
        'conducta1',
        'conducta2',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('matricula')) {
            return;
        }

        Schema::table('matricula', function (Blueprint $table) {
            if (! Schema::hasColumn('matricula', 'obs1')) {
                $table->text('obs1')->nullable();
            }
            if (! Schema::hasColumn('matricula', 'obs2')) {
                $table->text('obs2')->nullable();
            }
            if (! Schema::hasColumn('matricula', 'obsAnual')) {
                $table->string('obsAnual', 500)->nullable();
            }
            if (! Schema::hasColumn('matricula', 'just1')) {
                $table->string('just1', 20)->nullable();
            }
            if (! Schema::hasColumn('matricula', 'inju1')) {
                $table->string('inju1', 20)->nullable();
            }
            if (! Schema::hasColumn('matricula', 'just2')) {
                $table->string('just2', 20)->nullable();
            }
            if (! Schema::hasColumn('matricula', 'inju2')) {
                $table->string('inju2', 20)->nullable();
            }
            if (! Schema::hasColumn('matricula', 'conducta1')) {
                $table->string('conducta1', 100)->nullable();
            }
            if (! Schema::hasColumn('matricula', 'conducta2')) {
                $table->string('conducta2', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('matricula')) {
            return;
        }

        $columnasExistentes = array_values(array_filter(
            self::COLUMNAS,
            fn (string $columna) => Schema::hasColumn('matricula', $columna)
        ));

        if ($columnasExistentes === []) {
            return;
        }

        Schema::table('matricula', function (Blueprint $table) use ($columnasExistentes) {
            $table->dropColumn($columnasExistentes);
        });
    }
};
