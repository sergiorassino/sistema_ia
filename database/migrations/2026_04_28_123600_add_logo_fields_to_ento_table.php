<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            if (! Schema::hasColumn('ento', 'logo_path')) {
                $table->string('logo_path', 255)->nullable()->after('replegal');
            }
            if (! Schema::hasColumn('ento', 'logo_original_name')) {
                $table->string('logo_original_name', 255)->nullable()->after('logo_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            if (Schema::hasColumn('ento', 'logo_path')) {
                $table->dropColumn('logo_path');
            }
            if (Schema::hasColumn('ento', 'logo_original_name')) {
                $table->dropColumn('logo_original_name');
            }
        });
    }
};

