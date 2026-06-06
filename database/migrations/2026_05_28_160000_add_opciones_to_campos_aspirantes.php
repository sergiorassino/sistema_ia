<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campos_aspirantes')) {
            return;
        }

        if (Schema::hasColumn('campos_aspirantes', 'opciones')) {
            return;
        }

        Schema::table('campos_aspirantes', function (Blueprint $table) {
            $table->string('opciones', 500)->nullable()->after('etiqueta');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('campos_aspirantes')) {
            return;
        }

        if (! Schema::hasColumn('campos_aspirantes', 'opciones')) {
            return;
        }

        Schema::table('campos_aspirantes', function (Blueprint $table) {
            $table->dropColumn('opciones');
        });
    }
};
