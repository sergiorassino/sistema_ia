<?php

namespace App\Support\Alumnos;

use App\Models\Curso;
use App\Models\Ento;
use App\Models\Nivel;
use App\Models\Terlec;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de la ficha de matrícula pedagógica Caixal (legacy FPDF A4).
 */
final class FichaMatriculaCaixalsfDatos
{
    /** @var list<string> */
    private const COLUMNAS_LEGAJO = [
        'apellido', 'nombre', 'dni', 'legajo', 'nacion', 'fechnaci',
        'ln_ciudad', 'ln_depto', 'ln_provincia', 'ln_pais',
        'callenum', 'barrio', 'localidad', 'telefono', 'email',
        'nombremad', 'dnimad', 'domimad', 'telemad', 'ocupacmad', 'lugtramad', 'telltm', 'emailmad',
        'nombrepad', 'dnipad', 'domipad', 'telepad', 'ocupacpad', 'lugtrapad', 'telltp', 'emailpad',
        'nombretut', 'ocupactut', 'domitut',
        'fechActDatos',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public static function paraMatricula(int $idMatricula, ?int $idTerlec = null): ?array
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $idTerlec = $idTerlec ?? (int) $ctx->idTerlec;
        $ano = $idTerlec === (int) $ctx->idTerlec
            ? (int) ($ctx->terlecAno() ?? 0)
            : (int) (Terlec::query()->whereKey($idTerlec)->value('ano') ?? 0);

        return self::desdeMatriculaEnContexto(
            $idMatricula,
            (int) $ctx->idNivel,
            $idTerlec,
            schoolPdfHeaderData(),
            $ano,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function paraAutogestion(): ?array
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $matricula = MatriculaFamiliaCiclo::matricula();
        if ($matricula === null) {
            return null;
        }

        $idNivel = (int) ($matricula->idNivel ?? 0);
        if ($idNivel < 1) {
            $idNivel = (int) $ctx->idNivel;
        }

        return self::desdeMatriculaEnContexto(
            (int) $matricula->id,
            $idNivel,
            (int) $matricula->idTerlec,
            studentPdfHeaderData($idNivel),
            MatriculaFamiliaCiclo::ano($matricula),
        );
    }

    /**
     * @param  array{insti: string, direccion: string, localidad: string, provincia?: string, cue: string, ee: string, logo_file: ?string}  $header
     * @return array<string, mixed>|null
     */
    private static function desdeMatriculaEnContexto(
        int $idMatricula,
        int $idNivel,
        int $idTerlec,
        array $header,
        int $anoTerlec,
    ): ?array {
        $select = self::columnasSelect();
        $select[] = 'matricula.idCursos as id_curso';

        $row = DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->where('matricula.id', $idMatricula)
            ->where('matricula.idNivel', $idNivel)
            ->where('matricula.idTerlec', $idTerlec)
            ->whereNull('matricula.fechaBaja')
            ->select($select)
            ->first();

        if ($row === null) {
            return null;
        }

        $header['telefono'] = self::telefonoInstitucion($idNivel);
        $curso = self::cursoConNivel((int) $row->id_curso);

        return [
            'header' => $header,
            'cicloLectivo' => $anoTerlec > 0 ? (string) $anoTerlec : '',
            'curso' => $curso['cursec'],
            'cursoC' => $curso['c'],
            'cursoS' => $curso['s'],
            'nivelAbrev' => $curso['nivelAbrev'],
            'apellido' => self::columna($row, 'apellido'),
            'nombre' => self::columna($row, 'nombre'),
            'dni' => self::columna($row, 'dni'),
            'legajo' => self::columna($row, 'legajo'),
            'nacion' => self::columna($row, 'nacion'),
            'fechnaci' => self::fecha(self::valor($row, 'fechnaci')),
            'ln_ciudad' => self::columna($row, 'ln_ciudad'),
            'ln_depto' => self::columna($row, 'ln_depto'),
            'ln_provincia' => self::columna($row, 'ln_provincia'),
            'ln_pais' => self::columna($row, 'ln_pais'),
            'callenum' => self::columna($row, 'callenum'),
            'barrio' => self::columna($row, 'barrio'),
            'localidad' => self::columna($row, 'localidad'),
            'telefono' => self::columna($row, 'telefono'),
            'email' => self::columna($row, 'email'),
            'nombremad' => self::columna($row, 'nombremad'),
            'dnimad' => self::columna($row, 'dnimad'),
            'domimad' => self::columna($row, 'domimad'),
            'telemad' => self::columna($row, 'telemad'),
            'ocupacmad' => self::columna($row, 'ocupacmad'),
            'lugtramad' => self::columna($row, 'lugtramad'),
            'telltm' => self::columna($row, 'telltm'),
            'emailmad' => self::columna($row, 'emailmad'),
            'nombrepad' => self::columna($row, 'nombrepad'),
            'dnipad' => self::columna($row, 'dnipad'),
            'domipad' => self::columna($row, 'domipad'),
            'telepad' => self::columna($row, 'telepad'),
            'ocupacpad' => self::columna($row, 'ocupacpad'),
            'lugtrapad' => self::columna($row, 'lugtrapad'),
            'telltp' => self::columna($row, 'telltp'),
            'emailpad' => self::columna($row, 'emailpad'),
            'nombretut' => self::columna($row, 'nombretut'),
            'ocupactut' => self::columna($row, 'ocupactut'),
            'domitut' => self::columna($row, 'domitut'),
            'fechActDatos' => self::fecha(self::valor($row, 'fechActDatos')),
        ];
    }

    /**
     * @return list<string>
     */
    private static function columnasSelect(): array
    {
        $columnas = [];
        foreach (self::COLUMNAS_LEGAJO as $columna) {
            if (Schema::hasColumn('legajos', $columna)) {
                $columnas[] = 'legajos.'.$columna.' as '.$columna;
            }
        }

        if ($columnas === []) {
            return ['legajos.id as id'];
        }

        return $columnas;
    }

    /**
     * @return array{c: string, s: string, cursec: string, nivelAbrev: string}
     */
    private static function cursoConNivel(int $idCurso): array
    {
        $vacio = ['c' => '', 's' => '', 'cursec' => '', 'nivelAbrev' => ''];
        if ($idCurso <= 0) {
            return $vacio;
        }

        $curso = Curso::query()->find($idCurso, ['cursec', 'c', 's', 'idNivel']);
        if ($curso === null) {
            return $vacio;
        }

        $nivel = Nivel::query()->find((int) $curso->idNivel, ['abrev']);

        return [
            'c' => trim((string) ($curso->c ?? '')),
            's' => trim((string) ($curso->s ?? '')),
            'cursec' => trim((string) ($curso->cursec ?? '')),
            'nivelAbrev' => trim((string) ($nivel?->abrev ?? '')),
        ];
    }

    private static function telefonoInstitucion(int $idNivel): string
    {
        if ($idNivel <= 0 || ! Schema::hasTable('ento') || ! Schema::hasColumn('ento', 'telefono')) {
            return '';
        }

        $valor = Ento::query()->where('idNivel', $idNivel)->value('telefono');

        return trim((string) ($valor ?? ''));
    }

    private static function columna(object $row, string $columna): string
    {
        return self::texto(self::valor($row, $columna));
    }

    private static function valor(object $row, string $columna): mixed
    {
        return property_exists($row, $columna) ? $row->{$columna} : null;
    }

    private static function texto(mixed $valor): string
    {
        return trim((string) ($valor ?? ''));
    }

    private static function fecha(mixed $valor): string
    {
        if ($valor instanceof CarbonInterface) {
            return $valor->year > 0 ? $valor->format('d/m/Y') : '';
        }

        if ($valor === null || $valor === '') {
            return '';
        }

        $texto = trim((string) $valor);
        if ($texto === '' || str_starts_with($texto, '0000-00-00')) {
            return '';
        }

        try {
            return Carbon::parse($texto)->format('d/m/Y');
        } catch (\Throwable) {
            return $texto;
        }
    }
}
