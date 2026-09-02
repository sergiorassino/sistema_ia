<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sanciontipo')) {
            return;
        }

        if (Schema::hasColumn('sanciontipo', 'enResumenComunicado')) {
            return;
        }

        Schema::table('sanciontipo', function (Blueprint $table) {
            $col = $table->tinyInteger('enResumenComunicado')->default(0);
            if (Schema::hasColumn('sanciontipo', 'permiteNotifPadres')) {
                $col->after('permiteNotifPadres');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sanciontipo')) {
            return;
        }

        if (Schema::hasColumn('sanciontipo', 'enResumenComunicado')) {
            Schema::table('sanciontipo', function (Blueprint $table) {
                $table->dropColumn('enResumenComunicado');
            });
        }
    }
};
