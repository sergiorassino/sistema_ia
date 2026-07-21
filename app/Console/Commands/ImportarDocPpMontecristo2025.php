<?php

/**
 * Importación one-shot Montecristo 2025 → public/archivos + SQL doc_pp.
 * Solo LEE la BD; escribe archivos en disco y un .sql revisable.
 *
 * Uso: php artisan app:importar-doc-pp-montecristo-2025 --dry-run
 *      php artisan app:importar-doc-pp-montecristo-2025
 */

namespace App\Console\Commands;

use App\Support\DocPp\DocPpStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportarDocPpMontecristo2025 extends Command
{
    protected $signature = 'app:importar-doc-pp-montecristo-2025
                            {--origen= : Carpeta con PDF legacy (default D:/SCRIPTCASE_DEPLOY/ia/montecristo)}
                            {--dry-run : Solo informar, no copiar ni escribir SQL}';

    protected $description = 'Incorpora PDF Montecristo 2025 al repositorio public/archivos y genera SQL doc_pp (sin INSERT automático).';

    public function handle(): int
    {
        $origen = $this->option('origen') ?: 'D:/SCRIPTCASE_DEPLOY/ia/montecristo';
        $dry = (bool) $this->option('dry-run');

        if (! is_dir($origen)) {
            $this->error("No existe la carpeta origen: {$origen}");

            return self::FAILURE;
        }

        if (! Schema::hasTable('doc_pp')) {
            $this->warn('La tabla doc_pp aún no existe. Se generará el SQL igual; créela antes de ejecutarlo.');
        }

        $anio = 2025;
        $terlec = DB::table('terlec')->where('ano', $anio)->orderByDesc('id')->first();
        if ($terlec === null) {
            $this->error("No hay terlec para el año {$anio}.");

            return self::FAILURE;
        }
        $idTerlec = (int) $terlec->id;

        $idNivel = 3; // secundario
        $codCol = Schema::hasColumn('ento', 'codCol')
            ? trim((string) (DB::table('ento')->where('idNivel', $idNivel)->value('codCol') ?? ''))
            : '';
        if ($codCol === '') {
            $codCol = 'MC';
            $this->warn('ento.codCol vacío; se usa MC.');
        }

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

        $this->info("Materias 2025 (nivel {$idNivel}): ".$materias->count());
        $this->info("Terlec id={$idTerlec} · codCol={$codCol}");

        $indice = $this->construirIndiceMaterias($materias);
        $pdfs = glob(rtrim($origen, '/\\').DIRECTORY_SEPARATOR.'*.pdf') ?: [];
        $this->info('PDF en origen: '.count($pdfs));

        $sqlLineas = [];
        $sqlLineas[] = '-- Import Montecristo 2025 → doc_pp (revisar antes de ejecutar)';
        $sqlLineas[] = '-- Generado: '.now()->format('Y-m-d H:i:s');
        $sqlLineas[] = '-- Unique (idMaterias, tipo): ON DUPLICATE KEY UPDATE actualiza nombre/aprobado.';
        $sqlLineas[] = '';

        $ok = 0;
        $sinMatch = 0;
        $errores = 0;
        $sinMatchLista = [];

        foreach ($pdfs as $path) {
            $base = basename($path);
            $parsed = $this->parseNombreLegacy($base);
            if ($parsed === null) {
                $sinMatch++;
                $sinMatchLista[] = "NO_PARSE\t{$base}";
                continue;
            }

            $clave = $this->claveMatch($parsed['cursec'], $parsed['materia']);
            $match = $indice[$clave] ?? null;

            if ($match === null) {
                // intento fuzzy: misma cursec, materia contenida
                $match = $this->buscarFuzzy($indice, $parsed['cursec'], $parsed['materia']);
            }

            if ($match === null) {
                $sinMatch++;
                $sinMatchLista[] = "NO_MATCH\t{$base}\tcursec={$parsed['cursec']}\tmateria={$parsed['materia']}";
                continue;
            }

            $tipo = $parsed['tipo'];
            $cursecEtiqueta = trim((string) $match->cursec);
            $materiaNombre = trim((string) $match->materia);

            try {
                $nombreCanonico = DocPpStorage::generarNombreArchivo(
                    $anio,
                    $idNivel,
                    $tipo,
                    $cursecEtiqueta,
                    $materiaNombre,
                );
                $destinoRel = DocPpStorage::rutaRelativaArchivo($anio, $tipo, $idNivel, $nombreCanonico);
                $destinoAbs = public_path('archivos'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $destinoRel));
            } catch (\Throwable $e) {
                $errores++;
                $this->error("Nombre/ruta: {$base} → ".$e->getMessage());
                continue;
            }

            if (! $dry) {
                $dir = dirname($destinoAbs);
                if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                    $errores++;
                    $this->error("No se pudo crear: {$dir}");
                    continue;
                }
                if (! copy($path, $destinoAbs)) {
                    $errores++;
                    $this->error("No se pudo copiar: {$base}");
                    continue;
                }
            }

            $idMaterias = (int) $match->idMaterias;
            $idCursos = (int) $match->idCursos;
            $nombreEsc = addslashes($nombreCanonico);
            $sqlLineas[] = "INSERT INTO `doc_pp` (`idNivel`, `idTerlec`, `idMaterias`, `idCursos`, `tipo`, `nombre_archivo`, `aprobado`, `observaciones`, `subido_por`, `subido_en`) VALUES"
                ." ({$idNivel}, {$idTerlec}, {$idMaterias}, {$idCursos}, '{$tipo}', '{$nombreEsc}', 1, NULL, NULL, NOW())"
                .' ON DUPLICATE KEY UPDATE `nombre_archivo` = VALUES(`nombre_archivo`), `aprobado` = VALUES(`aprobado`), `idCursos` = VALUES(`idCursos`);';

            $ok++;
            $this->line(($dry ? '[dry] ' : '')."OK {$tipo} · {$cursecEtiqueta} · {$materiaNombre} → {$nombreCanonico}");
        }

        $sqlPath = database_path('sql/import_doc_pp_montecristo_2025.sql');
        if (! $dry) {
            file_put_contents($sqlPath, implode("\n", $sqlLineas)."\n");
            $this->info("SQL escrito: {$sqlPath}");
        }

        $reportPath = storage_path('logs/import_doc_pp_montecristo_2025_sin_match.txt');
        if ($sinMatchLista !== [] && ! $dry) {
            file_put_contents($reportPath, implode("\n", $sinMatchLista)."\n");
            $this->warn("Sin match: {$reportPath}");
        }

        $this->newLine();
        $this->info("Incorporados: {$ok} · Sin match/parse: {$sinMatch} · Errores: {$errores}");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
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
        // quitar diacríticos residuales
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
        $matN = $this->normalizar($materia);
        $candidatos = [];

        foreach ($indice as $clave => $m) {
            [$c, $mat] = explode('|', $clave, 2);
            if ($c !== $curN) {
                continue;
            }
            if ($mat === $matN || str_contains($mat, $matN) || str_contains($matN, $mat)) {
                $candidatos[] = $m;
            }
        }

        return count($candidatos) === 1 ? $candidatos[0] : null;
    }

    /**
     * MC_2025_{CURSEC}_{MATERIA}_{Planif|Prog}_YYYY_MM_DD_HH_MM_SS.pdf
     *
     * @return array{cursec: string, materia: string, tipo: string}|null
     */
    private function parseNombreLegacy(string $nombre): ?array
    {
        if (! preg_match('/^MC_2025_(.+?)_(Planif|Prog)_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.pdf$/iu', $nombre, $m)) {
            return null;
        }

        $resto = $m[1];
        $sufijo = strtolower($m[2]);
        $tipo = str_starts_with($sufijo, 'plan') ? DocPpStorage::TIPO_PLAN : DocPpStorage::TIPO_PROG;

        // cursec: PRIMERO_A, CUARTO__A, TERCERO_B_, etc.
        if (! preg_match('/^(PRIMERO|SEGUNDO|TERCERO|CUARTO|QUINTO|SEXTO)_+_?([A-Z])_+(.*)$/iu', $resto, $p)) {
            // variante PRIMERO_A_...
            if (! preg_match('/^(PRIMERO|SEGUNDO|TERCERO|CUARTO|QUINTO|SEXTO)_([A-Z])_(.+)$/iu', $resto, $p)) {
                return null;
            }
        }

        $cursec = strtoupper($p[1]).' '.strtoupper($p[2]);
        $materia = str_replace('_', ' ', $p[3]);
        $materia = preg_replace('/\s+/', ' ', trim($materia)) ?? '';
        $materia = ltrim($materia, ' _-');

        return [
            'cursec' => $cursec,
            'materia' => $materia,
            'tipo' => $tipo,
        ];
    }
}
