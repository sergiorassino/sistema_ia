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
 *  - Sin solapamiento: lockForUpdate + re-validación dentro de transacción.
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
                'auxiliar'         => trim((string) ($datos['auxiliar'] ?? '')),
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
     * Registrar la devolución de una reserva.
     *
     * @throws RrdReservaException
     */
    public static function registrarDevolucion(RrdReserva $reserva, int $idProfesorDevolucion): void
    {
        if (! $reserva->esEntregado()) {
            throw new RrdReservaException('Solo se puede registrar la devolución de reservas en estado entregado.');
        }

        $reserva->update([
            'estado'       => RrdReserva::ESTADO_DEVUELTO,
            'devuelto_por' => $idProfesorDevolucion,
            'devuelto_at'  => now(),
        ]);
    }

    // ---------------------------------------------------------------
    // Validaciones internas
    // ---------------------------------------------------------------

    /**
     * Solo el préstamo espontáneo (admin marca entregado al guardar) omite la antelación.
     */
    private static function omitirAntelacion(bool $esAdmin, array $datos): bool
    {
        return $esAdmin && ! empty($datos['entregado_directo']);
    }

    /** @throws RrdReservaException */
    private static function validarAntelacion(RrdRecurso $recurso, Carbon $inicio): void
    {
        $horas = (int) $recurso->antelacion_min_horas;
        if ($horas <= 0) {
            return;
        }

        $tz = config('app.timezone');
        $inicio = $inicio->copy()->timezone($tz);
        $minimoInicio = now($tz)->addHours($horas);

        if (! $inicio->gte($minimoInicio)) {
            throw new RrdReservaException(
                "El recurso \"{$recurso->nombre}\" requiere reservarse con al menos {$horas} hora(s) de antelación."
            );
        }
    }

    /** @throws RrdReservaException */
    private static function validarVentanaDisponibilidad(
        RrdRecurso $recurso,
        Carbon $inicio,
        Carbon $fin
    ): void {
        // Recursos marcados como "siempre disponible" no tienen restricción de ventana horaria
        if ($recurso->siempre_disponible) {
            return;
        }

        if ($recurso->disponibilidades->isEmpty()) {
            throw new RrdReservaException(
                "El recurso \"{$recurso->nombre}\" no tiene ventanas de disponibilidad configuradas."
            );
        }

        // dia_semana ISO: 1=Lunes…7=Domingo
        $diaSemana = (int) $inicio->isoFormat('E');

        $dentroDeVentana = $recurso->disponibilidades
            ->where('dia_semana', $diaSemana)
            ->first(function ($d) use ($inicio, $fin) {
                $ventanaInicio = Carbon::parse($inicio->format('Y-m-d').' '.$d->hora_inicio);
                $ventanaFin    = Carbon::parse($inicio->format('Y-m-d').' '.$d->hora_fin);

                return $inicio->gte($ventanaInicio) && $fin->lte($ventanaFin);
            });

        if (! $dentroDeVentana) {
            throw new RrdReservaException(
                "El recurso \"{$recurso->nombre}\" no está disponible en el horario solicitado para ese día."
            );
        }
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
