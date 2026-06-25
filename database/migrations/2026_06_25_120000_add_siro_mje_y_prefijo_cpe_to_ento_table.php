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

        $columnasSiro = [
            'siroMje' => static fn (Blueprint $table) => $table->string('siroMje', 40)->nullable(),
            'siroPrefijoCPE' => static fn (Blueprint $table) => $table->string('siroPrefijoCPE', 2)->nullable(),
            'siroIdentCuenta' => static fn (Blueprint $table) => $table->string('siroIdentCuenta', 20)->nullable(),
        ];

        foreach ($columnasSiro as $nombre => $definicion) {
            if (Schema::hasColumn('ento', $nombre)) {
                continue;
            }

            $ancla = $this->anclaParaColumnaSiro($nombre);

            Schema::table('ento', function (Blueprint $table) use ($definicion, $ancla) {
                $column = $definicion($table);
                if ($ancla !== null) {
                    $column->after($ancla);
                }
            });
        }
    }

    /**
     * @param  list<string>  $candidatas
     */
    private function anclaColumnaEnto(array $candidatas): ?string
    {
        foreach ($candidatas as $columna) {
            if (Schema::hasColumn('ento', $columna)) {
                return $columna;
            }
        }

        return null;
    }

    private function anclaParaColumnaSiro(string $columna): ?string
    {
        return match ($columna) {
            'siroMje' => $this->anclaColumnaEnto(['siroSecu', 'siroIniPrim', 'replegal']),
            'siroPrefijoCPE' => $this->anclaColumnaEnto(['siroMje', 'siroSecu', 'siroIniPrim', 'replegal']),
            'siroIdentCuenta' => $this->anclaColumnaEnto(['siroPrefijoCPE', 'siroMje', 'siroSecu', 'siroIniPrim', 'replegal']),
            default => null,
        };
    }

    public function down(): void
    {
        if (! Schema::hasTable('ento')) {
            return;
        }

        Schema::table('ento', function (Blueprint $table) {
            if (Schema::hasColumn('ento', 'siroPrefijoCPE')) {
                $table->dropColumn('siroPrefijoCPE');
            }
        });

        // siroMje y siroIdentCuenta pueden ser columnas legacy: no eliminar en down.
    }
};
