<?php

namespace App\Support;

use App\Models\CuotaGenerada;
use App\Models\Matricula;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bloqueos pedagógico y administrativo por matrícula (ciclo lectivo).
 */
final class MatriculaBloqueos
{
    public const MENSAJE_PEDAGOGICO_DEFAULT = 'Su matrícula tiene un bloqueo pedagógico. Comuníquese con secretaría.';

    public const MENSAJE_ADMINISTRATIVO_DEFAULT = 'Su matrícula tiene un bloqueo administrativo. Comuníquese con secretaría.';

    public static function bloqmatr(?Matricula $matricula): bool
    {
        return (bool) ($matricula?->bloqmatr ?? false);
    }

    public static function bloqadmi(?Matricula $matricula): bool
    {
        return (bool) ($matricula?->bloqadmi ?? false);
    }

    public static function estaBloqueado(?Matricula $matricula): bool
    {
        return self::bloqmatr($matricula) || self::bloqadmi($matricula);
    }

    /**
     * Impide ficha de matrícula y actualización de datos en autogestión familia.
     *
     * @return array{bloqueada: bool, mensaje: string}
     */
    public static function impideFichaYDatosAutogestion(?Matricula $matricula): array
    {
        if ($matricula === null || ! self::estaBloqueado($matricula)) {
            return ['bloqueada' => false, 'mensaje' => ''];
        }

        $idNivel = (int) ($matricula->idNivel ?? 0);
        if ($idNivel < 1) {
            $idNivel = (int) (studentCtx()->idNivel ?? 0);
        }

        $mensajesEnto = self::mensajesEntoNivel($idNivel);
        $partes = [];

        if (self::bloqmatr($matricula)) {
            $partes[] = $mensajesEnto['peda'] !== ''
                ? $mensajesEnto['peda']
                : self::MENSAJE_PEDAGOGICO_DEFAULT;
        }

        if (self::bloqadmi($matricula)) {
            $partes[] = $mensajesEnto['admi'] !== ''
                ? $mensajesEnto['admi']
                : self::MENSAJE_ADMINISTRATIVO_DEFAULT;
        }

        return [
            'bloqueada' => true,
            'mensaje' => implode("\n\n", $partes),
        ];
    }

    /**
     * @return array{bloqueada: bool, mensaje: string}
     */
    public static function paraEstudianteActual(): array
    {
        return self::impideFichaYDatosAutogestion(InformeInasistencias::matriculaAutogestion());
    }

    public static function autogestionFichaYDatosBloqueada(): bool
    {
        return self::paraEstudianteActual()['bloqueada'];
    }

    public static function mensajeAutogestionFichaYDatos(): string
    {
        return self::paraEstudianteActual()['mensaje'];
    }

    /**
     * Mensajes configurados en `ento` del nivel (un registro por nivel).
     *
     * @return array{peda: string, admi: string}
     */
    public static function mensajesEntoNivel(int $idNivel): array
    {
        if ($idNivel < 1 || ! Schema::hasTable('ento')) {
            return ['peda' => '', 'admi' => ''];
        }

        $columnas = [];
        if (Schema::hasColumn('ento', 'mensajeBloqPeda')) {
            $columnas[] = 'mensajeBloqPeda';
        }
        if (Schema::hasColumn('ento', 'mensajeBloqAdmi')) {
            $columnas[] = 'mensajeBloqAdmi';
        }

        if ($columnas === []) {
            return ['peda' => '', 'admi' => ''];
        }

        $row = DB::table('ento')
            ->where('idNivel', $idNivel)
            ->first($columnas);

        if (! $row) {
            return ['peda' => '', 'admi' => ''];
        }

        $attrs = (array) $row;

        return [
            'peda' => self::normalizarMensaje((string) ($attrs['mensajeBloqPeda'] ?? '')),
            'admi' => self::normalizarMensaje((string) ($attrs['mensajeBloqAdmi'] ?? '')),
        ];
    }

    /**
     * Convierte saltos `<br>` del mensaje configurado en texto plano (SweetAlert2 / Blade).
     */
    public static function normalizarMensaje(string $mensaje): string
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            return '';
        }

        return trim(preg_replace('/<br\s*\/?>/i', "\n", $mensaje) ?? $mensaje);
    }

    /**
     * Matrícula del ciclo de la cuota generada (por idMatricula o legajo + idTerlec).
     */
    public static function paraCuotaGenerada(CuotaGenerada $registro): ?Matricula
    {
        if ($registro->relationLoaded('matricula')) {
            return $registro->matricula;
        }

        $idMatricula = (int) ($registro->idMatricula ?? 0);
        if ($idMatricula > 0) {
            return Matricula::query()->find($idMatricula);
        }

        $idLegajo = (int) ($registro->idLegajos ?? 0);
        $idTerlec = (int) ($registro->idTerlec ?? 0);
        if ($idLegajo < 1 || $idTerlec < 1) {
            return null;
        }

        return Matricula::query()
            ->where('idLegajos', $idLegajo)
            ->where('idTerlec', $idTerlec)
            ->first();
    }
}
