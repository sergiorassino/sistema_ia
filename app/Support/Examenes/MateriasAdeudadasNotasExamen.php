<?php

namespace App\Support\Examenes;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class MateriasAdeudadasNotasExamen
{
    /**
     * Rendiciones anteriores de una calificación adeudada (solo lectura en UI).
     *
     * @return list<array{
     *     id: int,
     *     fecha: string,
     *     fecha_iso: string,
     *     nota: string,
     *     condicion: string,
     *     libro: string,
     *     folio: string
     * }>
     */
    public static function historial(int $idCalificacion, int $idLegajos, int $idNivel): array
    {
        if ($idCalificacion < 1 || $idLegajos < 1 || $idNivel < 1) {
            return [];
        }

        if (! self::calificacionAdeudadaDelAlumno($idCalificacion, $idLegajos, $idNivel)) {
            return [];
        }

        $raw = DB::table('notasexamen')
            ->where('idCalificaciones', $idCalificacion)
            ->where('idLegajos', $idLegajos)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['id', 'fecha', 'nota', 'condExamen', 'libro', 'folio']);

        $out = [];
        foreach ($raw as $r) {
            $fechaIso = self::fechaAString($r->fecha ?? null);
            $out[] = [
                'id' => (int) $r->id,
                'fecha' => self::fechaParaMostrar($fechaIso),
                'fecha_iso' => $fechaIso,
                'nota' => trim((string) ($r->nota ?? '')),
                'condicion' => strtoupper(trim((string) ($r->condExamen ?? ''))),
                'libro' => trim((string) ($r->libro ?? '')),
                'folio' => trim((string) ($r->folio ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param array{
     *     idCalificacion: int,
     *     idLegajos: int,
     *     idNivel: int,
     *     fecha: string,
     *     nota: string,
     *     condExamen?: string|null,
     *     libro?: string|null,
     *     folio?: string|null
     * } $datos
     *
     * @return 'ok'|'no_encontrada'|'condicion_invalida'
     */
    public static function registrarNueva(array $datos): string
    {
        $idCalificacion = (int) ($datos['idCalificacion'] ?? 0);
        $idLegajos = (int) ($datos['idLegajos'] ?? 0);
        $idNivel = (int) ($datos['idNivel'] ?? 0);

        if ($idCalificacion < 1 || $idLegajos < 1 || $idNivel < 1) {
            return 'no_encontrada';
        }

        if (! self::calificacionAdeudadaDelAlumno($idCalificacion, $idLegajos, $idNivel)) {
            return 'no_encontrada';
        }

        $cond = trim((string) ($datos['condExamen'] ?? ''));
        if ($cond !== '') {
            $condNorm = MateriasAdeudadasFiltros::normalizeCondicion($cond);
            if ($condNorm === null) {
                return 'condicion_invalida';
            }
            $cond = $condNorm;
        }

        $fecha = trim((string) ($datos['fecha'] ?? ''));
        if ($fecha === '' || ! self::esFechaValida($fecha)) {
            return 'no_encontrada';
        }

        $nota = trim((string) ($datos['nota'] ?? ''));
        if ($nota === '') {
            return 'no_encontrada';
        }

        DB::table('notasexamen')->insert([
            'idCalificaciones' => $idCalificacion,
            'idLegajos' => $idLegajos,
            'fecha' => $fecha,
            'nota' => mb_substr($nota, 0, 10),
            'condExamen' => $cond !== '' ? mb_substr($cond, 0, 2) : null,
            'libro' => self::truncarOpcional($datos['libro'] ?? null, 10),
            'folio' => self::truncarOpcional($datos['folio'] ?? null, 10),
        ]);

        return 'ok';
    }

    public static function calificacionAdeudadaDelAlumno(int $idCalificacion, int $idLegajos, int $idNivel): bool
    {
        if ($idCalificacion < 1 || $idLegajos < 1 || $idNivel < 1) {
            return false;
        }

        return DB::table('calificaciones as c')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.id', $idCalificacion)
            ->where('c.idLegajos', $idLegajos)
            ->where('cu.idNivel', $idNivel)
            ->where('c.apro', 1)
            ->exists();
    }

    private static function esFechaValida(string $fecha): bool
    {
        try {
            Carbon::createFromFormat('Y-m-d', $fecha);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function fechaAString(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        $s = trim((string) $valor);
        if ($s === '') {
            return '';
        }

        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function fechaParaMostrar(string $fechaIso): string
    {
        if ($fechaIso === '') {
            return '—';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $fechaIso)->format('d/m/Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private static function truncarOpcional(mixed $valor, int $max): ?string
    {
        $s = trim((string) ($valor ?? ''));

        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
