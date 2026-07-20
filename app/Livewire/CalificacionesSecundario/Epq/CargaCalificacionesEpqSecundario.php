<?php

namespace App\Livewire\CalificacionesSecundario\Epq;

use App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos;
use App\Support\CalificacionesSecundario\Epq\CalificacionesEpqSecundarioCatalogo;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\PermisosIaCatalog;
use App\Support\PortalDocente\PortalDocenteContext;
use App\Livewire\CalificacionesSecundario\CargaCalificacionesSecundario;
use Illuminate\Support\Facades\DB;

/**
 * EPQ — carga de calificaciones secundario (planilla cuatrimestral).
 *
 * Sin promedio automático; columnas ic07..ic34 + dic/feb según CalificacionesEpqSecundarioCatalogo.
 */
class CargaCalificacionesEpqSecundario extends CargaCalificacionesSecundario
{
    public function mount(?int $curso = null, ?int $materia = null): void
    {
        CalificacionesSecundarioModulos::abortSiImplementacionInactiva(
            CalificacionesSecundarioModulos::CARGA,
            CalificacionesEpqSecundarioCatalogo::IMPLEMENTACION,
        );

        parent::mount($curso, $materia);
    }

    /** @return list<string> */
    protected function columnasCalificacionSoloTabla(): array
    {
        return array_merge(['ord'], CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA);
    }

    protected function mergeCalificacionDbAFila(int $id, object $r): void
    {
        if (! isset($this->rows[$id])) {
            return;
        }

        // `ord` en UI es correlativo 1..n (asignado en fetchRowsSnapshot); no pisar con BD.
        foreach (CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA as $campo) {
            $this->rows[$id][$campo] = (string) ($r->{$campo} ?? '');
        }
    }

    protected function fetchRowsSnapshot(): array
    {
        $this->ensureScopeOr404();

        $ctx = schoolCtx();
        $campos = CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA;
        $idsCondicionesRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );
        $select = array_merge(
            ['c.id', 'c.ord', 'l.apellido', 'l.nombre'],
            array_map(fn (string $c) => 'c.'.$c, $campos),
        );

        $califs = DB::table('calificaciones as c')
            ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->join('matricula as m', 'm.id', '=', 'c.idMatricula')
            ->where('c.idTerlec', (int) $ctx->idTerlec)
            ->where('c.idCursos', (int) $this->cursoId)
            ->where('c.idMaterias', (int) $this->materiaId)
            ->whereIn('m.idCondiciones', $idsCondicionesRegulares)
            ->orderByRaw('COALESCE(c.ord, 9999) asc')
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->get($select);

        $out = [];
        $nro = 1;
        foreach ($califs as $r) {
            $id = (int) $r->id;
            $row = [
                'id' => $id,
                'ord' => $nro++,
                'alumno' => trim(((string) $r->apellido).', '.((string) $r->nombre)),
            ];
            foreach ($campos as $campo) {
                $row[$campo] = (string) ($r->{$campo} ?? '');
            }
            $out[$id] = $row;
        }

        return $out;
    }

    protected function campoSujetoANotasPermitidas(string $field): bool
    {
        return in_array($field, CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA, true);
    }

    /** @return list<string> */
    protected function editableFields(): array
    {
        return CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA;
    }

    protected function debeRecalcularPromedioAnual(string $field): bool
    {
        return false;
    }

    protected function syncPromedioAnual(int $id): void
    {
        // EPQ: sin promedio automático.
    }

    public function saveCell(int $id, string $field, mixed $value): void
    {
        if ($this->modoPortalDocente) {
            if ($this->cargaNotasOffBloqueaEdicion()) {
                return;
            }
        } else {
            PortalDocenteContext::abortSiStaffSinPermisoIa(PermisosIaCatalog::CALIF_CARGA);
        }

        $field = trim($field);
        if ($field === 'tea' || $field === 'calif') {
            abort(400);
        }

        parent::saveCell($id, $field, $value);
    }

    public function render()
    {
        $cursos = $this->cursos();
        $materias = $this->materias();

        $cursoLabel = $this->cursoId
            ? optional($cursos->firstWhere('Id', (int) $this->cursoId))->cursec
            : null;

        $materiaLabel = $this->materiaId
            ? optional($materias->firstWhere('id', (int) $this->materiaId))->materia
            : null;

        $notasPermitidasLista = $this->notasPermitidasLista;
        $notasPermitidasActiva = $this->notasPermitidasActiva();

        $modoPortalDocente = $this->modoPortalDocente;
        $pdfUrl = null;
        $urlLista = null;

        if ($this->cursoId && $this->materiaId) {
            if ($this->modoPortalDocente) {
                $pdfUrl = route(CalificacionesSecundarioModulos::rutaPortal(CalificacionesSecundarioModulos::CARGA, 'pdf'), [
                    'curso' => $this->cursoId,
                    'materia' => $this->materiaId,
                ]);
                $urlLista = route(CalificacionesSecundarioModulos::rutaPortal(CalificacionesSecundarioModulos::CARGA));
            } else {
                $pdfUrl = route(CalificacionesSecundarioModulos::rutaStaff(CalificacionesSecundarioModulos::CARGA, 'pdf'), [
                    'curso' => $this->cursoId,
                    'materia' => $this->materiaId,
                ]);
            }
        }

        $viewData = array_merge(
            compact(
                'cursos',
                'materias',
                'cursoLabel',
                'materiaLabel',
                'notasPermitidasLista',
                'notasPermitidasActiva',
                'modoPortalDocente',
                'pdfUrl',
                'urlLista',
            ),
            $this->datosVistaAvisoCargaNotasOff($this->modoPortalDocente),
        );

        $layout = $this->modoPortalDocente ? 'layouts.docente' : 'layouts.app';
        $pageTitle = $this->modoPortalDocente
            ? 'Calificaciones'
            : 'Carga de calificaciones (EPQ · secundario)';

        return view('livewire.calificaciones-secundario.epq.carga-calificaciones-epq-secundario', $viewData)
            ->layout($layout, ['pageTitle' => $pageTitle]);
    }
}
