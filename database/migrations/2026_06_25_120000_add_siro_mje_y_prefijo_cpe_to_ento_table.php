<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            if (! Schema::hasColumn('ento', 'siroMje')) {
                $table->string('siroMje', 40)->nullable()->after('siroSecu');
            }
        });

        Schema::table('ento', function (Blueprint $table) {
            if (! Schema::hasColumn('ento', 'siroPrefijoCPE')) {
                $after = Schema::hasColumn('ento', 'siroMje') ? 'siroMje' : 'siroSecu';
                $table->string('siroPrefijoCPE', 2)->nullable()->after($after);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            if (Schema::hasColumn('ento', 'siroPrefijoCPE')) {
                $table->dropColumn('siroPrefijoCPE');
            }
        });

        // siroMje puede ser columna legacy: no eliminar en down salvo que la migración la haya creado.
    }
};
