<?php

namespace App\Livewire\EmailsMasivos\Concerns;

use App\Support\EmailsMasivos\DestinatariosEmailsMasivos;

trait SeleccionDestinatariosCorreoMasivo
{
    /** alumnos | cursos */
    public string $tipoDestino = 'cursos';

    /** multiple | prioridad */
    public string $modoContacto = 'multiple';

    public bool $incluirMadre = true;

    public bool $incluirPadre = true;

    public bool $incluirTutor = true;

    /** @var list<array{id:int,label:string}> */
    public array $alumnosSeleccionados = [];

    /** @var list<array{id:int,label:string,idNivel?:int}> */
    public array $cursosSeleccionados = [];

    /**
     * @var list<array{key:string,idLegajo:int,idCurso:int,idNivel:int,label:string,cursoLabel:string,marcado:bool}>
     */
    public array $lineasAlumnos = [];

    public bool $modalAlumnosAbierto = false;

    public string $modalAlumnosFiltro = '';

    /** @var list<array{id:int,label:string,dni:?string,idCurso:int,idNivel:int}> */
    public array $modalAlumnosLista = [];

    /** @var list<int> */
    public array $modalAlumnosMarcados = [];

    public bool $modalCursosAbierto = false;

    public string $modalCursosFiltro = '';

    /** @var list<array{id:int,label:string,idNivel:int}> */
    public array $modalCursosLista = [];

    /** @var list<int> */
    public array $modalCursosMarcados = [];

    public function updatedModalAlumnosFiltro(): void
    {
        if ($this->modalAlumnosAbierto) {
            $this->recargarModalAlumnosLista();
        }
    }

    public function updatedModalCursosFiltro(): void
    {
        if ($this->modalCursosAbierto) {
            $this->recargarModalCursosLista();
        }
    }

    public function updatedTipoDestino(): void
    {
        $this->lineasAlumnos = [];
        $this->alumnosSeleccionados = [];
        $this->cursosSeleccionados = [];
    }

    public function abrirModalAlumnos(): void
    {
        $this->modalAlumnosAbierto = true;
        $this->modalAlumnosFiltro = '';
        $this->modalAlumnosMarcados = array_map(fn ($a) => (int) $a['id'], $this->alumnosSeleccionados);
        $this->recargarModalAlumnosLista();
    }

    public function cerrarModalAlumnos(): void
    {
        $this->modalAlumnosAbierto = false;
    }

    public function aplicarModalAlumnos(): void
    {
        $ctx = schoolCtx();
        $labelsPorId = collect($this->modalAlumnosLista)->keyBy('id');
        $prev = collect($this->alumnosSeleccionados)->keyBy('id');
        $out = [];

        foreach (array_unique(array_map('intval', $this->modalAlumnosMarcados)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $fromLista = $labelsPorId->get($id);
            if ($fromLista !== null) {
                $out[] = ['id' => $id, 'label' => (string) $fromLista['label']];
            } elseif ($prev->has($id)) {
                $out[] = ['id' => $id, 'label' => (string) $prev->get($id)['label']];
            }
        }

        $this->alumnosSeleccionados = $out;
        $this->modalAlumnosAbierto = false;
        $this->reconstruirLineasDesdeAlumnos((int) $ctx->idTerlec);
    }

    public function abrirModalCursos(): void
    {
        $this->modalCursosAbierto = true;
        $this->modalCursosFiltro = '';
        $this->modalCursosMarcados = array_map(fn ($c) => (int) $c['id'], $this->cursosSeleccionados);
        $this->recargarModalCursosLista();
    }

    public function cerrarModalCursos(): void
    {
        $this->modalCursosAbierto = false;
    }

    public function aplicarModalCursos(): void
    {
        $labelsPorId = collect($this->modalCursosLista)->keyBy('id');
        $prev = collect($this->cursosSeleccionados)->keyBy('id');
        $out = [];

        foreach (array_unique(array_map('intval', $this->modalCursosMarcados)) as $id) {
            if ($id <= 0) {
                continue;
            }
            $fromLista = $labelsPorId->get($id);
            if ($fromLista !== null) {
                $out[] = [
                    'id' => $id,
                    'label' => (string) $fromLista['label'],
                    'idNivel' => (int) ($fromLista['idNivel'] ?? 0),
                ];
            } elseif ($prev->has($id)) {
                $prevRow = $prev->get($id);
                $out[] = [
                    'id' => $id,
                    'label' => (string) $prevRow['label'],
                    'idNivel' => (int) ($prevRow['idNivel'] ?? 0),
                ];
            }
        }

        $this->cursosSeleccionados = $out;
        $this->modalCursosAbierto = false;

        $ctx = schoolCtx();
        $this->reconstruirLineasDesdeCursos((int) $ctx->idTerlec);
    }

    public function modalCursosSeleccionarTodos(): void
    {
        $this->modalCursosMarcados = array_map(fn ($c) => (int) $c['id'], $this->modalCursosLista);
    }

    public function toggleLineaAlumno(string $key): void
    {
        $lineas = $this->lineasAlumnos;
        foreach ($lineas as $i => $linea) {
            if (($linea['key'] ?? '') === $key) {
                $lineas[$i]['marcado'] = ! (bool) ($linea['marcado'] ?? false);
                break;
            }
        }
        $this->lineasAlumnos = $lineas;
    }

    public function marcarTodosLineasCurso(int $idCurso, bool $marcado): void
    {
        $lineas = $this->lineasAlumnos;
        foreach ($lineas as $i => $linea) {
            if ((int) ($linea['idCurso'] ?? 0) === $idCurso) {
                $lineas[$i]['marcado'] = $marcado;
            }
        }
        $this->lineasAlumnos = $lineas;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function calcularDestinatariosEnvio(): array
    {
        return DestinatariosEmailsMasivos::resolverDestinatariosEnvio(
            $this->lineasAlumnosMarcadas(),
            $this->modoContacto,
            $this->incluirMadre,
            $this->incluirPadre,
            $this->incluirTutor,
        );
    }

    protected function validarSeleccionDestinatarios(): void
    {
        if ($this->lineasAlumnosMarcadas() === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lineasAlumnos' => 'Seleccione al menos un alumno matriculado regular.',
            ]);
        }

        if ($this->modoContacto === 'multiple' && ! $this->incluirMadre && ! $this->incluirPadre && ! $this->incluirTutor) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'modoContacto' => 'Seleccione al menos un tipo de contacto (madre, padre o tutor).',
            ]);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    protected function lineasAlumnosMarcadas(): array
    {
        return array_values(array_filter(
            $this->lineasAlumnos,
            fn (array $l) => ! empty($l['marcado']),
        ));
    }

    protected function reconstruirLineasDesdeAlumnos(int $idTerlec): void
    {
        $lineas = [];
        foreach ($this->alumnosSeleccionados as $alumno) {
            $idLegajo = (int) $alumno['id'];
            $mat = DestinatariosEmailsMasivos::matriculaRegularDeLegajo($idTerlec, $idLegajo);
            if ($mat === null) {
                continue;
            }
            $lineas[] = [
                'key' => 'a-' . $idLegajo,
                'idLegajo' => $idLegajo,
                'idCurso' => (int) $mat['idCurso'],
                'idNivel' => (int) $mat['idNivel'],
                'label' => (string) $alumno['label'],
                'cursoLabel' => '',
                'marcado' => true,
            ];
        }
        $this->lineasAlumnos = $lineas;
    }

    protected function reconstruirLineasDesdeCursos(int $idTerlec): void
    {
        $lineas = [];
        foreach ($this->cursosSeleccionados as $curso) {
            $idCurso = (int) $curso['id'];
            $idNivelCurso = (int) ($curso['idNivel'] ?? 0);
            $alumnos = DestinatariosEmailsMasivos::alumnosRegularesPorCurso($idTerlec, $idCurso);
            foreach ($alumnos as $a) {
                $lineas[] = [
                    'key' => $idCurso . '-' . $a['id'],
                    'idLegajo' => (int) $a['id'],
                    'idCurso' => $idCurso,
                    'idNivel' => (int) ($a['idNivel'] ?: $idNivelCurso),
                    'label' => (string) $a['label'],
                    'cursoLabel' => (string) $curso['label'],
                    'marcado' => true,
                ];
            }
        }
        $this->lineasAlumnos = $lineas;
    }

    protected function recargarModalAlumnosLista(): void
    {
        $ctx = schoolCtx();
        if (! $ctx->idTerlec) {
            $this->modalAlumnosLista = [];

            return;
        }

        $t = trim($this->modalAlumnosFiltro);
        if ($t === '') {
            $this->modalAlumnosLista = [];

            return;
        }

        $this->modalAlumnosLista = DestinatariosEmailsMasivos::buscarAlumnosRegulares(
            (int) $ctx->idTerlec,
            $t,
            80,
        );
    }

    protected function recargarModalCursosLista(): void
    {
        $ctx = schoolCtx();
        if (! $ctx->idTerlec) {
            $this->modalCursosLista = [];

            return;
        }

        $all = DestinatariosEmailsMasivos::cursosDelContexto((int) $ctx->idTerlec);
        $f = mb_strtolower(trim($this->modalCursosFiltro));
        if ($f !== '') {
            $all = array_values(array_filter(
                $all,
                fn (array $c) => str_contains(mb_strtolower((string) ($c['label'] ?? '')), $f),
            ));
        }
        $this->modalCursosLista = $all;
    }
}
