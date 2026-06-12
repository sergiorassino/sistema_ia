<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('materias')) {
            return;
        }

        if (Schema::hasColumn('materias', 'esInstitucional')) {
            return;
        }

        Schema::table('materias', function (Blueprint $table) {
            $table->unsignedTinyInteger('esInstitucional')->default(0)->after('abrev');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('materias')) {
            return;
        }

        if (! Schema::hasColumn('materias', 'esInstitucional')) {
            return;
        }

        Schema::table('materias', function (Blueprint $table) {
            $table->dropColumn('esInstitucional');
        });
    }
};
