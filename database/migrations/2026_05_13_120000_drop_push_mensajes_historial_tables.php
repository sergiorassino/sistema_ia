<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El historial de push para familias pasó a comunicaciones (com_mensaje_envios, medio push).
 * Las tablas push_mensajes_* solo alimentaban la pantalla "Mis notificaciones", ya retirada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('push_mensajes_destinatarios');
        Schema::dropIfExists('push_mensajes_enviados');
    }

    public function down(): void
    {
        if (! Schema::hasTable('push_mensajes_enviados')) {
            Schema::create('push_mensajes_enviados', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('titulo', 255);
                $table->text('cuerpo');
                $table->text('url')->nullable();
                $table->string('tipo_destino', 30)->nullable();
                $table->unsignedInteger('id_terlec')->nullable();
                $table->string('id_usuario_envio', 50)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('push_mensajes_destinatarios')) {
            Schema::create('push_mensajes_destinatarios', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_mensaje');
                $table->string('user_key', 50);
                $table->string('estado', 20);
                $table->string('motivo', 255)->nullable();

                $table->index(['user_key']);
                $table->index(['id_mensaje']);
                $table->foreign('id_mensaje')->references('id')->on('push_mensajes_enviados')->onDelete('cascade');
            });
        }
    }
};
