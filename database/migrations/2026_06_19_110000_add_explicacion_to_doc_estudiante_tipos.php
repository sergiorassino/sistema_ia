<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('doc_estudiante_tipos')) {
            return;
        }

        if (Schema::hasColumn('doc_estudiante_tipos', 'explicacion')) {
            return;
        }

        Schema::table('doc_estudiante_tipos', function (Blueprint $table) {
            $table->string('explicacion', 500)->nullable()->after('etiqueta');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('doc_estudiante_tipos')) {
            return;
        }

        if (! Schema::hasColumn('doc_estudiante_tipos', 'explicacion')) {
            return;
        }

        Schema::table('doc_estudiante_tipos', function (Blueprint $table) {
            $table->dropColumn('explicacion');
        });
    }
};
