<?php

/**
 * Importación one-shot NSSC (programas 2019–2026) → public/archivos + SQL doc_pp.
 * Solo LEE la BD; escribe archivos en disco y un .sql revisable (sin INSERT automático).
 *
 * Uso: php artisan app:importar-doc-pp-nssc --dry-run
 *      php artisan app:importar-doc-pp-nssc
 */

namespace App\Console\Commands;

use App\Support\DocPp\DocPpStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportarDocPpNssc extends Command
{
    protected $signature = 'app:importar-doc-pp-nssc
                            {--origen= : Carpeta raíz con subcarpetas por año (default D:/SCRIPTCASE_DEPLOY/ia/nssc)}
                            {--cod-col=NSSC : Código de colegio para rutas/nombres si falta ento.codCol}
                            {--dry-run : Solo informar, no copiar ni escribir SQL}';

    protected $description = 'Incorpora PDF NSSC al repositorio public/archivos y genera SQL doc_pp (sin INSERT automático).';

    /** @var array<int, string> */
    private const ANOS_NUMERO = [
        1 => 'PRIMERO',
        2 => 'SEGUNDO',
        3 => 'TERCERO',
        4 => 'CUARTO',
        5 => 'QUINTO',
        6 => 'SEXTO',
    ];

    public function handle(): int
    {
        $origen = $this->option('origen') ?: 'D:/SCRIPTCASE_DEPLOY/ia/nssc';
        $dry = (bool) $this->option('dry-run');
        $codColOpt = trim((string) $this->option('cod-col'));
        if ($codColOpt === '') {
            $codColOpt = 'NSSC';
        }

        if (! is_dir($origen)) {
            $this->error("No existe la carpeta origen: {$origen}");

            return self::FAILURE;
        }

        if (! Schema::hasTable('doc_pp')) {
            $this->warn('La tabla doc_pp aún no existe. Se generará el SQL igual; créela antes de ejecutarlo.');
        }

        $idNivel = 3; // secundario
        $codColDb = Schema::hasColumn('ento', 'codCol')
            ? trim((string) (DB::table('ento')->where('idNivel', $idNivel)->value('codCol') ?? ''))
            : '';
        $codCol = $codColDb !== '' ? $codColDb : $codColOpt;
        if ($codColDb === '') {
            $this->warn("ento.codCol ausente/vacío; se usa {$codCol}. Ejecutá el ALTER/UPDATE del SQL generado.");
        }

        $aniosDirs = $this->descubrirAnios($origen);
        if ($aniosDirs === []) {
            $this->error('No se encontraron carpetas de año con PDF en '.$origen);

            return self::FAILURE;
        }

        $this->info('Años a procesar: '.implode(', ', array_keys($aniosDirs)));
        $this->info("Nivel {$idNivel} · codCol={$codCol}");

        $sqlLineas = [];
        $sqlLineas[] = '-- Import NSSC programas → doc_pp';
        $sqlLineas[] = '-- HeidiSQL: abrir este archivo y ejecutar sobre la sesión correcta (ia_nssc).';
        $sqlLineas[] = '-- Generado: '.now()->format('Y-m-d H:i:s');
        $sqlLineas[] = '-- Unique (idMaterias, tipo): ON DUPLICATE KEY UPDATE actualiza nombre/aprobado.';
        $sqlLineas[] = '';
        $sqlLineas[] = 'USE `ia_nssc`;';
        $sqlLineas[] = '';
        $sqlLineas[] = '-- Requisito: ento.codCol (módulo doc_pp / rutas public/archivos).';
        if (! Schema::hasColumn('ento', 'codCol')) {
            $sqlLineas[] = 'ALTER TABLE `ento` ADD COLUMN `codCol` VARCHAR(20) NULL AFTER `idNivel`;';
        }
        $sqlLineas[] = "UPDATE `ento` SET `codCol` = '".addslashes($codCol)."' WHERE `idNivel` IN (1, 2, 3) AND (`codCol` IS NULL OR `codCol` = '');";
        $sqlLineas[] = '';

        $ok = 0;
        $sinMatch = 0;
        $errores = 0;
        $omitidosDup = 0;
        $sinMatchLista = [];

        /** @var array<int, array{idTerlec: int, indice: array<string, object>}> $cacheAnio */
        $cacheAnio = [];
        /** @var array<string, array{path: string, size: int, anio: int, idTerlec: int, parsed: array, match: object}> $elegidos */
        $elegidos = [];

        foreach ($aniosDirs as $anioCarpeta => $dirAnio) {
            $pdfs = $this->listarPdfsAnio($dirAnio);
            $this->info("— carpeta {$anioCarpeta} · PDF=".count($pdfs));

            foreach ($pdfs as $path) {
                $base = basename($path);
                $parsed = $this->parseNombre($base);
                if ($parsed === null) {
                    $sinMatch++;
                    $sinMatchLista[] = "{$anioCarpeta}\tNO_PARSE\t{$base}";

                    continue;
                }

                $anio = $parsed['anio'];
                if (! isset($cacheAnio[$anio])) {
                    $terlec = DB::table('terlec')->where('ano', $anio)->orderByDesc('id')->first();
                    if ($terlec === null) {
                        $sinMatch++;
                        $sinMatchLista[] = "{$anioCarpeta}\tNO_TERLEC\t{$base}\tanio={$anio}";

                        continue;
                    }
                    $idTerlec = (int) $terlec->id;
                    $materias = DB::table('materias as m')
                        ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
                        ->where('m.idTerlec', $idTerlec)
                        ->where('m.idNivel', $idNivel)
                        ->select([
                            'm.id as idMaterias',
                            'm.idCursos',
                            'm.materia',
                            'c.cursec',
                        ])
                        ->orderBy('c.cursec')
                        ->orderBy('m.ord')
                        ->orderBy('m.id')
                        ->get();
                    $cacheAnio[$anio] = [
                        'idTerlec' => $idTerlec,
                        'indice' => $this->construirIndiceMaterias($materias),
                    ];
                    $this->info("  índice año {$anio} · terlec={$idTerlec} · materias={$materias->count()}");
                }

                $idTerlec = $cacheAnio[$anio]['idTerlec'];
                $indice = $cacheAnio[$anio]['indice'];

                $clave = $this->claveMatch($parsed['cursec'], $parsed['materia']);
                $match = $indice[$clave] ?? null;
                if ($match === null) {
                    $match = $this->buscarFuzzy($indice, $parsed['cursec'], $parsed['materia']);
                }
                if ($match === null) {
                    $sinMatch++;
                    $sinMatchLista[] = "{$anioCarpeta}\tNO_MATCH\t{$base}\tcursec={$parsed['cursec']}\tmateria={$parsed['materia']}\tanio={$anio}";

                    continue;
                }

                $kDoc = ((int) $match->idMaterias).'|'.$parsed['tipo'];
                $size = (int) filesize($path);
                if (isset($elegidos[$kDoc])) {
                    if ($size <= $elegidos[$kDoc]['size']) {
                        $omitidosDup++;

                        continue;
                    }
                    $omitidosDup++;
                }
                $elegidos[$kDoc] = [
                    'path' => $path,
                    'size' => $size,
                    'anio' => $anio,
                    'idTerlec' => $idTerlec,
                    'parsed' => $parsed,
                    'match' => $match,
                ];
            }
        }

        ksort($cacheAnio);
        foreach ($cacheAnio as $anio => $meta) {
            $docsAnio = array_filter($elegidos, fn ($e) => $e['anio'] === $anio);
            $sqlLineas[] = "-- Año {$anio} (idTerlec = {$meta['idTerlec']}) · ".count($docsAnio).' documentos';

            foreach ($docsAnio as $item) {
                $match = $item['match'];
                $tipo = $item['parsed']['tipo'];
                $idTerlec = $item['idTerlec'];
                $cursecEtiqueta = trim((string) $match->cursec);
                $materiaNombre = trim((string) $match->materia);

                try {
                    $nombreCanonico = DocPpStorage::generarNombreArchivo(
                        $anio,
                        $idNivel,
                        $tipo,
                        $cursecEtiqueta,
                        $materiaNombre,
                        $codCol,
                    );
                    $destinoRel = DocPpStorage::rutaRelativaArchivo($anio, $tipo, $idNivel, $nombreCanonico, $codCol);
                    $destinoAbs = public_path('archivos'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $destinoRel));
                } catch (\Throwable $e) {
                    $errores++;
                    $this->error('Nombre/ruta: '.basename($item['path']).' → '.$e->getMessage());

                    continue;
                }

                if (! $dry) {
                    $dir = dirname($destinoAbs);
                    if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                        $errores++;
                        $this->error("No se pudo crear: {$dir}");

                        continue;
                    }
                    if (! copy($item['path'], $destinoAbs)) {
                        $errores++;
                        $this->error('No se pudo copiar: '.basename($item['path']));

                        continue;
                    }
                }

                $idMaterias = (int) $match->idMaterias;
                $idCursos = (int) $match->idCursos;
                $nombreEsc = addslashes($nombreCanonico);
                $sqlLineas[] = 'INSERT INTO `doc_pp` (`idNivel`, `idTerlec`, `idMaterias`, `idCursos`, `tipo`, `nombre_archivo`, `aprobado`, `observaciones`, `subido_por`, `subido_en`) VALUES'
                    ." ({$idNivel}, {$idTerlec}, {$idMaterias}, {$idCursos}, '{$tipo}', '{$nombreEsc}', 1, NULL, NULL, NOW())"
                    .' ON DUPLICATE KEY UPDATE `nombre_archivo` = VALUES(`nombre_archivo`), `aprobado` = VALUES(`aprobado`), `idCursos` = VALUES(`idCursos`);';

                $ok++;
                if ($this->output->isVerbose()) {
                    $this->line(($dry ? '[dry] ' : '')."OK {$anio} {$tipo} · {$cursecEtiqueta} · {$materiaNombre}");
                }
            }

            $sqlLineas[] = '';
        }

        $sqlPath = database_path('sql/import_doc_pp_nssc.sql');
        if (! $dry) {
            file_put_contents($sqlPath, implode("\n", $sqlLineas)."\n");
            $this->info("SQL escrito: {$sqlPath}");
        }

        $reportPath = storage_path('logs/import_doc_pp_nssc_sin_match.txt');
        if ($sinMatchLista !== [] && ! $dry) {
            file_put_contents($reportPath, implode("\n", $sinMatchLista)."\n");
            $this->warn("Sin match: {$reportPath}");
        }

        $this->newLine();
        $this->info("Incorporados: {$ok} · Sin match/parse: {$sinMatch} · Duplicados omitidos: {$omitidosDup} · Errores: {$errores}");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string> año => ruta absoluta carpeta año
     */
    private function descubrirAnios(string $origen): array
    {
        $out = [];
        foreach (scandir($origen) ?: [] as $entry) {
            if (! preg_match('/^\d{4}$/', $entry)) {
                continue;
            }
            $anio = (int) $entry;
            $dir = rtrim($origen, '/\\').DIRECTORY_SEPARATOR.$entry;
            if (! is_dir($dir)) {
                continue;
            }
            if ($this->listarPdfsAnio($dir) === []) {
                continue;
            }
            $out[$anio] = $dir;
        }
        ksort($out);

        return $out;
    }

    /**
     * @return list<string>
     */
    private function listarPdfsAnio(string $dirAnio): array
    {
        $paths = [];
        foreach ([DocPpStorage::CARPETA_PROGRAMAS, DocPpStorage::CARPETA_PLANIFICACIONES] as $sub) {
            $dir = $dirAnio.DIRECTORY_SEPARATOR.$sub;
            if (! is_dir($dir)) {
                continue;
            }
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*.pdf') ?: [] as $p) {
                $paths[] = $p;
            }
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*.PDF') ?: [] as $p) {
                $paths[] = $p;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $materias
     * @return array<string, object>
     */
    private function construirIndiceMaterias($materias): array
    {
        $indice = [];
        foreach ($materias as $m) {
            $clave = $this->claveMatch((string) $m->cursec, (string) $m->materia);
            $indice[$clave] = $m;
        }

        return $indice;
    }

    private function claveMatch(string $cursec, string $materia): string
    {
        return $this->normalizar($cursec).'|'.$this->normalizar($materia);
    }

    private function normalizar(string $texto): string
    {
        $t = mb_strtoupper(trim($texto), 'UTF-8');
        $t = strtr($t, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O',
        ]);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        if (is_string($converted) && $converted !== '') {
            $t = $converted;
        }
        $t = preg_replace('/[^A-Z0-9]+/', '', $t) ?? '';

        return $t;
    }

    /**
     * @param  array<string, object>  $indice
     */
    private function buscarFuzzy(array $indice, string $cursec, string $materia): ?object
    {
        $curN = $this->normalizar($cursec);
        $variantes = $this->variantesMateria($materia);

        $candidatos = [];
        foreach ($variantes as $matN) {
            foreach ($indice as $clave => $m) {
                [$c, $mat] = explode('|', $clave, 2);
                if ($c !== $curN) {
                    continue;
                }
                if ($mat === $matN || str_contains($mat, $matN) || str_contains($matN, $mat)) {
                    $candidatos[$clave] = $m;
                }
            }
        }

        if (count($candidatos) === 1) {
            return reset($candidatos);
        }

        // Educación artística: archivos legacy a veces dicen TEATRO / ART.VIS
        // y en BD figura la orientación de ese año (o al revés).
        $matN = $this->normalizar($materia);
        if (str_contains($matN, 'EDUCACIONARTISTICA')
            || str_contains($matN, 'TEATRO')
            || str_contains($matN, 'ARTESVISUALES')
            || str_contains($matN, 'ARTVIS')) {
            $art = [];
            foreach ($indice as $clave => $m) {
                [$c, $mat] = explode('|', $clave, 2);
                if ($c === $curN && str_starts_with($mat, 'EDUCACIONARTISTICA')) {
                    $art[$clave] = $m;
                }
            }
            if (count($art) === 1) {
                return reset($art);
            }
        }

        // Tokens ≥4 chars del nombre archivo, todos presentes en la materia BD.
        $tokens = [];
        foreach (preg_split('/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9]+/u', $materia) ?: [] as $tok) {
            $n = $this->normalizar($tok);
            if (strlen($n) >= 4) {
                $tokens[] = $n;
            }
        }
        $tokens = array_values(array_unique($tokens));
        if ($tokens === []) {
            return null;
        }

        $porTokens = [];
        foreach ($indice as $clave => $m) {
            [$c, $mat] = explode('|', $clave, 2);
            if ($c !== $curN) {
                continue;
            }
            $ok = true;
            foreach ($tokens as $tok) {
                if (! str_contains($mat, $tok)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $porTokens[$clave] = $m;
            }
        }

        return count($porTokens) === 1 ? reset($porTokens) : null;
    }

    /**
     * @return list<string> normalizadas
     */
    private function variantesMateria(string $materia): array
    {
        $raw = mb_strtoupper(trim($materia), 'UTF-8');
        $raw = strtr($raw, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);

        $reemplazos = [
            'EXTRAJERA' => 'EXTRANJERA',
            'ARTISITCA' => 'ARTISTICA',
            'CS NATURALES' => 'CIENCIAS NATURALES',
            'CS SOCIALES' => 'CIENCIAS SOCIALES',
            'ART.VIS' => 'ARTES VISUALES',
            'ART VIS' => 'ARTES VISUALES',
            'TECNOLOGIA INFORM. Y COMUN' => 'TECNOL. DE LA INFORM. Y LA COMUNICACION',
            'ECON.Y DESARROLLO SUSTENTABLE' => 'ECONOMIA Y DESARROLLO SUSTENTABLE',
            'SIST.DE INFORMACION CONTABLE' => 'SISTEMA DE INFORMACION CONTABLE',
            'SISTEMAS DE INFORM.CONTABLE' => 'SISTEMA DE INFORMACION CONTABLE',
            'METODOLOG. DE LA INVESTIGACION' => 'METODOLOGIA DE LA INVESTIGACION',
            'METOD.INVESTIG.CIENCIAS SOC' => 'METOD. DE LA INVEST. EN CIENCIAS SOCIALES',
            'METOD.INVEST.EN CIENCIAS SOC' => 'METOD. DE LA INVEST. EN CIENCIAS SOCIALES',
            'ADMIN.DE LA PROD.Y COMERC' => 'ADMIN.DE LA PROD. Y LA COMERCIALIZACION',
            'ADMINISTRAC.DE RECURSOS HUM' => 'ADMINISTRACION DE RECURSOS HUMANOS',
            'EDUC. TECNOL. Y CIENCIAS DE LA COMPUTACION' => 'EDUCACION TECNOLOGICA',
        ];

        $candidatosTexto = [$raw];
        foreach ($reemplazos as $desde => $hasta) {
            if (str_contains($raw, $desde)) {
                $candidatosTexto[] = str_replace($desde, $hasta, $raw);
                $candidatosTexto[] = $hasta;
            }
        }

        if (str_contains($this->normalizar($raw), 'VIDA') && str_starts_with($this->normalizar($raw), 'FORM')) {
            $candidatosTexto[] = 'FORMACION PARA LA VIDA Y EL TRABAJO';
        }

        $out = [];
        foreach ($candidatosTexto as $t) {
            $n = $this->normalizar($t);
            if ($n !== '') {
                $out[] = $n;
            }
        }

        $base = $this->normalizar($materia);
        if ($base === 'INGLES' || str_contains($base, 'EXTRAJERA') || str_contains($base, 'EXTRANJERA')) {
            $out[] = 'LENGUAEXTRANJERAINGLES';
            $out[] = 'INGLES';
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array{anio: int, cursec: string, materia: string, tipo: string}|null
     */
    private function parseNombre(string $nombre): ?array
    {
        // Moderno: 2025_CUARTO_A_BIOLOGÍA_Prog.pdf
        if (preg_match(
            '/^(\d{4})_(PRIMERO|SEGUNDO|TERCERO|CUARTO|QUINTO|SEXTO)_([A-Za-z])_(.+)_(Prog|Plan|Planif|Planificacion)\.pdf$/iu',
            $nombre,
            $m
        )) {
            $cursec = strtoupper($m[2]).' '.strtoupper($m[3]);
            $materia = str_replace('_', ' ', $m[4]);
            $materia = preg_replace('/\s+/', ' ', trim($materia)) ?? '';
            $tipo = $this->tipoDesdeSufijo($m[5]);

            return [
                'anio' => (int) $m[1],
                'cursec' => $cursec,
                'materia' => $materia,
                'tipo' => $tipo,
            ];
        }

        // Legacy: 1_A_CATEQUESIS_2019_(nssc-Programa).pdf
        if (preg_match(
            '/^(\d+)_([A-Za-z])_(.+)_(\d{4})_\(nssc-(Programa|Planificacion|Planif|Plan)\)\.pdf$/iu',
            $nombre,
            $m
        )) {
            $nCurso = (int) $m[1];
            if (! isset(self::ANOS_NUMERO[$nCurso])) {
                return null;
            }
            $cursec = self::ANOS_NUMERO[$nCurso].' '.strtoupper($m[2]);
            $materia = trim(str_replace(['_', '-'], [' ', ' - '], $m[3]));
            $materia = preg_replace('/\s+/', ' ', $materia) ?? '';
            $materia = preg_replace('/\s*-\s*/', ' - ', $materia) ?? $materia;
            $tipo = $this->tipoDesdeSufijo($m[5]);

            return [
                'anio' => (int) $m[4],
                'cursec' => $cursec,
                'materia' => $materia,
                'tipo' => $tipo,
            ];
        }

        return null;
    }

    private function tipoDesdeSufijo(string $sufijo): string
    {
        $s = mb_strtolower($sufijo, 'UTF-8');

        return str_starts_with($s, 'plan') ? DocPpStorage::TIPO_PLAN : DocPpStorage::TIPO_PROG;
    }
}
