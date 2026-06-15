<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coop_rubros_ingreso')) {
            return;
        }

        Schema::table('coop_rubros_ingreso', function (Blueprint $table) {
            if (Schema::hasColumn('coop_rubros_ingreso', 'categoria')) {
                $table->dropColumn('categoria');
            }

            if (! Schema::hasColumn('coop_rubros_ingreso', 'aplica_descuento_hermano')) {
                $table->boolean('aplica_descuento_hermano')->default(false)->after('tipo');
            }
        });

        DB::table('coop_rubros_ingreso')
            ->where('id', 1)
            ->update(['aplica_descuento_hermano' => true]);

        DB::table('coop_rubros_ingreso')
            ->where('id', '!=', 1)
            ->where('tipo', 'origen_estudiantes')
            ->whereRaw('LOWER(nombre) LIKE ?', ['%cuota%'])
            ->update(['aplica_descuento_hermano' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('coop_rubros_ingreso')) {
            return;
        }

        Schema::table('coop_rubros_ingreso', function (Blueprint $table) {
            if (Schema::hasColumn('coop_rubros_ingreso', 'aplica_descuento_hermano')) {
                $table->dropColumn('aplica_descuento_hermano');
            }
        });
    }
};
