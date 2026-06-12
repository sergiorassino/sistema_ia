<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rrd_recursos')) {
            return;
        }

        if (Schema::hasColumn('rrd_recursos', 'siempre_disponible')) {
            return;
        }

        Schema::table('rrd_recursos', function (Blueprint $table) {
            // true = sin restricción de ventana horaria; false = respetar rrd_recurso_disponibilidad
            $table->boolean('siempre_disponible')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('rrd_recursos') && Schema::hasColumn('rrd_recursos', 'siempre_disponible')) {
            Schema::table('rrd_recursos', function (Blueprint $table) {
                $table->dropColumn('siempre_disponible');
            });
        }
    }
};
