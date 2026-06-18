<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rrd_reservas')) {
            return;
        }

        if (Schema::hasColumn('rrd_reservas', 'devuelto_a')) {
            return;
        }

        Schema::table('rrd_reservas', function (Blueprint $table) {
            $table->unsignedInteger('devuelto_a')->nullable()->after('entregado_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rrd_reservas') || ! Schema::hasColumn('rrd_reservas', 'devuelto_a')) {
            return;
        }

        Schema::table('rrd_reservas', function (Blueprint $table) {
            $table->dropColumn('devuelto_a');
        });
    }
};
