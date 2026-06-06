<?php

namespace App\Comunicaciones;

use App\Models\ComCanal;
use App\Models\Profesor;
use App\Models\ProfesorTipo;
use App\Support\Comunicaciones\ComCanalRolCatalog;
use Illuminate\Support\Facades\Cache;

class CanalesPolicy
{
    private const CACHE_TTL = 60; // segundos

    /**
     * Nivel activo: parámetro explícito, contexto de secretaría o portal familia.
     */
    public static function resolveIdNivel(?int $idNivel = null): int
    {
        if ($idNivel !== null && $idNivel > 0) {
            return $idNivel;
        }

        $fromSchool = (int) (schoolCtx()->idNivel ?? 0);
        if ($fromSchool > 0) {
            return $fromSchool;
        }

        return (int) (studentCtx()->idNivel ?? 0);
    }

    /**
     * Normaliza el tipo de un profesor (desde profesortipo.tipo) al rol del canal legacy.
     *
     * Se conserva para hilos antiguos, búsquedas y compatibilidad; los canales usan `tipo:{id}`.
     */
    public static function normalizarRolProfesor(?string $tipo): string
    {
        if ($tipo === null || $tipo === '') {
            return 'profesor';
        }

        $tipo = mb_strtolower(trim($tipo));

        if (str_contains($tipo, 'direct') || str_contains($tipo, 'secret')) {
            return 'directivo';
        }
        if (str_contains($tipo, 'preceptor') || str_contains($tipo, 'preceptora')) {
            return 'preceptor';
        }

        return 'profesor';
    }

    /**
     * Clasifica un `profesortipo.tipo` para el selector de destinatarios docentes
     * en «Nuevo comunicado» (botones Profesores / Personal).
     *
     * @return 'profesor'|'institucional'|null  null = excluir del selector (p. ej. «Sin Rol»).
     */
    public static function modoSelectorNuevoComunicadoDocente(?string $tipo): ?string
    {
        if ($tipo === null || trim($tipo) === '') {
            return null;
        }

        $t = mb_strtolower(trim($tipo));

        if (str_contains($t, 'sin rol')) {
            return null;
        }

        if (str_contains($t, 'direct') || str_contains($t, 'secret')) {
            return 'institucional';
        }
        if (str_contains($t, 'preceptor')) {
            return 'institucional';
        }
        if (str_contains($t, 'bibliotec')) {
            return 'institucional';
        }
        if (str_contains($t, 'no docente')) {
            return 'institucional';
        }

        if (str_contains($t, 'atp') || str_contains($t, 'doe')) {
            return 'profesor';
        }
        if (str_contains($t, 'profesor')) {
            return 'profesor';
        }

        return null;
    }

    /**
     * Clave de canal del profesor (`tipo:{IdTipoProf}`).
     */
    public static function claveRolDeProfesor(Profesor $profesor): string
    {
        $idTipo = (int) ($profesor->IdTipoProf ?? 0);
        $clave  = ComCanalRolCatalog::claveDeIdTipoProf($idTipo);
        if ($clave !== null) {
            return $clave;
        }

        $tipo = (string) ($profesor->tipo?->tipo ?? '');
        $canon = static::normalizarRolProfesor($tipo);
        $ids   = ComCanalRolCatalog::idsTipoProfConRolCanonicoLegacy($canon);

        return $ids !== []
            ? ComCanalRolCatalog::claveTipoProf($ids[0])
            : ComCanalRolCatalog::claveTipoProf(6);
    }

    /**
     * Rol canónico legacy (directivo|preceptor|profesor) para metadatos de hilos antiguos.
     */
    public static function rolDeProfesor(Profesor $profesor): string
    {
        $tipo = (string) ($profesor->tipo?->tipo ?? '');

        return static::normalizarRolProfesor($tipo);
    }

    public static function claveRolDeIdTipoProf(int $idTipoProf): ?string
    {
        return ComCanalRolCatalog::claveDeIdTipoProf($idTipoProf);
    }

    /**
     * Obtiene el canal entre dos roles para un nivel, con caché.
     */
    public static function obtenerCanal(string $rolEmisor, string $rolReceptor, ?int $idNivel = null): ?ComCanal
    {
        $idNivel = static::resolveIdNivel($idNivel);
        if ($idNivel <= 0) {
            return null;
        }

        $cacheKey = "com_canal:{$idNivel}:{$rolEmisor}:{$rolReceptor}";

        return Cache::remember($cacheKey, static::CACHE_TTL, function () use ($rolEmisor, $rolReceptor, $idNivel) {
            $canal = ComCanal::query()
                ->where('id_nivel', $idNivel)
                ->where('rol_emisor', $rolEmisor)
                ->where('rol_receptor', $rolReceptor)
                ->where('activo', true)
                ->first();

            if ($canal !== null) {
                return $canal;
            }

            $emLegacy = ComCanalRolCatalog::rolCanonicoLegacy($rolEmisor);
            $recLegacy = ComCanalRolCatalog::rolCanonicoLegacy($rolReceptor);
            if ($emLegacy === null || $recLegacy === null) {
                return null;
            }
            if ($emLegacy === $rolEmisor && $recLegacy === $rolReceptor) {
                return null;
            }

            return ComCanal::query()
                ->where('id_nivel', $idNivel)
                ->where('rol_emisor', $emLegacy)
                ->where('rol_receptor', $recLegacy)
                ->where('activo', true)
                ->first();
        });
    }

    public static function puedeIniciar(string $rolEmisor, string $rolReceptor, ?int $idNivel = null): bool
    {
        return (bool) static::obtenerCanal($rolEmisor, $rolReceptor, $idNivel)?->puede_iniciar;
    }

    public static function puedeResponder(string $rolEmisor, string $rolReceptor, ?int $idNivel = null): bool
    {
        return (bool) static::obtenerCanal($rolEmisor, $rolReceptor, $idNivel)?->puede_responder;
    }

    /**
     * Medios permitidos por el canal (intersección con los activos en el sistema).
     *
     * @return list<string>
     */
    public static function mediosPermitidos(string $rolEmisor, string $rolReceptor, ?int $idNivel = null): array
    {
        $canal = static::obtenerCanal($rolEmisor, $rolReceptor, $idNivel);
        if ($canal === null) {
            return [];
        }
        $medios = $canal->medios_permitidos ?? [];
        $disponibles = ComCanal::mediosDisponibles();

        return array_values(array_intersect($medios, $disponibles));
    }

    /** Invalida la caché de un par de roles en un nivel */
    public static function invalidar(string $rolEmisor, string $rolReceptor, ?int $idNivel = null): void
    {
        $idNivel = static::resolveIdNivel($idNivel);
        if ($idNivel <= 0) {
            return;
        }

        Cache::forget("com_canal:{$idNivel}:{$rolEmisor}:{$rolReceptor}");

        $emLegacy = ComCanalRolCatalog::rolCanonicoLegacy($rolEmisor);
        $recLegacy = ComCanalRolCatalog::rolCanonicoLegacy($rolReceptor);
        if ($emLegacy !== null && $recLegacy !== null && ($emLegacy !== $rolEmisor || $recLegacy !== $rolReceptor)) {
            Cache::forget("com_canal:{$idNivel}:{$emLegacy}:{$recLegacy}");
        }
    }

    /**
     * Claves receptoras (`familia` o `tipo:{id}`) a las que el emisor puede iniciar conversación.
     *
     * @return list<string>
     */
    public static function receptoresPermitidosParaIniciar(string $rolEmisor, ?int $idNivel = null): array
    {
        $idNivel = static::resolveIdNivel($idNivel);
        if ($idNivel <= 0) {
            return [];
        }

        return ComCanal::query()
            ->where('id_nivel', $idNivel)
            ->where('rol_emisor', $rolEmisor)
            ->where('puede_iniciar', true)
            ->where('activo', true)
            ->pluck('rol_receptor')
            ->all();
    }

    public static function emisorPuedeIniciarHaciaIdTipoProf(string $rolEmisor, int $idTipoProf, ?int $idNivel = null): bool
    {
        $clave = ComCanalRolCatalog::claveDeIdTipoProf($idTipoProf);
        if ($clave === null) {
            return false;
        }

        return static::puedeIniciar($rolEmisor, $clave, $idNivel);
    }

    /**
     * Opciones del selector de destinatario en «Nuevo comunicado» (gestión / portal docente).
     *
     * @return list<array{value:string,label:string,es_familia:bool,id_tipo_prof:?int}>
     */
    public static function opcionesDestinatarioNuevoComunicado(string $rolEmisor, ?int $idNivel = null): array
    {
        $idNivel = static::resolveIdNivel($idNivel);
        if ($idNivel <= 0) {
            return [];
        }

        $receptores = static::receptoresPermitidosParaIniciar($rolEmisor, $idNivel);
        if ($receptores === []) {
            return [];
        }

        $catalogo = ComCanalRolCatalog::catalogo();
        $opciones = [];

        foreach ($receptores as $clave) {
            if (! isset($catalogo[$clave])) {
                continue;
            }
            $parsed = ComCanalRolCatalog::parseClave($clave);
            $opciones[] = [
                'value'        => $clave,
                'label'        => $catalogo[$clave],
                'es_familia'   => $parsed['familia'],
                'id_tipo_prof' => $parsed['id_tipo_prof'],
            ];
        }

        usort($opciones, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $opciones;
    }

    /** Valores permitidos para validación (`familia` o `tipo:{id}`). */
    public static function valoresDestinatarioNuevoComunicado(string $rolEmisor, ?int $idNivel = null): array
    {
        return array_column(static::opcionesDestinatarioNuevoComunicado($rolEmisor, $idNivel), 'value');
    }
}
