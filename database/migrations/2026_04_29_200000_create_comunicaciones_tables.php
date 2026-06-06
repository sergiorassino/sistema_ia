<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('com_canales')) {
            Schema::create('com_canales', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->enum('rol_emisor', ['directivo', 'preceptor', 'profesor', 'familia']);
                $table->enum('rol_receptor', ['directivo', 'preceptor', 'profesor', 'familia']);
                $table->boolean('puede_iniciar')->default(false);
                $table->boolean('puede_responder')->default(false);
                $table->json('medios_permitidos')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['rol_emisor', 'rol_receptor'], 'uq_canal_par');
            });
        }

        if (! Schema::hasTable('com_hilos')) {
            Schema::create('com_hilos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('asunto', 200);
                $table->unsignedBigInteger('cuerpo_inicial_id')->nullable();
                $table->enum('scope', ['alumno', 'varios_alumnos', 'curso', 'varios_cursos', 'colegio']);
                $table->unsignedInteger('id_legajo')->nullable();
                $table->unsignedInteger('id_curso')->nullable();
                $table->json('cursos_envio')->nullable();
                $table->unsignedInteger('id_nivel')->nullable();
                $table->unsignedInteger('id_terlec')->nullable();
                $table->enum('creado_por_tipo', ['profesor', 'familia']);
                $table->unsignedInteger('creado_por_id');
                $table->string('creado_por_rol', 30)->nullable();
                $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
                $table->boolean('familia_puede_responder')->default(true);
                $table->timestamp('ultimo_mensaje_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->index(['id_nivel', 'id_terlec']);
                $table->index(['id_legajo']);
                $table->index(['id_curso']);
                $table->index(['creado_por_tipo', 'creado_por_id']);
                $table->index('ultimo_mensaje_at');
                $table->index('cuerpo_inicial_id');
            });
        }

        if (! Schema::hasTable('com_mensajes')) {
            Schema::create('com_mensajes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_hilo');
                $table->unsignedBigInteger('id_mensaje_padre')->nullable();
                $table->enum('tipo_remitente', ['profesor', 'familia']);
                $table->unsignedInteger('id_profesor')->nullable();
                $table->unsignedInteger('id_legajo')->nullable();
                $table->string('rol_remitente', 30)->nullable();
                $table->enum('vinculo_familiar', ['madre', 'padre', 'tutor', 'resp_admin', 'otro'])->nullable();
                $table->string('nombre_remitente_snapshot', 150)->nullable();
                $table->string('dni_remitente_snapshot', 20)->nullable();
                $table->text('contenido');
                $table->date('fecha');
                $table->time('hora');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_hilo')->references('id')->on('com_hilos')->onDelete('cascade');
                $table->index(['id_hilo', 'created_at']);
                $table->index(['tipo_remitente', 'id_profesor']);
                $table->index(['tipo_remitente', 'id_legajo']);
            });
        }

        if (! Schema::hasTable('com_mensajes_destinatarios')) {
            Schema::create('com_mensajes_destinatarios', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_mensaje');
                $table->unsignedBigInteger('id_hilo');
                $table->enum('tipo_destinatario', ['profesor', 'familia']);
                $table->unsignedInteger('id_profesor')->nullable();
                $table->unsignedInteger('id_legajo')->nullable();
                $table->string('rol_destinatario', 30)->nullable();
                $table->string('nombre_snapshot', 150)->nullable();
                $table->string('dni_snapshot', 20)->nullable();
                $table->timestamp('leido_at')->nullable();
                $table->timestamp('respondido_at')->nullable();
                $table->unsignedBigInteger('id_mensaje_respuesta')->nullable();

                $table->foreign('id_mensaje')->references('id')->on('com_mensajes')->onDelete('cascade');
                $table->foreign('id_hilo')->references('id')->on('com_hilos')->onDelete('cascade');
                $table->index(['tipo_destinatario', 'id_legajo', 'leido_at'], 'idx_dest_legajo_leido');
                $table->index(['tipo_destinatario', 'id_profesor', 'leido_at'], 'idx_dest_prof_leido');
                $table->index(['id_hilo', 'tipo_destinatario', 'id_legajo'], 'idx_cmd_hilo_tipo_legajo');
                $table->index(['id_hilo', 'tipo_destinatario', 'id_profesor'], 'idx_cmd_hilo_tipo_prof');
            });
        }

        if (! Schema::hasTable('com_mensajes_envios')) {
            Schema::create('com_mensajes_envios', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_mensaje_destinatario');
                $table->enum('medio', ['push', 'email', 'whatsapp']);
                $table->enum('estado', ['pendiente', 'enviado', 'fallido', 'no_aplicable'])->default('pendiente');
                $table->string('motivo', 255)->nullable();
                $table->string('proveedor_msgid', 255)->nullable();
                $table->timestamp('enviado_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_mensaje_destinatario')
                    ->references('id')->on('com_mensajes_destinatarios')->onDelete('cascade');
                $table->index(['id_mensaje_destinatario', 'medio']);
            });
        }

        if (! Schema::hasTable('com_preferencias')) {
            Schema::create('com_preferencias', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->enum('tipo_usuario', ['familia', 'profesor']);
                $table->unsignedInteger('id_legajo')->nullable();
                $table->unsignedInteger('id_profesor')->nullable();
                $table->enum('vinculo_contacto', ['madre', 'padre', 'tutor', 'resp_admin', 'otro'])->nullable();
                $table->boolean('push')->default(true);
                $table->boolean('email')->default(true);
                $table->boolean('whatsapp')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->unique('id_legajo', 'uq_pref_legajo');
                $table->unique('id_profesor', 'uq_pref_profesor');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('com_preferencias');
        Schema::dropIfExists('com_mensajes_envios');
        Schema::dropIfExists('com_mensajes_destinatarios');
        Schema::dropIfExists('com_mensajes');
        Schema::dropIfExists('com_hilos');
        Schema::dropIfExists('com_canales');
    }
};
