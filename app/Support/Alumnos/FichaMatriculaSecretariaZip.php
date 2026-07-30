<?php

namespace App\Support\Alumnos;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Genera un ZIP con una ficha de matrícula en PDF por alumno (secretaría).
 */
final class FichaMatriculaSecretariaZip
{
    /**
     * @param  list<int>  $matriculaIds
     */
    public static function respuestaHttp(array $matriculaIds): Response
    {
        abort_unless(tenantSecretariaFichaMatriculaHabilitada(), 404);

        if (! class_exists(ZipArchive::class)) {
            abort(500, 'La extensión ZIP no está disponible en el servidor.');
        }

        $ids = FichaMatriculaSecretariaLoteParams::resolverIdsMatriculas($matriculaIds);
        if ($ids === []) {
            abort(404);
        }

        $implementacion = tenantSecretariaFichaMatriculaImplementacion();
        $header = schoolPdfHeaderData();
        $hojas = [];

        foreach ($ids as $idMatricula) {
            $datos = match ($implementacion) {
                'sanfranciscoasis', 'iess' => FichaMatriculaDatos::paraMatricula($idMatricula),
                'montecristo', 'sanjose' => FichaMatriculaMontecristoDatos::paraMatricula($idMatricula),
                default => null,
            };

            if ($datos !== null) {
                $hojas[] = $datos;
            }
        }

        if ($hojas === []) {
            abort(404);
        }

        $tempZip = tempnam(sys_get_temp_dir(), 'ficha_matr_zip_');
        if ($tempZip === false) {
            abort(500, 'No se pudo preparar el archivo ZIP.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tempZip);
            abort(500, 'No se pudo crear el archivo ZIP.');
        }

        /** @var array<string, int> $nombresUsados */
        $nombresUsados = [];
        $archivosAgregados = 0;

        foreach ($hojas as $datos) {
            $nombreArchivo = self::nombreArchivoIndividual($datos, $nombresUsados);

            $pdf = match ($implementacion) {
                'sanfranciscoasis' => FichaMatriculaConAceptacionTcpdf::generar(
                    $datos,
                    $datos['header'] ?? $header,
                ),
                'iess' => FichaMatriculaIessTcpdf::generar(
                    $datos,
                    $datos['header'] ?? $header,
                ),
                'montecristo' => FichaMatriculaSolicitudMontecristoTcpdf::generar($datos),
                'sanjose' => FichaMatriculaSanJoseTcpdf::generar($datos),
                default => null,
            };

            if ($pdf === null) {
                continue;
            }

            $zip->addFromString($nombreArchivo, $pdf->Output($nombreArchivo, 'S'));
            $archivosAgregados++;
        }

        if ($archivosAgregados === 0) {
            $zip->close();
            @unlink($tempZip);
            abort(404);
        }

        $zip->close();

        $nombreZip = self::nombreArchivoZip($hojas);

        $binario = file_get_contents($tempZip);
        @unlink($tempZip);

        if ($binario === false) {
            abort(500, 'No se pudo leer el archivo ZIP generado.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response($binario, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$nombreZip.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, int>  $nombresUsados
     */
    public static function nombreArchivoIndividual(array $datos, array &$nombresUsados = []): string
    {
        $curso = self::segmentoCurso($datos);
        $nivel = self::segmentoNivel($datos);
        $apellido = self::segmentoPersonaPalabras((string) ($datos['apellido'] ?? ''), 'apellido');
        $nombre = self::segmentoPersonaPalabras((string) ($datos['nombre'] ?? ''), 'nombre');

        $base = $curso.'_'.$nivel.'_'.$apellido.'_'.$nombre.'_fichaMatr.pdf';

        if (! isset($nombresUsados[$base])) {
            $nombresUsados[$base] = 1;

            return $base;
        }

        $nombresUsados[$base]++;
        $sufijo = $nombresUsados[$base];

        return str_replace('.pdf', '_'.$sufijo.'.pdf', $base);
    }

    /**
     * @param  list<array<string, mixed>>  $hojas
     */
    private static function nombreArchivoZip(array $hojas): string
    {
        $cursos = collect($hojas)
            ->map(fn (array $datos) => self::segmentoCurso($datos))
            ->unique()
            ->sort()
            ->values();

        $niveles = collect($hojas)
            ->map(fn (array $datos) => self::segmentoNivel($datos))
            ->unique()
            ->sort()
            ->values();

        if ($cursos->isEmpty()) {
            return 'fichasMatr.zip';
        }

        $prefijo = $cursos->implode('_');

        if ($niveles->count() === 1) {
            $prefijo .= '_'.$niveles->first();
        } elseif ($niveles->isNotEmpty()) {
            $prefijo .= '_'.$niveles->implode('_');
        }

        return $prefijo.'_fichasMatr.zip';
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function segmentoCurso(array $datos): string
    {
        $c = trim((string) ($datos['cursoC'] ?? ''));
        $s = trim((string) ($datos['cursoS'] ?? ''));
        $codigo = self::segmentoAlfanumerico($c.$s);

        if ($codigo !== '') {
            return strtolower($codigo);
        }

        return 'curso';
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function segmentoNivel(array $datos): string
    {
        $abrev = self::segmentoAlfanumerico((string) ($datos['nivelAbrev'] ?? ''));

        return $abrev !== '' ? strtolower($abrev) : 'nivel';
    }

    private static function segmentoPersonaPalabras(string $texto, string $fallback): string
    {
        $ascii = Str::ascii(trim($texto));
        $palabras = preg_split('/\s+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $segmentos = [];

        foreach ($palabras as $palabra) {
            $limpio = self::segmentoAlfanumerico($palabra);
            if ($limpio !== '') {
                $segmentos[] = strtolower($limpio);
            }
        }

        if ($segmentos === []) {
            return $fallback;
        }

        return implode('_', $segmentos);
    }

    private static function segmentoAlfanumerico(string $texto): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', Str::ascii(trim($texto))) ?? '';
    }
}
