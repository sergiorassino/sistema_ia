<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo permisos_ia superó orden 49 (varchar(50) original truncaba al guardar).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('profesores') || ! Schema::hasColumn('profesores', 'permisos_ia')) {
            return;
        }

        DB::statement('ALTER TABLE `profesores` MODIFY `permisos_ia` VARCHAR(128) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('profesores') || ! Schema::hasColumn('profesores', 'permisos_ia')) {
            return;
        }

        DB::statement('ALTER TABLE `profesores` MODIFY `permisos_ia` VARCHAR(50) NULL DEFAULT NULL');
    }
};
