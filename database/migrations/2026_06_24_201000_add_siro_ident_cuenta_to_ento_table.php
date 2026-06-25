<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento') || Schema::hasColumn('ento', 'siroIdentCuenta')) {
            return;
        }

        $ancla = null;
        foreach (['siroPrefijoCPE', 'siroMje', 'siroSecu', 'siroIniPrim', 'replegal'] as $columna) {
            if (Schema::hasColumn('ento', $columna)) {
                $ancla = $columna;
                break;
            }
        }

        Schema::table('ento', function (Blueprint $table) use ($ancla) {
            $column = $table->string('siroIdentCuenta', 20)->nullable();
            if ($ancla !== null) {
                $column->after($ancla);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ento') || ! Schema::hasColumn('ento', 'siroIdentCuenta')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            $table->dropColumn('siroIdentCuenta');
        });
    }
};
