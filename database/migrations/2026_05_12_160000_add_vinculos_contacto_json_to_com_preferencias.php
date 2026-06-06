<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('com_preferencias')
            || Schema::hasColumn('com_preferencias', 'vinculos_contacto')) {
            return;
        }

        Schema::table('com_preferencias', function (Blueprint $table) {
            $table->json('vinculos_contacto')->nullable()->after('vinculo_contacto');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('com_preferencias')
            || ! Schema::hasColumn('com_preferencias', 'vinculos_contacto')) {
            return;
        }

        Schema::table('com_preferencias', function (Blueprint $table) {
            $table->dropColumn('vinculos_contacto');
        });
    }
};
