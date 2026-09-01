<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Habilita foto carnet en el ABM de legajos (Secretaría) para IESS.
 *
 * Equivalente a database/sql/campos_legajo_foto_carnet_solapa_idempotente.sql.
 * Solo aplica con TENANT_SLUG=iess. Autogestión familia no se activa.
 *
 * Si fotoCarnet ya está en otra solapa, no la mueve.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->esTenantIess()) {
            return;
        }

        $this->asegurarColumnaFotoCarnet();
        $this->asegurarSolapaYCampo();
    }

    public function down(): void
    {
        // No revertir columna ni parametrización: la solapa puede tener fotos en disco.
    }

    private function esTenantIess(): bool
    {
        return strtolower(trim((string) config('tenant.slug', ''))) === 'iess';
    }

    private function asegurarColumnaFotoCarnet(): void
    {
        if (! Schema::hasTable('legajos') || Schema::hasColumn('legajos', 'fotoCarnet')) {
            return;
        }

        Schema::table('legajos', function (Blueprint $table) {
            $table->string('fotoCarnet', 255)->nullable();
        });
    }

    private function asegurarSolapaYCampo(): void
    {
        if (! Schema::hasTable('solapas_legajo') || ! Schema::hasTable('campos_legajo')) {
            return;
        }

        if (! DB::table('solapas_legajo')->where('slug', 'foto_carnet')->exists()) {
            $maxOrden = (int) DB::table('solapas_legajo')->max('orden');
            DB::table('solapas_legajo')->insert([
                'nombre' => 'Foto Carnet',
                'slug' => 'foto_carnet',
                'orden' => $maxOrden + 1,
            ]);
        }

        $idSolapa = DB::table('solapas_legajo')->where('slug', 'foto_carnet')->value('id');
        if ($idSolapa === null) {
            return;
        }

        $ordenCol = 0;
        if (Schema::hasColumn('legajos', 'fotoCarnet')) {
            $idx = array_search('fotoCarnet', Schema::getColumnListing('legajos'), true);
            $ordenCol = $idx === false ? 0 : $idx + 1;
        }

        $campo = DB::table('campos_legajo')->where('columna', 'fotoCarnet')->first();
        if ($campo === null) {
            DB::table('campos_legajo')->insert([
                'columna' => 'fotoCarnet',
                'etiqueta' => 'Foto Carnet',
                'visible_listado' => 1,
                'orden' => $ordenCol,
                'solapa_legajo_id' => $idSolapa,
                'orden_en_solapa' => 1,
            ]);

            return;
        }

        if ($campo->solapa_legajo_id !== null) {
            return;
        }

        $etiqueta = trim((string) ($campo->etiqueta ?? ''));
        $ordenEnSolapa = (int) ($campo->orden_en_solapa ?? 0);

        DB::table('campos_legajo')
            ->where('columna', 'fotoCarnet')
            ->update([
                'solapa_legajo_id' => $idSolapa,
                'orden_en_solapa' => $ordenEnSolapa === 0 ? 1 : $ordenEnSolapa,
                'etiqueta' => $etiqueta === '' ? 'Foto Carnet' : $etiqueta,
            ]);
    }
};
