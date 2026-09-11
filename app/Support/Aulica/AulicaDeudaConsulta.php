<?php

namespace App\Support\Aulica;

use App\Models\Familia;
use App\Models\Legajo;
use App\Support\InformeInasistencias;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Consulta deuda Áulica del estudiante (por DNI) y del grupo familiar (`familias.dniResp`).
 */
final class AulicaDeudaConsulta
{
    private const FAMILIA_SIN_ASIGNAR = 1;

    private const ETIQUETA_DNI_FAMILIA = 'DNI del responsable de la familia (familias.dniResp)';

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

        $legajo = self::legajoConFamilia((int) $ctx->idLegajo);
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
     * Fila de listado Secretaría u objeto con dni / dniResp / idFamilias.
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
     * Grupo familiar en Áulica: `familias.dniResp` (no tutor, padre, madre ni respAdmiDni).
     *
     * @return array{campo: string, dni: string, etiqueta: string}|null
     */
    public static function origenResponsableDesdeLegajo(Legajo $legajo): ?array
    {
        $idFamilia = (int) ($legajo->idFamilias ?? 0);
        if ($idFamilia <= self::FAMILIA_SIN_ASIGNAR) {
            return null;
        }

        if (! self::tablaFamiliasTieneDniResp()) {
            return null;
        }

        $dniRaw = null;
        if ($legajo->relationLoaded('familia')) {
            $dniRaw = $legajo->familia?->dniResp ?? null;
        } else {
            $dniRaw = Familia::query()->whereKey($idFamilia)->value('dniResp');
        }

        return self::origenDesdeDniResp($dniRaw);
    }

    public static function dniResponsableDesdeFila(object $fila): ?string
    {
        $origen = self::origenResponsableDesdeFila($fila);

        return $origen['dni'] ?? null;
    }

    /**
     * @return array{campo: string, dni: string, etiqueta: string}|null
     */
    public static function origenResponsableDesdeFila(object $fila): ?array
    {
        if ($fila instanceof Legajo) {
            return self::origenResponsableDesdeLegajo($fila);
        }

        $desdeFila = self::origenDesdeDniResp($fila->dniResp ?? null);
        if ($desdeFila !== null) {
            return $desdeFila;
        }

        $idFamilia = (int) ($fila->idFamilias ?? 0);
        if ($idFamilia <= self::FAMILIA_SIN_ASIGNAR || ! self::tablaFamiliasTieneDniResp()) {
            return null;
        }

        return self::origenDesdeDniResp(
            Familia::query()->whereKey($idFamilia)->value('dniResp')
        );
    }

    /**
     * @return array{campo: string, dni: string, etiqueta: string}|null
     */
    private static function origenDesdeDniResp(mixed $valor): ?array
    {
        $dni = AulicaDni::normalizar($valor);
        if ($dni === null) {
            return null;
        }

        return [
            'campo' => 'dniResp',
            'dni' => $dni,
            'etiqueta' => self::ETIQUETA_DNI_FAMILIA,
        ];
    }

    private static function tablaFamiliasTieneDniResp(): bool
    {
        return Schema::hasTable('familias') && Schema::hasColumn('familias', 'dniResp');
    }

    private static function legajoConFamilia(int $idLegajo): ?Legajo
    {
        $query = Legajo::query()->where('id', $idLegajo);
        if (self::tablaFamiliasTieneDniResp()) {
            $query->with('familia');
        }

        return $query->first();
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
