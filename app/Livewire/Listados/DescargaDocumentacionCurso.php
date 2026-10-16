<?php

namespace App\Livewire\Listados;

use App\Models\Curso;
use App\Support\Alumnos\DescargaDocumentacionCurso as DescargaDocumentacion;
use App\Support\Alumnos\DocumentosEstudianteAutogestion;
use App\Support\Listados\ListadoCursoConsulta;
use App\Support\PermisosIaCatalog;
use App\Support\SchoolAlcancePedagogico;
use App\Support\Security\OpaqueRouteToken;
use Livewire\Component;

/**
 * Elige un curso del ciclo activo y descarga la documentación existente en un ZIP.
 */
class DescargaDocumentacionCurso extends Component
{
    public ?int $cursoId = null;

    public function mount(): void
    {
        abort_unless(tienePermiso(PermisosIaCatalog::DESCARGA_DOCUMENTACION_CURSO), 403);

        $ctx = schoolCtx();
        if ((int) $ctx->idNivel < 1 || (int) $ctx->idTerlec < 1) {
            abort(403, 'Seleccione nivel y ciclo lectivo en el contexto activo.');
        }
    }

    public function updatedCursoId(mixed $value): void
    {
        $id = (int) $value;
        $this->cursoId = $this->cursoPermitido($id) ? $id : null;
    }

    public function render()
    {
        $cursos = ListadoCursoConsulta::cursosPermitidosEnContexto();
        $conNivel = SchoolAlcancePedagogico::idNivelFiltroUnico() === null;
        $id = (int) ($this->cursoId ?? 0);
        $hayTipos = DocumentosEstudianteAutogestion::habilitadoConTipos();
        $resumen = ($id > 0 && $hayTipos) ? DescargaDocumentacion::resumen($id) : null;
        $urlZip = null;
        if ($resumen !== null && $resumen['total_archivos'] > 0) {
            $urlZip = route('listados.documentacion-curso.zip', [
                'ref' => OpaqueRouteToken::forDescargaDocumentacionCurso($id),
            ]);
        }

        return view('listados::livewire.listados.descarga-documentacion-curso', [
            'cursos' => $cursos,
            'conNivel' => $conNivel,
            'resumen' => $resumen,
            'urlZip' => $urlZip,
            'hayTipos' => $hayTipos,
        ])->layout(layoutMenuStaff(), [
            'pageTitle' => 'Descarga de documentación',
        ]);
    }

    private function cursoPermitido(int $idCurso): bool
    {
        if ($idCurso < 1) {
            return false;
        }

        return ListadoCursoConsulta::cursosPermitidosEnContexto()
            ->contains(static fn (Curso $curso): bool => (int) $curso->Id === $idCurso);
    }
}
