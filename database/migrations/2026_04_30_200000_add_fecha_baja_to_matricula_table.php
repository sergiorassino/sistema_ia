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

        if (Schema::hasColumn('matricula', 'fechaBaja')) {
            return;
        }

        Schema::table('matricula', function (Blueprint $table) {
            $table->date('fechaBaja')->nullable()->after('fechaMatricula');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('matricula')) {
            return;
        }

        if (! Schema::hasColumn('matricula', 'fechaBaja')) {
            return;
        }

        Schema::table('matricula', function (Blueprint $table) {
            $table->dropColumn('fechaBaja');
        });
    }
};
