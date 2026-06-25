<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rendicionesroela') || Schema::hasColumn('rendicionesroela', 'obs')) {
            return;
        }

        Schema::table('rendicionesroela', function (Blueprint $table) {
            $table->string('obs', 500)->nullable()->after('impactado');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rendicionesroela') || ! Schema::hasColumn('rendicionesroela', 'obs')) {
            return;
        }

        Schema::table('rendicionesroela', function (Blueprint $table) {
            $table->dropColumn('obs');
        });
    }
};
