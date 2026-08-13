<?php

namespace App\Support\CapacitacionDocente;

use App\Models\CapacitacionDocente;
use App\Models\Profesor;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Consultas y almacenamiento de certificados PDF del módulo Capacitación Docente.
 *
 * Disco `privado` → ento/capacitacion-docente/{tenantSlug}/{id_nivel}/{uuid}.pdf
 * (mismo criterio que foto-carnet y doc-estudiante).
 */
final class CapacitacionDocenteService
{
    public const DISK = 'privado';

    public const CARPETA = 'ento/capacitacion-docente';

    public const MAX_KB = 5120; // 5 MB

    /** @return list<string> */
    public static function modalidades(): array
    {
        return ['presencial', 'virtual', 'hibrida'];
    }

    /** @return array<string, string> */
    public static function etiquetasModalidad(): array
    {
        return [
            'presencial' => 'Presencial',
            'virtual' => 'Virtual',
            'hibrida' => 'Híbrida',
        ];
    }

    public static function tablaDisponible(): bool
    {
        return Schema::hasTable('capacitacion_docente');
    }

    public static function mensajeTablaFaltante(): string
    {
        return 'Este colegio no tiene la tabla capacitacion_docente. Ejecute el SQL de creación del módulo.';
    }

    public static function idNivelContexto(): ?int
    {
        return SchoolAlcancePedagogico::idNivelLegajosDocente();
    }

    public static function scopedProfesorOrFail(int $id): Profesor
    {
        return Profesor::query()
            ->delNivel(self::idNivelContexto())
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, Profesor>
     */
    public static function profesoresParaSelector(): Collection
    {
        return Profesor::query()
            ->delNivel(self::idNivelContexto())
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get(['id', 'apellido', 'nombre', 'dni']);
    }

    public static function queryEnContexto(): Builder
    {
        $q = CapacitacionDocente::query();
        $idNivel = self::idNivelContexto();
        if ($idNivel !== null) {
            $q->where('id_nivel', $idNivel);
        }

        return $q;
    }

    public static function scopedOrFail(int $id): CapacitacionDocente
    {
        return self::queryEnContexto()->whereKey($id)->firstOrFail();
    }

    /**
     * @return LengthAwarePaginator<int, CapacitacionDocente>
     */
    public static function paginar(
        ?int $idProfesor,
        string $buscar,
        int $porPagina = 50,
    ): LengthAwarePaginator {
        $q = self::queryEnContexto()
            ->with('profesor')
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($idProfesor !== null && $idProfesor > 0) {
            $q->where('id_profesor', $idProfesor);
        }

        $termino = trim($buscar);
        if ($termino !== '') {
            $q->where(function (Builder $inner) use ($termino) {
                $inner->where('nombre', 'like', '%'.$termino.'%')
                    ->orWhere('entidad_otorgante', 'like', '%'.$termino.'%');
            });
        }

        return $q->paginate(max(10, min(100, $porPagina)));
    }

    /**
     * Cantidad de cursos por docente en el año calendario indicado (nivel activo).
     *
     * @return list<array{id_profesor: int, apellido: string, nombre: string, cantidad: int}>
     */
    public static function resumenPorDocenteAnio(int $anio): array
    {
        $idNivel = self::idNivelContexto();
        if ($idNivel === null || $anio < 1900) {
            return [];
        }

        $rows = DB::table('capacitacion_docente as c')
            ->join('profesores as p', 'p.id', '=', 'c.id_profesor')
            ->where('c.id_nivel', $idNivel)
            ->whereYear('c.fecha', $anio)
            ->groupBy('c.id_profesor', 'p.apellido', 'p.nombre')
            ->orderByDesc('cantidad')
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->selectRaw('c.id_profesor, p.apellido, p.nombre, COUNT(*) as cantidad')
            ->get();

        return $rows->map(static fn ($r): array => [
            'id_profesor' => (int) $r->id_profesor,
            'apellido' => (string) $r->apellido,
            'nombre' => (string) $r->nombre,
            'cantidad' => (int) $r->cantidad,
        ])->all();
    }

    public static function totalCursosAnio(int $anio): int
    {
        $idNivel = self::idNivelContexto();
        if ($idNivel === null || $anio < 1900) {
            return 0;
        }

        return (int) CapacitacionDocente::query()
            ->where('id_nivel', $idNivel)
            ->whereYear('fecha', $anio)
            ->count();
    }

    public static function validarPdf(mixed $archivo): ?string
    {
        if (! $archivo instanceof TemporaryUploadedFile) {
            return 'Seleccione un archivo PDF.';
        }

        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        $mime = (string) ($archivo->getMimeType() ?? '');
        if ($ext !== 'pdf' && ! str_contains($mime, 'pdf')) {
            return 'El certificado debe ser un archivo PDF.';
        }

        if ($archivo->getSize() > self::MAX_KB * 1024) {
            return 'El PDF no puede superar '.max(1, (int) round(self::MAX_KB / 1024)).' MB.';
        }

        return null;
    }

    public static function guardarCertificado(int $idNivel, TemporaryUploadedFile $archivo): string
    {
        $nombre = Str::uuid()->toString().'.pdf';
        $dir = self::CARPETA.'/'.tenantSlug().'/'.$idNivel;
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory($dir);
        $path = $archivo->storeAs($dir, $nombre, self::DISK);
        if ($path === false || $path === '') {
            throw new \RuntimeException('No se pudo guardar el certificado. Verifique permisos en storage/app/private.');
        }

        return $path;
    }

    public static function eliminarCertificado(?string $ruta): void
    {
        $ruta = trim((string) $ruta);
        if ($ruta === '') {
            return;
        }

        $disk = Storage::disk(self::DISK);
        if ($disk->exists($ruta)) {
            $disk->delete($ruta);
        }
    }

    public static function existeCertificado(?string $ruta): bool
    {
        $ruta = trim((string) $ruta);

        return $ruta !== '' && Storage::disk(self::DISK)->exists($ruta);
    }
}
