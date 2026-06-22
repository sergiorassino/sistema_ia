<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('materias')) {
            return;
        }

        if (! Schema::hasColumn('materias', 'infoCalif')) {
            Schema::table('materias', function (Blueprint $table) {
                $after = Schema::hasColumn('materias', 'esInstitucional') ? 'esInstitucional' : 'abrev';
                $table->unsignedTinyInteger('infoCalif')->default(0)->after($after);
            });
        }

        if (Schema::hasColumn('materias', 'esInstitucional') && Schema::hasColumn('materias', 'infoCalif')) {
            DB::table('materias')
                ->where('esInstitucional', 1)
                ->update(['infoCalif' => 1]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('materias')) {
            return;
        }

        if (! Schema::hasColumn('materias', 'infoCalif')) {
            return;
        }

        Schema::table('materias', function (Blueprint $table) {
            $table->dropColumn('infoCalif');
        });
    }
};
