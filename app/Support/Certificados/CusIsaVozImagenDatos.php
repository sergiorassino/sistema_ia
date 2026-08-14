<?php

namespace App\Support\Certificados;

use App\Models\Curso;
use App\Models\Ento;
use App\Models\Matricula;
use App\Models\Terlec;
use App\Support\InformeInasistencias;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Datos para C.U.S., I.S.A. y autorización de uso de imagen y voz.
 */
final class CusIsaVozImagenDatos
{
    public const TIPO_CUS = 'cus';

    public const TIPO_ISA = 'isa';

    public const TIPO_VOZ_IMAGEN = 'voz-imagen';

    /**
     * Matrículas regulares (idCondiciones = 1) del curso, nivel y ciclo del contexto.
     *
     * @return Collection<int, Matricula>
     */
    public static function matriculasRegularesDelCurso(int $cursoId): Collection
    {
        if ($cursoId <= 0) {
            return collect();
        }

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        if ($idNivel < 1 || $idTerlec < 1) {
            return collect();
        }

        $cursoOk = Curso::query()
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('Id', $cursoId)
            ->exists();

        if (! $cursoOk) {
            return collect();
        }

        return self::queryRegularesCurso($cursoId, $idNivel, $idTerlec)
            ->with('legajo')
            ->get()
            ->sortBy(function (Matricula $m) {
                $a = mb_strtolower((string) ($m->legajo?->apellido ?? ''));
                $n = mb_strtolower((string) ($m->legajo?->nombre ?? ''));

                return [$a, $n];
            })
            ->values();
    }

    /**
     * @param  list<int>  $idsMatricula
     * @return list<array<string, mixed>>
     */
    public static function alumnosParaPdf(array $idsMatricula, int $cursoId): array
    {
        if ($cursoId <= 0 || $idsMatricula === []) {
            return [];
        }

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        if ($idNivel < 1 || $idTerlec < 1) {
            return [];
        }

        $cursoOk = Curso::query()
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('Id', $cursoId)
            ->exists();

        if (! $cursoOk) {
            return [];
        }

        $ids = collect($idsMatricula)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        /** @var Collection<int, Matricula> $matriculas */
        $matriculas = self::queryRegularesCurso($cursoId, $idNivel, $idTerlec)
            ->with(['legajo', 'curso'])
            ->whereIn('id', $ids->all())
            ->get();

        if ($matriculas->isEmpty()) {
            return [];
        }

        $porId = $matriculas->keyBy(fn (Matricula $m) => (int) $m->id);
        $filas = [];

        foreach ($ids as $idMatricula) {
            $matricula = $porId->get((int) $idMatricula);
            if ($matricula === null) {
                continue;
            }

            $fila = self::filaDesdeMatricula($matricula);
            if ($fila === null) {
                continue;
            }

            $filas[] = $fila;
        }

        usort($filas, function (array $a, array $b): int {
            $apA = mb_strtolower((string) ($a['apellido'] ?? ''));
            $apB = mb_strtolower((string) ($b['apellido'] ?? ''));
            if ($apA !== $apB) {
                return $apA <=> $apB;
            }

            return mb_strtolower((string) ($a['nombre'] ?? '')) <=> mb_strtolower((string) ($b['nombre'] ?? ''));
        });

        return $filas;
    }

    /**
     * Fila de PDF para el alumno en sesión (ciclo de autogestión).
     *
     * @return array<string, mixed>|null
     */
    public static function alumnoParaAutogestion(): ?array
    {
        $ctx = studentCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        $matricula = InformeInasistencias::matriculaAutogestion();
        if ($matricula === null) {
            return null;
        }

        $matricula->loadMissing(['legajo', 'curso']);

        return self::filaDesdeMatricula($matricula);
    }

    /**
     * @return array{insti: string, ano_lectivo: int}
     */
    public static function contextoInstitucional(): array
    {
        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;

        $ento = $idNivel > 0
            ? Ento::query()->where('idNivel', $idNivel)->first(['insti'])
            : null;

        $header = schoolPdfHeaderData();
        $insti = trim((string) ($ento?->insti ?? $header['insti'] ?? ''));

        $ano = 0;
        $idTerlec = (int) $ctx->idTerlec;
        if ($idTerlec > 0) {
            $ano = (int) (Terlec::query()->where('id', $idTerlec)->value('ano') ?? 0);
        }

        return [
            'insti' => $insti,
            'ano_lectivo' => $ano,
        ];
    }

    public static function rutaPlantilla(string $archivo): ?string
    {
        foreach ([
            public_path('img/certificados/'.$archivo),
            public_path('img/'.$archivo),
        ] as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    public static function tipoValido(?string $tipo): ?string
    {
        return match ($tipo) {
            self::TIPO_CUS, self::TIPO_ISA, self::TIPO_VOZ_IMAGEN => $tipo,
            default => null,
        };
    }

    public static function etiquetaSexo(int $sexo): string
    {
        return match ($sexo) {
            1 => 'Femenino',
            2 => 'Masculino',
            default => '.......',
        };
    }

    private static function formatearFecha(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('d/m/Y');
        }

        $texto = trim((string) $fecha);
        if ($texto === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $texto)) {
            try {
                return (new \DateTimeImmutable(substr($texto, 0, 10)))->format('d/m/Y');
            } catch (\Throwable) {
                return $texto;
            }
        }

        return $texto;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function filaDesdeMatricula(Matricula $matricula): ?array
    {
        $legajo = $matricula->legajo;
        if ($legajo === null) {
            return null;
        }

        return [
            'matricula_id' => (int) $matricula->id,
            'apellido' => trim((string) ($legajo->apellido ?? '')),
            'nombre' => trim((string) ($legajo->nombre ?? '')),
            'dni' => trim((string) ($legajo->dni ?? '')),
            'cursec' => trim((string) ($matricula->curso?->cursec ?? '')),
            'fechnaci' => self::formatearFecha($legajo->fechnaci),
            'ln_ciudad' => trim((string) ($legajo->ln_ciudad ?? '')),
            'ln_provincia' => trim((string) ($legajo->ln_provincia ?? '')),
            'callenum' => trim((string) ($legajo->callenum ?? '')),
            'barrio' => trim((string) ($legajo->barrio ?? '')),
            'localidad' => trim((string) ($legajo->localidad ?? '')),
            'telemad' => trim((string) ($legajo->telemad ?? '')),
            'telepad' => trim((string) ($legajo->telepad ?? '')),
            'sexo' => (int) ($legajo->sexo ?? 0),
            'sexo_etiqueta' => self::etiquetaSexo((int) ($legajo->sexo ?? 0)),
            'nombrepad' => trim((string) ($legajo->nombrepad ?? '')),
            'dnipad' => trim((string) ($legajo->dnipad ?? '')),
            'nombremad' => trim((string) ($legajo->nombremad ?? '')),
            'dnimad' => trim((string) ($legajo->dnimad ?? '')),
        ];
    }

    /**
     * @return Builder<Matricula>
     */
    private static function queryRegularesCurso(int $cursoId, int $idNivel, int $idTerlec): Builder
    {
        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );

        return Matricula::query()
            ->where('idCursos', $cursoId)
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->whereIn('idCondiciones', $idsCondiciones)
            ->whereNull('fechaBaja');
    }
}
