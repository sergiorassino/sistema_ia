<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloqueos por ciclo lectivo en matricula.
 * legajos.bloqmatr / bloqadmi no se modifican (legacy Scriptcase).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('matricula')) {
            return;
        }

        Schema::table('matricula', function (Blueprint $table) {
            if (! Schema::hasColumn('matricula', 'bloqmatr')) {
                $table->unsignedTinyInteger('bloqmatr')->default(0)->after('fechaBaja');
            }
            if (! Schema::hasColumn('matricula', 'bloqadmi')) {
                $table->unsignedTinyInteger('bloqadmi')->default(0)->after('bloqmatr');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('matricula')) {
            return;
        }

        Schema::table('matricula', function (Blueprint $table) {
            if (Schema::hasColumn('matricula', 'bloqadmi')) {
                $table->dropColumn('bloqadmi');
            }
            if (Schema::hasColumn('matricula', 'bloqmatr')) {
                $table->dropColumn('bloqmatr');
            }
        });
    }
};
