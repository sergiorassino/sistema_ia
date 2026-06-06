<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('com_auditoria')) {
            return;
        }

        Schema::table('com_auditoria', function (Blueprint $table) {
            if (! Schema::hasColumn('com_auditoria', 'mensaje_remitente_snapshot')) {
                $table->string('mensaje_remitente_snapshot', 200)->nullable()->after('mensaje_fecha_snapshot');
            }
            if (! Schema::hasColumn('com_auditoria', 'mensaje_destinatario_snapshot')) {
                $table->text('mensaje_destinatario_snapshot')->nullable()->after('mensaje_remitente_snapshot');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('com_auditoria')) {
            return;
        }

        Schema::table('com_auditoria', function (Blueprint $table) {
            if (Schema::hasColumn('com_auditoria', 'mensaje_destinatario_snapshot')) {
                $table->dropColumn('mensaje_destinatario_snapshot');
            }
            if (Schema::hasColumn('com_auditoria', 'mensaje_remitente_snapshot')) {
                $table->dropColumn('mensaje_remitente_snapshot');
            }
        });
    }
};
