<?php

namespace App\Support\Cuotas;

use App\Models\Cuota;
use App\Models\CuotasDetalle;
use App\Models\CuotasImporte;
use App\Models\Curso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Ítems de discriminación por curso (`cuotasdetalle`): nombre e importe en la misma fila.
 * Un curso puede tener líneas que otro no tiene.
 */
final class CuotasDetalleCatalog
{
    public const MAX_ITEMS = 30;

    public const NOMBRE_MAX = 180;

    public static function tablasListas(): bool
    {
        return Schema::hasTable('cuotasdetalle')
            && Schema::hasColumn('cuotasdetalle', 'idCursos')
            && Schema::hasColumn('cuotasdetalle', 'importe');
    }

    /** Quedó una versión anterior de la tabla, sin curso o sin importe en la misma fila. */
    public static function esquemaAnterior(): bool
    {
        if (Schema::hasTable('cuotasdetalleimportes') && ! self::tablasListas()) {
            return true;
        }

        return Schema::hasTable('cuotasdetalle')
            && (! Schema::hasColumn('cuotasdetalle', 'idCursos') || ! Schema::hasColumn('cuotasdetalle', 'importe'));
    }

    public static function cuotaDelCicloOrFail(int $idCuotas): Cuota
    {
        return CuotasImportesCatalog::cuotaDelCicloOrFail($idCuotas);
    }

    public static function detalleDelCursoOrFail(int $idDetalle, int $idCuotas, int $idCurso): CuotasDetalle
    {
        return CuotasDetalle::query()
            ->whereKey($idDetalle)
            ->where('idCuotas', $idCuotas)
            ->where('idCursos', $idCurso)
            ->firstOrFail();
    }

    public static function cursoDelCicloOrFail(int $idCurso, ?int $idTerlec = null): Curso
    {
        $idTerlec = $idTerlec ?? CuotasImportesCatalog::idTerlecActivo();

        return Curso::query()
            ->where('Id', $idCurso)
            ->where('idTerlec', $idTerlec)
            ->firstOrFail();
    }

    /**
     * @return list<int>
     */
    public static function idsCursosDelCiclo(?int $idTerlec = null): array
    {
        $idTerlec = $idTerlec ?? CuotasImportesCatalog::idTerlecActivo();

        return Curso::query()
            ->where('idTerlec', $idTerlec)
            ->orderBy('Id')
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Cursos del ciclo, en orden pedagógico, con etiqueta de listado.
     *
     * @return list<array{id: int, label: string}>
     */
    public static function cursosDelCiclo(?int $idTerlec = null): array
    {
        $idTerlec = $idTerlec ?? CuotasImportesCatalog::idTerlecActivo();

        $cursos = Curso::ordenarColeccion(
            Curso::query()
                ->where('idTerlec', $idTerlec)
                ->with(['nivel', 'curplan', 'turnoClase'])
                ->get()
        );

        $out = [];
        foreach ($cursos as $curso) {
            if (! $curso instanceof Curso) {
                continue;
            }
            $id = (int) $curso->Id;
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'label' => self::etiquetaCurso($curso),
            ];
        }

        return $out;
    }

    public static function etiquetaCurso(Curso $curso): string
    {
        $nombre = $curso->nombreParaListado();
        $abrev = trim((string) ($curso->nivel?->abrev ?? ''));

        if ($abrev !== '') {
            return $nombre.' ('.$abrev.')';
        }

        return $nombre !== '' ? $nombre : 'Curso #'.(int) $curso->Id;
    }

    /**
     * Ítems de un curso dentro de la plantilla.
     *
     * @return list<array{id: int, orden: int, nombre: string}>
     */
    public static function itemsDelCurso(int $idCuotas, int $idCurso): array
    {
        if ($idCurso <= 0) {
            return [];
        }

        return CuotasDetalle::query()
            ->where('idCuotas', $idCuotas)
            ->where('idCursos', $idCurso)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'orden', 'nombre'])
            ->map(fn (CuotasDetalle $item): array => [
                'id' => (int) $item->id,
                'orden' => (int) $item->orden,
                'nombre' => (string) $item->nombre,
            ])
            ->values()
            ->all();
    }

    /**
     * Cantidad de cursos de la plantilla que tienen al menos un ítem.
     *
     * @param  list<int>  $idsCuotas
     * @return array<int, int>
     */
    public static function conteoCursosConItems(array $idsCuotas): array
    {
        if ($idsCuotas === [] || ! self::tablasListas()) {
            return [];
        }

        $filas = CuotasDetalle::query()
            ->whereIn('idCuotas', $idsCuotas)
            ->selectRaw('idCuotas, COUNT(DISTINCT idCursos) as cursos')
            ->groupBy('idCuotas')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->idCuotas] = (int) $fila->cursos;
        }

        return $out;
    }

    /**
     * Cantidad de ítems por curso, para la plantilla indicada.
     *
     * @return array<int, int>
     */
    public static function conteoItemsPorCurso(int $idCuotas): array
    {
        $filas = CuotasDetalle::query()
            ->where('idCuotas', $idCuotas)
            ->selectRaw('idCursos, COUNT(*) as cantidad')
            ->groupBy('idCursos')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->idCursos] = (int) $fila->cantidad;
        }

        return $out;
    }

    /**
     * Importes de los ítems indicados. Clave = id de `cuotasdetalle`.
     *
     * @param  list<int>  $idsDetalle
     * @return array<string, string>
     */
    public static function montosTextoDeItems(array $idsDetalle): array
    {
        if ($idsDetalle === []) {
            return [];
        }

        $guardados = CuotasDetalle::query()
            ->whereIn('id', $idsDetalle)
            ->pluck('importe', 'id');

        $out = [];
        foreach ($idsDetalle as $idDetalle) {
            $valor = $guardados->has($idDetalle) ? $guardados->get($idDetalle) : 0;
            $out[(string) $idDetalle] = CuotasFormato::importeParaInput($valor);
        }

        return $out;
    }

    public static function importeCuotaCurso(int $idCuotas, int $idCurso): ?float
    {
        $importe = CuotasImporte::query()
            ->where('idCuotas', $idCuotas)
            ->where('idCursos', $idCurso)
            ->value('importe');

        return $importe === null ? null : round((float) $importe, 2);
    }

    /**
     * Alta de un ítem solo en el curso indicado, con importe 0.
     */
    public static function crearItem(int $idCuotas, int $idCurso, string $nombre): CuotasDetalle
    {
        $nombre = self::normalizarNombre($nombre);
        $cantidad = (int) CuotasDetalle::query()
            ->where('idCuotas', $idCuotas)
            ->where('idCursos', $idCurso)
            ->count();
        if ($cantidad >= self::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'nuevoNombre' => 'Puede definir hasta '.self::MAX_ITEMS.' ítems en este curso.',
            ]);
        }

        $orden = (int) CuotasDetalle::query()
            ->where('idCuotas', $idCuotas)
            ->where('idCursos', $idCurso)
            ->max('orden') + 1;

        return CuotasDetalle::query()->create([
            'idCuotas' => $idCuotas,
            'idCursos' => $idCurso,
            'orden' => $orden,
            'nombre' => $nombre,
            'importe' => 0,
        ]);
    }

    public static function renombrar(CuotasDetalle $item, string $nombre): void
    {
        $item->nombre = self::normalizarNombre($nombre);
        $item->save();
    }

    public static function eliminarItem(CuotasDetalle $item): void
    {
        DB::transaction(function () use ($item): void {
            $idCuotas = (int) $item->idCuotas;
            $idCurso = (int) $item->idCursos;
            $item->delete();
            self::renumerar($idCuotas, $idCurso);
        });
    }

    /**
     * Sube o baja el ítem un lugar (`$delta` -1 o 1).
     */
    public static function mover(CuotasDetalle $item, int $delta): void
    {
        if ($delta !== -1 && $delta !== 1) {
            return;
        }

        $items = CuotasDetalle::query()
            ->where('idCuotas', $item->idCuotas)
            ->where('idCursos', $item->idCursos)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $indice = $items->search(fn (CuotasDetalle $row): bool => (int) $row->id === (int) $item->id);
        if ($indice === false) {
            return;
        }

        $destino = $indice + $delta;
        if ($destino < 0 || $destino >= $items->count()) {
            return;
        }

        $lista = $items->all();
        [$lista[$indice], $lista[$destino]] = [$lista[$destino], $lista[$indice]];

        DB::transaction(function () use ($lista): void {
            $orden = 1;
            foreach ($lista as $row) {
                if ((int) $row->orden !== $orden) {
                    $row->orden = $orden;
                    $row->save();
                }
                $orden++;
            }
        });
    }

    /**
     * Persiste los importes de los ítems de un curso.
     * Las claves de `$montosTexto` son id de `cuotasdetalle`.
     *
     * @param  array<string|int, string>  $montosTexto
     */
    public static function guardarMontosCurso(int $idCuotas, int $idCurso, array $montosTexto): void
    {
        $ids = CuotasDetalle::query()
            ->where('idCuotas', $idCuotas)
            ->where('idCursos', $idCurso)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $errores = [];
        $valores = [];
        foreach ($ids as $idDetalle) {
            $texto = trim((string) ($montosTexto[(string) $idDetalle] ?? $montosTexto[$idDetalle] ?? ''));
            try {
                $valores[$idDetalle] = self::importeDesdeTexto($texto);
            } catch (ValidationException $e) {
                $errores['montos.'.$idDetalle] = $e->validator->errors()->first() ?: 'Importe inválido.';
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        DB::transaction(function () use ($idCuotas, $idCurso, $valores): void {
            foreach ($valores as $idDetalle => $importe) {
                CuotasDetalle::query()
                    ->whereKey($idDetalle)
                    ->where('idCuotas', $idCuotas)
                    ->where('idCursos', $idCurso)
                    ->update(['importe' => $importe]);
            }
        });
    }

    /**
     * Copia ítems e importes del curso origen a cada destino, reemplazando lo que hubiera.
     *
     * @param  list<int>  $idsCursosDestino
     */
    public static function copiarDiscriminacion(int $idCuotas, int $idCursoOrigen, array $idsCursosDestino): int
    {
        $origen = CuotasDetalle::query()
            ->where('idCuotas', $idCuotas)
            ->where('idCursos', $idCursoOrigen)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        if ($origen->isEmpty()) {
            return 0;
        }

        $destinos = [];
        foreach ($idsCursosDestino as $idCurso) {
            $idCurso = (int) $idCurso;
            if ($idCurso > 0 && $idCurso !== $idCursoOrigen) {
                $destinos[$idCurso] = $idCurso;
            }
        }

        if ($destinos === []) {
            return 0;
        }

        DB::transaction(function () use ($idCuotas, $origen, $destinos): void {
            foreach ($destinos as $idCurso) {
                $viejos = CuotasDetalle::query()
                    ->where('idCuotas', $idCuotas)
                    ->where('idCursos', $idCurso)
                    ->pluck('id');

                if ($viejos->isNotEmpty()) {
                    CuotasDetalle::query()->whereIn('id', $viejos)->delete();
                }

                foreach ($origen as $item) {
                    CuotasDetalle::query()->create([
                        'idCuotas' => $idCuotas,
                        'idCursos' => $idCurso,
                        'orden' => (int) $item->orden,
                        'nombre' => (string) $item->nombre,
                        'importe' => round((float) ($item->importe ?? 0), 2),
                    ]);
                }
            }
        });

        return count($destinos);
    }

    /**
     * Copia ítems e importes de todos los cursos de una plantilla a otras del mismo ciclo.
     * Reemplaza lo que hubiera en cada destino. No modifica la cuota de origen ni `cuotasimportes`.
     *
     * @param  list<int>  $idsCuotasDestino
     */
    public static function copiarDiscriminacionACuotas(int $idCuotaOrigen, array $idsCuotasDestino): int
    {
        $origenCuota = self::cuotaDelCicloOrFail($idCuotaOrigen);

        $origen = CuotasDetalle::query()
            ->where('idCuotas', $idCuotaOrigen)
            ->orderBy('idCursos')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        if ($origen->isEmpty()) {
            return 0;
        }

        $pedidos = [];
        foreach ($idsCuotasDestino as $idCuota) {
            $idCuota = (int) $idCuota;
            if ($idCuota > 0 && $idCuota !== $idCuotaOrigen) {
                $pedidos[$idCuota] = $idCuota;
            }
        }

        if ($pedidos === []) {
            return 0;
        }

        $destinos = Cuota::query()
            ->where('idTerlec', (int) $origenCuota->idTerlec)
            ->whereIn('id', array_values($pedidos))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($destinos === []) {
            return 0;
        }

        DB::transaction(function () use ($origen, $destinos): void {
            foreach ($destinos as $idCuota) {
                CuotasDetalle::query()->where('idCuotas', $idCuota)->delete();

                foreach ($origen as $item) {
                    CuotasDetalle::query()->create([
                        'idCuotas' => $idCuota,
                        'idCursos' => (int) $item->idCursos,
                        'orden' => (int) $item->orden,
                        'nombre' => (string) $item->nombre,
                        'importe' => round((float) ($item->importe ?? 0), 2),
                    ]);
                }
            }
        });

        return count($destinos);
    }

    /**
     * Borra ítems e importes de una plantilla. Si las tablas aún no existen, no hace nada.
     */
    public static function eliminarPorCuota(int $idCuotas): void
    {
        if (! Schema::hasTable('cuotasdetalle')) {
            return;
        }

        CuotasDetalle::query()->where('idCuotas', $idCuotas)->delete();
    }

    public static function normalizarNombre(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nuevoNombre' => 'Escriba el nombre del ítem.',
            ]);
        }
        if (mb_strlen($nombre) > self::NOMBRE_MAX) {
            throw ValidationException::withMessages([
                'nuevoNombre' => 'El nombre admite hasta '.self::NOMBRE_MAX.' caracteres.',
            ]);
        }

        return $nombre;
    }

    /**
     * Texto de importe argentino (1.234,56). Vacío equivale a 0.
     */
    public static function importeDesdeTexto(string $texto): float
    {
        $raw = trim(str_replace(['$', ' '], '', $texto));
        if ($raw === '') {
            return 0.0;
        }

        if (! preg_match('/^(?:\d{1,8}(?:\.\d{3})*(?:,\d{1,2})?|\d{1,10}(?:,\d{1,2})?|\d{1,8}\.\d{1,2})$/', $raw)) {
            throw ValidationException::withMessages([
                'importe' => 'Importe inválido. Use el formato 1.234,56.',
            ]);
        }

        if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $raw)) {
            $raw = str_replace('.', '', $raw);
        }

        $valor = CuotasFormato::parseImporte($raw);
        if ($valor < 0 || $valor > 99999999.99) {
            throw ValidationException::withMessages([
                'importe' => 'El importe debe estar entre 0 y 99.999.999,99.',
            ]);
        }

        return round($valor, 2);
    }

    /**
     * Suma los textos de importe. Null si alguno no se puede leer.
     *
     * @param  array<string|int, string>  $montosTexto
     */
    public static function subtotalTextos(array $montosTexto): ?float
    {
        $suma = 0.0;
        foreach ($montosTexto as $texto) {
            try {
                $suma += self::importeDesdeTexto((string) $texto);
            } catch (ValidationException) {
                return null;
            }
        }

        return round($suma, 2);
    }

    private static function renumerar(int $idCuotas, int $idCurso): void
    {
        $items = CuotasDetalle::query()
            ->where('idCuotas', $idCuotas)
            ->where('idCursos', $idCurso)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $orden = 1;
        foreach ($items as $item) {
            if ((int) $item->orden !== $orden) {
                $item->orden = $orden;
                $item->save();
            }
            $orden++;
        }
    }
}
