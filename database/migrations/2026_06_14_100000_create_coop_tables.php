<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coop_config')) {
            Schema::create('coop_config', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_institucion', 200)->default('Cooperadora');
                $table->string('direccion', 200)->default('');
                $table->string('localidad', 120)->default('');
                $table->string('telefono', 80)->default('');
                $table->decimal('descuento_hermano_pct', 5, 2)->default(0);
                $table->unsignedInteger('recibo_proximo_num')->default(1);
                $table->unsignedInteger('orden_pago_proximo_num')->default(1);
            });

            DB::table('coop_config')->insert([
                'nombre_institucion' => 'Cooperadora',
                'direccion' => '',
                'localidad' => '',
                'telefono' => '',
                'descuento_hermano_pct' => 0,
                'recibo_proximo_num' => 1,
                'orden_pago_proximo_num' => 1,
            ]);
        }

        if (! Schema::hasTable('coop_rubros_ingreso')) {
            Schema::create('coop_rubros_ingreso', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 120);
                $table->enum('tipo', ['origen_estudiantes', 'otros_origenes']);
                $table->boolean('es_anual')->default(false);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);

                $table->index(['activo', 'orden'], 'idx_coop_rubros_activo_orden');
            });
        }

        if (! Schema::hasTable('coop_items_ingreso')) {
            Schema::create('coop_items_ingreso', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_rubro');
                $table->string('nombre', 120);
                $table->unsignedSmallInteger('anio')->nullable();
                $table->decimal('precio', 12, 2)->default(0);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);

                $table->foreign('id_rubro', 'fk_coop_items_rubro')
                    ->references('id')->on('coop_rubros_ingreso')
                    ->onDelete('cascade');

                $table->unique(['id_rubro', 'nombre', 'anio'], 'uq_coop_items_rubro_nombre_anio');
                $table->index(['id_rubro', 'anio', 'activo'], 'idx_coop_items_rubro_anio');
            });
        }

        if (! Schema::hasTable('coop_proveedores')) {
            Schema::create('coop_proveedores', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 200);
                $table->string('cuit', 20)->nullable();
                $table->string('telefono', 80)->nullable();
                $table->string('email', 120)->nullable();
                $table->string('direccion', 200)->nullable();
                $table->text('observaciones')->nullable();
                $table->boolean('activo')->default(true);

                $table->index(['activo', 'nombre'], 'idx_coop_proveedores_activo_nombre');
            });
        }

        if (! Schema::hasTable('coop_ingresos')) {
            Schema::create('coop_ingresos', function (Blueprint $table) {
                $table->id();
                $table->enum('tipo', ['origen_estudiantes', 'otros_origenes']);
                $table->unsignedBigInteger('id_rubro');
                $table->unsignedBigInteger('id_item')->nullable();
                $table->unsignedInteger('id_legajo')->nullable();
                $table->unsignedInteger('id_matricula')->nullable();
                $table->string('pagador_nombre', 200);
                $table->date('fecha');
                $table->text('concepto');
                $table->decimal('importe_bruto', 12, 2)->nullable();
                $table->decimal('descuento_pct', 5, 2)->default(0);
                $table->decimal('importe', 12, 2);
                $table->string('importe_letras', 255);
                $table->unsignedInteger('recibo_numero');
                $table->string('medio_pago', 80)->nullable();
                $table->unsignedInteger('id_profesor');
                $table->boolean('anulado')->default(false);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_rubro', 'fk_coop_ingresos_rubro')
                    ->references('id')->on('coop_rubros_ingreso')
                    ->onDelete('restrict');

                $table->foreign('id_item', 'fk_coop_ingresos_item')
                    ->references('id')->on('coop_items_ingreso')
                    ->onDelete('set null');

                $table->unique('recibo_numero', 'uq_coop_ingresos_recibo');
                $table->index(['fecha', 'anulado'], 'idx_coop_ingresos_fecha');
                $table->index('id_legajo', 'idx_coop_ingresos_legajo');
            });
        }

        if (! Schema::hasTable('coop_egresos')) {
            Schema::create('coop_egresos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_proveedor');
                $table->date('fecha');
                $table->text('concepto');
                $table->decimal('importe', 12, 2);
                $table->string('importe_letras', 255);
                $table->unsignedInteger('orden_numero');
                $table->string('firmante', 120)->nullable();
                $table->unsignedInteger('id_profesor');
                $table->boolean('anulado')->default(false);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_proveedor', 'fk_coop_egresos_proveedor')
                    ->references('id')->on('coop_proveedores')
                    ->onDelete('restrict');

                $table->unique('orden_numero', 'uq_coop_egresos_orden');
                $table->index(['fecha', 'anulado'], 'idx_coop_egresos_fecha');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coop_egresos');
        Schema::dropIfExists('coop_ingresos');
        Schema::dropIfExists('coop_proveedores');
        Schema::dropIfExists('coop_items_ingreso');
        Schema::dropIfExists('coop_rubros_ingreso');
        Schema::dropIfExists('coop_config');
    }
};
