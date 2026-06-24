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
            if (! Schema::hasColumn('ento', 'afipCertCarpeta')) {
                $table->string('afipCertCarpeta', 40)->nullable()->after('ptoVta');
            }
            if (! Schema::hasColumn('ento', 'afipCertKey')) {
                $table->string('afipCertKey', 120)->nullable()->after('afipCertCarpeta');
            }
            if (! Schema::hasColumn('ento', 'afipCertCrt')) {
                $table->string('afipCertCrt', 120)->nullable()->after('afipCertKey');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            foreach (['afipCertCarpeta', 'afipCertKey', 'afipCertCrt'] as $column) {
                if (Schema::hasColumn('ento', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
