<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('com_hilos') && ! Schema::hasColumn('com_hilos', 'cursos_envio')) {
            Schema::table('com_hilos', function (Blueprint $table) {
                $table->json('cursos_envio')->nullable()->after('id_curso');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('com_hilos', 'cursos_envio')) {
            Schema::table('com_hilos', function (Blueprint $table) {
                $table->dropColumn('cursos_envio');
            });
        }
    }
};
