<?php

use App\Support\Comunicaciones\ComCanalRolCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('com_canales')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `com_canales` MODIFY `rol_emisor` VARCHAR(64) NOT NULL');
            DB::statement('ALTER TABLE `com_canales` MODIFY `rol_receptor` VARCHAR(64) NOT NULL');
        }

        $legacy = ['directivo', 'preceptor', 'profesor', 'familia'];
        $filas = DB::table('com_canales')
            ->where(function ($q) use ($legacy) {
                $q->whereIn('rol_emisor', $legacy)
                    ->orWhereIn('rol_receptor', $legacy);
            })
            ->get();

        foreach ($filas as $canal) {
            $emisores = $this->expandirClaveLegacy((string) $canal->rol_emisor);
            $receptores = $this->expandirClaveLegacy((string) $canal->rol_receptor);

            DB::table('com_canales')->where('id', $canal->id)->delete();

            foreach ($emisores as $em) {
                foreach ($receptores as $rec) {
                    if ($em === $rec) {
                        continue;
                    }
                    $existe = DB::table('com_canales')
                        ->where('id_nivel', $canal->id_nivel)
                        ->where('rol_emisor', $em)
                        ->where('rol_receptor', $rec)
                        ->exists();
                    if ($existe) {
                        continue;
                    }
                    DB::table('com_canales')->insert([
                        'id_nivel'          => $canal->id_nivel,
                        'rol_emisor'        => $em,
                        'rol_receptor'      => $rec,
                        'puede_iniciar'     => $canal->puede_iniciar,
                        'puede_responder'   => $canal->puede_responder,
                        'medios_permitidos' => $canal->medios_permitidos,
                        'activo'            => $canal->activo,
                        'created_at'        => $canal->created_at,
                        'updated_at'        => now(),
                    ]);
                }
            }
        }

        ComCanalRolCatalog::invalidarCache();
    }

    public function down(): void
    {
        // No revertir: la expansión de filas no es reversible de forma segura sin pérdida de detalle.
    }

    /**
     * @return list<string>
     */
    private function expandirClaveLegacy(string $clave): array
    {
        if ($clave === ComCanalRolCatalog::CLAVE_FAMILIA || $clave === 'familia') {
            return [ComCanalRolCatalog::CLAVE_FAMILIA];
        }

        if (in_array($clave, ['directivo', 'preceptor', 'profesor'], true)) {
            $ids = ComCanalRolCatalog::idsTipoProfConRolCanonicoLegacy($clave);

            return array_map(
                static fn (int $id): string => ComCanalRolCatalog::claveTipoProf($id),
                $ids
            );
        }

        if (str_starts_with($clave, ComCanalRolCatalog::PREFIJO_TIPO)) {
            return [$clave];
        }

        return [];
    }
};
