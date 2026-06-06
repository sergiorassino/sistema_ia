<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campos_aspirantes_nivel')) {
            return;
        }

        Schema::table('campos_aspirantes_nivel', function (Blueprint $table) {
            if (! Schema::hasColumn('campos_aspirantes_nivel', 'etiqueta')) {
                $table->string('etiqueta', 100)->nullable()->after('obligatorio');
            }
            if (! Schema::hasColumn('campos_aspirantes_nivel', 'opciones')) {
                $table->string('opciones', 500)->nullable()->after('etiqueta');
            }
        });

        $tieneEtiquetaGlobal = Schema::hasTable('campos_aspirantes')
            && Schema::hasColumn('campos_aspirantes', 'etiqueta');
        $tieneOpcionesGlobal = Schema::hasTable('campos_aspirantes')
            && Schema::hasColumn('campos_aspirantes', 'opciones');

        if ($tieneEtiquetaGlobal || $tieneOpcionesGlobal) {
            $sets = [];
            if ($tieneEtiquetaGlobal && Schema::hasColumn('campos_aspirantes_nivel', 'etiqueta')) {
                $sets[] = 'cn.etiqueta = COALESCE(cn.etiqueta, ca.etiqueta)';
            }
            if ($tieneOpcionesGlobal && Schema::hasColumn('campos_aspirantes_nivel', 'opciones')) {
                $sets[] = 'cn.opciones = COALESCE(cn.opciones, ca.opciones)';
            }
            if ($sets !== []) {
                DB::statement(
                    'UPDATE campos_aspirantes_nivel cn '
                    .'INNER JOIN campos_aspirantes ca ON ca.id = cn.campo_aspirante_id '
                    .'SET '.implode(', ', $sets)
                );
            }
        }

        if (! Schema::hasTable('campos_aspirantes')) {
            return;
        }

        Schema::table('campos_aspirantes', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('campos_aspirantes', 'opciones')) {
                $drop[] = 'opciones';
            }
            if (Schema::hasColumn('campos_aspirantes', 'etiqueta')) {
                $drop[] = 'etiqueta';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('campos_aspirantes')) {
            Schema::table('campos_aspirantes', function (Blueprint $table) {
                if (! Schema::hasColumn('campos_aspirantes', 'etiqueta')) {
                    $table->string('etiqueta', 100)->nullable()->after('columna');
                }
                if (! Schema::hasColumn('campos_aspirantes', 'opciones')) {
                    $table->string('opciones', 500)->nullable()->after('etiqueta');
                }
            });
        }

        if (! Schema::hasTable('campos_aspirantes_nivel')) {
            return;
        }

        Schema::table('campos_aspirantes_nivel', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('campos_aspirantes_nivel', 'opciones')) {
                $drop[] = 'opciones';
            }
            if (Schema::hasColumn('campos_aspirantes_nivel', 'etiqueta')) {
                $drop[] = 'etiqueta';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
