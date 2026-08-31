<?php

namespace App\Support\CalificacionesSecundario;

use App\Support\Database\PersistenciaColumnas;
use App\Support\SchoolContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Journal de cierre anual: lotes, snapshots y reversión condicional.
 */
final class CierreAnualJournal
{
    public const TABLA_LOTES = 'cierre_anual_lotes';

    public const TABLA_FILAS = 'cierre_anual_lote_filas';

    public const SESSION_LOTE_ID = 'cierre_anual.lote_id';

    public const ESTADO_APLICADO = 'aplicado';

    public const ESTADO_REVERTIDO_PARCIAL = 'revertido_parcial';

    public const ESTADO_REVERTIDO = 'revertido';

    public const TIPO_MATRIZ = 'matriz';

    public const TIPO_PREVIA = 'previa';

    public static function tablasListas(): bool
    {
        return Schema::hasTable(self::TABLA_LOTES) && Schema::hasTable(self::TABLA_FILAS);
    }

    public static function asegurarTablas(): void
    {
        if (! self::tablasListas()) {
            throw new RuntimeException(
                'Faltan las tablas de registro del cierre anual. Aplique el SQL de esquema (cierre_anual_lotes) antes de ejecutar el cierre.'
            );
        }
    }

    /**
     * @return array{id_profesor: int, nombre_profesor: string}
     */
    public static function actorDesdeAuth(): array
    {
        $user = auth()->user();
        $id = (int) (auth()->id() ?? 0);
        $nombre = '';
        if ($user !== null) {
            $nombre = trim((string) ($user->apellido ?? '').', '.(string) ($user->nombre ?? ''));
            $nombre = trim($nombre, ', ');
            if ($nombre === '') {
                $nombre = trim((string) ($user->name ?? ''));
            }
        }

        return [
            'id_profesor' => $id,
            'nombre_profesor' => mb_substr($nombre !== '' ? $nombre : 'Usuario', 0, 150),
        ];
    }

    /**
     * @param  array{
     *     operacion: string,
     *     id_nivel: int,
     *     id_terlec: int,
     *     ano_lectivo: int,
     *     nivel_nombre: string,
     *     id_profesor: int,
     *     nombre_profesor: string
     * }  $datos
     */
    public static function crearLote(array $datos): int
    {
        $payload = [
            'operacion' => $datos['operacion'],
            'id_nivel' => (int) $datos['id_nivel'],
            'id_terlec' => (int) $datos['id_terlec'],
            'ano_lectivo' => (int) $datos['ano_lectivo'],
            'nivel_nombre' => mb_substr(trim((string) $datos['nivel_nombre']), 0, 80),
            'id_profesor' => (int) $datos['id_profesor'],
            'nombre_profesor' => mb_substr(trim((string) $datos['nombre_profesor']), 0, 150),
            'procesados' => 0,
            'aprobados' => 0,
            'previas' => 0,
            'omitidos' => 0,
            'actualizados' => 0,
            'estado' => self::ESTADO_APLICADO,
            'created_at' => now(),
        ];

        $preparado = PersistenciaColumnas::prepararPayload(self::TABLA_LOTES, $payload);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            throw new RuntimeException(
                PersistenciaColumnas::mensajeColumnasInexistentes(
                    self::TABLA_LOTES,
                    $preparado['columnas_con_valor_sin_columna']
                )
            );
        }

        return (int) DB::table(self::TABLA_LOTES)->insertGetId($preparado['payload']);
    }

    /**
     * @param  array{
     *     procesados: int,
     *     aprobados: int,
     *     previas: int,
     *     omitidos: int
     * }  $counts
     */
    public static function finalizarLote(int $idLote, array $counts): void
    {
        $aprobados = (int) ($counts['aprobados'] ?? 0);
        $previas = (int) ($counts['previas'] ?? 0);
        DB::table(self::TABLA_LOTES)
            ->where('id', $idLote)
            ->update([
                'procesados' => (int) ($counts['procesados'] ?? 0),
                'aprobados' => $aprobados,
                'previas' => $previas,
                'omitidos' => (int) ($counts['omitidos'] ?? 0),
                'actualizados' => $aprobados + $previas,
            ]);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public static function insertarFilas(array $filas): void
    {
        if ($filas === []) {
            return;
        }

        $preparado = PersistenciaColumnas::prepararPayload(self::TABLA_FILAS, $filas[0]);
        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
            throw new RuntimeException(
                PersistenciaColumnas::mensajeColumnasInexistentes(
                    self::TABLA_FILAS,
                    $preparado['columnas_con_valor_sin_columna']
                )
            );
        }

        $lote = [];
        foreach ($filas as $fila) {
            $p = PersistenciaColumnas::prepararPayload(self::TABLA_FILAS, $fila);
            $lote[] = $p['payload'];
        }

        foreach (array_chunk($lote, 200) as $chunk) {
            DB::table(self::TABLA_FILAS)->insert($chunk);
        }
    }

    /**
     * @return array{
     *     apro: int,
     *     calif: string,
     *     mes: int|null,
     *     ano: int|null,
     *     cond: string,
     *     escuapro: string,
     *     condAdeuda: string,
     *     inscri: int
     * }
     */
    public static function snapshotDesdeFila(object $fila): array
    {
        return [
            'apro' => (int) ($fila->apro ?? 0),
            'calif' => self::texto($fila->calif ?? ''),
            'mes' => self::enteroONull($fila->mes ?? null),
            'ano' => self::enteroONull($fila->ano ?? null),
            'cond' => self::texto($fila->cond ?? ''),
            'escuapro' => self::texto($fila->escuapro ?? ''),
            'condAdeuda' => self::texto($fila->condAdeuda ?? ''),
            'inscri' => (int) ($fila->inscri ?? 0),
        ];
    }

    /**
     * Aplica sobre el snapshot anterior solo las claves del UPDATE.
     *
     * @param  array{
     *     apro: int,
     *     calif: string,
     *     mes: int|null,
     *     ano: int|null,
     *     cond: string,
     *     escuapro: string,
     *     condAdeuda: string,
     *     inscri: int
     * }  $antes
     * @param  array<string, mixed>  $payload
     * @return array{
     *     apro: int,
     *     calif: string,
     *     mes: int|null,
     *     ano: int|null,
     *     cond: string,
     *     escuapro: string,
     *     condAdeuda: string,
     *     inscri: int
     * }
     */
    public static function snapshotTrasUpdate(array $antes, array $payload): array
    {
        $despues = $antes;
        if (array_key_exists('apro', $payload)) {
            $despues['apro'] = (int) $payload['apro'];
        }
        if (array_key_exists('calif', $payload)) {
            $despues['calif'] = self::texto($payload['calif']);
        }
        if (array_key_exists('mes', $payload)) {
            $despues['mes'] = self::enteroONull($payload['mes']);
        }
        if (array_key_exists('ano', $payload)) {
            $despues['ano'] = self::enteroONull($payload['ano']);
        }
        if (array_key_exists('cond', $payload)) {
            $despues['cond'] = self::texto($payload['cond']);
        }
        if (array_key_exists('escuapro', $payload)) {
            $despues['escuapro'] = self::texto($payload['escuapro']);
        }
        if (array_key_exists('condAdeuda', $payload)) {
            $despues['condAdeuda'] = self::texto($payload['condAdeuda']);
        }
        if (array_key_exists('inscri', $payload)) {
            $despues['inscri'] = (int) $payload['inscri'];
        }

        return $despues;
    }

    /**
     * @param  array{
     *     apro: int,
     *     calif: string,
     *     mes: int|null,
     *     ano: int|null,
     *     cond: string,
     *     escuapro: string,
     *     condAdeuda: string,
     *     inscri: int
     * }  $a
     * @param  array{
     *     apro: int,
     *     calif: string,
     *     mes: int|null,
     *     ano: int|null,
     *     cond: string,
     *     escuapro: string,
     *     condAdeuda: string,
     *     inscri: int
     * }  $b
     */
    public static function igualSnapshot(array $a, array $b): bool
    {
        return $a['apro'] === $b['apro']
            && $a['calif'] === $b['calif']
            && self::enteroComparable($a['mes']) === self::enteroComparable($b['mes'])
            && self::enteroComparable($a['ano']) === self::enteroComparable($b['ano'])
            && $a['cond'] === $b['cond']
            && $a['escuapro'] === $b['escuapro']
            && $a['condAdeuda'] === $b['condAdeuda']
            && $a['inscri'] === $b['inscri'];
    }

    /**
     * @param  array{
     *     apro: int,
     *     calif: string,
     *     mes: int|null,
     *     ano: int|null,
     *     cond: string,
     *     escuapro: string,
     *     condAdeuda: string,
     *     inscri: int
     * }  $antes
     * @param  array{
     *     apro: int,
     *     calif: string,
     *     mes: int|null,
     *     ano: int|null,
     *     cond: string,
     *     escuapro: string,
     *     condAdeuda: string,
     *     inscri: int
     * }  $despues
     * @return array<string, mixed>
     */
    public static function filaDetalle(
        int $idLote,
        object $fila,
        string $tipo,
        array $antes,
        array $despues,
        string $curso,
    ): array {
        return [
            'id_lote' => $idLote,
            'id_calificacion' => (int) ($fila->id ?? 0),
            'id_legajos' => (int) ($fila->idLegajos ?? 0),
            'id_matricula' => (int) ($fila->idMatricula ?? 0),
            'id_materias' => (int) ($fila->idMaterias ?? 0),
            'apellido' => mb_substr(self::texto($fila->apellido ?? ''), 0, 100),
            'nombre' => mb_substr(self::texto($fila->nombre ?? ''), 0, 100),
            'dni' => mb_substr(self::texto($fila->dni ?? ''), 0, 20),
            'materia' => mb_substr(self::texto($fila->materia ?? ''), 0, 150),
            'curso' => mb_substr(trim($curso), 0, 80),
            'tipo' => $tipo,
            'apro_antes' => $antes['apro'],
            'calif_antes' => mb_substr($antes['calif'], 0, 20),
            'mes_antes' => $antes['mes'],
            'ano_antes' => $antes['ano'],
            'cond_antes' => mb_substr($antes['cond'], 0, 20),
            'escuapro_antes' => mb_substr($antes['escuapro'], 0, 100),
            'cond_adeuda_antes' => $antes['condAdeuda'] !== '' ? mb_substr($antes['condAdeuda'], 0, 20) : null,
            'inscri_antes' => $antes['inscri'],
            'apro_despues' => $despues['apro'],
            'calif_despues' => mb_substr($despues['calif'], 0, 20),
            'mes_despues' => $despues['mes'],
            'ano_despues' => $despues['ano'],
            'cond_despues' => mb_substr($despues['cond'], 0, 20),
            'escuapro_despues' => mb_substr($despues['escuapro'], 0, 100),
            'cond_adeuda_despues' => $despues['condAdeuda'] !== '' ? mb_substr($despues['condAdeuda'], 0, 20) : null,
            'inscri_despues' => $despues['inscri'],
        ];
    }

    public static function guardarSesionLoteId(int $idLote): void
    {
        if ($idLote > 0) {
            session([self::SESSION_LOTE_ID => $idLote]);
        } else {
            session()->forget(self::SESSION_LOTE_ID);
        }
    }

    public static function leerSesionLoteId(): int
    {
        return (int) session(self::SESSION_LOTE_ID, 0);
    }

    public static function contarLotes(int $idNivel, int $idTerlec): int
    {
        if (! self::tablasListas() || $idNivel < 1 || $idTerlec < 1) {
            return 0;
        }

        return (int) DB::table(self::TABLA_LOTES)
            ->where('id_nivel', $idNivel)
            ->where('id_terlec', $idTerlec)
            ->count();
    }

    /**
     * @return object{
     *     id: int|string,
     *     operacion: string,
     *     id_nivel: int|string,
     *     id_terlec: int|string,
     *     ano_lectivo: int|string|null,
     *     nivel_nombre: string,
     *     id_profesor: int|string,
     *     nombre_profesor: string,
     *     procesados: int|string,
     *     aprobados: int|string,
     *     previas: int|string,
     *     omitidos: int|string,
     *     actualizados: int|string,
     *     estado: string,
     *     created_at: string,
     *     revertido_at: string|null,
     *     id_profesor_reverso: int|string|null,
     *     nombre_profesor_reverso: string|null,
     *     revertidos_ok: int|string,
     *     revertidos_omitidos: int|string
     * }|null
     */
    public static function loteEnAlcance(int $idLote, int $idNivel, int $idTerlec): ?object
    {
        if (! self::tablasListas() || $idLote < 1 || $idNivel < 1 || $idTerlec < 1) {
            return null;
        }

        $row = DB::table(self::TABLA_LOTES)
            ->where('id', $idLote)
            ->where('id_nivel', $idNivel)
            ->where('id_terlec', $idTerlec)
            ->first();

        return $row;
    }

    /**
     * @return array{
     *     operacion: string,
     *     titulo: string,
     *     procesados: int,
     *     actualizados: int,
     *     aprobados: int,
     *     previas: int,
     *     omitidos: int,
     *     nivel: string,
     *     ano_lectivo: int|string,
     *     lote_id: int,
     *     estado: string,
     *     created_at: string|null,
     *     nombre_profesor: string
     * }
     */
    public static function armarInformeDesdeLote(object $lote): array
    {
        $operacion = (string) ($lote->operacion ?? 'dic');

        return [
            'operacion' => $operacion,
            'titulo' => $operacion === 'feb'
                ? 'Informe — Cierre febrero (matriz y previas)'
                : 'Informe — Cierre diciembre (matriz)',
            'procesados' => (int) ($lote->procesados ?? 0),
            'actualizados' => (int) ($lote->actualizados ?? 0),
            'aprobados' => (int) ($lote->aprobados ?? 0),
            'previas' => (int) ($lote->previas ?? 0),
            'omitidos' => (int) ($lote->omitidos ?? 0),
            'nivel' => trim((string) ($lote->nivel_nombre ?? '')),
            'ano_lectivo' => $lote->ano_lectivo ?? '—',
            'lote_id' => (int) ($lote->id ?? 0),
            'estado' => (string) ($lote->estado ?? self::ESTADO_APLICADO),
            'created_at' => isset($lote->created_at) ? (string) $lote->created_at : null,
            'nombre_profesor' => trim((string) ($lote->nombre_profesor ?? '')),
        ];
    }

    /**
     * Hay un lote más nuevo del mismo ciclo (conviene revertir ese primero).
     */
    public static function hayLotePosterior(object $lote): bool
    {
        if (! self::tablasListas()) {
            return false;
        }

        return DB::table(self::TABLA_LOTES)
            ->where('id_nivel', (int) $lote->id_nivel)
            ->where('id_terlec', (int) $lote->id_terlec)
            ->where('id', '>', (int) $lote->id)
            ->exists();
    }

    /**
     * @return array<int, list<array{
     *     lote_id: int,
     *     operacion: string,
     *     tipo: string,
     *     fecha: string,
     *     estado_lote: string,
     *     revertida: bool
     * }>>
     */
    public static function chipsPorCalificacion(int $idLegajos, int $idNivel): array
    {
        if (! self::tablasListas() || $idLegajos < 1 || $idNivel < 1) {
            return [];
        }

        $rows = DB::table(self::TABLA_FILAS.' as f')
            ->join(self::TABLA_LOTES.' as l', 'l.id', '=', 'f.id_lote')
            ->where('f.id_legajos', $idLegajos)
            ->where('l.id_nivel', $idNivel)
            ->orderByDesc('l.created_at')
            ->orderByDesc('f.id')
            ->get([
                'f.id_calificacion',
                'f.tipo',
                'f.revertida_at',
                'l.id as lote_id',
                'l.operacion',
                'l.created_at',
                'l.estado',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $idCal = (int) $r->id_calificacion;
            if ($idCal < 1) {
                continue;
            }
            $created = (string) ($r->created_at ?? '');
            $fecha = '';
            if ($created !== '') {
                try {
                    $fecha = \Carbon\Carbon::parse($created)->format('d/m/Y');
                } catch (\Throwable) {
                    $fecha = $created;
                }
            }
            $out[$idCal][] = [
                'lote_id' => (int) $r->lote_id,
                'operacion' => (string) $r->operacion,
                'tipo' => (string) $r->tipo,
                'fecha' => $fecha,
                'estado_lote' => (string) $r->estado,
                'revertida' => $r->revertida_at !== null && (string) $r->revertida_at !== '',
            ];
        }

        return $out;
    }

    /**
     * Restaura el snapshot “antes” solo si la calificación sigue igual al “después”.
     *
     * @return array{ok: int, omitidos: int, estado: string}
     */
    public static function revertirLote(int $idLote, int $idNivel, int $idTerlec): array
    {
        self::asegurarTablas();

        $lote = self::loteEnAlcance($idLote, $idNivel, $idTerlec);
        if ($lote === null) {
            throw new RuntimeException('No se encontró el lote de cierre en el ciclo lectivo activo.');
        }

        if ((string) $lote->estado === self::ESTADO_REVERTIDO) {
            throw new RuntimeException('Este lote ya fue revertido por completo.');
        }

        if ((int) ($lote->actualizados ?? 0) < 1) {
            throw new RuntimeException('Este lote no modificó calificaciones: no hay nada que revertir.');
        }

        $actor = self::actorDesdeAuth();
        $okRun = 0;
        $omitRun = 0;
        $ahora = now();

        DB::transaction(function () use (
            $idLote,
            $idTerlec,
            $actor,
            $ahora,
            &$okRun,
            &$omitRun,
        ) {
            DB::table(self::TABLA_FILAS)
                ->where('id_lote', $idLote)
                ->whereNull('revertida_at')
                ->chunkById(200, function ($filas) use ($idTerlec, $ahora, &$okRun, &$omitRun) {
                    foreach ($filas as $fila) {
                        $cal = DB::table('calificaciones')
                            ->where('id', (int) $fila->id_calificacion)
                            ->where('idTerlec', $idTerlec)
                            ->first([
                                'id',
                                'apro',
                                'calif',
                                'mes',
                                'ano',
                                'cond',
                                'escuapro',
                                'condAdeuda',
                                'inscri',
                            ]);

                        if ($cal === null) {
                            $omitRun++;

                            continue;
                        }

                        $actual = self::snapshotDesdeFila($cal);
                        $despues = self::snapshotDesdeFila((object) [
                            'apro' => $fila->apro_despues,
                            'calif' => $fila->calif_despues,
                            'mes' => $fila->mes_despues,
                            'ano' => $fila->ano_despues,
                            'cond' => $fila->cond_despues,
                            'escuapro' => $fila->escuapro_despues,
                            'condAdeuda' => $fila->cond_adeuda_despues,
                            'inscri' => $fila->inscri_despues,
                        ]);

                        if (! self::igualSnapshot($actual, $despues)) {
                            $omitRun++;

                            continue;
                        }

                        $payload = [
                            'apro' => (int) $fila->apro_antes,
                            'calif' => (string) $fila->calif_antes,
                            'mes' => $fila->mes_antes,
                            'ano' => $fila->ano_antes,
                            'cond' => (string) $fila->cond_antes,
                            'escuapro' => (string) $fila->escuapro_antes,
                            'condAdeuda' => $fila->cond_adeuda_antes,
                            'inscri' => (int) $fila->inscri_antes,
                        ];

                        $preparado = PersistenciaColumnas::prepararPayload('calificaciones', $payload);
                        if ($preparado['columnas_con_valor_sin_columna'] !== []) {
                            throw new RuntimeException(
                                PersistenciaColumnas::mensajeColumnasInexistentes(
                                    'calificaciones',
                                    $preparado['columnas_con_valor_sin_columna']
                                )
                            );
                        }

                        $aEscribir = PersistenciaColumnas::reemplazarNulosExplicitos(
                            'calificaciones',
                            $preparado['payload']
                        );

                        try {
                            $afectados = DB::table('calificaciones')
                                ->where('id', (int) $fila->id_calificacion)
                                ->where('idTerlec', $idTerlec)
                                ->update($aEscribir);
                        } catch (QueryException $e) {
                            $msg = PersistenciaColumnas::mensajeDesdeQueryException($e);
                            throw new RuntimeException($msg ?? 'No se pudo revertir una calificación.');
                        }

                        if ($afectados < 1) {
                            $omitRun++;

                            continue;
                        }

                        $releo = DB::table('calificaciones')
                            ->where('id', (int) $fila->id_calificacion)
                            ->first([
                                'apro', 'calif', 'mes', 'ano', 'cond', 'escuapro', 'condAdeuda', 'inscri',
                            ]);
                        $esperado = self::snapshotDesdeFila((object) [
                            'apro' => $fila->apro_antes,
                            'calif' => $fila->calif_antes,
                            'mes' => $fila->mes_antes,
                            'ano' => $fila->ano_antes,
                            'cond' => $fila->cond_antes,
                            'escuapro' => $fila->escuapro_antes,
                            'condAdeuda' => $fila->cond_adeuda_antes,
                            'inscri' => $fila->inscri_antes,
                        ]);

                        if ($releo === null || ! self::igualSnapshot(self::snapshotDesdeFila($releo), $esperado)) {
                            throw new RuntimeException(
                                'Una calificación no quedó restaurada como se esperaba. Revise el esquema y reintente.'
                            );
                        }

                        DB::table(self::TABLA_FILAS)
                            ->where('id', (int) $fila->id)
                            ->update(['revertida_at' => $ahora]);
                        $okRun++;
                    }
                });

            $revertidosOk = (int) DB::table(self::TABLA_FILAS)
                ->where('id_lote', $idLote)
                ->whereNotNull('revertida_at')
                ->count();
            $pendientes = (int) DB::table(self::TABLA_FILAS)
                ->where('id_lote', $idLote)
                ->whereNull('revertida_at')
                ->count();

            $estado = $pendientes === 0
                ? self::ESTADO_REVERTIDO
                : self::ESTADO_REVERTIDO_PARCIAL;

            DB::table(self::TABLA_LOTES)
                ->where('id', $idLote)
                ->update([
                    'estado' => $estado,
                    'revertido_at' => $ahora,
                    'id_profesor_reverso' => $actor['id_profesor'],
                    'nombre_profesor_reverso' => $actor['nombre_profesor'],
                    'revertidos_ok' => $revertidosOk,
                    'revertidos_omitidos' => $pendientes,
                ]);
        });

        $loteActualizado = self::loteEnAlcance($idLote, $idNivel, $idTerlec);

        return [
            'ok' => $okRun,
            'omitidos' => $omitRun,
            'estado' => (string) ($loteActualizado->estado ?? self::ESTADO_REVERTIDO_PARCIAL),
        ];
    }

    public static function etiquetaOperacion(string $operacion): string
    {
        return $operacion === 'feb' ? 'Febrero' : 'Diciembre';
    }

    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_REVERTIDO => 'Revertido',
            self::ESTADO_REVERTIDO_PARCIAL => 'Revertido parcial',
            default => 'Aplicado',
        };
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return $tipo === self::TIPO_PREVIA ? 'Previa' : 'Matriz';
    }

    public static function formatearFecha(?string $valor): string
    {
        $t = trim((string) $valor);
        if ($t === '') {
            return '—';
        }
        try {
            return \Carbon\Carbon::parse($t)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $t;
        }
    }

    public static function texto(mixed $v): string
    {
        return trim((string) ($v ?? ''));
    }

    public static function enteroONull(mixed $v): ?int
    {
        if ($v === null) {
            return null;
        }
        if (is_string($v) && trim($v) === '') {
            return null;
        }

        return (int) $v;
    }

    /** MySQL INT NOT NULL trata NULL como 0: comparar sin distinguir. */
    public static function enteroComparable(?int $v): int
    {
        return $v ?? 0;
    }
}
