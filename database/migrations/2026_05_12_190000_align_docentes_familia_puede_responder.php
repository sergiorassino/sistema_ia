<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los hilos con scope "docentes" se creaban con familia_puede_responder=false sin uso en UI.
 * Al interpretar ese flag como "destinatarios docentes pueden responder", las filas
 * existentes deben quedar en true para no cambiar el comportamiento previo (canal).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('com_hilos') || ! Schema::hasColumn('com_hilos', 'familia_puede_responder')) {
            return;
        }

        DB::table('com_hilos')
            ->where('scope', 'docentes')
            ->update(['familia_puede_responder' => true]);
    }

    public function down(): void
    {
        // Sin reversión fiable del valor anterior por fila.
    }
};
