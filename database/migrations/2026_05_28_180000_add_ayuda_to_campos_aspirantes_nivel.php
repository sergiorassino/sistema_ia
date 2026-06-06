<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campos_aspirantes_nivel')) {
            return;
        }

        Schema::table('campos_aspirantes_nivel', function (Blueprint $table) {
            if (! Schema::hasColumn('campos_aspirantes_nivel', 'ayuda')) {
                $table->string('ayuda', 500)->nullable()->after('opciones');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('campos_aspirantes_nivel')) {
            return;
        }

        Schema::table('campos_aspirantes_nivel', function (Blueprint $table) {
            if (Schema::hasColumn('campos_aspirantes_nivel', 'ayuda')) {
                $table->dropColumn('ayuda');
            }
        });
    }
};
