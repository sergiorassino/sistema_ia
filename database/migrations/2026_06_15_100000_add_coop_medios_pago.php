<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coop_medios_pago')) {
            Schema::create('coop_medios_pago', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 80);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);

                $table->index(['activo', 'orden'], 'idx_coop_medios_pago_activo_orden');
            });

            DB::table('coop_medios_pago')->insert([
                ['nombre' => 'Efectivo', 'orden' => 1, 'activo' => true],
                ['nombre' => 'Transferencia', 'orden' => 2, 'activo' => true],
                ['nombre' => 'Débito', 'orden' => 3, 'activo' => true],
                ['nombre' => 'Crédito', 'orden' => 4, 'activo' => true],
                ['nombre' => 'Cheque', 'orden' => 5, 'activo' => true],
            ]);
        }

        if (Schema::hasTable('coop_ingresos') && ! Schema::hasColumn('coop_ingresos', 'id_medio_pago')) {
            Schema::table('coop_ingresos', function (Blueprint $table) {
                $table->unsignedBigInteger('id_medio_pago')->nullable()->after('medio_pago');

                $table->foreign('id_medio_pago', 'fk_coop_ingresos_medio_pago')
                    ->references('id')->on('coop_medios_pago')
                    ->onDelete('set null');

                $table->index('id_medio_pago', 'idx_coop_ingresos_medio_pago');
            });
        }

        if (Schema::hasTable('coop_egresos') && ! Schema::hasColumn('coop_egresos', 'id_medio_pago')) {
            Schema::table('coop_egresos', function (Blueprint $table) {
                $table->unsignedBigInteger('id_medio_pago')->nullable()->after('firmante');
                $table->string('medio_pago', 80)->nullable()->after('id_medio_pago');

                $table->foreign('id_medio_pago', 'fk_coop_egresos_medio_pago')
                    ->references('id')->on('coop_medios_pago')
                    ->onDelete('set null');

                $table->index('id_medio_pago', 'idx_coop_egresos_medio_pago');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coop_egresos')) {
            Schema::table('coop_egresos', function (Blueprint $table) {
                if (Schema::hasColumn('coop_egresos', 'id_medio_pago')) {
                    $table->dropForeign('fk_coop_egresos_medio_pago');
                    $table->dropIndex('idx_coop_egresos_medio_pago');
                    $table->dropColumn(['id_medio_pago', 'medio_pago']);
                }
            });
        }

        if (Schema::hasTable('coop_ingresos')) {
            Schema::table('coop_ingresos', function (Blueprint $table) {
                if (Schema::hasColumn('coop_ingresos', 'id_medio_pago')) {
                    $table->dropForeign('fk_coop_ingresos_medio_pago');
                    $table->dropIndex('idx_coop_ingresos_medio_pago');
                    $table->dropColumn('id_medio_pago');
                }
            });
        }

        Schema::dropIfExists('coop_medios_pago');
    }
};
