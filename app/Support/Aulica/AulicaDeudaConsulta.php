<?php

namespace App\Support\Aulica;

use App\Models\Legajo;
use App\Support\InformeInasistencias;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta deuda Áulica del estudiante (por DNI) y del grupo familiar (DNI del responsable).
 */
final class AulicaDeudaConsulta
{
    public function __construct(private readonly AulicaSaldos $saldos = new AulicaSaldos) {}

    public static function habilitada(): bool
    {
        return AulicaConfig::habilitada();
    }

    public function paraEstudianteActual(): AulicaDeudaResultado
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return AulicaDeudaResultado::deshabilitado();
        }

        $legajo = Legajo::query()->where('id', (int) $ctx->idLegajo)->first();
        if ($legajo === null) {
            return AulicaDeudaResultado::deshabilitado();
        }

        return $this->paraLegajo($legajo);
    }

    public function paraMatriculaAutogestion(): AulicaDeudaResultado
    {
        $matricula = InformeInasistencias::matriculaAutogestion();
        $legajo = $matricula?->legajo;
        if ($legajo === null) {
            return $this->paraEstudianteActual();
        }

        return $this->paraLegajo($legajo);
    }

    public function paraLegajo(Legajo $legajo): AulicaDeudaResultado
    {
        return $this->paraDnis(
            AulicaDni::normalizar($legajo->dni ?? null),
            self::dniResponsableDesdeLegajo($legajo),
        );
    }

    /**
     * Fila de listado Secretaría (`EstudiantesDatosConsulta`) u objeto con dni / dnitut / etc.
     */
    public function paraFilaListado(object $fila): AulicaDeudaResultado
    {
        return $this->paraDnis(
            AulicaDni::normalizar($fila->dni ?? null),
            self::dniResponsableDesdeFila($fila),
        );
    }

    /**
     * @param  iterable<int, object>  $filas
     * @return array<string, AulicaDeudaResultado> clave = matricula_id
     */
    public function paraFilasListado(iterable $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $id = (string) (int) ($fila->matricula_id ?? 0);
            if ($id === '0') {
                continue;
            }
            $out[$id] = $this->paraFilaListado($fila);
        }

        return $out;
    }

    public function paraDnis(?string $dniEstudiante, ?string $dniResponsable): AulicaDeudaResultado
    {
        if (! AulicaConfig::habilitada()) {
            return AulicaDeudaResultado::deshabilitado();
        }

        $dniEstudiante = AulicaDni::normalizar($dniEstudiante);
        $dniResponsable = AulicaDni::normalizar($dniResponsable);

        if ($dniEstudiante === null && $dniResponsable === null) {
            return new AulicaDeudaResultado('', '', [], [], true);
        }

        try {
            $crudoEstudiante = $dniEstudiante !== null
                ? $this->saldos->porDocumento($dniEstudiante)
                : [];
            $estudiante = $this->soloDni($crudoEstudiante, $dniEstudiante);

            $grupo = [];
            if ($dniResponsable !== null && $dniResponsable !== $dniEstudiante) {
                $grupo = $this->saldos->porDocumento($dniResponsable);
            } elseif ($dniResponsable !== null && $dniResponsable === $dniEstudiante) {
                $grupo = $crudoEstudiante !== [] ? $crudoEstudiante : $estudiante;
            }

            return new AulicaDeudaResultado(
                $dniEstudiante ?? '',
                $dniResponsable ?? '',
                $estudiante,
                $grupo,
                true,
            );
        } catch (AulicaClienteException $e) {
            Log::warning('Áulica: consulta de deuda fallida', ['message' => $e->getMessage()]);

            return AulicaDeudaResultado::error(
                'No se pudo consultar la deuda en Áulica. Intente más tarde.',
                $dniEstudiante ?? '',
                $dniResponsable ?? '',
            );
        } catch (Throwable $e) {
            Log::warning('Áulica: error inesperado al consultar deuda', ['message' => $e->getMessage()]);

            return AulicaDeudaResultado::error(
                'No se pudo consultar la deuda en Áulica. Intente más tarde.',
                $dniEstudiante ?? '',
                $dniResponsable ?? '',
            );
        }
    }

    public static function dniResponsableDesdeLegajo(Legajo $legajo): ?string
    {
        $origen = self::origenResponsableDesdeLegajo($legajo);

        return $origen['dni'] ?? null;
    }

    /**
     * @return array{campo: string, dni: string, etiqueta: string}|null
     */
    public static function origenResponsableDesdeLegajo(Legajo $legajo): ?array
    {
        $campos = [
            'dnitut' => 'DNI del tutor',
            'respAdmiDni' => 'DNI del responsable administrativo',
            'dnipad' => 'DNI del padre',
            'dnimad' => 'DNI de la madre',
        ];

        foreach ($campos as $campo => $etiqueta) {
            $dni = AulicaDni::normalizar($legajo->{$campo} ?? null);
            if ($dni !== null) {
                return [
                    'campo' => $campo,
                    'dni' => $dni,
                    'etiqueta' => $etiqueta,
                ];
            }
        }

        return null;
    }

    public static function dniResponsableDesdeFila(object $fila): ?string
    {
        foreach (['dnitut', 'respAdmiDni', 'dnipad', 'dnimad'] as $campo) {
            $dni = AulicaDni::normalizar($fila->{$campo} ?? null);
            if ($dni !== null) {
                return $dni;
            }
        }

        return null;
    }

    /**
     * @param  list<AulicaSaldoPersona>  $personas
     * @return list<AulicaSaldoPersona>
     */
    private function soloDni(array $personas, ?string $dni): array
    {
        if ($dni === null) {
            return $personas;
        }

        return array_values(array_filter(
            $personas,
            fn (AulicaSaldoPersona $p) => AulicaDni::normalizar($p->nroDoc) === $dni,
        ));
    }
}
