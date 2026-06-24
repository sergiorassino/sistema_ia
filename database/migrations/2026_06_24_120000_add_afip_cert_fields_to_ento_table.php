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

        if (! Schema::hasColumn('ento', 'ptoVta')) {
            Schema::table('ento', function (Blueprint $table) {
                $column = $table->unsignedSmallInteger('ptoVta')->nullable();
                if (Schema::hasColumn('ento', 'cuit')) {
                    $column->after('cuit');
                }
            });
        }

        $anclaAfip = Schema::hasColumn('ento', 'ptoVta')
            ? 'ptoVta'
            : (Schema::hasColumn('ento', 'cuit') ? 'cuit' : null);

        Schema::table('ento', function (Blueprint $table) use ($anclaAfip) {
            if (! Schema::hasColumn('ento', 'afipCertCarpeta')) {
                $column = $table->string('afipCertCarpeta', 40)->nullable();
                if ($anclaAfip !== null) {
                    $column->after($anclaAfip);
                }
            }
            if (! Schema::hasColumn('ento', 'afipCertKey')) {
                $column = $table->string('afipCertKey', 120)->nullable();
                if (Schema::hasColumn('ento', 'afipCertCarpeta')) {
                    $column->after('afipCertCarpeta');
                } elseif ($anclaAfip !== null) {
                    $column->after($anclaAfip);
                }
            }
            if (! Schema::hasColumn('ento', 'afipCertCrt')) {
                $column = $table->string('afipCertCrt', 120)->nullable();
                if (Schema::hasColumn('ento', 'afipCertKey')) {
                    $column->after('afipCertKey');
                } elseif (Schema::hasColumn('ento', 'afipCertCarpeta')) {
                    $column->after('afipCertCarpeta');
                } elseif ($anclaAfip !== null) {
                    $column->after($anclaAfip);
                }
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
