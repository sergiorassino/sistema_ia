<?php

namespace App\Support\Mora;

use App\Models\CuotaGenerada;
use App\Models\Ento;
use App\Support\SchoolAlcancePedagogico;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de la constancia de libre deuda (cuotas internas, un estudiante).
 */
final class LibreDeudaFamiliarDatos
{
    /**
     * @param  array<int>  $idsLegajos
     * @return array<int, true>
     */
    public static function idsConDeuda(array $idsLegajos): array
    {
        $idsLegajos = array_values(array_unique(array_filter(
            array_map('intval', $idsLegajos),
            fn (int $id) => $id > 0,
        )));

        if ($idsLegajos === []) {
            return [];
        }

        $marcados = [];
        $ids = CuotaGenerada::query()
            ->whereIn('idLegajos', $idsLegajos)
            ->where('faltapa', '>', 0)
            ->where('importe', '>', 0)
            ->distinct()
            ->pluck('idLegajos');

        foreach ($ids as $id) {
            $marcados[(int) $id] = true;
        }

        return $marcados;
    }

    public static function tieneDeuda(int $idLegajo): bool
    {
        if ($idLegajo <= 0) {
            return true;
        }

        return CuotaGenerada::query()
            ->where('idLegajos', $idLegajo)
            ->where('faltapa', '>', 0)
            ->where('importe', '>', 0)
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function paraEstudiante(int $idLegajo): ?array
    {
        $legajo = EstadoDeudaEstudianteListado::estudianteEnAlcance($idLegajo);
        if ($legajo === null) {
            return null;
        }

        $idTerlec = (int) schoolCtx()->idTerlec;
        $legajo->load([
            'matriculas' => function ($matricula) use ($idTerlec) {
                $matricula->where('idTerlec', $idTerlec);
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($matricula, 'idNivel');
                $matricula->with([
                    'nivel:id,nivel',
                    'curso:Id,cursec,c,s,idCurPlan,idTurnoClase,idNivel',
                    'curso.curplan:id,curPlanCurso',
                    'curso.turnoClase:id,nombre',
                ]);
            },
        ]);

        $matricula = $legajo->matriculas->first();
        $curso = $matricula?->curso;
        $cursec = mb_strtoupper(trim((string) ($curso?->nombreParaListado() ?? '')));
        $cursecCrudo = trim((string) ($curso?->cursec ?? ''));
        if ($cursec === '0' || $cursecCrudo === '0') {
            $cursec = '';
        }

        $nivel = trim((string) ($matricula?->nivel?->nivel ?? ''));
        $idNivel = (int) ($matricula?->idNivel ?? $curso?->idNivel ?? 0);
        $ento = self::entoDelNivel($idNivel);

        $apellido = trim((string) ($legajo->apellido ?? ''));
        $nombre = trim((string) ($legajo->nombre ?? ''));

        return [
            'apellido' => $apellido,
            'nombre' => $nombre,
            'apenom' => trim($apellido.' '.$nombre),
            'dni' => trim((string) ($legajo->dni ?? '')),
            'cursec' => $cursec,
            'nivel' => $nivel,
            'fecha' => Carbon::today()->format('d/m/Y'),
            'lugar' => trim((string) ($ento['localidad'] ?? '')),
            'replegal' => self::replegalInstitucional($ento),
            'firma_file' => self::firmaAbsoluta(),
            'header' => [
                'insti' => trim((string) ($ento['insti'] ?? '')),
                'direccion' => trim((string) ($ento['direccion'] ?? '')),
                'cuit' => trim((string) ($ento['cuit'] ?? '')),
                'condicion_iva' => trim((string) ($ento['condicionIva'] ?? '')),
                'ingresos_brutos' => trim((string) ($ento['ingresosBrutos'] ?? '')),
                'logo_file' => $ento['logo_file'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function entoDelNivel(int $idNivel): array
    {
        if ($idNivel <= 0 || ! Schema::hasTable('ento')) {
            return [];
        }

        $existentes = [];
        foreach (['insti', 'direccion', 'localidad', 'cuit', 'condicionIva', 'ingresosBrutos', 'replegal', 'logo_path'] as $buscada) {
            $columna = self::columnaEnto($buscada);
            if ($columna !== null) {
                $existentes[$buscada] = $columna;
            }
        }

        if ($existentes === []) {
            return [];
        }

        $ento = Ento::query()->where('idNivel', $idNivel)->first(array_values($existentes));
        if ($ento === null) {
            return [];
        }

        $datos = [];
        foreach ($existentes as $logica => $columna) {
            $datos[$logica] = $ento->{$columna};
        }

        $datos['logo_file'] = self::logoAbsoluto(trim((string) ($datos['logo_path'] ?? '')));

        return $datos;
    }

    /**
     * Logo del colegio (`ento.logo_path` del nivel; si no hay archivo, el de otro nivel o el de login).
     */
    private static function logoAbsoluto(string $delNivel): ?string
    {
        $abs = self::archivoLogo($delNivel);
        if ($abs !== null) {
            return $abs;
        }

        foreach (['logo_path', 'logo_login_path'] as $buscada) {
            $columna = self::columnaEnto($buscada);
            if ($columna === null) {
                continue;
            }

            $filas = Ento::query()->orderBy(self::columnaEnto('idNivel') ?? 'idNivel')->get([$columna]);
            foreach ($filas as $fila) {
                $abs = self::archivoLogo(trim((string) ($fila->{$columna} ?? '')));
                if ($abs !== null) {
                    return $abs;
                }
            }
        }

        return null;
    }

    private static function archivoLogo(string $relativo): ?string
    {
        $relativo = ltrim(str_replace('\\', '/', trim($relativo)), '/');
        if ($relativo === '' || str_contains($relativo, '..')) {
            return null;
        }

        if (function_exists('publicStorageRelativePathExists')) {
            publicStorageRelativePathExists($relativo);
        }

        foreach ([
            Storage::disk('public')->path($relativo),
            public_path('storage/'.$relativo),
        ] as $abs) {
            if ($abs !== '' && is_file($abs)) {
                return $abs;
            }
        }

        return null;
    }

    /**
     * Nombre del representante legal (`ento.replegal`).
     * Primero el del nivel de la matrícula; si está vacío, el de otro nivel que sí lo tenga cargado.
     *
     * @param  array<string, mixed>  $entoNivel
     */
    private static function replegalInstitucional(array $entoNivel): string
    {
        $delNivel = trim((string) ($entoNivel['replegal'] ?? ''));
        if ($delNivel !== '') {
            return $delNivel;
        }

        $columna = self::columnaEnto('replegal');
        if ($columna === null) {
            return '';
        }

        $columnaNivel = self::columnaEnto('idNivel') ?? 'idNivel';
        $filas = Ento::query()->orderBy($columnaNivel)->get([$columnaNivel, $columna]);
        foreach ($filas as $fila) {
            $nombre = trim((string) ($fila->{$columna} ?? ''));
            if ($nombre !== '') {
                return $nombre;
            }
        }

        return '';
    }

    private static function columnaEnto(string $buscada): ?string
    {
        if (! Schema::hasTable('ento')) {
            return null;
        }

        foreach (Schema::getColumnListing('ento') as $columna) {
            if (strcasecmp($columna, $buscada) === 0) {
                return $columna;
            }
        }

        return null;
    }

    private static function firmaAbsoluta(): ?string
    {
        $abs = public_path('img/firmaRepLegal.jpg');

        return is_file($abs) ? $abs : null;
    }
}
