<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calificaciones')) {
            return;
        }

        if (Schema::hasColumn('calificaciones', 'tea')) {
            return;
        }

        Schema::table('calificaciones', function (Blueprint $table) {
            $table->tinyInteger('tea')->default(0)->after('feb');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('calificaciones')) {
            return;
        }

        if (! Schema::hasColumn('calificaciones', 'tea')) {
            return;
        }

        Schema::table('calificaciones', function (Blueprint $table) {
            $table->dropColumn('tea');
        });
    }
};

