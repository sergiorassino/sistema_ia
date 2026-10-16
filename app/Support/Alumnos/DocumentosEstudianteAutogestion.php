<?php

namespace App\Support\Alumnos;

use App\Models\DocEstudianteTipo;
use App\Support\Pdf\PdfCombinadorArchivos;
use App\Support\Security\OpaqueRouteToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Documentación del estudiante subida desde autogestión (actualización de datos).
 *
 * Tipos parametrizados en tabla `doc_estudiante_tipos` (solo registros activos).
 * Almacenamiento en carpeta única del tenant (sin subcarpeta por estudiante):
 * disco `privado` → ento/doc-estudiante/{tenantSlug}/{dni}_{clave}.jpg
 * (parte 2 en adelante: {dni}_{clave}_2.jpg). Un PDF subido se guarda como PDF.
 * Los JPG ya guardados como PDF siguen leyéndose en la ruta anterior.
 */
final class DocumentosEstudianteAutogestion
{
    public const DISK = 'privado';

    /** Ruta de visualización del portal familia. */
    public const AUDIENCIA_ALUMNO = 'alumno';

    /** Ruta de visualización del ABM de legajos (Menú de Secretaría). */
    public const AUDIENCIA_LEGAJO = 'legajo';

    public const EXTENSIONES_PERMITIDAS = DocEstudianteTipo::EXTENSIONES_SOPORTADAS;

    /**
     * Calidad JPEG al guardar (0–100). Más bajo, archivo más chico.
     * 25 es el piso usable para una foto de documento (DNI, partida, carnet).
     */
    public const JPEG_CALIDAD = 25;

    /** Lado más largo del JPG guardado. No se agranda una foto que ya es más chica. */
    public const JPEG_LADO_MAX_PX = 1200;

    /**
     * @return list<array{
     *     id: int,
     *     clave: string,
     *     label: string,
     *     extensiones: list<string>,
     *     max_archivos: int,
     *     max_mb: int,
     *     obligatorio: bool,
     *     explicacion: ?string
     * }>
     */
    public static function tiposConfigurados(): array
    {
        if (! DocEstudianteTipo::tablaDisponible()) {
            return [];
        }

        return DocEstudianteTipo::query()
            ->activos()
            ->ordenados()
            ->get()
            ->map(static fn (DocEstudianteTipo $tipo) => $tipo->toDefinicionAutogestion())
            ->filter(static fn (array $def) => $def['extensiones'] !== [] && $def['label'] !== '')
            ->values()
            ->all();
    }

    public static function habilitadoConTipos(): bool
    {
        return self::tiposConfigurados() !== [];
    }

    public static function claveValida(string $clave): bool
    {
        return self::definicion($clave) !== null;
    }

    /**
     * @return array{
     *     id: int,
     *     clave: string,
     *     label: string,
     *     extensiones: list<string>,
     *     max_archivos: int,
     *     max_mb: int,
     *     obligatorio: bool,
     *     explicacion: ?string
     * }|null
     */
    public static function definicion(string $clave): ?array
    {
        if (! DocEstudianteTipo::tablaDisponible()) {
            return null;
        }

        $clave = DocEstudianteTipo::normalizarClave($clave);
        if ($clave === '') {
            return null;
        }

        $tipo = DocEstudianteTipo::query()
            ->activos()
            ->where('clave', $clave)
            ->first();

        if ($tipo === null) {
            return null;
        }

        $def = $tipo->toDefinicionAutogestion();

        return $def['extensiones'] !== [] ? $def : null;
    }

    public static function maxBytesPorArchivo(?string $clave = null): int
    {
        if ($clave !== null) {
            $def = self::definicion($clave);
            if ($def !== null) {
                return $def['max_mb'] * 1024 * 1024;
            }
        }

        return DocEstudianteTipo::MAX_MB_DEFAULT * 1024 * 1024;
    }

    public static function dniSanitizado(string $dni): string
    {
        $soloDigitos = preg_replace('/\D/', '', trim($dni)) ?? '';

        return $soloDigitos !== '' ? $soloDigitos : 'sin-dni';
    }

    public static function storageDir(): string
    {
        return 'ento/doc-estudiante/'.tenantSlug();
    }

    public static function nombreArchivoPdf(string $dni, string $clave): string
    {
        return self::nombreBase($dni, $clave).'.pdf';
    }

    public static function nombreBase(string $dni, string $clave): string
    {
        return self::dniSanitizado($dni).'_'.DocEstudianteTipo::normalizarClave($clave);
    }

    public static function nombreArchivoParte(string $dni, string $clave, int $indice, string $extension): string
    {
        $indice = max(1, $indice);
        $extension = strtolower($extension) === 'jpeg' ? 'jpg' : strtolower($extension);
        $sufijo = $indice === 1 ? '' : '_'.$indice;

        return self::nombreBase($dni, $clave).$sufijo.'.'.$extension;
    }

    public static function pathRelativo(string $dni, string $clave): string
    {
        return self::storageDir().'/'.self::nombreArchivoPdf($dni, $clave);
    }

    public static function pathRelativoLegacy(string $dni, string $clave): string
    {
        $dniSan = self::dniSanitizado($dni);

        return 'ento/doc-estudiante/'.tenantSlug().'/'.$dniSan.'/'.self::nombreArchivoPdf($dni, $clave);
    }

    /**
     * Primera parte guardada (JPG nuevo o PDF anterior).
     */
    public static function pathAlmacenadoResuelto(string $dni, string $clave): ?string
    {
        return self::pathsAlmacenados($dni, $clave)[0] ?? null;
    }

    public static function pathParte(string $dni, string $clave, int $indice): ?string
    {
        $paths = self::pathsAlmacenados($dni, $clave);
        $pos = max(1, $indice) - 1;

        return $paths[$pos] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function pathsAlmacenados(string $dni, string $clave): array
    {
        $dniSan = self::dniSanitizado($dni);
        $claveNorm = DocEstudianteTipo::normalizarClave($clave);
        if ($dniSan === 'sin-dni' || $claveNorm === '') {
            return [];
        }

        $disk = Storage::disk(self::DISK);
        $encontrados = [];
        for ($i = 1; $i <= DocEstudianteTipo::MAX_ARCHIVOS_LIMITE; $i++) {
            $path = self::pathExistenteEnIndice($disk, $dniSan, $claveNorm, $i);
            if ($path === null) {
                break;
            }
            $encontrados[] = $path;
        }

        return $encontrados;
    }

    public static function urlVerDocumento(
        string $dni,
        string $clave,
        int $idLegajo,
        int $indice = 1,
        string $audiencia = self::AUDIENCIA_ALUMNO,
    ): ?string {
        if ($idLegajo <= 0) {
            return null;
        }

        $def = self::definicion($clave);
        if ($def === null || self::pathParte($dni, $clave, $indice) === null) {
            return null;
        }

        $idTipo = (int) $def['id'];
        if ($audiencia === self::AUDIENCIA_LEGAJO) {
            $ref = OpaqueRouteToken::forLegajoDocumentoEstudiante($idTipo, $idLegajo, $indice);
            $nombreRuta = 'abm.legajos.documento-estudiante';
        } else {
            $ref = OpaqueRouteToken::forDocumentoEstudianteAutogestion($idTipo, $idLegajo, $indice);
            $nombreRuta = 'alumnos.actualizacion-datos.documento-estudiante';
        }

        $url = route($nombreRuta, ['ref' => $ref], false);
        $path = self::pathParte($dni, $clave, $indice);
        $mtime = $path !== null ? (int) Storage::disk(self::DISK)->lastModified($path) : 0;
        if ($mtime > 0) {
            $url .= '?v='.$mtime;
        }

        return $url;
    }

    /**
     * Origen para el modal: la foto va embebida (no depende de otra petición HTTP).
     * El PDF se abre por la ruta autenticada, en la misma pantalla.
     *
     * @return array{src: string, es_pdf: bool}|null
     */
    public static function srcVista(
        string $dni,
        string $clave,
        int $idLegajo,
        int $indice = 1,
        string $audiencia = self::AUDIENCIA_ALUMNO,
    ): ?array {
        $path = self::pathParte($dni, $clave, $indice);
        if ($path === null) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            $url = self::urlVerDocumento($dni, $clave, $idLegajo, $indice, $audiencia);

            return $url === null ? null : ['src' => $url, 'es_pdf' => true];
        }

        $bin = Storage::disk(self::DISK)->get($path);
        if (! is_string($bin) || $bin === '') {
            return null;
        }

        $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';

        return [
            'src' => 'data:'.$mime.';base64,'.base64_encode($bin),
            'es_pdf' => false,
        ];
    }

    /**
     * @param  array<int|string, TemporaryUploadedFile|mixed>  $slots
     * @return list<TemporaryUploadedFile>
     */
    public static function archivosDesdeSlots(array $slots): array
    {
        ksort($slots, SORT_NUMERIC);
        $validos = [];
        foreach ($slots as $archivo) {
            if ($archivo instanceof TemporaryUploadedFile) {
                $validos[] = $archivo;
            }
        }

        return $validos;
    }

    public static function validarArchivoIndividual(string $clave, TemporaryUploadedFile $archivo): ?string
    {
        $def = self::definicion($clave);
        if ($def === null) {
            return 'Tipo de documento no válido.';
        }

        $maxBytes = self::maxBytesPorArchivo($clave);
        $extensiones = array_map('strtolower', $def['extensiones']);
        $ext = self::extensionDeUpload($archivo);

        if (! self::extensionPermitida($ext, $extensiones)) {
            return 'Formato no permitido. Extensiones aceptadas: '.implode(', ', $extensiones).'.';
        }

        $bytes = (int) ($archivo->getSize() ?? 0);
        if ($bytes < 1) {
            return 'El archivo está vacío o no terminó de subirse.';
        }
        if ($bytes > $maxBytes) {
            $mb = (int) round($maxBytes / 1024 / 1024);

            return 'El archivo no puede superar los '.$mb.' MB.';
        }

        if (! self::mimeAceptado($archivo, $ext)) {
            return 'El archivo no es un JPG o PDF válido.';
        }

        return null;
    }

    /**
     * @return array{
     *     existe: bool,
     *     path: ?string,
     *     nombre: string,
     *     actualizado_en: ?string,
     *     url_ver: ?string,
     *     archivos: list<array{indice: int, nombre: string, extension: string, url_ver: ?string}>
     * }
     */
    public static function estadoDocumento(
        string $dni,
        string $clave,
        ?int $idLegajo = null,
        string $audiencia = self::AUDIENCIA_ALUMNO,
    ): array
    {
        $vacio = [
            'existe' => false,
            'path' => null,
            'nombre' => '',
            'actualizado_en' => null,
            'url_ver' => null,
            'archivos' => [],
        ];

        $def = self::definicion($clave);
        if ($def === null) {
            return $vacio;
        }

        $paths = self::pathsAlmacenados($dni, $clave);
        $disk = Storage::disk(self::DISK);
        $archivos = [];

        foreach ($paths as $pos => $path) {
            $indice = $pos + 1;
            $archivos[] = [
                'indice' => $indice,
                'nombre' => basename($path),
                'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                'url_ver' => $idLegajo !== null && $idLegajo > 0
                    ? self::urlVerDocumento($dni, $clave, $idLegajo, $indice, $audiencia)
                    : null,
            ];
        }

        $primero = $paths[0] ?? null;
        $actualizado = null;
        if ($primero !== null) {
            $ts = $disk->lastModified($primero);
            if ($ts > 0) {
                $actualizado = date('d/m/Y H:i', $ts);
            }
        }

        return [
            'existe' => $primero !== null,
            'path' => $primero,
            'nombre' => $primero !== null ? basename($primero) : '',
            'actualizado_en' => $actualizado,
            'url_ver' => $archivos[0]['url_ver'] ?? null,
            'archivos' => $archivos,
        ];
    }

    /**
     * @return array<string, array{existe: bool, path: ?string, nombre: string, actualizado_en: ?string, url_ver: ?string, archivos: list<array{indice: int, nombre: string, extension: string, url_ver: ?string}>}>
     */
    public static function estadoTodos(
        string $dni,
        ?int $idLegajo = null,
        string $audiencia = self::AUDIENCIA_ALUMNO,
    ): array {
        $estado = [];
        foreach (self::tiposConfigurados() as $tipo) {
            $estado[$tipo['clave']] = self::estadoDocumento($dni, $tipo['clave'], $idLegajo, $audiencia);
        }

        return $estado;
    }

    /**
     * @return list<array{clave: string, label: string}>
     */
    public static function obligatoriosPendientes(string $dni): array
    {
        $pendientes = [];
        foreach (self::tiposConfigurados() as $tipo) {
            if (! $tipo['obligatorio']) {
                continue;
            }
            if (! self::estadoDocumento($dni, $tipo['clave'])['existe']) {
                $pendientes[] = [
                    'clave' => $tipo['clave'],
                    'label' => $tipo['label'],
                ];
            }
        }

        return $pendientes;
    }

    /**
     * Elimina todas las partes (JPG o PDF) del estudiante para el tipo indicado.
     */
    public static function eliminar(string $dni, string $clave): void
    {
        if (! self::claveValida($clave)) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }

        $dniSan = self::dniSanitizado($dni);
        if ($dniSan === 'sin-dni') {
            throw new \InvalidArgumentException('El estudiante no tiene DNI registrado.');
        }

        if (self::eliminarPartesEnDisco($dniSan, $clave) < 1) {
            throw new \RuntimeException('El documento no existe o ya fue eliminado.');
        }
    }

    /**
     * @param  list<TemporaryUploadedFile|mixed>  $archivos
     */
    public static function validarArchivos(string $clave, array $archivos): ?string
    {
        $def = self::definicion($clave);
        if ($def === null) {
            return 'Tipo de documento no válido.';
        }

        $validos = self::archivosDesdeSlots(is_array($archivos) ? $archivos : [$archivos]);

        if ($validos === []) {
            return 'Seleccione al menos un archivo (JPG o PDF).';
        }

        $hayFoto = false;
        $hayPdf = false;
        foreach ($validos as $archivo) {
            if (self::extensionDeUpload($archivo) === 'pdf') {
                $hayPdf = true;
            } else {
                $hayFoto = true;
            }
        }
        if ($hayFoto && $hayPdf) {
            return 'Suba solo fotos JPG o solo PDF, no ambos en el mismo documento.';
        }

        if (count($validos) > $def['max_archivos']) {
            return 'Puede subir como máximo '.$def['max_archivos'].' archivo(s) para este documento.';
        }

        $maxBytes = self::maxBytesPorArchivo($clave);
        $extensiones = array_map('strtolower', $def['extensiones']);

        foreach ($validos as $archivo) {
            $ext = self::extensionDeUpload($archivo);
            if (! self::extensionPermitida($ext, $extensiones)) {
                return 'Formato no permitido. Extensiones aceptadas: '.implode(', ', $extensiones).'.';
            }

            $bytes = (int) ($archivo->getSize() ?? 0);
            if ($bytes < 1) {
                return 'Uno de los archivos está vacío o no terminó de subirse.';
            }
            if ($bytes > $maxBytes) {
                $mb = (int) round($maxBytes / 1024 / 1024);

                return 'Cada archivo no puede superar los '.$mb.' MB.';
            }

            if (! self::mimeAceptado($archivo, $ext)) {
                return 'Uno de los archivos no es un JPG o PDF válido.';
            }
        }

        return null;
    }

    /**
     * @param  list<TemporaryUploadedFile|mixed>  $archivos
     */
    public static function guardarDesdeUploads(string $dni, string $clave, array $archivos): void
    {
        $error = self::validarArchivos($clave, $archivos);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        $def = self::definicion($clave);
        if ($def === null) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }

        $dniSan = self::dniSanitizado($dni);
        if ($dniSan === 'sin-dni') {
            throw new \InvalidArgumentException('El estudiante no tiene DNI registrado.');
        }

        $validos = self::archivosDesdeSlots($archivos);
        $dirTemp = null;
        $esPdf = self::loteEsPdf($validos);

        try {
            self::asegurarRaizDiscoPrivado();

            $relDir = self::storageDir();
            $disk = Storage::disk(self::DISK);
            self::asegurarDirectorioEnDisco($disk, $relDir);

            if ($esPdf) {
                $contenido = self::contenidoPdfDeUploads($validos, $dirTemp);
                self::eliminarPartesEnDisco($dniSan, $clave);
                $pathFinal = $relDir.'/'.self::nombreArchivoParte($dniSan, $clave, 1, 'pdf');
                if (! $disk->put($pathFinal, $contenido)) {
                    throw new \RuntimeException('No se pudo guardar el documento en el servidor.');
                }

                return;
            }

            $binarios = [];
            foreach ($validos as $archivo) {
                $origen = $archivo->getRealPath();
                if ($origen === false || ! is_file($origen)) {
                    throw new \RuntimeException('Uno de los archivos temporales ya no está disponible. Vuelva a seleccionarlos.');
                }
                $binarios[] = self::comprimirJpegABinario($origen);
            }

            self::eliminarPartesEnDisco($dniSan, $clave);
            foreach ($binarios as $pos => $binario) {
                $pathFinal = $relDir.'/'.self::nombreArchivoParte($dniSan, $clave, $pos + 1, 'jpg');
                if (! $disk->put($pathFinal, $binario)) {
                    throw new \RuntimeException('No se pudo guardar el documento en el servidor.');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('doc-estudiante-autogestion: error al guardar', [
                'dni' => $dniSan,
                'clave' => $clave,
                'message' => $e->getMessage(),
                'path' => self::pathRelativo($dniSan, $clave),
                'disk_root' => (string) config('filesystems.disks.'.self::DISK.'.root'),
            ]);
            throw $e;
        } finally {
            if ($dirTemp !== null && is_dir($dirTemp)) {
                foreach (glob($dirTemp.'/*') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($dirTemp);
            }
        }
    }

    private static function asegurarRaizDiscoPrivado(): void
    {
        $root = storage_path('app/private');
        if (is_dir($root)) {
            return;
        }

        if (! mkdir($root, 0755, true) && ! is_dir($root)) {
            throw new \RuntimeException('No se pudo crear la carpeta privada de almacenamiento.');
        }
    }

    private static function asegurarDirectorioEnDisco(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $relDir): void
    {
        if ($disk->exists($relDir)) {
            return;
        }

        if (! $disk->makeDirectory($relDir)) {
            throw new \RuntimeException('No se pudo crear el directorio de documentos del colegio.');
        }
    }

    /**
     * @param  list<TemporaryUploadedFile>  $archivos
     */
    private static function loteEsPdf(array $archivos): bool
    {
        foreach ($archivos as $archivo) {
            if (self::extensionDeUpload($archivo) !== 'pdf') {
                return false;
            }
        }

        return $archivos !== [];
    }

    /**
     * @param  list<TemporaryUploadedFile>  $archivos
     */
    private static function contenidoPdfDeUploads(array $archivos, ?string &$dirTemp): string
    {
        if (count($archivos) === 1) {
            $origen = $archivos[0]->getRealPath();
            if ($origen === false || ! is_file($origen)) {
                throw new \RuntimeException('El archivo temporal ya no está disponible. Vuelva a seleccionarlo.');
            }
            $contenido = file_get_contents($origen);
            if ($contenido === false || $contenido === '') {
                throw new \RuntimeException('No se pudo leer el PDF seleccionado.');
            }

            return $contenido;
        }

        $tempPaths = [];
        foreach ($archivos as $archivo) {
            $ruta = $archivo->getRealPath();
            if ($ruta === false || ! is_file($ruta)) {
                throw new \RuntimeException('Uno de los archivos temporales ya no está disponible. Vuelva a seleccionarlos.');
            }
            $tempPaths[] = $ruta;
        }

        $dirTemp = storage_path('app/temp/doc-estudiante/'.uniqid('merge_', true));
        if (! is_dir($dirTemp) && ! mkdir($dirTemp, 0755, true) && ! is_dir($dirTemp)) {
            throw new \RuntimeException('No se pudo preparar el directorio temporal.');
        }

        $salidaTemp = $dirTemp.'/documento.pdf';
        PdfCombinadorArchivos::combinar($tempPaths, $salidaTemp);

        if (! is_file($salidaTemp) || filesize($salidaTemp) < 1) {
            throw new \RuntimeException('No se pudo generar el PDF final.');
        }

        $contenido = file_get_contents($salidaTemp);
        if ($contenido === false || $contenido === '') {
            throw new \RuntimeException('No se pudo leer el PDF generado.');
        }

        return $contenido;
    }

    /**
     * Achica el lado largo y regraba el JPEG con calidad baja. El binario no incluye EXIF.
     */
    public static function comprimirJpegABinario(string $rutaOrigen): string
    {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('No se pudo comprimir la imagen.');
        }

        $info = @getimagesize($rutaOrigen);
        if ($info === false || (int) ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
            throw new \RuntimeException('La imagen no es un JPG válido.');
        }

        $origen = @imagecreatefromjpeg($rutaOrigen);
        if ($origen === false) {
            throw new \RuntimeException('La imagen no es un JPG válido.');
        }

        $origen = self::aplicarOrientacionExif($origen, $rutaOrigen);

        return self::redimensionarYComprimirJpeg($origen);
    }

    /**
     * Achica el lado largo a JPEG_LADO_MAX_PX y regraba con JPEG_CALIDAD.
     * Toma posesión de la imagen y la libera.
     */
    private static function redimensionarYComprimirJpeg(\GdImage $imagen): string
    {
        $anchoOrig = imagesx($imagen);
        $altoOrig = imagesy($imagen);
        if ($anchoOrig < 1 || $altoOrig < 1) {
            imagedestroy($imagen);
            throw new \RuntimeException('La imagen no tiene dimensiones válidas.');
        }

        $escala = min(
            self::JPEG_LADO_MAX_PX / $anchoOrig,
            self::JPEG_LADO_MAX_PX / $altoOrig,
            1.0
        );
        $ancho = max(1, (int) round($anchoOrig * $escala));
        $alto = max(1, (int) round($altoOrig * $escala));

        $destino = $imagen;
        if ($ancho !== $anchoOrig || $alto !== $altoOrig) {
            $redimensionada = imagecreatetruecolor($ancho, $alto);
            if ($redimensionada === false) {
                imagedestroy($imagen);
                throw new \RuntimeException('No se pudo comprimir la imagen.');
            }
            imagecopyresampled($redimensionada, $imagen, 0, 0, 0, 0, $ancho, $alto, $anchoOrig, $altoOrig);
            imagedestroy($imagen);
            $destino = $redimensionada;
        }

        ob_start();
        $ok = imagejpeg($destino, null, self::JPEG_CALIDAD);
        $bin = ob_get_clean();
        imagedestroy($destino);

        if (! $ok || ! is_string($bin) || $bin === '') {
            throw new \RuntimeException('No se pudo comprimir la imagen.');
        }

        return $bin;
    }

    /**
     * Gira la imagen 90° en sentido horario. Un JPG vuelve a pasar por la misma
     * compresión del alta (lado largo y calidad).
     */
    public static function rotarImagenABinario(string $rutaOrigen): string
    {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('No se pudo rotar la imagen.');
        }

        $info = @getimagesize($rutaOrigen);
        if ($info === false) {
            throw new \RuntimeException('La imagen no se pudo leer.');
        }

        $tipo = (int) ($info[2] ?? 0);
        $origen = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($rutaOrigen),
            IMAGETYPE_PNG => @imagecreatefrompng($rutaOrigen),
            default => false,
        };
        if ($origen === false) {
            throw new \RuntimeException('La imagen no se pudo leer.');
        }

        if ($tipo === IMAGETYPE_PNG) {
            imagealphablending($origen, false);
            imagesavealpha($origen, true);
        }

        $rotado = imagerotate($origen, -90, 0);
        imagedestroy($origen);
        if ($rotado === false) {
            throw new \RuntimeException('No se pudo rotar la imagen.');
        }

        if ($tipo === IMAGETYPE_JPEG) {
            return self::redimensionarYComprimirJpeg($rotado);
        }

        imagealphablending($rotado, false);
        imagesavealpha($rotado, true);
        ob_start();
        $ok = imagepng($rotado);
        $bin = ob_get_clean();
        imagedestroy($rotado);

        if (! $ok || ! is_string($bin) || $bin === '') {
            throw new \RuntimeException('No se pudo rotar la imagen.');
        }

        return $bin;
    }

    /**
     * Gira el archivo guardado 90° en sentido horario y lo reemplaza.
     * El PDF no se modifica.
     */
    public static function rotarImagenGuardada(string $dni, string $clave, int $indice): void
    {
        $path = self::pathParte($dni, $clave, $indice);
        if ($path === null) {
            throw new \RuntimeException('No hay un documento subido para rotar.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            throw new \RuntimeException('Solo se puede rotar una imagen.');
        }

        $absoluta = Storage::disk(self::DISK)->path($path);
        if (! is_file($absoluta)) {
            throw new \RuntimeException('No hay un documento subido para rotar.');
        }

        $recibidoEn = filemtime($absoluta);
        $bin = self::rotarImagenABinario($absoluta);
        if (file_put_contents($absoluta, $bin, LOCK_EX) === false) {
            throw new \RuntimeException('No se pudo guardar la imagen rotada.');
        }

        if ($recibidoEn !== false) {
            @touch($absoluta, $recibidoEn);
        }
    }

    private static function aplicarOrientacionExif(\GdImage $imagen, string $ruta): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $orientacion = (int) ($exif['Orientation'] ?? 1);
        if ($orientacion <= 1) {
            return $imagen;
        }

        $rotado = match ($orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => false,
        };

        if ($rotado === false) {
            return $imagen;
        }

        imagedestroy($imagen);

        return $rotado;
    }

    /**
     * @return list<string>
     */
    private static function candidatosIndice(string $dniSan, string $claveNorm, int $indice): array
    {
        $base = self::storageDir().'/'.$dniSan.'_'.$claveNorm;
        $nombre = $indice <= 1 ? $base : $base.'_'.$indice;
        $paths = [
            $nombre.'.jpg',
            $nombre.'.jpeg',
            $nombre.'.pdf',
        ];

        if ($indice === 1) {
            $legacy = 'ento/doc-estudiante/'.tenantSlug().'/'.$dniSan.'/'.$dniSan.'_'.$claveNorm;
            $paths[] = $legacy.'.pdf';
            $paths[] = $legacy.'.jpg';
            $paths[] = $legacy.'.jpeg';
        }

        return $paths;
    }

    private static function pathExistenteEnIndice(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $dniSan, string $claveNorm, int $indice): ?string
    {
        $mejor = null;
        $mejorTs = -1;
        foreach (self::candidatosIndice($dniSan, $claveNorm, $indice) as $path) {
            if (! $disk->exists($path)) {
                continue;
            }
            $ts = (int) $disk->lastModified($path);
            if ($mejor === null || $ts >= $mejorTs) {
                $mejor = $path;
                $mejorTs = $ts;
            }
        }

        return $mejor;
    }

    private static function eliminarPartesEnDisco(string $dniSan, string $clave): int
    {
        $claveNorm = DocEstudianteTipo::normalizarClave($clave);
        if ($claveNorm === '') {
            return 0;
        }

        $disk = Storage::disk(self::DISK);
        $borrados = 0;
        for ($i = 1; $i <= DocEstudianteTipo::MAX_ARCHIVOS_LIMITE; $i++) {
            foreach (self::candidatosIndice($dniSan, $claveNorm, $i) as $path) {
                if ($disk->exists($path) && $disk->delete($path)) {
                    $borrados++;
                }
            }
        }

        return $borrados;
    }

    /**
     * Extensión efectiva del upload. La cámara del celular a veces no manda extensión;
     * en ese caso se infiere por el MIME. jpeg se trata como jpg.
     */
    public static function extensionDeUpload(TemporaryUploadedFile $archivo): string
    {
        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if ($ext !== '') {
            return $ext;
        }

        $mime = strtolower((string) ($archivo->getMimeType() ?? ''));
        if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true)) {
            return 'jpg';
        }
        if (in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            return 'pdf';
        }

        return '';
    }

    /**
     * @param  list<string>  $permitidas
     */
    private static function extensionPermitida(string $ext, array $permitidas): bool
    {
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $normalizadas = [];
        foreach ($permitidas as $permitida) {
            $permitida = strtolower(trim($permitida));
            $normalizadas[] = $permitida === 'jpeg' ? 'jpg' : $permitida;
        }

        return $ext !== '' && in_array($ext, $normalizadas, true);
    }

    public static function acceptAttribute(array $extensiones): string
    {
        $parts = [];
        foreach ($extensiones as $ext) {
            $ext = strtolower(trim($ext));
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $parts[] = 'image/jpeg';
            } elseif ($ext === 'pdf') {
                $parts[] = 'application/pdf';
            }
            if ($ext !== '') {
                $parts[] = '.'.$ext;
            }
        }

        return implode(',', array_unique($parts));
    }

    private static function mimeAceptado(TemporaryUploadedFile $archivo, string $ext): bool
    {
        $mime = strtolower((string) $archivo->getMimeType());
        if ($mime === '') {
            return true;
        }

        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            return in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true);
        }

        if ($ext === 'pdf') {
            return in_array($mime, ['application/pdf', 'application/x-pdf'], true);
        }

        return false;
    }
}
