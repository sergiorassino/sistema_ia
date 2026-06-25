<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cupones_a_pagar')) {
            return;
        }

        Schema::create('cupones_a_pagar', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_cuotas_generadas');
            $table->unsignedInteger('id_cursos');
            $table->unsignedInteger('id_cuotasbecas')->default(0);
            $table->decimal('saldo_pagar', 12, 2);
            $table->char('cpe', 19);
            $table->char('id_factura', 20);
            $table->unsignedTinyInteger('ult_upload');
            $table->string('origen', 24);

            $table->char('signo1v', 1);
            $table->decimal('valor1v', 12, 4)->default(0);
            $table->char('porcan1v', 1)->default('%');
            $table->date('fecha1venc');
            $table->decimal('importe1venc', 12, 2);

            $table->char('signo2v', 1);
            $table->decimal('valor2v', 12, 4)->default(0);
            $table->char('porcan2v', 1)->default('%');
            $table->date('fecha2venc');
            $table->decimal('importe2venc', 12, 2);

            $table->char('signo3v', 1);
            $table->decimal('valor3v', 12, 4)->default(0);
            $table->char('porcan3v', 1)->default('%');
            $table->date('fecha3venc');
            $table->decimal('importe3venc', 12, 2);

            $table->dateTime('fecha_emision');
            $table->string('nombre_archivo_siro', 64)->nullable();

            $table->unique('id_factura', 'uq_cupones_a_pagar_id_factura');
            $table->index('id_cuotas_generadas', 'idx_cupones_a_pagar_cuota_gen');
            $table->index(['id_cuotas_generadas', 'ult_upload'], 'idx_cupones_a_pagar_cuota_upload');
            $table->index('origen', 'idx_cupones_a_pagar_origen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones_a_pagar');
    }
};
