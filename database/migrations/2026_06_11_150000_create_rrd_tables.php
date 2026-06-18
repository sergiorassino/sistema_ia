<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // rrd_grupos — categorías de recursos (ej: Electrónica, Espacios)
        // ---------------------------------------------------------------
        if (! Schema::hasTable('rrd_grupos')) {
            Schema::create('rrd_grupos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_nivel');
                $table->string('nombre', 120);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);

                $table->index(['id_nivel', 'orden'], 'idx_rrd_grupos_nivel_orden');
            });
        }

        // ---------------------------------------------------------------
        // rrd_recursos — recursos reservables (notebooks, proyectores, etc.)
        // ---------------------------------------------------------------
        if (! Schema::hasTable('rrd_recursos')) {
            Schema::create('rrd_recursos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_grupo');
                $table->unsignedInteger('id_nivel');
                $table->string('nombre', 120);
                $table->unsignedSmallInteger('antelacion_min_horas')->default(0);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);

                $table->foreign('id_grupo', 'fk_rrd_recursos_grupo')
                    ->references('id')->on('rrd_grupos')
                    ->onDelete('cascade');

                $table->index(['id_nivel', 'activo', 'orden'], 'idx_rrd_recursos_nivel');
                $table->index('id_grupo', 'idx_rrd_recursos_grupo');
            });
        }

        // ---------------------------------------------------------------
        // rrd_recurso_disponibilidad — ventanas horarias por día de semana
        // ---------------------------------------------------------------
        if (! Schema::hasTable('rrd_recurso_disponibilidad')) {
            Schema::create('rrd_recurso_disponibilidad', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_recurso');
                // 1=Lunes … 7=Domingo (ISO 8601)
                $table->unsignedTinyInteger('dia_semana');
                $table->time('hora_inicio');
                $table->time('hora_fin');

                $table->foreign('id_recurso', 'fk_rrd_disp_recurso')
                    ->references('id')->on('rrd_recursos')
                    ->onDelete('cascade');

                $table->index(['id_recurso', 'dia_semana'], 'idx_rrd_disp_recurso_dia');
            });
        }

        // ---------------------------------------------------------------
        // rrd_pedidos — cabecera de una solicitud (puede tener N recursos)
        // ---------------------------------------------------------------
        if (! Schema::hasTable('rrd_pedidos')) {
            Schema::create('rrd_pedidos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_nivel');
                $table->unsignedInteger('id_terlec');
                $table->unsignedInteger('id_profesor');
                $table->date('fecha');
                $table->time('hora_inicio');
                $table->time('hora_fin');
                $table->string('sala_curso_grado', 120)->default('');
                $table->string('auxiliar', 100)->default('');
                $table->text('observaciones')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['id_nivel', 'id_terlec', 'fecha'], 'idx_rrd_pedidos_ctx_fecha');
                $table->index(['id_profesor', 'fecha'], 'idx_rrd_pedidos_prof_fecha');
            });
        }

        // ---------------------------------------------------------------
        // rrd_reservas — un ítem por recurso dentro de un pedido
        // ---------------------------------------------------------------
        if (! Schema::hasTable('rrd_reservas')) {
            Schema::create('rrd_reservas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_pedido');
                $table->unsignedBigInteger('id_recurso');
                $table->unsignedInteger('id_nivel');
                $table->unsignedInteger('id_terlec');
                // Denormalizados para índices y validación de solapamiento
                $table->date('fecha');
                $table->time('hora_inicio');
                $table->time('hora_fin');
                $table->enum('estado', ['pendiente', 'entregado', 'devuelto', 'cancelado'])
                    ->default('pendiente');
                // Entrega
                $table->string('entregado_a', 100)->nullable();
                $table->unsignedInteger('entregado_por')->nullable();
                $table->timestamp('entregado_at')->nullable();
                // Devolución: devuelto_por = quien devuelve; devuelto_a = operador que recibe
                $table->string('devuelto_por', 100)->nullable();
                $table->unsignedInteger('devuelto_a')->nullable();
                $table->timestamp('devuelto_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_pedido', 'fk_rrd_reservas_pedido')
                    ->references('id')->on('rrd_pedidos')
                    ->onDelete('cascade');

                $table->foreign('id_recurso', 'fk_rrd_reservas_recurso')
                    ->references('id')->on('rrd_recursos')
                    ->onDelete('restrict');

                // Índice principal para detección de solapamiento
                $table->index(['id_recurso', 'fecha', 'estado'], 'idx_rrd_reservas_recurso_fecha');
                $table->index(['id_nivel', 'id_terlec', 'fecha'], 'idx_rrd_reservas_ctx_fecha');
                $table->index('id_pedido', 'idx_rrd_reservas_pedido');
            });
        }
    }

    public function down(): void
    {
        // Orden inverso respetando FKs
        Schema::dropIfExists('rrd_reservas');
        Schema::dropIfExists('rrd_pedidos');
        Schema::dropIfExists('rrd_recurso_disponibilidad');
        Schema::dropIfExists('rrd_recursos');
        Schema::dropIfExists('rrd_grupos');
    }
};
