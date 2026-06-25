<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Si ya se aplicó la migración anterior con nombre siro_deudas_subida, renombra y completa columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('siro_deudas_subida') || Schema::hasTable('cupones_a_pagar')) {
            return;
        }

        Schema::rename('siro_deudas_subida', 'cupones_a_pagar');

        Schema::table('cupones_a_pagar', function (Blueprint $table) {
            if (! Schema::hasColumn('cupones_a_pagar', 'origen')) {
                $table->string('origen', 24)->default('subida_siro')->after('ult_upload');
            }
        });

        if (Schema::hasColumn('cupones_a_pagar', 'fecha_subida') && ! Schema::hasColumn('cupones_a_pagar', 'fecha_emision')) {
            DB::statement('ALTER TABLE `cupones_a_pagar` CHANGE `fecha_subida` `fecha_emision` DATETIME NOT NULL');
        }

        if (Schema::hasColumn('cupones_a_pagar', 'nombre_archivo') && ! Schema::hasColumn('cupones_a_pagar', 'nombre_archivo_siro')) {
            DB::statement('ALTER TABLE `cupones_a_pagar` CHANGE `nombre_archivo` `nombre_archivo_siro` VARCHAR(64) NULL DEFAULT NULL');
        }

        DB::table('cupones_a_pagar')->whereNull('origen')->orWhere('origen', '')->update(['origen' => 'subida_siro']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cupones_a_pagar') || Schema::hasTable('siro_deudas_subida')) {
            return;
        }

        if (Schema::hasColumn('cupones_a_pagar', 'fecha_emision') && ! Schema::hasColumn('cupones_a_pagar', 'fecha_subida')) {
            DB::statement('ALTER TABLE `cupones_a_pagar` CHANGE `fecha_emision` `fecha_subida` DATETIME NOT NULL');
        }

        if (Schema::hasColumn('cupones_a_pagar', 'nombre_archivo_siro') && ! Schema::hasColumn('cupones_a_pagar', 'nombre_archivo')) {
            DB::statement('ALTER TABLE `cupones_a_pagar` CHANGE `nombre_archivo_siro` `nombre_archivo` VARCHAR(64) NULL DEFAULT NULL');
        }

        Schema::table('cupones_a_pagar', function (Blueprint $table) {
            if (Schema::hasColumn('cupones_a_pagar', 'origen')) {
                $table->dropColumn('origen');
            }
        });

        Schema::rename('cupones_a_pagar', 'siro_deudas_subida');
    }
};
