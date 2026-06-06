<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_hilos') && ! Schema::hasColumn('com_hilos', 'familia_puede_responder')) {
            Schema::table('com_hilos', function (Blueprint $table) {
                $table->boolean('familia_puede_responder')->default(true)->after('estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('com_hilos', 'familia_puede_responder')) {
            Schema::table('com_hilos', function (Blueprint $table) {
                $table->dropColumn('familia_puede_responder');
            });
        }
    }
};
