<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coop_config')) {
            Schema::table('coop_config', function (Blueprint $table) {
                if (! Schema::hasColumn('coop_config', 'cuit')) {
                    $table->string('cuit', 20)->default('')->after('telefono');
                }
                if (! Schema::hasColumn('coop_config', 'repace')) {
                    $table->string('repace', 80)->default('')->after('cuit');
                }
            });
        }

        if (Schema::hasTable('coop_ingresos')) {
            Schema::table('coop_ingresos', function (Blueprint $table) {
                if (! Schema::hasColumn('coop_ingresos', 'recibo_grupo_id')) {
                    $table->unsignedBigInteger('recibo_grupo_id')->nullable()->after('recibo_numero');
                    $table->index('recibo_grupo_id', 'idx_coop_ingresos_recibo_grupo');
                }
            });

            if ($this->tieneIndiceUnicoRecibo()) {
                Schema::table('coop_ingresos', function (Blueprint $table) {
                    $table->dropUnique('uq_coop_ingresos_recibo');
                });
            }

            if (! $this->tieneIndiceReciboNum()) {
                Schema::table('coop_ingresos', function (Blueprint $table) {
                    $table->index('recibo_numero', 'idx_coop_ingresos_recibo_num');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coop_ingresos')) {
            if ($this->tieneIndiceReciboNum()) {
                Schema::table('coop_ingresos', function (Blueprint $table) {
                    $table->dropIndex('idx_coop_ingresos_recibo_num');
                });
            }

            if (! $this->tieneIndiceUnicoRecibo()) {
                Schema::table('coop_ingresos', function (Blueprint $table) {
                    $table->unique('recibo_numero', 'uq_coop_ingresos_recibo');
                });
            }

            Schema::table('coop_ingresos', function (Blueprint $table) {
                if (Schema::hasColumn('coop_ingresos', 'recibo_grupo_id')) {
                    $table->dropIndex('idx_coop_ingresos_recibo_grupo');
                    $table->dropColumn('recibo_grupo_id');
                }
            });
        }

        if (Schema::hasTable('coop_config')) {
            Schema::table('coop_config', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('coop_config', 'repace')) {
                    $cols[] = 'repace';
                }
                if (Schema::hasColumn('coop_config', 'cuit')) {
                    $cols[] = 'cuit';
                }
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }

    private function tieneIndiceUnicoRecibo(): bool
    {
        $indexes = Schema::getIndexes('coop_ingresos');

        foreach ($indexes as $index) {
            if (($index['name'] ?? '') === 'uq_coop_ingresos_recibo') {
                return true;
            }
        }

        return false;
    }

    private function tieneIndiceReciboNum(): bool
    {
        $indexes = Schema::getIndexes('coop_ingresos');

        foreach ($indexes as $index) {
            if (($index['name'] ?? '') === 'idx_coop_ingresos_recibo_num') {
                return true;
            }
        }

        return false;
    }
};
