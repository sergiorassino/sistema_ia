<?php

namespace App\Support\Examenes;

use App\Support\CalificacionesColoquioSecundario;
use App\Support\Database\PersistenciaColumnas;
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
     * @return 'ok'|'ok_aprobada'|'no_encontrada'|'condicion_invalida'
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

        $libro = self::truncarOpcional($datos['libro'] ?? null, 10);
        $folio = self::truncarOpcional($datos['folio'] ?? null, 10);
        $aprobada = false;

        DB::transaction(function () use (
            $idCalificacion,
            $idLegajos,
            $idNivel,
            $fecha,
            $nota,
            $cond,
            $libro,
            $folio,
            &$aprobada,
        ): void {
            DB::table('notasexamen')->insert([
                'idCalificaciones' => $idCalificacion,
                'idLegajos' => $idLegajos,
                'fecha' => $fecha,
                'nota' => mb_substr($nota, 0, 10),
                'condExamen' => $cond !== '' ? mb_substr($cond, 0, 2) : null,
                'libro' => $libro,
                'folio' => $folio,
            ]);

            $aprobada = self::aprobarSiNotaSuficiente(
                $idCalificacion,
                $idLegajos,
                $idNivel,
                $nota,
                $fecha,
                $cond,
            );
        });

        return $aprobada ? 'ok_aprobada' : 'ok';
    }

    /**
     * Si la nota es ≥ 7 y la materia sigue adeudada (`apro = 1`), la pasa a matriz (`apro = 2`)
     * y deja de figurar en pendientes (mismo criterio que coloquios / cierre anual).
     * La rendición (fecha, nota, libro, folio) permanece en `notasexamen`.
     */
    public static function aprobarSiNotaSuficiente(
        int $idCalificacion,
        int $idLegajos,
        int $idNivel,
        string $nota,
        string $fecha,
        string $condExamen = '',
    ): bool {
        if ($idCalificacion < 1 || $idLegajos < 1 || $idNivel < 1) {
            return false;
        }

        if (! CalificacionesColoquioSecundario::notaColoquioAprobada($nota)) {
            return false;
        }

        if (! self::esFechaValida($fecha)) {
            return false;
        }

        $fila = DB::table('calificaciones as c')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.id', $idCalificacion)
            ->where('c.idLegajos', $idLegajos)
            ->where('cu.idNivel', $idNivel)
            ->where('c.apro', 1)
            ->select(['c.id', 'c.idTerlec', 'c.condAdeuda'])
            ->first();

        if ($fila === null) {
            return false;
        }

        $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fecha);
        $calif = CalificacionesColoquioSecundario::califDesdeNotaColoquio($nota);
        $condOrigen = trim($condExamen) !== ''
            ? $condExamen
            : trim((string) ($fila->condAdeuda ?? ''));
        $condCalif = mb_substr(
            MateriasAdeudadasFiltros::condCalificacionDesdeExamen($condOrigen),
            0,
            20,
        );
        $escuapro = self::nombreInstitucion($idNivel);

        $payload = [
            'apro' => 2,
            'calif' => $calif !== '' ? mb_substr($calif, 0, 10) : mb_substr(trim($nota), 0, 10),
            'mes' => (int) $fechaCarbon->format('n'),
            'ano' => (int) $fechaCarbon->format('Y'),
            'cond' => $condCalif,
            'escuapro' => $escuapro,
            'condAdeuda' => null,
            'inscri' => 0,
        ];

        $preparado = PersistenciaColumnas::prepararPayload('calificaciones', $payload);
        $update = $preparado['payload'];

        if (! array_key_exists('apro', $update)) {
            return false;
        }

        $afectados = DB::table('calificaciones')
            ->where('id', $idCalificacion)
            ->where('idLegajos', $idLegajos)
            ->where('apro', 1)
            ->update($update);

        return $afectados > 0;
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

    private static function nombreInstitucion(int $idNivel): string
    {
        $insti = trim((string) DB::table('ento')->where('idNivel', $idNivel)->value('insti'));

        return $insti !== '' ? mb_substr($insti, 0, 100) : '';
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
