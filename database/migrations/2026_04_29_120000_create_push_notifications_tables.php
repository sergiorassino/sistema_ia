<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions')) {
            return;
        }

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->string('endpoint_hash', 64);
            $table->text('endpoint');
            $table->string('auth_key', 255);
            $table->string('p256dh_key', 255);
            $table->string('user_key', 50)->default(''); // legajo id (string para mantener compatibilidad)
            $table->string('device_type', 20)->nullable(); // mobile|tablet|desktop
            $table->string('user_agent', 512)->nullable();
            $table->string('device_label', 100)->nullable();
            $table->string('client_hints', 512)->nullable(); // JSON string
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->primary(['endpoint_hash', 'user_key']);
            $table->index(['user_key']);
        });

        Schema::create('push_mensajes_enviados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('titulo', 255);
            $table->text('cuerpo');
            $table->text('url')->nullable();
            $table->string('tipo_destino', 30)->nullable(); // alumno|varios_curso|curso|colegio
            $table->unsignedInteger('id_terlec')->nullable();
            $table->string('id_usuario_envio', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('push_mensajes_destinatarios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_mensaje');
            $table->string('user_key', 50);
            $table->string('estado', 20); // enviado|no_enviado
            $table->string('motivo', 255)->nullable();

            $table->index(['user_key']);
            $table->index(['id_mensaje']);
            $table->foreign('id_mensaje')->references('id')->on('push_mensajes_enviados')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_mensajes_destinatarios');
        Schema::dropIfExists('push_mensajes_enviados');
        Schema::dropIfExists('push_subscriptions');
    }
};

