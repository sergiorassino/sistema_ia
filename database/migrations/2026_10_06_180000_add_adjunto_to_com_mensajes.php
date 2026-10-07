<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('com_mensajes')) {
            return;
        }

        Schema::table('com_mensajes', function (Blueprint $table) {
            if (! Schema::hasColumn('com_mensajes', 'adjunto_nombre')) {
                $table->string('adjunto_nombre', 255)->nullable()->after('hora');
            }
            if (! Schema::hasColumn('com_mensajes', 'adjunto_ruta')) {
                $table->string('adjunto_ruta', 500)->nullable()->after('adjunto_nombre');
            }
            if (! Schema::hasColumn('com_mensajes', 'adjunto_mime')) {
                $table->string('adjunto_mime', 100)->nullable()->after('adjunto_ruta');
            }
            if (! Schema::hasColumn('com_mensajes', 'adjunto_bytes')) {
                $table->unsignedInteger('adjunto_bytes')->nullable()->after('adjunto_mime');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('com_mensajes')) {
            return;
        }

        Schema::table('com_mensajes', function (Blueprint $table) {
            foreach (['adjunto_nombre', 'adjunto_ruta', 'adjunto_mime', 'adjunto_bytes'] as $col) {
                if (Schema::hasColumn('com_mensajes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
