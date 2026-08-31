<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destinatario de facturación AFIP en el legajo (`respAdmiNom`, `respAdmiDni`).
 * Equivalente a database/sql/legajos_resp_admi_idempotente.sql.
 * Se aplica con php artisan migrate
 *   o php artisan se:migrate-legacy --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legajos')) {
            return;
        }

        if (! Schema::hasColumn('legajos', 'respAdmiNom')) {
            Schema::table('legajos', function (Blueprint $table) {
                $col = $table->string('respAdmiNom', 100)->nullable();
                if (Schema::hasColumn('legajos', 'emailtut')) {
                    $col->after('emailtut');
                } elseif (Schema::hasColumn('legajos', 'dnitut')) {
                    $col->after('dnitut');
                }
            });
        }

        if (! Schema::hasColumn('legajos', 'respAdmiDni')) {
            Schema::table('legajos', function (Blueprint $table) {
                $col = $table->string('respAdmiDni', 20)->nullable();
                if (Schema::hasColumn('legajos', 'respAdmiNom')) {
                    $col->after('respAdmiNom');
                }
            });
        }
    }

    public function down(): void
    {
        // No eliminar columnas aditivas de legajos.
    }
};
