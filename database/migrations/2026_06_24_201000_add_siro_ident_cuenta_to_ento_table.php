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

        Schema::table('ento', function (Blueprint $table) {
            $table->string('siroIdentCuenta', 20)->nullable()->after('siroSecu');
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
