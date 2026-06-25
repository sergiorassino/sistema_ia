<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legajos')) {
            return;
        }

        Schema::table('legajos', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('legajos', 'bloqadmi')) {
                $drops[] = 'bloqadmi';
            }
            if (Schema::hasColumn('legajos', 'bloqmatr')) {
                $drops[] = 'bloqmatr';
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('legajos')) {
            return;
        }

        Schema::table('legajos', function (Blueprint $table) {
            if (! Schema::hasColumn('legajos', 'bloqmatr')) {
                $table->unsignedTinyInteger('bloqmatr')->default(0);
            }
            if (! Schema::hasColumn('legajos', 'bloqadmi')) {
                $table->unsignedTinyInteger('bloqadmi')->default(0)->after('bloqmatr');
            }
        });
    }
};
