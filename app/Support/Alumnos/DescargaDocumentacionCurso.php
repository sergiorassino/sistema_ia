<?php

namespace App\Support\Alumnos;

use App\Models\Curso;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\Listados\ListadoCursoConsulta;
use App\Support\OrdenAlfabeticoEstudiante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * ZIP de la documentación cargada (tipos activos de doc-estudiante) de un curso.
 *
 * Estructura: {curso}/{apellido} {nombre} {dni}/{etiqueta del tipo}.ext
 * Solo entran estudiantes regulares del curso, del ciclo y del alcance de schoolCtx().
 */
final class DescargaDocumentacionCurso
{
    private const EXTENSIONES = ['jpg', 'pdf', 'png'];

    /**
     * @return array{
     *     nombre_curso: string,
     *     alumnos: list<array{carpeta: string, archivos: int}>,
     *     sin_archivos: int,
     *     total_archivos: int
     * }|null
     */
    public static function resumen(int $idCurso): ?array
    {
        $armado = self::recolectar($idCurso);
        if ($armado === null) {
            return null;
        }

        return [
            'nombre_curso' => $armado['nombre_curso'],
            'alumnos' => $armado['alumnos'],
            'sin_archivos' => $armado['sin_archivos'],
            'total_archivos' => $armado['total_archivos'],
        ];
    }

    /**
     * @return array{ruta: string, nombre: string, archivos: int}|null
     */
    public static function escribirZip(int $idCurso): ?array
    {
        $armado = self::recolectar($idCurso);
        if ($armado === null || $armado['entradas'] === []) {
            return null;
        }

        $tmp = self::empaquetar($armado['entradas']);
        if ($tmp === null) {
            return null;
        }

        Log::info('descarga-documentacion-curso', [
            'id_curso' => $idCurso,
            'archivos' => count($armado['entradas']),
            'user' => auth()->id(),
        ]);

        return [
            'ruta' => $tmp,
            'nombre' => self::nombreArchivoZip($armado['nombre_curso']),
            'archivos' => count($armado['entradas']),
        ];
    }

    public static function nombreCarpetaCurso(string $nombre): string
    {
        return self::segmento($nombre, 'Curso');
    }

    public static function nombreCarpetaAlumno(string $apellido, string $nombre, string $dni): string
    {
        $partes = array_values(array_filter([
            self::segmento($apellido, ''),
            self::segmento($nombre, ''),
            self::dniParaCarpeta($dni),
        ], static fn (string $parte): bool => $parte !== ''));

        return self::segmento(implode(' ', $partes), 'sin nombre');
    }

    public static function nombreArchivoZip(string $carpetaCurso): string
    {
        $ascii = Str::ascii($carpetaCurso);
        $ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '', $ascii) ?? '';
        $ascii = trim(preg_replace('/\s+/', ' ', $ascii) ?? '');
        $ascii = str_replace(' ', '-', $ascii);
        if ($ascii === '' || $ascii === '.' || $ascii === '..') {
            $ascii = 'documentacion';
        }

        return $ascii.'.zip';
    }

    public static function rutaDentroDelZip(string $curso, string $alumno, string $archivo): string
    {
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (! in_array($extension, self::EXTENSIONES, true)) {
            return '';
        }

        $base = self::segmento((string) pathinfo($archivo, PATHINFO_FILENAME), 'documento');
        $cursoSeg = self::nombreCarpetaCurso($curso);
        $alumnoSeg = self::segmento($alumno, 'sin nombre');
        $ruta = $cursoSeg.'/'.$alumnoSeg.'/'.$base.'.'.$extension;

        if (str_contains($ruta, '..') || str_starts_with($ruta, '/')) {
            return '';
        }

        return $ruta;
    }

    /**
     * @param  list<array{etiqueta: string, extension: string}>  $piezas
     * @return list<string>
     */
    public static function nombresDeArchivos(array $piezas): array
    {
        /** @var array<string, true> $usados */
        $usados = [];
        $nombres = [];

        foreach ($piezas as $pieza) {
            $extension = strtolower((string) ($pieza['extension'] ?? ''));
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }
            if (! in_array($extension, self::EXTENSIONES, true)) {
                $nombres[] = '';

                continue;
            }

            $base = self::segmento((string) ($pieza['etiqueta'] ?? ''), 'documento');
            $candidato = $base.'.'.$extension;
            $n = 2;
            while (isset($usados[mb_strtolower($candidato)]) && $n < 500) {
                $candidato = $base.' '.$n.'.'.$extension;
                $n++;
            }
            $usados[mb_strtolower($candidato)] = true;
            $nombres[] = $candidato;
        }

        return $nombres;
    }

    /**
     * @param  list<array{zip: string, absoluto: string}>  $entradas
     */
    public static function empaquetar(array $entradas): ?string
    {
        if (! class_exists(ZipArchive::class) || $entradas === []) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'doc_curso_');
        if ($temp === false) {
            return null;
        }

        $flagsApertura = ZipArchive::CREATE | ZipArchive::OVERWRITE;
        $zip = new ZipArchive;
        if ($zip->open($temp, $flagsApertura) !== true) {
            @unlink($temp);

            return null;
        }

        $flagsArchivo = ZipArchive::FL_OVERWRITE;
        if (defined(ZipArchive::class.'::FL_ENC_UTF_8')) {
            $flagsArchivo |= ZipArchive::FL_ENC_UTF_8;
        }

        $agregados = 0;
        foreach ($entradas as $entrada) {
            $zipPath = str_replace('\\', '/', (string) ($entrada['zip'] ?? ''));
            $absoluto = (string) ($entrada['absoluto'] ?? '');
            if ($zipPath === '' || str_contains($zipPath, '..') || ! is_file($absoluto)) {
                continue;
            }
            if ($zip->addFile($absoluto, $zipPath, 0, 0, $flagsArchivo)) {
                $agregados++;
            }
        }

        $zip->close();

        if ($agregados < 1 || ! is_file($temp)) {
            @unlink($temp);

            return null;
        }

        return $temp;
    }

    /**
     * @return array{
     *     nombre_curso: string,
     *     alumnos: list<array{carpeta: string, archivos: int}>,
     *     sin_archivos: int,
     *     total_archivos: int,
     *     entradas: list<array{zip: string, absoluto: string}>
     * }|null
     */
    private static function recolectar(int $idCurso): ?array
    {
        if ($idCurso < 1) {
            return null;
        }

        $curso = ListadoCursoConsulta::cursosPermitidosEnContexto()
            ->first(static fn (Curso $fila): bool => (int) $fila->Id === $idCurso);
        if (! $curso instanceof Curso) {
            return null;
        }

        $nombreCurso = self::nombreCarpetaCurso($curso->nombreParaListado());
        $tipos = DocumentosEstudianteAutogestion::tiposConfigurados();
        $alumnos = self::alumnosRegulares($curso);
        $raiz = self::raizAlmacenamiento();

        /** @var array<string, true> $carpetasUsadas */
        $carpetasUsadas = [];
        $filas = [];
        $entradas = [];
        $sinArchivos = 0;

        foreach ($alumnos as $alumno) {
            $piezas = self::piezasDelAlumno((string) ($alumno->dni ?? ''), $tipos, $raiz);
            if ($piezas === []) {
                $sinArchivos++;

                continue;
            }

            $carpeta = self::nombreUnico(
                self::nombreCarpetaAlumno(
                    (string) ($alumno->apellido ?? ''),
                    (string) ($alumno->nombre ?? ''),
                    (string) ($alumno->dni ?? ''),
                ),
                $carpetasUsadas,
            );

            $nombres = self::nombresDeArchivos($piezas);
            $cantidad = 0;
            foreach ($piezas as $i => $pieza) {
                $nombre = $nombres[$i] ?? '';
                if ($nombre === '') {
                    continue;
                }
                $zipPath = self::rutaDentroDelZip($nombreCurso, $carpeta, $nombre);
                if ($zipPath === '') {
                    continue;
                }
                $entradas[] = [
                    'zip' => $zipPath,
                    'absoluto' => $pieza['absoluto'],
                ];
                $cantidad++;
            }

            if ($cantidad < 1) {
                $sinArchivos++;

                continue;
            }

            $filas[] = [
                'carpeta' => $carpeta,
                'archivos' => $cantidad,
            ];
        }

        return [
            'nombre_curso' => $nombreCurso,
            'alumnos' => $filas,
            'sin_archivos' => $sinArchivos,
            'total_archivos' => count($entradas),
            'entradas' => $entradas,
        ];
    }

    /**
     * @param  list<array{clave: string, label: string}>  $tipos
     * @return list<array{etiqueta: string, extension: string, absoluto: string}>
     */
    private static function piezasDelAlumno(string $dni, array $tipos, ?string $raiz): array
    {
        if ($tipos === [] || $raiz === null || DocumentosEstudianteAutogestion::dniSanitizado($dni) === 'sin-dni') {
            return [];
        }

        $disk = Storage::disk(DocumentosEstudianteAutogestion::DISK);
        $piezas = [];

        foreach ($tipos as $tipo) {
            $clave = (string) ($tipo['clave'] ?? '');
            $etiqueta = (string) ($tipo['label'] ?? '');
            if ($clave === '') {
                continue;
            }
            foreach (DocumentosEstudianteAutogestion::pathsAlmacenados($dni, $clave) as $relativo) {
                $absoluto = $disk->path($relativo);
                $real = realpath($absoluto);
                if ($real === false || ! is_file($real) || ! self::dentroDeRaiz($real, $raiz)) {
                    continue;
                }
                $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));
                $piezas[] = [
                    'etiqueta' => $etiqueta !== '' ? $etiqueta : $clave,
                    'extension' => $extension,
                    'absoluto' => $real,
                ];
            }
        }

        return $piezas;
    }

    /**
     * @return list<object{apellido: string, nombre: string, dni: string}>
     */
    private static function alumnosRegulares(Curso $curso): array
    {
        $ctx = schoolCtx();
        $condiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(ListadoCursoCondicionFiltro::REGULARES);

        $query = DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->where('matricula.idCursos', (int) $curso->Id)
            ->where('matricula.idTerlec', (int) $ctx->idTerlec)
            ->where('matricula.idNivel', (int) $curso->idNivel)
            ->whereIn('matricula.idCondiciones', $condiciones)
            ->whereNull('matricula.fechaBaja');

        ListadoCursoConsulta::aplicarFiltroMatriculaNivel($query, 'matricula.idNivel');

        $filas = $query
            ->orderByRaw(OrdenAlfabeticoEstudiante::sql('legajos.apellido'))
            ->orderByRaw(OrdenAlfabeticoEstudiante::sql('legajos.nombre'))
            ->orderBy('legajos.id')
            ->get([
                'legajos.id as id_legajo',
                'legajos.apellido',
                'legajos.nombre',
                'legajos.dni',
            ]);

        $vistos = [];
        $unicos = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila->id_legajo ?? 0);
            if ($id < 1 || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $unicos[] = $fila;
        }

        return $unicos;
    }

    private static function raizAlmacenamiento(): ?string
    {
        $disk = Storage::disk(DocumentosEstudianteAutogestion::DISK);
        $dir = $disk->path(DocumentosEstudianteAutogestion::storageDir());
        if (! is_dir($dir)) {
            return null;
        }

        $real = realpath($dir);

        return $real === false ? null : $real;
    }

    private static function dentroDeRaiz(string $archivo, string $raiz): bool
    {
        $archivoNorm = str_replace('\\', '/', strtolower($archivo));
        $raizNorm = rtrim(str_replace('\\', '/', strtolower($raiz)), '/');

        return str_starts_with($archivoNorm, $raizNorm.'/');
    }

    public static function dniParaCarpeta(string $dni): string
    {
        $digitos = preg_replace('/\D/u', '', trim($dni)) ?? '';

        return $digitos;
    }

    /**
     * @param  array<string, true>  $usados
     */
    private static function nombreUnico(string $base, array &$usados): string
    {
        $candidato = $base;
        $n = 2;
        while (isset($usados[mb_strtolower($candidato)]) && $n < 500) {
            $candidato = self::segmento($base.' '.$n, $base);
            $n++;
        }
        $usados[mb_strtolower($candidato)] = true;

        return $candidato;
    }

    private static function segmento(string $texto, string $fallback): string
    {
        $texto = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $texto);
        $texto = preg_replace('/\p{C}+/u', '', $texto) ?? '';
        $texto = preg_replace('/\s+/u', ' ', trim($texto)) ?? '';
        $texto = rtrim($texto, ". \t");
        if (mb_strlen($texto) > 120) {
            $texto = rtrim(mb_substr($texto, 0, 120), ". \t");
        }
        if ($texto === '' || $texto === '.' || $texto === '..') {
            return $fallback;
        }
        if (preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$/i', $texto) === 1) {
            $texto .= '_';
        }

        return $texto;
    }
}
