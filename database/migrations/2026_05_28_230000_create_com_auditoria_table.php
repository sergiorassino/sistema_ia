<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_auditoria')) {
            return;
        }

        Schema::create('com_auditoria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('accion', [
                'marcar_leido',
                'marcar_no_leido',
                'borrar_mensaje',
                'borrar_hilo',
            ]);
            $table->enum('portal', ['secretaria', 'docente', 'familia']);
            $table->enum('tipo_actor', ['profesor', 'familia']);
            $table->enum('actor_categoria', ['estudiante', 'profesor', 'personal']);
            $table->unsignedInteger('id_profesor_actor')->nullable();
            $table->unsignedInteger('id_legajo_actor')->nullable();
            $table->string('nombre_actor_snapshot', 150);
            $table->string('dni_actor_snapshot', 20)->nullable();
            $table->unsignedBigInteger('id_hilo');
            $table->string('hilo_asunto_snapshot', 200);
            $table->unsignedBigInteger('id_mensaje')->nullable();
            $table->text('mensaje_contenido_snapshot')->nullable();
            $table->date('mensaje_fecha_snapshot')->nullable();
            $table->string('mensaje_remitente_snapshot', 200)->nullable();
            $table->text('mensaje_destinatario_snapshot')->nullable();
            $table->unsignedInteger('id_nivel');
            $table->unsignedInteger('id_terlec');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['id_nivel', 'id_terlec', 'created_at'], 'idx_com_aud_nivel_terlec');
            $table->index(['tipo_actor', 'id_profesor_actor', 'created_at'], 'idx_com_aud_prof');
            $table->index(['tipo_actor', 'id_legajo_actor', 'created_at'], 'idx_com_aud_legajo');
            $table->index(['actor_categoria', 'created_at'], 'idx_com_aud_categoria');
            $table->index(['accion', 'created_at'], 'idx_com_aud_accion');
            $table->index('id_hilo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('com_auditoria');
    }
};
