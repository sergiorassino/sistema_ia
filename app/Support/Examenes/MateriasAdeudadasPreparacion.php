<?php

namespace App\Support\Examenes;

use App\Models\Terlec;
use App\Support\SchoolContext;
use Illuminate\Support\Facades\DB;

/**
 * Turno y año lectivo por módulo. Los valores guardados precargan el formulario.
 * Cada ingreso desde el menú exige confirmar de nuevo (visit_ok_*); dentro del módulo se mantiene
 * la visita y el recálculo ya hecho (recalculo_ok_*) hasta salir por el menú.
 */
final class MateriasAdeudadasPreparacion
{
    public const MODULO_LISTADO = 'listado';

    public const MODULO_GESTION = 'gestion';

    public const MODULO_ACTA_VOLANTE = 'acta_volante';

    public const MODULO_PERMISO_EXAMEN = 'permiso_examen';

    private const SESSION_LISTADO = 'materias_adeudadas_prep_listado';

    private const SESSION_GESTION = 'materias_adeudadas_prep_gestion';

    private const SESSION_ACTA_VOLANTE = 'materias_adeudadas_prep_acta_volante';

    private const SESSION_PERMISO_EXAMEN = 'materias_adeudadas_prep_permiso_examen';

    private const SESSION_VISIT_OK_LISTADO = 'materias_adeudadas_visit_ok_listado';

    private const SESSION_VISIT_OK_GESTION = 'materias_adeudadas_visit_ok_gestion';

    private const SESSION_VISIT_OK_ACTA_VOLANTE = 'materias_adeudadas_visit_ok_acta_volante';

    private const SESSION_VISIT_OK_PERMISO_EXAMEN = 'materias_adeudadas_visit_ok_permiso_examen';

    private const SESSION_SOLICITAR_PREP_LISTADO = 'materias_adeudadas_solicitar_prep_listado';

    private const SESSION_SOLICITAR_PREP_GESTION = 'materias_adeudadas_solicitar_prep_gestion';

    private const SESSION_SOLICITAR_PREP_ACTA_VOLANTE = 'materias_adeudadas_solicitar_prep_acta_volante';

    private const SESSION_SOLICITAR_PREP_PERMISO_EXAMEN = 'materias_adeudadas_solicitar_prep_permiso_examen';

    private const SESSION_RECALCULO_OK_LISTADO = 'materias_adeudadas_recalculo_ok_listado';

    private const SESSION_RECALCULO_OK_GESTION = 'materias_adeudadas_recalculo_ok_gestion';

    private const SESSION_RECALCULO_OK_ACTA_VOLANTE = 'materias_adeudadas_recalculo_ok_acta_volante';

    private const SESSION_RECALCULO_OK_PERMISO_EXAMEN = 'materias_adeudadas_recalculo_ok_permiso_examen';

    /**
     * Clic en el menú: en la próxima carga del módulo se muestra el formulario de preparación.
     */
    public static function solicitarFormularioPreparacion(string $modulo): void
    {
        self::marcarVisitaSinConfirmar($modulo);
        session([self::solicitarPrepKey($modulo) => true]);
    }

    public static function marcarRecalculoEjecutadoEnVisita(string $modulo): void
    {
        session([self::recalculoOkKey($modulo) => true]);
    }

    public static function recalculoEjecutadoEnVisita(string $modulo): bool
    {
        return session(self::recalculoOkKey($modulo)) === true;
    }

    public static function consumirSolicitudFormularioPreparacion(string $modulo): bool
    {
        return session()->pull(self::solicitarPrepKey($modulo), false) === true;
    }

    public static function guardar(int $idNivel, int $idTurno, int $idTerlec, string $modulo): void
    {
        session()->put(self::datosKey($modulo), [
            'idNivel' => $idNivel,
            'idTurno' => $idTurno,
            'idTerlec' => $idTerlec,
        ]);
    }

    public static function marcarVisitaSinConfirmar(string $modulo): void
    {
        session()->forget([
            self::visitOkKey($modulo),
            self::recalculoOkKey($modulo),
        ]);
    }

    public static function marcarVisitaConfirmada(string $modulo): void
    {
        session([self::visitOkKey($modulo) => true]);
    }

    public static function visitaConfirmadaEnSesion(string $modulo): bool
    {
        return session(self::visitOkKey($modulo)) === true;
    }

    public static function clear(?string $modulo = null): void
    {
        if ($modulo !== null) {
            session()->forget([self::datosKey($modulo), self::visitOkKey($modulo)]);

            return;
        }

        session()->forget([
            self::SESSION_LISTADO,
            self::SESSION_GESTION,
            self::SESSION_ACTA_VOLANTE,
            self::SESSION_PERMISO_EXAMEN,
            self::SESSION_VISIT_OK_LISTADO,
            self::SESSION_VISIT_OK_GESTION,
            self::SESSION_VISIT_OK_ACTA_VOLANTE,
            self::SESSION_VISIT_OK_PERMISO_EXAMEN,
            self::SESSION_SOLICITAR_PREP_LISTADO,
            self::SESSION_SOLICITAR_PREP_GESTION,
            self::SESSION_SOLICITAR_PREP_ACTA_VOLANTE,
            self::SESSION_SOLICITAR_PREP_PERMISO_EXAMEN,
            self::SESSION_RECALCULO_OK_LISTADO,
            self::SESSION_RECALCULO_OK_GESTION,
            self::SESSION_RECALCULO_OK_ACTA_VOLANTE,
            self::SESSION_RECALCULO_OK_PERMISO_EXAMEN,
            'examenes.materias_adeudadas_preparacion',
        ]);
    }

    /**
     * @return array{idTurno:int, idTerlec:int}|null
     */
    public static function datosConfirmadosParaRestaurar(SchoolContext $ctx, string $modulo): ?array
    {
        if (! $ctx->isValid() || ! self::visitaConfirmadaEnSesion($modulo)) {
            return null;
        }

        $data = self::datosGuardados($modulo);
        if ($data === null || (int) ($data['idNivel'] ?? 0) !== (int) $ctx->idNivel) {
            return null;
        }

        $idTurno = (int) ($data['idTurno'] ?? 0);
        $idTerlec = (int) ($data['idTerlec'] ?? 0);
        if ($idTurno <= 0 || $idTerlec <= 0) {
            return null;
        }

        return ['idTurno' => $idTurno, 'idTerlec' => $idTerlec];
    }

    public static function valoresParaPrecargar(SchoolContext $ctx, string $modulo): ?array
    {
        if (! $ctx->isValid()) {
            return null;
        }

        $data = self::datosGuardados($modulo);
        if ($data === null || (int) ($data['idNivel'] ?? 0) !== (int) $ctx->idNivel) {
            foreach (self::modulosFallbackPrecarga($modulo) as $otro) {
                $data = self::datosGuardados($otro);
                if ($data !== null && (int) ($data['idNivel'] ?? 0) === (int) $ctx->idNivel) {
                    break;
                }
                $data = null;
            }
        }

        if ($data === null || (int) ($data['idNivel'] ?? 0) !== (int) $ctx->idNivel) {
            return null;
        }

        $idTurno = (int) ($data['idTurno'] ?? 0);
        $idTerlec = (int) ($data['idTerlec'] ?? 0);
        if ($idTurno <= 0 || $idTerlec <= 0) {
            return null;
        }

        return ['idTurno' => $idTurno, 'idTerlec' => $idTerlec];
    }

    /**
     * @return list<object{id:int, turno:?string, nturno:string}>
     */
    public static function turnosDisponibles(): array
    {
        return DB::table('turnos')
            ->orderBy('id')
            ->get(['id', 'turno', 'nturno'])
            ->all();
    }

    /**
     * @return list<object{id:int, ano:int}>
     */
    public static function ciclosLectivosDisponibles(): array
    {
        return Terlec::paraSelector()->all();
    }

    public static function etiquetaTurno(int $idTurno): string
    {
        $row = DB::table('turnos')->where('id', $idTurno)->first(['turno', 'nturno']);
        if ($row === null) {
            return 'Turno #'.$idTurno;
        }

        $nombre = trim((string) ($row->turno ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) ($row->nturno ?? ''));
        }

        return $nombre !== '' ? $nombre : 'Turno #'.$idTurno;
    }

    public static function anoTerlec(int $idTerlec): ?int
    {
        $ano = DB::table('terlec')->where('id', $idTerlec)->value('ano');

        return $ano !== null ? (int) $ano : null;
    }

    /**
     * @return list<string>
     */
    private static function modulosFallbackPrecarga(string $modulo): array
    {
        return match ($modulo) {
            self::MODULO_GESTION => [self::MODULO_LISTADO],
            self::MODULO_ACTA_VOLANTE => [self::MODULO_LISTADO, self::MODULO_GESTION],
            self::MODULO_PERMISO_EXAMEN => [self::MODULO_LISTADO, self::MODULO_GESTION, self::MODULO_ACTA_VOLANTE],
            default => [self::MODULO_GESTION],
        };
    }

    private static function datosKey(string $modulo): string
    {
        return match ($modulo) {
            self::MODULO_GESTION => self::SESSION_GESTION,
            self::MODULO_ACTA_VOLANTE => self::SESSION_ACTA_VOLANTE,
            self::MODULO_PERMISO_EXAMEN => self::SESSION_PERMISO_EXAMEN,
            default => self::SESSION_LISTADO,
        };
    }

    private static function visitOkKey(string $modulo): string
    {
        return match ($modulo) {
            self::MODULO_GESTION => self::SESSION_VISIT_OK_GESTION,
            self::MODULO_ACTA_VOLANTE => self::SESSION_VISIT_OK_ACTA_VOLANTE,
            self::MODULO_PERMISO_EXAMEN => self::SESSION_VISIT_OK_PERMISO_EXAMEN,
            default => self::SESSION_VISIT_OK_LISTADO,
        };
    }

    private static function solicitarPrepKey(string $modulo): string
    {
        return match ($modulo) {
            self::MODULO_GESTION => self::SESSION_SOLICITAR_PREP_GESTION,
            self::MODULO_ACTA_VOLANTE => self::SESSION_SOLICITAR_PREP_ACTA_VOLANTE,
            self::MODULO_PERMISO_EXAMEN => self::SESSION_SOLICITAR_PREP_PERMISO_EXAMEN,
            default => self::SESSION_SOLICITAR_PREP_LISTADO,
        };
    }

    private static function recalculoOkKey(string $modulo): string
    {
        return match ($modulo) {
            self::MODULO_GESTION => self::SESSION_RECALCULO_OK_GESTION,
            self::MODULO_ACTA_VOLANTE => self::SESSION_RECALCULO_OK_ACTA_VOLANTE,
            self::MODULO_PERMISO_EXAMEN => self::SESSION_RECALCULO_OK_PERMISO_EXAMEN,
            default => self::SESSION_RECALCULO_OK_LISTADO,
        };
    }

    /**
     * @return array{idNivel:int, idTurno:int, idTerlec:int}|null
     */
    private static function datosGuardados(string $modulo): ?array
    {
        $data = session(self::datosKey($modulo));
        if (is_array($data) && isset($data['idTurno'], $data['idTerlec'])) {
            return $data;
        }

        if ($modulo === self::MODULO_GESTION) {
            $otro = session(self::SESSION_LISTADO);
            if (is_array($otro) && isset($otro['idTurno'], $otro['idTerlec'])) {
                return $otro;
            }

            return null;
        }

        $legacy = session('examenes.materias_adeudadas_preparacion');
        if (is_array($legacy) && isset($legacy['idTurno'], $legacy['idTerlec'])) {
            return $legacy;
        }

        return null;
    }
}
