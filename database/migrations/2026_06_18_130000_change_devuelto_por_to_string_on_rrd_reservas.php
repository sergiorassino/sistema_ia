<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rrd_reservas') || ! Schema::hasColumn('rrd_reservas', 'devuelto_por')) {
            return;
        }

        // Nombre de quien devuelve (texto); antes pudo existir como entero en instalaciones legacy.
        DB::statement(
            'ALTER TABLE `rrd_reservas` MODIFY COLUMN `devuelto_por` VARCHAR(100) NULL'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('rrd_reservas') || ! Schema::hasColumn('rrd_reservas', 'devuelto_por')) {
            return;
        }

        DB::statement(
            'ALTER TABLE `rrd_reservas` MODIFY COLUMN `devuelto_por` INT UNSIGNED NULL'
        );
    }
};
