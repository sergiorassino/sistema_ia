<?php

namespace App\Support\MaterialDidactico;

use App\Models\RrdPedido;
use App\Models\RrdRecurso;
use App\Models\RrdReserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio para el módulo Reserva de Material Didáctico.
 *
 * Reglas:
 *  - Antelación mínima: ahora + antelacion_min_horas <= fecha+hora_inicio
 *    (solo se omite en préstamo espontáneo: admin + entregado_directo).
 *  - Ventana de disponibilidad: el rango solicitado debe estar contenido
 *    en al menos una ventana rrd_recurso_disponibilidad del recurso en ese día de semana.
 *  - Sin solapamiento: no puede existir otra reserva activa del mismo recurso
 *    en el mismo horario (pendiente o entregado).
 *  - Multireserva atómica: todo o nada (N recursos, 1 pedido).
 */
class RrdReservaService
{
    // ---------------------------------------------------------------
    // Excepciones tipadas para distinguir errores de negocio
    // ---------------------------------------------------------------

    /**
     * @throws RrdReservaException
     */
    public static function crearPedido(
        array $datos,
        array $idsRecursos,
        bool $esAdmin = false
    ): RrdPedido {
        $ctx = schoolCtx();

        $fecha      = $datos['fecha'];          // string Y-m-d
        $horaInicio = $datos['hora_inicio'];    // string H:i
        $horaFin    = $datos['hora_fin'];       // string H:i

        $tz = config('app.timezone');
        $inicioCarbon = Carbon::parse("{$fecha} {$horaInicio}", $tz);
        $finCarbon    = Carbon::parse("{$fecha} {$horaFin}", $tz);

        if ($finCarbon->lte($inicioCarbon)) {
            throw new RrdReservaException('La hora de fin debe ser posterior a la hora de inicio.');
        }

        // Carga de recursos para las validaciones previas a la transacción
        /** @var \Illuminate\Database\Eloquent\Collection<int,RrdRecurso> $recursos */
        $recursos = RrdRecurso::query()
            ->whereIn('id', $idsRecursos)
            ->where('id_nivel', (int) ($ctx->idNivel ?? 0))
            ->where('activo', true)
            ->with('disponibilidades')
            ->get();

        if ($recursos->count() !== count($idsRecursos)) {
            throw new RrdReservaException('Uno o más recursos no son válidos o no pertenecen al nivel activo.');
        }

        $omitirAntelacion = self::omitirAntelacion($esAdmin, $datos);

        // Validaciones pre-transacción (sin lock) por cada recurso
        foreach ($recursos as $recurso) {
            if (! $omitirAntelacion) {
                self::validarAntelacion($recurso, $inicioCarbon);
            }
            self::validarVentanaDisponibilidad($recurso, $inicioCarbon, $finCarbon);
        }

        // Transacción con lock de última instancia
        return DB::transaction(function () use (
            $datos,
            $recursos,
            $idsRecursos,
            $inicioCarbon,
            $finCarbon,
            $fecha,
            $horaInicio,
            $horaFin,
            $esAdmin,
            $ctx,
        ): RrdPedido {
            // Re-validar solapamiento con lockForUpdate para cada recurso
            foreach ($recursos as $recurso) {
                self::validarSolapamientoConLock($recurso->id, $fecha, $horaInicio, $horaFin);
            }

            $estadoInicial = $esAdmin && isset($datos['entregado_directo']) && $datos['entregado_directo']
                ? RrdReserva::ESTADO_ENTREGADO
                : RrdReserva::ESTADO_PENDIENTE;

            $pedido = RrdPedido::create([
                'id_nivel'        => (int) ($ctx->idNivel  ?? 0),
                'id_terlec'       => (int) ($ctx->idTerlec ?? 0),
                'id_profesor'     => (int) ($ctx->idProfesor ?? 0),
                'fecha'           => $fecha,
                'hora_inicio'     => $horaInicio,
                'hora_fin'        => $horaFin,
                'sala_curso_grado' => trim((string) ($datos['sala_curso_grado'] ?? '')),
                'auxiliar'        => trim((string) ($datos['auxiliar'] ?? '')),
                'observaciones'   => trim((string) ($datos['observaciones'] ?? '')) ?: null,
                'created_at'      => now(),
            ]);

            foreach ($recursos as $recurso) {
                $reservaData = [
                    'id_pedido'  => $pedido->id,
                    'id_recurso' => $recurso->id,
                    'id_nivel'   => (int) ($ctx->idNivel  ?? 0),
                    'id_terlec'  => (int) ($ctx->idTerlec ?? 0),
                    'fecha'      => $fecha,
                    'hora_inicio' => $horaInicio,
                    'hora_fin'   => $horaFin,
                    'estado'     => $estadoInicial,
                    'created_at' => now(),
                ];

                if ($estadoInicial === RrdReserva::ESTADO_ENTREGADO) {
                    $reservaData['entregado_a']   = trim((string) ($datos['entregado_a'] ?? ''));
                    $reservaData['entregado_por'] = (int) ($ctx->idProfesor ?? 0);
                    $reservaData['entregado_at']  = now();
                }

                RrdReserva::create($reservaData);
            }

            return $pedido;
        });
    }

    /**
     * Actualizar datos de un pedido pendiente (todos los ítems deben estar pendientes).
     *
     * @throws RrdReservaException
     */
    public static function editarPedido(
        RrdPedido $pedido,
        array $datos,
        array $idsRecursos,
        bool $esAdmin = false
    ): RrdPedido {
        // Solo modificable si todos los ítems están pendientes
        $tieneEntregado = $pedido->reservas()
            ->where('estado', '!=', RrdReserva::ESTADO_PENDIENTE)
            ->exists();

        if ($tieneEntregado && ! $esAdmin) {
            throw new RrdReservaException('No se puede editar un pedido que ya tiene recursos entregados o devueltos.');
        }

        // Cancelar las reservas actuales y crear las nuevas dentro de una transacción
        DB::transaction(function () use ($pedido, $datos, $idsRecursos, $esAdmin) {
            $pedido->reservas()->update(['estado' => RrdReserva::ESTADO_CANCELADO]);

            // Eliminar las reservas canceladas del mismo pedido para permitir re-reservar los mismos recursos
            $pedido->reservas()->where('estado', RrdReserva::ESTADO_CANCELADO)->delete();
        });

        // Re-crear usando crearPedido (reutiliza todas las validaciones)
        // pero actualizando el pedido existente en vez de crear uno nuevo
        $ctx = schoolCtx();
        $fecha      = $datos['fecha'];
        $horaInicio = $datos['hora_inicio'];
        $horaFin    = $datos['hora_fin'];

        $tz = config('app.timezone');
        $inicioCarbon = Carbon::parse("{$fecha} {$horaInicio}", $tz);
        $finCarbon    = Carbon::parse("{$fecha} {$horaFin}", $tz);

        if ($finCarbon->lte($inicioCarbon)) {
            throw new RrdReservaException('La hora de fin debe ser posterior a la hora de inicio.');
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int,RrdRecurso> $recursos */
        $recursos = RrdRecurso::query()
            ->whereIn('id', $idsRecursos)
            ->where('id_nivel', (int) ($ctx->idNivel ?? 0))
            ->where('activo', true)
            ->with('disponibilidades')
            ->get();

        if ($recursos->count() !== count($idsRecursos)) {
            throw new RrdReservaException('Uno o más recursos no son válidos.');
        }

        $omitirAntelacion = self::omitirAntelacion($esAdmin, $datos);

        foreach ($recursos as $recurso) {
            if (! $omitirAntelacion) {
                self::validarAntelacion($recurso, $inicioCarbon);
            }
            self::validarVentanaDisponibilidad($recurso, $inicioCarbon, $finCarbon);
        }

        DB::transaction(function () use ($pedido, $datos, $recursos, $fecha, $horaInicio, $horaFin, $ctx) {
            foreach ($recursos as $recurso) {
                self::validarSolapamientoConLock($recurso->id, $fecha, $horaInicio, $horaFin);
            }

            $pedido->update([
                'fecha'            => $fecha,
                'hora_inicio'      => $horaInicio,
                'hora_fin'         => $horaFin,
                'sala_curso_grado' => trim((string) ($datos['sala_curso_grado'] ?? '')),
                'auxiliar'         => array_key_exists('auxiliar', $datos)
                    ? trim((string) $datos['auxiliar'])
                    : $pedido->auxiliar,
                'observaciones'    => trim((string) ($datos['observaciones'] ?? '')) ?: null,
            ]);

            foreach ($recursos as $recurso) {
                RrdReserva::create([
                    'id_pedido'   => $pedido->id,
                    'id_recurso'  => $recurso->id,
                    'id_nivel'    => (int) ($ctx->idNivel  ?? 0),
                    'id_terlec'   => (int) ($ctx->idTerlec ?? 0),
                    'fecha'       => $fecha,
                    'hora_inicio' => $horaInicio,
                    'hora_fin'    => $horaFin,
                    'estado'      => RrdReserva::ESTADO_PENDIENTE,
                    'created_at'  => now(),
                ]);
            }
        });

        return $pedido->refresh();
    }

    /**
     * Cancelar un pedido completo (solo si ningún ítem está entregado).
     *
     * @throws RrdReservaException
     */
    public static function cancelarPedido(RrdPedido $pedido): void
    {
        $tieneEntregado = $pedido->reservas()
            ->whereIn('estado', [RrdReserva::ESTADO_ENTREGADO])
            ->exists();

        if ($tieneEntregado) {
            throw new RrdReservaException('No se puede cancelar un pedido con recursos ya entregados. Primero registre la devolución.');
        }

        $pedido->reservas()->update(['estado' => RrdReserva::ESTADO_CANCELADO]);
    }

    /**
     * Cancelar un ítem (recurso) del pedido.
     *
     * @throws RrdReservaException
     */
    public static function cancelarReserva(RrdReserva $reserva): void
    {
        if (! $reserva->esPendiente()) {
            throw new RrdReservaException('Solo se puede cancelar una reserva en estado pendiente.');
        }

        $reserva->update(['estado' => RrdReserva::ESTADO_CANCELADO]);
    }

    /**
     * Registrar la entrega de una reserva.
     *
     * @throws RrdReservaException
     */
    public static function registrarEntrega(RrdReserva $reserva, string $entregadoA, int $idProfesorEntrega): void
    {
        if (! $reserva->esPendiente()) {
            throw new RrdReservaException('Solo se puede registrar la entrega de reservas en estado pendiente.');
        }

        $reserva->update([
            'estado'        => RrdReserva::ESTADO_ENTREGADO,
            'entregado_a'   => substr(trim($entregadoA), 0, 100),
            'entregado_por' => $idProfesorEntrega,
            'entregado_at'  => now(),
        ]);
    }

    /**
     * @deprecated El nombre de entrega queda bloqueado al registrar.
     *
     * @throws RrdReservaException
     */
    public static function actualizarEntrega(RrdReserva $reserva, string $entregadoA, int $idProfesorEntrega): void
    {
        throw new RrdReservaException('El nombre de entrega no puede modificarse una vez registrado.');
    }

    /**
     * @deprecated El nombre de devolución queda bloqueado al registrar.
     *
     * @throws RrdReservaException
     */
    public static function actualizarDevolucion(
        RrdReserva $reserva,
        string $devueltoPor,
        int $idOperadorRecibe
    ): void {
        throw new RrdReservaException('El nombre de devolución no puede modificarse una vez registrado.');
    }

    /**
     * @deprecated La entrega registrada no puede anularse desde el listado.
     *
     * @throws RrdReservaException
     */
    public static function revertirEntrega(RrdReserva $reserva): void
    {
        throw new RrdReservaException('La entrega registrada no puede anularse.');
    }

    /**
     * @deprecated La devolución registrada no puede anularse desde el listado.
     *
     * @throws RrdReservaException
     */
    public static function revertirDevolucion(RrdReserva $reserva): void
    {
        throw new RrdReservaException('La devolución registrada no puede anularse.');
    }

    /**
     * Registrar la devolución de una reserva.
     *
     * @throws RrdReservaException
     */
    public static function registrarDevolucion(
        RrdReserva $reserva,
        string $devueltoPor,
        int $idOperadorRecibe
    ): void {
        if (! $reserva->esEntregado()) {
            throw new RrdReservaException('Solo se puede registrar la devolución de reservas en estado entregado.');
        }

        $reserva->update([
            'estado'       => RrdReserva::ESTADO_DEVUELTO,
            'devuelto_por' => substr(trim($devueltoPor), 0, 100),
            'devuelto_a'   => max(0, $idOperadorRecibe) > 0 ? max(0, $idOperadorRecibe) : null,
            'devuelto_at'  => now(),
        ]);
    }

    // ---------------------------------------------------------------
    // Validaciones internas
    // ---------------------------------------------------------------

    /**
     * Indica si un recurso puede reservarse en el horario indicado
     * (antelación, ventana de disponibilidad y sin solapamiento con otras reservas).
     *
     * @param  list<int>|null  $idsRecursosOcupados  IDs ya ocupados en ese horario (evita N consultas en listados).
     */
    public static function esReservableEnHorario(
        RrdRecurso $recurso,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        bool $omitirAntelacion = false,
        ?int $excluirPedidoId = null,
        ?array $idsRecursosOcupados = null
    ): bool {
        $tz = config('app.timezone');

        try {
            $inicio = Carbon::parse("{$fecha} {$horaInicio}", $tz);
            $fin    = Carbon::parse("{$fecha} {$horaFin}", $tz);
        } catch (\Throwable) {
            return false;
        }

        if ($fin->lte($inicio)) {
            return false;
        }

        if (! $omitirAntelacion && ! self::cumpleAntelacion($recurso, $inicio)) {
            return false;
        }

        if (! self::cumpleVentanaDisponibilidad($recurso, $inicio, $fin)) {
            return false;
        }

        if ($idsRecursosOcupados !== null) {
            return ! in_array((int) $recurso->id, $idsRecursosOcupados, true);
        }

        return ! self::tieneSolapamientoReserva(
            (int) $recurso->id,
            $fecha,
            $horaInicio,
            $horaFin,
            $excluirPedidoId
        );
    }

    /**
     * IDs de recursos con al menos una reserva activa que solapa el horario indicado.
     *
     * @return list<int>
     */
    public static function idsRecursosConSolapamientoEnHorario(
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $excluirPedidoId = null
    ): array {
        $query = RrdReserva::query()
            ->enContexto()
            ->where('fecha', $fecha)
            ->activas()
            ->where(function ($q) use ($horaInicio, $horaFin) {
                $q->where('hora_inicio', '<', $horaFin)
                  ->where('hora_fin', '>', $horaInicio);
            });

        if ($excluirPedidoId !== null && $excluirPedidoId > 0) {
            $query->where('id_pedido', '!=', $excluirPedidoId);
        }

        return $query->pluck('id_recurso')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function tieneSolapamientoReserva(
        int $idRecurso,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $excluirPedidoId = null
    ): bool {
        $query = RrdReserva::query()
            ->where('id_recurso', $idRecurso)
            ->where('fecha', $fecha)
            ->activas()
            ->where(function ($q) use ($horaInicio, $horaFin) {
                $q->where('hora_inicio', '<', $horaFin)
                  ->where('hora_fin', '>', $horaInicio);
            });

        if ($excluirPedidoId !== null && $excluirPedidoId > 0) {
            $query->where('id_pedido', '!=', $excluirPedidoId);
        }

        return $query->exists();
    }

    /**
     * Solo el préstamo espontáneo (admin marca entregado al guardar) omite la antelación.
     */
    private static function omitirAntelacion(bool $esAdmin, array $datos): bool
    {
        return $esAdmin && ! empty($datos['entregado_directo']);
    }

    private static function cumpleAntelacion(RrdRecurso $recurso, Carbon $inicio): bool
    {
        $horas = (int) $recurso->antelacion_min_horas;
        if ($horas <= 0) {
            return true;
        }

        $tz = config('app.timezone');
        $inicio = $inicio->copy()->timezone($tz);
        $minimoInicio = now($tz)->addHours($horas);

        return $inicio->gte($minimoInicio);
    }

    /** @throws RrdReservaException */
    private static function validarAntelacion(RrdRecurso $recurso, Carbon $inicio): void
    {
        $horas = (int) $recurso->antelacion_min_horas;
        if ($horas <= 0) {
            return;
        }

        if (! self::cumpleAntelacion($recurso, $inicio)) {
            throw new RrdReservaException(
                "El recurso \"{$recurso->nombre}\" requiere reservarse con al menos {$horas} hora(s) de antelación."
            );
        }
    }

    private static function cumpleVentanaDisponibilidad(
        RrdRecurso $recurso,
        Carbon $inicio,
        Carbon $fin
    ): bool {
        if ($recurso->siempre_disponible) {
            return true;
        }

        if ($recurso->disponibilidades->isEmpty()) {
            return false;
        }

        $diaSemana = (int) $inicio->isoFormat('E');

        return $recurso->disponibilidades
            ->where('dia_semana', $diaSemana)
            ->contains(function ($d) use ($inicio, $fin) {
                $ventanaInicio = Carbon::parse($inicio->format('Y-m-d').' '.$d->hora_inicio);
                $ventanaFin    = Carbon::parse($inicio->format('Y-m-d').' '.$d->hora_fin);

                return $inicio->gte($ventanaInicio) && $fin->lte($ventanaFin);
            });
    }

    /** @throws RrdReservaException */
    private static function validarVentanaDisponibilidad(
        RrdRecurso $recurso,
        Carbon $inicio,
        Carbon $fin
    ): void {
        if (self::cumpleVentanaDisponibilidad($recurso, $inicio, $fin)) {
            return;
        }

        if (! $recurso->siempre_disponible && $recurso->disponibilidades->isEmpty()) {
            throw new RrdReservaException(
                "El recurso \"{$recurso->nombre}\" no tiene ventanas de disponibilidad configuradas."
            );
        }

        throw new RrdReservaException(
            "El recurso \"{$recurso->nombre}\" no está disponible en el horario solicitado para ese día."
        );
    }

    /**
     * Detección de solapamiento con bloqueo de fila para concurrencia.
     *
     * Debe llamarse dentro de DB::transaction.
     *
     * @throws RrdReservaException
     */
    private static function validarSolapamientoConLock(
        int $idRecurso,
        string $fecha,
        string $horaInicio,
        string $horaFin
    ): void {
        $solape = RrdReserva::query()
            ->where('id_recurso', $idRecurso)
            ->where('fecha', $fecha)
            ->whereNotIn('estado', [RrdReserva::ESTADO_CANCELADO, RrdReserva::ESTADO_DEVUELTO])
            ->where(function ($q) use ($horaInicio, $horaFin) {
                // [A,B] solapa con [C,D] si A < D y C < B
                $q->where('hora_inicio', '<', $horaFin)
                  ->where('hora_fin', '>', $horaInicio);
            })
            ->lockForUpdate()
            ->first();

        if ($solape) {
            $recurso = RrdRecurso::find($idRecurso);
            $nombre  = $recurso ? $recurso->nombre : "ID {$idRecurso}";
            throw new RrdReservaException(
                "El recurso \"{$nombre}\" ya tiene una reserva en ese horario. Por favor elija otro horario."
            );
        }
    }
}
