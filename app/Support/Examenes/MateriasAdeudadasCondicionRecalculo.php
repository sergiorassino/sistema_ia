<?php

namespace App\Support\Examenes;

use App\Support\Configuracion\PromoverAlumnosAnio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula condAdeuda e inscri en calificaciones adeudadas (apro = 1).
 *
 * Rama principal (EQ/TM intactas; regulares → PR): portación de anaCond() legado.
 * Rama egresados: último año de medio, turnos feb/abr/jul/sep del año posterior
 * (todas las materias adeudadas, no solo las del último curso).
 * Condición de esa ventana: `RE` por default; `PR` si el tenant lo configura.
 */
final class MateriasAdeudadasCondicionRecalculo
{
    /**
     * @return array{procesados:int, actualizados:int, omitidos:int}
     */
    public static function recalcularNivel(
        int $idNivel,
        int $idTerlecActual,
        int $idTerlecTurno,
        int $idTurno,
    ): array {
        $anoTurno = MateriasAdeudadasPreparacion::anoTerlec($idTerlecTurno);
        if ($idTurno <= 0 || $anoTurno === null) {
            return ['procesados' => 0, 'actualizados' => 0, 'omitidos' => 0];
        }

        $examTodosInscri = strtoupper(trim((string) DB::table('ento')
            ->where('idNivel', $idNivel)
            ->value('examTodosInscri')));

        $regulares = DB::table('matricula')
            ->where('idTerlec', $idTerlecActual)
            ->where('idCondiciones', 1)
            ->pluck('idLegajos')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $turnoHastaSeptiembre = self::turnoEsVentanaRegularEgresado(
            MateriasAdeudadasPreparacion::textoTurnoParaClasificar($idTurno),
        );

        $condicionVentanaEgresado = self::condicionVentanaEgresado();
        $egresadosUltimoAnio = self::legajosEgresadosUltimoAnioAnterior($idNivel, $anoTurno);

        $procesados = 0;
        $actualizados = 0;
        $omitidos = 0;

        DB::table('calificaciones as c')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.apro', 1)
            ->where('cu.idNivel', $idNivel)
            ->select([
                'c.id',
                'c.idLegajos',
                'c.condAdeuda',
            ])
            ->orderBy('c.id')
            ->chunk(250, function ($filas) use (
                $regulares,
                $egresadosUltimoAnio,
                $turnoHastaSeptiembre,
                $condicionVentanaEgresado,
                $examTodosInscri,
                &$procesados,
                &$actualizados,
                &$omitidos,
            ) {
                foreach ($filas as $fila) {
                    $procesados++;
                    $idLegajo = (int) $fila->idLegajos;
                    $cambio = self::analizarYActualizarFila(
                        (int) $fila->id,
                        (string) ($fila->condAdeuda ?? ''),
                        $regulares->has($idLegajo),
                        $egresadosUltimoAnio->has($idLegajo),
                        $turnoHastaSeptiembre,
                        $condicionVentanaEgresado,
                        $examTodosInscri,
                    );
                    if ($cambio) {
                        $actualizados++;
                    } else {
                        $omitidos++;
                    }
                }
            });

        return [
            'procesados' => $procesados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
        ];
    }

    /**
     * Condición escrita a egresados en la ventana feb/abr/jul/sep.
     * Default `RE`; `PR` si el tenant lo configura. Cualquier otro valor → `RE`.
     */
    public static function condicionVentanaEgresado(): string
    {
        $valor = function_exists('tenantExamenesEgresadosVentanaCondicion')
            ? tenantExamenesEgresadosVentanaCondicion()
            : 'RE';

        return self::normalizarCondicionVentanaEgresado((string) $valor);
    }

    public static function normalizarCondicionVentanaEgresado(string $valor): string
    {
        return strtoupper(trim($valor)) === 'PR' ? 'PR' : 'RE';
    }

    /**
     * Null = no tocar (EQ/TM). PR o RE en el resto.
     */
    public static function decidirCondicion(
        string $condAdeuda,
        bool $esRegularAnioActual,
        bool $esEgresadoUltimoAnioAnterior,
        bool $turnoHastaSeptiembre,
        string $condicionVentanaEgresado = 'RE',
    ): ?string {
        $cond = strtoupper(trim($condAdeuda));

        if ($cond === 'EQ' || $cond === 'TM') {
            return null;
        }

        if ($esRegularAnioActual) {
            return 'PR';
        }

        if ($esEgresadoUltimoAnioAnterior && $turnoHastaSeptiembre) {
            return self::normalizarCondicionVentanaEgresado($condicionVentanaEgresado);
        }

        return 'PR';
    }

    /**
     * Febrero, abril, julio o septiembre (setiembre). Diciembre y nombres no reconocidos: no.
     */
    public static function turnoEsVentanaRegularEgresado(string $etiquetaTurno): bool
    {
        $tokens = self::tokensTurno($etiquetaTurno);
        if ($tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            if (in_array($token, ['diciembre', 'dic'], true)) {
                return false;
            }
        }

        foreach ($tokens as $token) {
            if (in_array($token, [
                'febrero', 'feb',
                'abril', 'abr',
                'julio', 'jul',
                'setiembre', 'septiembre', 'sept', 'sep',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inscribe a mesa (`inscri = 1`) si el colegio inscribe a todos: regulares del
     * ciclo (PR) y egresados de último año en la ventana feb–sep (todas las materias
     * adeudadas, no solo las de 6.º), aunque el tenant asigne `PR` en vez de `RE`.
     *
     * @return array{condAdeuda: string, inscri?: int}
     */
    public static function payloadActualizacion(
        string $nuevaCond,
        bool $esRegularAnioActual,
        string $examTodosInscri,
        bool $esEgresadoEnVentana = false,
    ): array {
        $datos = ['condAdeuda' => $nuevaCond];
        if ($examTodosInscri !== 'T') {
            return $datos;
        }

        if ($esRegularAnioActual || $nuevaCond === 'RE' || $esEgresadoEnVentana) {
            $datos['inscri'] = 1;
        }

        return $datos;
    }

    /**
     * Equivalente a anaCond() + rama de egresados de último año (RE o PR según tenant).
     */
    private static function analizarYActualizarFila(
        int $idCalificacion,
        string $condAdeuda,
        bool $esRegularAnioActual,
        bool $esEgresadoUltimoAnioAnterior,
        bool $turnoHastaSeptiembre,
        string $condicionVentanaEgresado,
        string $examTodosInscri,
    ): bool {
        $esEgresadoEnVentana = $esEgresadoUltimoAnioAnterior && $turnoHastaSeptiembre;
        $nuevaCond = self::decidirCondicion(
            $condAdeuda,
            $esRegularAnioActual,
            $esEgresadoUltimoAnioAnterior,
            $turnoHastaSeptiembre,
            $condicionVentanaEgresado,
        );

        if ($nuevaCond === null) {
            return false;
        }

        return self::actualizar(
            $idCalificacion,
            self::payloadActualizacion(
                $nuevaCond,
                $esRegularAnioActual,
                $examTodosInscri,
                $esEgresadoEnVentana,
            ),
        );
    }

    /**
     * Legajos que cursaron como regulares el último año de medio en el ciclo
     * lectivo anterior al año del turno de examen (por `terlec.ano`, no por id).
     *
     * @return Collection<int, int>
     */
    private static function legajosEgresadosUltimoAnioAnterior(int $idNivel, int $anoTurno): Collection
    {
        $vacio = collect();

        if ($idNivel < 1 || $anoTurno < 2 || ! self::nivelEsSecundario($idNivel)) {
            return $vacio;
        }

        $idTerlecAnterior = (int) (DB::table('terlec')
            ->where('ano', $anoTurno - 1)
            ->orderByDesc('id')
            ->value('id') ?? 0);

        if ($idTerlecAnterior < 1) {
            return $vacio;
        }

        $ultimoCurso = PromoverAlumnosAnio::ultimoCursoSecundario();
        $filas = DB::table('matricula as m')
            ->join('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->where('m.idTerlec', $idTerlecAnterior)
            ->where('m.idCondiciones', 1)
            ->where('m.idNivel', $idNivel)
            ->get(['m.idLegajos', 'cu.c']);

        $ids = [];
        foreach ($filas as $fila) {
            if (PromoverAlumnosAnio::cursoNumero($fila->c) !== $ultimoCurso) {
                continue;
            }
            $ids[(int) $fila->idLegajos] = (int) $fila->idLegajos;
        }

        return collect($ids);
    }

    private static function nivelEsSecundario(int $idNivel): bool
    {
        $nombre = mb_strtolower(trim((string) DB::table('niveles')->where('id', $idNivel)->value('nivel')));

        return str_contains($nombre, 'secundari');
    }

    /**
     * @return list<string>
     */
    private static function tokensTurno(string $etiqueta): array
    {
        $texto = mb_strtolower(trim($etiqueta));
        if ($texto === '') {
            return [];
        }

        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u',
        ]);

        $partes = preg_split('/[^a-z0-9]+/', $texto, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($partes) ? array_values($partes) : [];
    }

    /**
     * @param  array{inscri?:int, condAdeuda:string}  $datos
     */
    private static function actualizar(int $idCalificacion, array $datos): bool
    {
        $afectados = DB::table('calificaciones')
            ->where('id', $idCalificacion)
            ->update($datos);

        return $afectados > 0;
    }
}
