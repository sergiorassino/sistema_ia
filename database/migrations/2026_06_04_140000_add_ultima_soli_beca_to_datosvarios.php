<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('datosvarios')) {
            return;
        }

        if (! Schema::hasColumn('datosvarios', 'ultimaSoliBeca')) {
            Schema::table('datosvarios', function (Blueprint $table) {
                $table->unsignedInteger('ultimaSoliBeca')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('datosvarios') && Schema::hasColumn('datosvarios', 'ultimaSoliBeca')) {
            Schema::table('datosvarios', function (Blueprint $table) {
                $table->dropColumn('ultimaSoliBeca');
            });
        }
    }
};
