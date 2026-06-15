<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('matricula')) {
            return;
        }

        Schema::table('matricula', function (Blueprint $table) {
            if (! Schema::hasColumn('matricula', 'coop_es_hermano')) {
                $table->unsignedTinyInteger('coop_es_hermano')->default(0)->after('idCuotasbecas');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('matricula')) {
            return;
        }

        Schema::table('matricula', function (Blueprint $table) {
            if (Schema::hasColumn('matricula', 'coop_es_hermano')) {
                $table->dropColumn('coop_es_hermano');
            }
        });
    }
};
