<?php

namespace App\Support\Certificados;

use App\Models\Ento;
use App\Models\Matricula;
use App\Support\MatrizAnaliticos\AnaliticoFrenteDatos;
use App\Support\CalificacionesPrimario\PlanillaCalificacionesPrimarioDatos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Datos por alumno e institución para el PDF de finalización (jardín / sexto).
 */
final class CertificadoFinalizacionNivelDatos
{
    /**
     * @param  list<int>  $idsMatricula
     * @param  array{
     *     serie: string,
     *     mesApro: string,
     *     anoApro: string,
     *     diaEmision: string,
     *     mesEmision: string,
     *     anoEmision: string,
     *     ppi: string
     * }  $form
     * @return list<array<string, mixed>>
     */
    public static function alumnosParaPdf(string $tipo, int $cursoId, array $idsMatricula, array $form): array
    {
        $ids = CertificadoFinalizacionNivel::resolverIdsMatriculas($tipo, $cursoId, $idsMatricula);
        if ($ids === []) {
            return [];
        }

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;

        /** @var Collection<int, Matricula> $matriculas */
        $matriculas = Matricula::query()
            ->with('legajo')
            ->whereIn('id', $ids)
            ->where('idCursos', $cursoId)
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->get()
            ->keyBy(fn (Matricula $m) => (int) $m->id);

        $institucion = self::institucion();
        $filas = [];

        foreach ($ids as $idMat) {
            $matricula = $matriculas->get($idMat);
            if ($matricula === null || $matricula->legajo === null) {
                continue;
            }

            $fila = self::filaAlumno($matricula);
            if ($fila === null) {
                continue;
            }

            $filas[] = [
                'alumno' => $fila,
                'institucion' => $institucion,
                'certificado' => $form,
            ];
        }

        return $filas;
    }

    /**
     * @return array{
     *     insti: string,
     *     cue: string,
     *     direccion: string,
     *     localidad: string,
     *     departamento: string,
     *     provincia: string,
     *     escudo_nac: ?string,
     *     leyenda_nacion: ?string,
     *     escudo_prov: ?string
     * }
     */
    public static function institucion(): array
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        $header = schoolPdfHeaderData();

        $columnas = ['insti', 'cue', 'direccion', 'localidad'];
        foreach (['departamento', 'provincia'] as $col) {
            if (Schema::hasColumn('ento', $col)) {
                $columnas[] = $col;
            }
        }

        $ento = $idNivel > 0
            ? Ento::query()->where('idNivel', $idNivel)->first($columnas)
            : null;

        return [
            'insti' => trim((string) ($ento?->insti ?? $header['insti'] ?? '')),
            'cue' => trim((string) ($ento?->cue ?? $header['cue'] ?? '')),
            'direccion' => trim((string) ($ento?->direccion ?? $header['direccion'] ?? '')),
            'localidad' => trim((string) ($ento?->localidad ?? $header['localidad'] ?? '')),
            'departamento' => trim((string) ($ento?->departamento ?? '')),
            'provincia' => trim((string) ($ento?->provincia ?? $header['provincia'] ?? '')),
            'escudo_nac' => self::rutaPrimeraExistente([
                public_path('img/certificados/escudo-nacion.png'),
                public_path('img/escudo-argentino.png'),
                public_path('img/escudonac.jpg'),
                public_path('img/certificados/escudonac.jpg'),
                AnaliticoFrenteDatos::rutaEscudoArgentino(),
            ]),
            'leyenda_nacion' => self::rutaPrimeraExistente([
                public_path('img/reparg.jpg'),
                public_path('img/republica-argentina.jpg'),
                public_path('img/certificados/reparg.jpg'),
            ]),
            'escudo_prov' => self::rutaPrimeraExistente([
                public_path('img/certificados/escudo-cordoba.png'),
                PlanillaCalificacionesPrimarioDatos::rutaEscudoProvincia(),
                public_path('img/escuprov.jpg'),
                public_path('img/certificados/escuprov.jpg'),
            ]),
        ];
    }

    /**
     * @return array{
     *     legajo: string,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     dni_puntos: string,
     *     ln_ciudad: string,
     *     ln_depto: string,
     *     ln_provincia: string,
     *     ln_pais: string,
     *     dia_naci: string,
     *     mes_naci: string,
     *     ano_naci: string
     * }|null
     */
    private static function filaAlumno(Matricula $matricula): ?array
    {
        $leg = $matricula->legajo;
        if ($leg === null) {
            return null;
        }

        $dni = trim((string) ($leg->dni ?? ''));
        $fechnaci = $leg->fechnaci;
        $diaNaci = '';
        $mesNaci = '';
        $anoNaci = '';

        if ($fechnaci instanceof \DateTimeInterface) {
            $diaNaci = CertificadoFinalizacionTextoEs::enLetras((int) $fechnaci->format('j'));
            $mesNaci = CertificadoFinalizacionTextoEs::mesNombre((int) $fechnaci->format('n'));
            $anoNaci = CertificadoFinalizacionTextoEs::enLetras((int) $fechnaci->format('Y'));
        }

        return [
            'legajo' => trim((string) ($leg->legajo ?? '')),
            'apellido' => trim((string) ($leg->apellido ?? '')),
            'nombre' => trim((string) ($leg->nombre ?? '')),
            'dni' => $dni,
            'dni_puntos' => CertificadoFinalizacionTextoEs::dniConPuntos($dni),
            'ln_ciudad' => trim((string) ($leg->ln_ciudad ?? '')),
            'ln_depto' => trim((string) ($leg->ln_depto ?? '')),
            'ln_provincia' => trim((string) ($leg->ln_provincia ?? '')),
            'ln_pais' => trim((string) ($leg->ln_pais ?? '')),
            'dia_naci' => $diaNaci,
            'mes_naci' => $mesNaci,
            'ano_naci' => $anoNaci,
        ];
    }

    /**
     * @param  list<?string>  $rutas
     */
    private static function rutaPrimeraExistente(array $rutas): ?string
    {
        foreach ($rutas as $ruta) {
            if (is_string($ruta) && $ruta !== '' && is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}
