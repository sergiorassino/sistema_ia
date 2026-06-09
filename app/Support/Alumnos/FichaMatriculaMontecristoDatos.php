<?php

namespace App\Support\Alumnos;

use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Terlec;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos para la ficha de solicitud de matrícula Montecristo (legacy FPDF Legal).
 */
final class FichaMatriculaMontecristoDatos
{
    /** @var list<string> */
    private const COLUMNAS_LEGAJO_BASE = [
        'apellido', 'nombre', 'dni', 'sexo', 'fechnaci', 'ln_ciudad', 'nacion',
        'callenum', 'barrio', 'localidad', 'telefono', 'email',
        'nombrepad', 'dnipad', 'telepad', 'emailpad', 'ocupacpad',
        'nombremad', 'dnimad', 'telemad', 'emailmad', 'ocupacmad',
        'vivecon', 'escori', 'codpos',
        'nacionpad', 'vivepad', 'fechnacpad', 'nacionmad', 'vivemad', 'fechnacmad',
        'domipad', 'domimad', 'lugtrapad', 'lugtramad',
        'ec_padres', 'estacivipad', 'obs',
    ];

    /** @var list<string> */
    private const COLUMNAS_LEGAJO_OPCIONALES = [
        'obso_sn', 'obso_nombre', 'obso_nro', 'religion', 'sacramentos',
        'locapad', 'locamad', 'telealte1_nom', 'telealte1_tel', 'telealte2_nom', 'telealte2_tel',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public static function paraMatricula(int $idMatricula): ?array
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $select = self::columnasSelect();
        $select[] = 'matricula.id as matricula_id';
        $select[] = 'matricula.idLegajos as id_legajo';
        $select[] = 'matricula.idCursos as id_curso';
        $select[] = 'matricula.nroMatricula as nro_matricula';
        $select[] = 'matricula.fechaMatricula as fecha_matricula';
        if (Schema::hasColumn('matricula', 'fechaMatriculacion')) {
            $select[] = 'matricula.fechaMatriculacion as fecha_matriculacion';
        }
        if (Schema::hasColumn('matricula', 'matricCondic')) {
            $select[] = 'matricula.matricCondic as matric_condic';
        } else {
            $select[] = 'matricula.idCondiciones as id_condiciones';
        }

        $row = DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->where('matricula.id', $idMatricula)
            ->where('matricula.idNivel', (int) $ctx->idNivel)
            ->where('matricula.idTerlec', (int) $ctx->idTerlec)
            ->whereNull('matricula.fechaBaja')
            ->select($select)
            ->first();

        if ($row === null) {
            return null;
        }

        $cursoActual = self::cursoConNivel((int) $row->id_curso);
        $cursoAnterior = self::cursoAnterior((int) $row->id_legajo, (int) $ctx->idTerlec);

        $fechaMatricula = self::fecha(
            $row->fecha_matriculacion ?? $row->fecha_matricula ?? null,
        );
        $fechnaci = self::fecha($row->fechnaci ?? null);
        $edad = self::calcularEdad($row->fechnaci ?? null, $row->fecha_matricula ?? null);

        $header = schoolPdfHeaderData();
        $anoTerlec = (int) ($ctx->terlecAno() ?? 0);

        return [
            'header' => $header,
            'insti' => trim((string) ($header['insti'] ?? '')),
            'cicloLectivo' => $anoTerlec > 0 ? (string) $anoTerlec : '',
            'localidadInstitucion' => trim((string) ($header['localidad'] ?? 'Monte Cristo')),
            'fechaMatriculacion' => $fechaMatricula,
            'nroMatricula' => trim((string) ($row->nro_matricula ?? '')),
            'matriculaCondicional' => self::esMatriculaCondicional($row),
            'curso' => trim((string) ($cursoActual['cursec'] ?? '')),
            'nombreNivel' => trim((string) ($cursoActual['nivel'] ?? '')),
            'cursoAnterior' => trim((string) ($cursoAnterior['cursec'] ?? '')),
            'nombreNivelAnterior' => trim((string) ($cursoAnterior['nivel'] ?? '')),
            'apellido' => trim((string) ($row->apellido ?? '')),
            'nombre' => trim((string) ($row->nombre ?? '')),
            'dni' => trim((string) ($row->dni ?? '')),
            'fechnaci' => $fechnaci,
            'edad' => $edad,
            'ln_ciudad' => trim((string) ($row->ln_ciudad ?? '')),
            'nacion' => trim((string) ($row->nacion ?? '')),
            'escori' => trim((string) ($row->escori ?? '')),
            'vivecon' => trim((string) ($row->vivecon ?? '')),
            'callenum' => trim((string) ($row->callenum ?? '')),
            'barrio' => trim((string) ($row->barrio ?? '')),
            'localidad' => trim((string) ($row->localidad ?? '')),
            'codpos' => trim((string) ($row->codpos ?? '')),
            'telefono' => trim((string) ($row->telefono ?? '')),
            'obso_sn' => trim((string) ($row->obso_sn ?? '')),
            'obso_nombre' => trim((string) ($row->obso_nombre ?? '')),
            'obso_nro' => trim((string) ($row->obso_nro ?? '')),
            'religion' => trim((string) ($row->religion ?? '')),
            'sacramentos' => trim((string) ($row->sacramentos ?? '')),
            'nombrepad' => trim((string) ($row->nombrepad ?? '')),
            'nacionpad' => trim((string) ($row->nacionpad ?? '')),
            'vivepad' => trim((string) ($row->vivepad ?? '')),
            'dnipad' => trim((string) ($row->dnipad ?? '')),
            'fechnacpad' => self::fecha($row->fechnacpad ?? null),
            'domipad' => trim((string) ($row->domipad ?? '')),
            'locapad' => trim((string) ($row->locapad ?? '')),
            'telepad' => trim((string) ($row->telepad ?? '')),
            'ocupacpad' => trim((string) ($row->ocupacpad ?? '')),
            'lugtrapad' => trim((string) ($row->lugtrapad ?? '')),
            'emailpad' => trim((string) ($row->emailpad ?? '')),
            'nombremad' => trim((string) ($row->nombremad ?? '')),
            'nacionmad' => trim((string) ($row->nacionmad ?? '')),
            'vivemad' => trim((string) ($row->vivemad ?? '')),
            'dnimad' => trim((string) ($row->dnimad ?? '')),
            'fechnacmad' => self::fecha($row->fechnacmad ?? null),
            'domimad' => trim((string) ($row->domimad ?? '')),
            'locamad' => trim((string) ($row->locamad ?? '')),
            'telemad' => trim((string) ($row->telemad ?? '')),
            'ocupacmad' => trim((string) ($row->ocupacmad ?? '')),
            'lugtramad' => trim((string) ($row->lugtramad ?? '')),
            'emailmad' => trim((string) ($row->emailmad ?? '')),
            'telealte1_nom' => trim((string) ($row->telealte1_nom ?? '')),
            'telealte1_tel' => trim((string) ($row->telealte1_tel ?? '')),
            'telealte2_nom' => trim((string) ($row->telealte2_nom ?? '')),
            'telealte2_tel' => trim((string) ($row->telealte2_tel ?? '')),
        ];
    }

    /**
     * @return list<string>
     */
    private static function columnasSelect(): array
    {
        $columnas = self::COLUMNAS_LEGAJO_BASE;

        foreach (self::COLUMNAS_LEGAJO_OPCIONALES as $columna) {
            if (Schema::hasColumn('legajos', $columna)) {
                $columnas[] = $columna;
            }
        }

        return collect($columnas)
            ->unique()
            ->map(fn (string $col) => 'legajos.'.$col.' as '.$col)
            ->values()
            ->all();
    }

    /**
     * @return array{cursec: string, nivel: string}
     */
    private static function cursoConNivel(int $idCurso): array
    {
        if ($idCurso <= 0) {
            return ['cursec' => '', 'nivel' => ''];
        }

        $curso = Curso::query()->find($idCurso, ['cursec', 'idNivel']);
        if ($curso === null) {
            return ['cursec' => '', 'nivel' => ''];
        }

        $nivel = Nivel::query()->find((int) $curso->idNivel, ['nivel']);

        return [
            'cursec' => trim((string) ($curso->cursec ?? '')),
            'nivel' => trim((string) ($nivel?->nivel ?? '')),
        ];
    }

    /**
     * @return array{cursec: string, nivel: string}
     */
    private static function cursoAnterior(int $idLegajo, int $idTerlecActual): array
    {
        if ($idLegajo <= 0 || $idTerlecActual <= 0) {
            return ['cursec' => '', 'nivel' => ''];
        }

        $terlecActual = Terlec::query()->find($idTerlecActual, ['ano']);
        $anoAnterior = (int) ($terlecActual?->ano ?? 0) - 1;
        if ($anoAnterior <= 0) {
            return ['cursec' => '', 'nivel' => ''];
        }

        $terlecAnterior = Terlec::query()
            ->where('ano', $anoAnterior)
            ->orderByDesc('orden')
            ->orderByDesc('id')
            ->first(['id']);

        if ($terlecAnterior === null) {
            return ['cursec' => '', 'nivel' => ''];
        }

        $matriculaAnterior = Matricula::query()
            ->where('idLegajos', $idLegajo)
            ->where('idTerlec', (int) $terlecAnterior->id)
            ->whereNull('fechaBaja')
            ->first(['idCursos']);

        if ($matriculaAnterior === null) {
            return ['cursec' => '', 'nivel' => ''];
        }

        return self::cursoConNivel((int) $matriculaAnterior->idCursos);
    }

    private static function esMatriculaCondicional(object $row): bool
    {
        if (property_exists($row, 'matric_condic')) {
            return (int) ($row->matric_condic ?? 0) === 1;
        }

        return (int) ($row->id_condiciones ?? 0) !== 1;
    }

    private static function calcularEdad(mixed $fechnaci, mixed $fechaReferencia): string
    {
        if ($fechnaci === null || $fechnaci === '') {
            return '';
        }

        try {
            $nacimiento = $fechnaci instanceof CarbonInterface
                ? $fechnaci
                : Carbon::parse((string) $fechnaci);

            $referencia = $fechaReferencia instanceof CarbonInterface
                ? $fechaReferencia
                : ($fechaReferencia !== null && $fechaReferencia !== ''
                    ? Carbon::parse((string) $fechaReferencia)
                    : Carbon::today());

            return (string) max(0, $nacimiento->diffInYears($referencia));
        } catch (\Throwable) {
            return '';
        }
    }

    private static function fecha(mixed $valor): string
    {
        if ($valor instanceof CarbonInterface) {
            return $valor->format('d/m/Y');
        }

        if ($valor === null || $valor === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $valor)->format('d/m/Y');
        } catch (\Throwable) {
            return trim((string) $valor);
        }
    }
}
