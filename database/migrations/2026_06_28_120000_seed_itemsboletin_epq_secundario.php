<?php

use App\Support\CalificacionesSecundario\Epq\ItemsBoletinEpqSecundarioCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carga ítems del pie del boletín EPQ secundario en {@see itemsboletin}.
 * Solo aplica cuando el despliegue es tenant EPQ (`TENANT_SLUG=epq`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('itemsboletin') || ! $this->esTenantEpq()) {
            return;
        }

        DB::table('itemsboletin')->delete();

        $filas = [];
        foreach (ItemsBoletinEpqSecundarioCatalogo::definiciones() as $def) {
            $filas[] = [
                'orden' => $def['orden'],
                'etiqueta' => $def['etiqueta'],
                'fuente' => $def['fuente'],
                'condicion_where' => $def['condicion_where'],
                'idTerlec' => null,
                'activo' => true,
            ];
        }

        DB::table('itemsboletin')->insert($filas);
    }

    public function down(): void
    {
        if (! Schema::hasTable('itemsboletin') || ! $this->esTenantEpq()) {
            return;
        }

        $etiquetas = array_column(ItemsBoletinEpqSecundarioCatalogo::definiciones(), 'etiqueta');
        DB::table('itemsboletin')->whereIn('etiqueta', $etiquetas)->delete();
    }

    private function esTenantEpq(): bool
    {
        $slug = strtolower(trim((string) env('TENANT_SLUG', '')));
        if ($slug === 'epq') {
            return true;
        }

        return strtolower(trim((string) config('tenant.nombre', ''))) === 'epq';
    }
};
