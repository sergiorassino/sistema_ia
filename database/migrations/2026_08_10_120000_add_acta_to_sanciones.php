<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sanciones')) {
            return;
        }

        if (Schema::hasColumn('sanciones', 'acta')) {
            return;
        }

        Schema::table('sanciones', function (Blueprint $table) {
            $column = $table->mediumText('acta')->nullable();
            if (Schema::hasColumn('sanciones', 'motivo')) {
                $column->after('motivo');
            } elseif (Schema::hasColumn('sanciones', 'solipor')) {
                $column->after('solipor');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sanciones')) {
            return;
        }

        if (! Schema::hasColumn('sanciones', 'acta')) {
            return;
        }

        Schema::table('sanciones', function (Blueprint $table) {
            $table->dropColumn('acta');
        });
    }
};
