<?php

namespace App\Support\Cuotas;

use App\Models\Cuota;
use App\Models\CuotaGenerada;
use App\Models\Curso;
use App\Models\Nivel;
use App\Support\NivelSistema;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Filtros y consulta de cuotas generadas para edición masiva (Administración).
 */
final class EdicionCuotasGeneradasConsulta
{
    public const MAX_FILAS = 2000;

    /**
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
     * @return Collection<int, Curso>
     */
    public static function cursosParaSelector(?int $idNivel = null): Collection
    {
        $cursos = GeneracionMasivaCuotasConsulta::cursosEnContexto();

        if ($idNivel !== null && $idNivel > 0) {
            $cursos = $cursos->filter(fn (Curso $c) => (int) ($c->idNivel ?? 0) === $idNivel);
        }

        return $cursos->values();
    }

    /**
     * Plantillas del ciclo lectivo activo.
     *
     * @return Collection<int, Cuota>
     */
    public static function cuotasParaSelector(): Collection
    {
        return Cuota::query()
            ->where('idTerlec', CuotasPlantillaCatalog::idTerlecActivo())
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'nombre', 'orden']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   idNivel: int,
     *   idCurso: int,
     *   idCuota: int,
     *   pagadoOp: string,
     *   pagadoValor: ?float,
     *   saldoOp: string,
     *   saldoValor: ?float
     * }
     */
    public static function normalizarFiltros(array $input): array
    {
        $idNivel = (int) ($input['idNivel'] ?? $input['nivel'] ?? 0);
        $nivelesPermitidos = collect(self::nivelesParaSelector())->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($idNivel !== 0 && ! in_array($idNivel, $nivelesPermitidos, true)) {
            throw ValidationException::withMessages([
                'idNivel' => 'Nivel no válido.',
            ]);
        }

        $idCurso = (int) ($input['idCurso'] ?? $input['curso'] ?? 0);
        $cursosPermitidos = self::cursosParaSelector($idNivel > 0 ? $idNivel : null)
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($idCurso !== 0 && ! in_array($idCurso, $cursosPermitidos, true)) {
            throw ValidationException::withMessages([
                'idCurso' => 'Curso no válido para el ciclo lectivo activo.',
            ]);
        }

        $idCuota = (int) ($input['idCuota'] ?? $input['cuota'] ?? 0);
        $cuotasPermitidas = self::cuotasParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($idCuota !== 0 && ! in_array($idCuota, $cuotasPermitidas, true)) {
            throw ValidationException::withMessages([
                'idCuota' => 'Cuota no válida.',
            ]);
        }

        $pagadoOp = FiltroComparacionNumerica::normalizarOperador($input['pagadoOp'] ?? $input['pagado_op'] ?? '');
        $pagadoValor = self::parseImporteOpcional($input['pagadoValor'] ?? $input['pagado'] ?? null);
        if ($pagadoOp !== '' && $pagadoValor === null) {
            throw ValidationException::withMessages([
                'pagadoValor' => 'Indique el importe pagado para aplicar el comparador.',
            ]);
        }

        $saldoOp = FiltroComparacionNumerica::normalizarOperador($input['saldoOp'] ?? $input['saldo_op'] ?? $input['faltapaOp'] ?? '');
        $saldoValor = self::parseImporteOpcional($input['saldoValor'] ?? $input['saldo'] ?? $input['faltapa'] ?? null);
        if ($saldoOp !== '' && $saldoValor === null) {
            throw ValidationException::withMessages([
                'saldoValor' => 'Indique el saldo para aplicar el comparador.',
            ]);
        }

        if ($idNivel === 0 && $idCurso === 0 && $idCuota === 0 && $pagadoOp === '' && $saldoOp === '') {
            throw ValidationException::withMessages([
                'filtros' => 'Defina al menos nivel, curso, cuota, pagado o saldo para acotar la búsqueda.',
            ]);
        }

        return [
            'idNivel' => $idNivel,
            'idCurso' => $idCurso,
            'idCuota' => $idCuota,
            'pagadoOp' => $pagadoOp,
            'pagadoValor' => $pagadoValor,
            'saldoOp' => $saldoOp,
            'saldoValor' => $saldoValor,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<CuotaGenerada>
     */
    public static function consulta(array $filtros): Builder
    {
        $idTerlec = CuotasPlantillaCatalog::idTerlecActivo();

        $query = CuotaGenerada::query()
            ->where('cuotasgeneradas.idTerlec', $idTerlec)
            ->join('legajos', 'legajos.id', '=', 'cuotasgeneradas.idLegajos')
            ->join('cursos', 'cursos.Id', '=', 'cuotasgeneradas.idCursos')
            ->join('niveles', 'niveles.id', '=', 'cursos.idNivel')
            ->join('cuotas', 'cuotas.id', '=', 'cuotasgeneradas.idCuotas')
            ->select([
                'cuotasgeneradas.id',
                'cuotasgeneradas.idLegajos',
                'cuotasgeneradas.idCursos',
                'cuotasgeneradas.idCuotas',
                'cuotasgeneradas.venc1',
                'cuotasgeneradas.venc2',
                'cuotasgeneradas.venc3',
                'cuotasgeneradas.nueVenc',
                'cuotasgeneradas.idCuotasbecas',
                'cuotasgeneradas.importe',
                'cuotasgeneradas.bonificacion',
                'cuotasgeneradas.interes',
                'cuotasgeneradas.pagado',
                'cuotasgeneradas.faltapa',
            ]);

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'cursos.idNivel');

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
            'cuotasgeneradas.pagado',
            (string) ($filtros['pagadoOp'] ?? ''),
            $filtros['pagadoValor'] ?? null,
        );

        FiltroComparacionNumerica::aplicar(
            $query,
            'cuotasgeneradas.faltapa',
            (string) ($filtros['saldoOp'] ?? ''),
            $filtros['saldoValor'] ?? null,
        );

        return $query
            ->orderBy('niveles.nivel')
            ->orderBy('cursos.orden')
            ->orderBy('cursos.cursec')
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->orderBy('cuotasgeneradas.id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, CuotaGenerada>
     */
    public static function registrosParaEdicion(array $filtros): Collection
    {
        $total = self::consulta($filtros)->count();
        if ($total > self::MAX_FILAS) {
            throw ValidationException::withMessages([
                'filtros' => 'Hay demasiados registros ('.$total.'). Acote los filtros (máximo '.self::MAX_FILAS.').',
            ]);
        }

        return self::consulta($filtros)
            ->with([
                'legajo:id,apellido,nombre',
                'curso:Id,cursec,idNivel',
                'curso.nivel:id,nivel,abrev',
                'cuota:id,nombre,sinConBeca,idCuotastipo',
            ])
            ->get();
    }

    public static function registroEditable(int $idCuotaGenerada): ?CuotaGenerada
    {
        if ($idCuotaGenerada < 1) {
            return null;
        }

        $idTerlec = CuotasPlantillaCatalog::idTerlecActivo();

        return CuotaGenerada::query()
            ->whereKey($idCuotaGenerada)
            ->where('idTerlec', $idTerlec)
            ->whereHas('curso', function ($query): void {
                SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'cursos.idNivel');
            })
            ->with(['cuota:id,sinConBeca,idCuotastipo'])
            ->first();
    }

    private static function parseImporteOpcional(mixed $valor): ?float
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw === '') {
            return null;
        }

        return round(CuotasFormato::parseImporte($raw), 2);
    }
}
