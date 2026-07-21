<?php

namespace App\Support\Tea;

use App\Models\Inasistencia;
use App\Models\ReincoRegistro;
use App\Support\InformeInasistencias;
use App\Support\InasistenciasResumen;
use Illuminate\Support\Collection;

/**
 * Detecta situaciones TEA alcanzadas por inasistencias del año y sin registro en {@see ReincoRegistro}.
 *
 * Umbrales alineados con los impresos PDF (tipos 1–5): 3/5 injustificadas, 10/20/25 totales de clase.
 */
final class TeaInstanciasPendientes
{
    /** @var array<int, array{min: float, metrica: 'injustificadas'|'total'}> */
    private const UMBRALES = [
        1 => ['min' => 3, 'metrica' => 'injustificadas'],
        2 => ['min' => 5, 'metrica' => 'injustificadas'],
        3 => ['min' => 10, 'metrica' => 'total'],
        4 => ['min' => 20, 'metrica' => 'total'],
        5 => ['min' => 25, 'metrica' => 'total'],
    ];

    public static function modulosActivos(): bool
    {
        return ReincoTea::tablasDisponibles();
    }

    /**
     * @return list<TeaInstanciaPendiente>
     */
    public static function deMatricula(int $idMatricula, ?int $anoLectivo = null): array
    {
        if ($idMatricula <= 0 || ! self::modulosActivos()) {
            return [];
        }

        return self::porMatriculas([$idMatricula], $anoLectivo)[$idMatricula] ?? [];
    }

    /**
     * @param  list<int>  $idsMatricula
     * @return array<int, list<TeaInstanciaPendiente>>
     */
    public static function porMatriculas(array $idsMatricula, ?int $anoLectivo = null): array
    {
        $idsMatricula = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $idsMatricula),
            static fn (int $id) => $id > 0,
        )));

        if ($idsMatricula === [] || ! self::modulosActivos()) {
            return [];
        }

        $ano = $anoLectivo ?? ReincoTea::anoLectivo();
        $rango = InformeInasistencias::rangoFechasParaAno($ano);

        /** @var Collection<int, Collection<int, Inasistencia>> $inasistenciasPorMatricula */
        $inasistenciasPorMatricula = Inasistencia::query()
            ->whereIn('idMatricula', $idsMatricula)
            ->whereBetween('fecha', [
                $rango['desde']->toDateString(),
                $rango['hasta']->toDateString(),
            ])
            ->get()
            ->groupBy(static fn (Inasistencia $i) => (int) $i->idMatricula);

        /** @var Collection<int, list<int>> $tiposCargadosPorMatricula */
        $tiposCargadosPorMatricula = ReincoRegistro::query()
            ->whereIn('idMatricula', $idsMatricula)
            ->get(['idMatricula', 'idReinco_tipo'])
            ->groupBy(static fn (ReincoRegistro $r) => (int) $r->idMatricula)
            ->map(static function (Collection $grupo): array {
                return $grupo
                    ->pluck('idReinco_tipo')
                    ->map(static fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            });

        $tipos = ReincoTea::tiposOrdenados();
        $resultado = [];

        foreach ($idsMatricula as $idMatricula) {
            $resumen = InasistenciasResumen::desdeColeccion(
                $inasistenciasPorMatricula->get($idMatricula, collect()),
            );
            $tiposCargados = $tiposCargadosPorMatricula->get($idMatricula, []);
            $pendientes = self::pendientesDesdeResumen($resumen, $tiposCargados, $tipos);

            if ($pendientes !== []) {
                $resultado[$idMatricula] = $pendientes;
            }
        }

        return $resultado;
    }

    /**
     * @param  list<int>  $tiposCargados
     * @param  Collection<int, \App\Models\ReincoTipo>  $tipos
     * @return list<TeaInstanciaPendiente>
     */
    private static function pendientesDesdeResumen(
        InasistenciasResumen $resumen,
        array $tiposCargados,
        Collection $tipos,
    ): array {
        $pendientes = [];

        foreach ($tipos as $tipo) {
            $idTipo = (int) $tipo->id;
            $regla = self::UMBRALES[$idTipo] ?? null;
            if ($regla === null) {
                continue;
            }

            if (in_array($idTipo, $tiposCargados, true)) {
                continue;
            }

            $valor = $regla['metrica'] === 'injustificadas'
                ? $resumen->injustificadas
                : $resumen->totalClase();

            if ($valor >= $regla['min']) {
                $pendientes[] = new TeaInstanciaPendiente(
                    idTipo: $idTipo,
                    etiqueta: $tipo->etiqueta(),
                    umbral: (int) $regla['min'],
                    metrica: $regla['metrica'],
                );
            }
        }

        return $pendientes;
    }

    /**
     * @param  list<TeaInstanciaPendiente>  $pendientes
     */
    public static function textoAviso(array $pendientes): string
    {
        if ($pendientes === []) {
            return '';
        }

        $partes = array_map(
            static fn (TeaInstanciaPendiente $p) => $p->etiquetaResumida(),
            $pendientes,
        );

        return 'TEA pendiente — '.implode(', ', $partes);
    }

    /**
     * Texto breve para grillas densas (detalle completo en {@see tituloAviso()}).
     *
     * @param  list<TeaInstanciaPendiente>  $pendientes
     */
    public static function textoAvisoCompacto(array $pendientes): string
    {
        if ($pendientes === []) {
            return '';
        }

        $umbrales = array_map(
            static fn (TeaInstanciaPendiente $p) => (string) $p->umbral,
            $pendientes,
        );

        return 'TEA pendiente ('.implode(', ', $umbrales).' inas.)';
    }

    /**
     * @param  list<TeaInstanciaPendiente>  $pendientes
     */
    public static function tituloAviso(array $pendientes): string
    {
        if ($pendientes === []) {
            return '';
        }

        $detalle = implode('; ', array_map(
            static fn (TeaInstanciaPendiente $p) => $p->etiquetaResumida(),
            $pendientes,
        ));

        return 'Ir a Gestión de TEA — situaciones sin cargar: '.$detalle;
    }
}
