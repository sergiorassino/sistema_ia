<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los enlaces manuales (wa.me o web.whatsapp.com/send con texto largo) superan VARCHAR(255).
     */
    public function up(): void
    {
        Schema::table('com_mensajes_envios', function (Blueprint $table) {
            $table->text('proveedor_msgid')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('com_mensajes_envios', function (Blueprint $table) {
            $table->string('proveedor_msgid', 255)->nullable()->change();
        });
    }
};
