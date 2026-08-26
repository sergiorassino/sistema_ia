<?php

namespace App\Support\Cuotas;

use App\Models\Cuota;
use App\Models\CuotaGenerada;
use App\Models\Curso;
use App\Models\Nivel;
use App\Models\Terlec;
use App\Support\NivelSistema;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Consulta y armado de datos para el PDF «Listado de estudiantes por cuota».
 */
final class ListadoEstudiantesPorCuotaDatos
{
    /**
     * Ciclos lectivos para el filtro de año de la cuota (más reciente primero).
     *
     * @return Collection<int, Terlec>
     */
    public static function terlecsParaSelector(): Collection
    {
        return Terlec::paraSelector();
    }

    /**
     * Niveles pedagógicos disponibles en el alcance institucional.
     *
     * @return list<array{id: int, nombre: string, abrev: string}>
     */
    public static function nivelesParaSelector(): array
    {
        $query = Nivel::query()
            ->where('id', '<', NivelSistema::ADMINISTRACION)
            ->orderBy('id');

        $idFiltro = SchoolAlcancePedagogico::idNivelFiltroUnico();
        if ($idFiltro !== null) {
            $query->whereKey($idFiltro);
        }

        return $query
            ->get(['id', 'nivel', 'abrev'])
            ->map(fn (Nivel $nivel) => [
                'id' => (int) $nivel->id,
                'nombre' => trim((string) ($nivel->nivel ?? '')),
                'abrev' => trim((string) ($nivel->abrev ?? '')),
            ])
            ->all();
    }

    /**
     * Cursos del ciclo lectivo activo (año actual de contexto).
     * Si se indica nivel, solo cursos de ese nivel.
     *
     * @return Collection<int, Curso>
     */
    public static function cursosAnoActualParaSelector(?int $idNivel = null): Collection
    {
        $cursos = GeneracionMasivaCuotasConsulta::cursosEnContexto();

        if ($idNivel !== null && $idNivel > 0) {
            $cursos = $cursos->filter(fn (Curso $c) => (int) ($c->idNivel ?? 0) === $idNivel);
        }

        return $cursos->values();
    }

    /**
     * Plantillas de cuota de todos los ciclos, ordenadas por año y orden.
     *
     * @return Collection<int, Cuota>
     */
    public static function cuotasParaSelector(): Collection
    {
        return Cuota::query()
            ->join('terlec', 'terlec.id', '=', 'cuotas.idTerlec')
            ->selectRaw('cuotas.id, cuotas.nombre, cuotas.orden, terlec.ano as terlec_ano')
            ->orderByDesc('terlec.ano')
            ->orderBy('cuotas.orden')
            ->orderBy('cuotas.id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizarFiltros(array $input): array
    {
        $anoOp = FiltroComparacionNumerica::normalizarOperador($input['ano_op'] ?? $input['anoOp'] ?? '');
        $idTerlecCuota = (int) ($input['terlec'] ?? $input['idTerlecCuota'] ?? 0);

        $terlecsPermitidos = self::terlecsParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($idTerlecCuota !== 0 && ! in_array($idTerlecCuota, $terlecsPermitidos, true)) {
            throw ValidationException::withMessages([
                'terlec' => 'Año lectivo no válido.',
            ]);
        }

        $anoValor = self::anoDesdeTerlec($idTerlecCuota);

        if ($anoOp !== '' && $anoValor === null) {
            throw ValidationException::withMessages([
                'terlec' => 'Seleccione el año lectivo de la cuota para aplicar el comparador.',
            ]);
        }

        $idNivel = (int) ($input['nivel'] ?? $input['idNivel'] ?? 0);
        $nivelesPermitidos = collect(self::nivelesParaSelector())->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($idNivel !== 0 && ! in_array($idNivel, $nivelesPermitidos, true)) {
            throw ValidationException::withMessages([
                'nivel' => 'Nivel no válido.',
            ]);
        }

        $idCurso = (int) ($input['curso'] ?? $input['idCurso'] ?? 0);
        $cursosPermitidos = self::cursosAnoActualParaSelector($idNivel > 0 ? $idNivel : null)
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($idCurso !== 0 && ! in_array($idCurso, $cursosPermitidos, true)) {
            throw ValidationException::withMessages([
                'curso' => 'Curso no válido para el ciclo lectivo activo.',
            ]);
        }

        $idCuota = (int) ($input['cuota'] ?? $input['idCuota'] ?? 0);
        $cuotasPermitidas = self::cuotasParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($idCuota !== 0 && ! in_array($idCuota, $cuotasPermitidas, true)) {
            throw ValidationException::withMessages([
                'cuota' => 'Cuota no válida.',
            ]);
        }

        $importeOp = FiltroComparacionNumerica::normalizarOperador($input['importe_op'] ?? $input['importeOp'] ?? '');
        $importeValor = self::parseImporteOpcional($input['importe'] ?? $input['importeValor'] ?? null);
        if ($importeOp !== '' && $importeValor === null) {
            throw ValidationException::withMessages([
                'importe' => 'Indique el importe para aplicar el comparador.',
            ]);
        }

        $pagadoOp = FiltroComparacionNumerica::normalizarOperador($input['pagado_op'] ?? $input['pagadoOp'] ?? '');
        $pagadoValor = self::parseImporteOpcional($input['pagado'] ?? $input['pagadoValor'] ?? null);
        if ($pagadoOp !== '' && $pagadoValor === null) {
            throw ValidationException::withMessages([
                'pagado' => 'Indique el importe pagado para aplicar el comparador.',
            ]);
        }

        return [
            'anoOp' => $anoOp,
            'idTerlecCuota' => $idTerlecCuota,
            'anoValor' => $anoValor,
            'idNivel' => $idNivel,
            'idCurso' => $idCurso,
            'idCuota' => $idCuota,
            'importeOp' => $importeOp,
            'importeValor' => $importeValor,
            'pagadoOp' => $pagadoOp,
            'pagadoValor' => $pagadoValor,
            'titAno' => self::tituloAno($anoOp, $anoValor),
            'titNivel' => self::tituloNivel($idNivel),
            'titCurso' => self::tituloCurso($idCurso, $idNivel),
            'titCuota' => self::tituloCuota($idCuota),
            'titImporte' => FiltroComparacionNumerica::etiquetaFiltro('Importe', $importeOp, $importeValor),
            'titPagado' => FiltroComparacionNumerica::etiquetaFiltro('Pagado', $pagadoOp, $pagadoValor),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    public static function build(array $filtros): ?array
    {
        $filas = self::consulta($filtros)
            ->get()
            ->map(fn (CuotaGenerada $registro, int $indice): array => self::filaDesdeRegistro($registro, $indice + 1))
            ->values()
            ->all();

        $totImporte = 0.0;
        $totBonif = 0.0;
        $totInteres = 0.0;
        $totPagado = 0.0;
        $totSaldo = 0.0;

        foreach ($filas as $fila) {
            $totImporte += (float) ($fila['_importe'] ?? 0);
            $totBonif += (float) ($fila['_bonificacion'] ?? 0);
            $totInteres += (float) ($fila['_interes'] ?? 0);
            $totPagado += (float) ($fila['_pagado'] ?? 0);
            $totSaldo += (float) ($fila['_saldo'] ?? 0);
        }

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'anoContexto' => (int) schoolCtx()->terlecAno(),
            'filtros' => $filtros,
            'filas' => $filas,
            'totales' => [
                'importe' => CuotasFormato::formatearImporte($totImporte),
                'bonificacion' => CuotasFormato::formatearImporte($totBonif),
                'interes' => CuotasFormato::formatearImporte($totInteres),
                'pagado' => CuotasFormato::formatearImporte($totPagado),
                'saldo' => CuotasFormato::formatearImporte($totSaldo),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<CuotaGenerada>
     */
    private static function consulta(array $filtros): Builder
    {
        $query = CuotaGenerada::query()
            ->select([
                'cuotasgeneradas.id',
                'cuotasgeneradas.idLegajos',
                'cuotasgeneradas.idCursos',
                'cuotasgeneradas.idCuotas',
                'cuotasgeneradas.idTerlec',
                'cuotasgeneradas.venc1',
                'cuotasgeneradas.venc2',
                'cuotasgeneradas.venc3',
                'cuotasgeneradas.importe',
                'cuotasgeneradas.bonificacion',
                'cuotasgeneradas.interes',
                'cuotasgeneradas.pagado',
                'cuotasgeneradas.faltapa',
            ])
            ->join('legajos', 'legajos.id', '=', 'cuotasgeneradas.idLegajos')
            ->join('cursos', 'cursos.Id', '=', 'cuotasgeneradas.idCursos')
            ->join('niveles', 'niveles.id', '=', 'cursos.idNivel')
            ->join('cuotas', 'cuotas.id', '=', 'cuotasgeneradas.idCuotas')
            ->join('terlec', 'terlec.id', '=', 'cuotasgeneradas.idTerlec')
            ->with([
                'legajo:id,apellido,nombre',
                'curso:Id,cursec,idNivel',
                'curso.nivel:id,nivel',
                'cuota:id,nombre,orden',
                'terlec:id,ano',
            ]);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'cursos.idNivel');

        $anoOp = (string) ($filtros['anoOp'] ?? '');
        $anoValor = $filtros['anoValor'] ?? null;
        if ($anoOp !== '' && $anoValor !== null) {
            FiltroComparacionNumerica::aplicar($query, 'terlec.ano', $anoOp, (float) $anoValor);
        }

        $idNivel = (int) ($filtros['idNivel'] ?? 0);
        if ($idNivel > 0) {
            $query->where('cursos.idNivel', $idNivel);
        }

        $idCurso = (int) ($filtros['idCurso'] ?? 0);
        if ($idCurso > 0) {
            $query->where('cuotasgeneradas.idCursos', $idCurso);
        }

        $idCuota = (int) ($filtros['idCuota'] ?? 0);
        if ($idCuota > 0) {
            $query->where('cuotasgeneradas.idCuotas', $idCuota);
        }

        FiltroComparacionNumerica::aplicar(
            $query,
            'cuotasgeneradas.importe',
            (string) ($filtros['importeOp'] ?? ''),
            $filtros['importeValor'] ?? null,
        );

        FiltroComparacionNumerica::aplicar(
            $query,
            'cuotasgeneradas.pagado',
            (string) ($filtros['pagadoOp'] ?? ''),
            $filtros['pagadoValor'] ?? null,
        );

        return $query
            ->orderBy('terlec.ano')
            ->orderBy('cuotas.orden')
            ->orderBy('niveles.nivel')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->orderBy('cuotasgeneradas.id');
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaDesdeRegistro(CuotaGenerada $registro, int $numero): array
    {
        $importe = round((float) ($registro->importe ?? 0), 2);
        $bonificacion = round((float) ($registro->bonificacion ?? 0), 2);
        $interes = round((float) ($registro->interes ?? 0), 2);
        $pagado = round((float) ($registro->pagado ?? 0), 2);
        $saldo = round((float) ($registro->faltapa ?? 0), 2);
        $ano = (int) ($registro->terlec?->ano ?? 0);
        $apellido = mb_strtoupper(trim((string) ($registro->legajo?->apellido ?? '')));
        $nombre = mb_strtoupper(trim((string) ($registro->legajo?->nombre ?? '')));
        $estudiante = trim($apellido.($apellido !== '' && $nombre !== '' ? ', ' : '').$nombre);

        return [
            'numero' => (string) $numero,
            'estudiante' => $estudiante,
            'cursec' => mb_strtoupper(trim((string) ($registro->curso?->cursec ?? ''))),
            'ano' => self::formatearAnoLectivo($ano),
            'nivel' => mb_strtoupper(trim((string) ($registro->curso?->nivel?->nivel ?? ''))),
            'cuota' => mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? ''))),
            'venc1' => CuotasFormato::formatearFecha($registro->venc1),
            'venc2' => CuotasFormato::formatearFecha($registro->venc2),
            'venc3' => CuotasFormato::formatearFecha($registro->venc3),
            'importe' => CuotasFormato::formatearImporte($importe),
            'bonificacion' => CuotasFormato::formatearImporte($bonificacion),
            'interes' => CuotasFormato::formatearImporte($interes),
            'pagado' => CuotasFormato::formatearImporte($pagado),
            'saldo' => CuotasFormato::formatearImporte($saldo),
            '_importe' => $importe,
            '_bonificacion' => $bonificacion,
            '_interes' => $interes,
            '_pagado' => $pagado,
            '_saldo' => $saldo,
        ];
    }

    private static function formatearAnoLectivo(int $ano): string
    {
        if ($ano < 1) {
            return '';
        }

        return number_format($ano, 0, '', '.');
    }

    private static function tituloAno(string $operador, ?int $valor): string
    {
        if ($operador === '' || $valor === null) {
            return 'TODOS';
        }

        $simbolo = match ($operador) {
            FiltroComparacionNumerica::OP_GT => '>',
            FiltroComparacionNumerica::OP_LT => '<',
            default => '=',
        };

        return 'Año '.$simbolo.' '.self::formatearAnoLectivo($valor);
    }

    private static function tituloNivel(int $idNivel): string
    {
        if ($idNivel === 0) {
            return 'TODOS';
        }

        $nivel = collect(self::nivelesParaSelector())->firstWhere('id', $idNivel);

        return $nivel !== null
            ? (string) ($nivel['nombre'] !== '' ? $nivel['nombre'] : 'TODOS')
            : 'TODOS';
    }

    private static function tituloCurso(int $idCurso, int $idNivel = 0): string
    {
        if ($idCurso === 0) {
            return 'TODOS';
        }

        $curso = self::cursosAnoActualParaSelector($idNivel > 0 ? $idNivel : null)
            ->firstWhere('Id', $idCurso);

        return $curso !== null
            ? GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($curso)
            : 'TODOS';
    }

    private static function tituloCuota(int $idCuota): string
    {
        if ($idCuota === 0) {
            return 'TODAS';
        }

        $cuota = self::cuotasParaSelector()->firstWhere('id', $idCuota);
        if ($cuota === null) {
            return 'TODAS';
        }

        $ano = (int) ($cuota->terlec_ano ?? 0);
        $nombre = trim((string) ($cuota->nombre ?? ''));

        return $ano > 0 ? $ano.' — '.$nombre : $nombre;
    }

    private static function anoDesdeTerlec(int $idTerlec): ?int
    {
        if ($idTerlec < 1) {
            return null;
        }

        $terlec = self::terlecsParaSelector()->firstWhere('id', $idTerlec);
        if ($terlec === null) {
            return null;
        }

        $ano = (int) ($terlec->ano ?? 0);

        return $ano > 0 ? $ano : null;
    }

    private static function parseImporteOpcional(mixed $valor): ?float
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw === '') {
            return null;
        }

        $parsed = CuotasFormato::parseImporte($raw);

        return round($parsed, 2);
    }
}
