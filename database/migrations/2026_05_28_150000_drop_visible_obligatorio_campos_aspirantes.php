<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campos_aspirantes')) {
            return;
        }

        $tieneVisible = Schema::hasColumn('campos_aspirantes', 'visible');
        $tieneObligatorio = Schema::hasColumn('campos_aspirantes', 'obligatorio');

        if (! $tieneVisible && ! $tieneObligatorio) {
            return;
        }

        Schema::table('campos_aspirantes', function (Blueprint $table) use ($tieneVisible, $tieneObligatorio) {
            if ($tieneVisible) {
                $table->dropColumn('visible');
            }
            if ($tieneObligatorio) {
                $table->dropColumn('obligatorio');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('campos_aspirantes')) {
            return;
        }

        Schema::table('campos_aspirantes', function (Blueprint $table) {
            if (! Schema::hasColumn('campos_aspirantes', 'visible')) {
                $table->boolean('visible')->default(false);
            }
            if (! Schema::hasColumn('campos_aspirantes', 'obligatorio')) {
                $table->boolean('obligatorio')->default(false);
            }
        });
    }
};

