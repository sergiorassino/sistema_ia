<?php

use App\Support\PermisosIaCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Nuevas columnas en sanciontipo
        if (Schema::hasTable('sanciontipo')) {
            Schema::table('sanciontipo', function (Blueprint $table) {
                if (! Schema::hasColumn('sanciontipo', 'textoNotifPadres')) {
                    $table->text('textoNotifPadres')->nullable()->after('tipo');
                }
                if (! Schema::hasColumn('sanciontipo', 'idProfesorNotif')) {
                    $table->unsignedInteger('idProfesorNotif')->nullable()->after('textoNotifPadres');
                }
                if (! Schema::hasColumn('sanciontipo', 'refuerzoMail')) {
                    $table->tinyInteger('refuerzoMail')->default(0)->after('idProfesorNotif');
                }
                if (! Schema::hasColumn('sanciontipo', 'permiteNotifPadres')) {
                    $table->tinyInteger('permiteNotifPadres')->default(1)->after('refuerzoMail');
                }
            });
        }

        // 2. Nueva columna en sanciones
        if (Schema::hasTable('sanciones')) {
            Schema::table('sanciones', function (Blueprint $table) {
                if (! Schema::hasColumn('sanciones', 'comunicadaPadres')) {
                    $table->tinyInteger('comunicadaPadres')->default(0)->after('publicada');
                }
            });
        }

        // 3. Permiso para ABM de tipos de sanción
        if (! Schema::hasTable('permisos_ia')) {
            return;
        }

        foreach (PermisosIaCatalog::definicionCatalogo() as $permiso) {
            if ((int) $permiso['orden'] !== PermisosIaCatalog::SANCION_TIPOS_CONFIG) {
                continue;
            }
            DB::table('permisos_ia')->updateOrInsert(['id' => $permiso['id']], $permiso);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sanciontipo')) {
            Schema::table('sanciontipo', function (Blueprint $table) {
                foreach (['textoNotifPadres', 'idProfesorNotif', 'refuerzoMail', 'permiteNotifPadres'] as $col) {
                    if (Schema::hasColumn('sanciontipo', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('sanciones')) {
            Schema::table('sanciones', function (Blueprint $table) {
                if (Schema::hasColumn('sanciones', 'comunicadaPadres')) {
                    $table->dropColumn('comunicadaPadres');
                }
            });
        }

        if (Schema::hasTable('permisos_ia')) {
            DB::table('permisos_ia')->where('id', 91)->delete();
        }
    }
};
