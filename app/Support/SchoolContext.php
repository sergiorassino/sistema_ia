<?php

namespace App\Support;

use App\Models\Nivel;
use App\Models\Profesor;
use App\Models\Terlec;

class SchoolContext
{
    public ?int $idProfesor = null;

    /** Nivel de identidad (login / `profesores.nivel` / fila `ento`). */
    public ?int $idNivel = null;

    public ?int $idTerlec = null;

    private ?Profesor $_profesor = null;

    private ?Nivel $_nivel = null;

    private ?Terlec $_terlec = null;

    public static function fromSession(): static
    {
        $ctx = new static();
        $ctx->idProfesor = session('school.idProfesor');
        $ctx->idNivel = session('school.idNivel');
        $ctx->idTerlec = session('school.idTerlec');

        return $ctx;
    }

    public static function set(int $idProfesor, int $idNivel, int $idTerlec): void
    {
        session()->forget('school.idNivelTrabajo');

        session([
            'school.idProfesor' => $idProfesor,
            'school.idNivel' => $idNivel,
            'school.idTerlec' => $idTerlec,
        ]);
    }

    public static function clear(): void
    {
        session()->forget([
            'school.idProfesor',
            'school.idNivel',
            'school.idTerlec',
            'school.idNivelTrabajo',
        ]);
    }

    public function isValid(): bool
    {
        return $this->idProfesor !== null
            && $this->idNivel !== null
            && $this->idTerlec !== null;
    }

    public function esAdministracion(): bool
    {
        return NivelSistema::esAdministracion((int) ($this->idNivel ?? 0));
    }

    /**
     * @deprecated Usar {@see SchoolAlcancePedagogico::idNivelFiltroUnico()}.
     */
    public function idNivelPedagogico(): int
    {
        return (int) (SchoolAlcancePedagogico::idNivelFiltroUnico() ?? 0);
    }

    public function profesor(): ?Profesor
    {
        if ($this->_profesor === null && $this->idProfesor) {
            $this->_profesor = Profesor::find($this->idProfesor);
        }

        return $this->_profesor;
    }

    public function refreshProfesor(): void
    {
        $this->_profesor = null;
        $this->profesor();
    }

    public function nivel(): ?Nivel
    {
        if ($this->_nivel === null && $this->idNivel) {
            $this->_nivel = Nivel::find($this->idNivel);
        }

        return $this->_nivel;
    }

    public function terlec(): ?Terlec
    {
        if ($this->_terlec === null && $this->idTerlec) {
            $this->_terlec = Terlec::find($this->idTerlec);
        }

        return $this->_terlec;
    }

    public function nivelNombre(): string
    {
        return $this->nivel()?->nivel ?? '';
    }

    public function terlecAno(): ?int
    {
        return $this->terlec()?->ano;
    }
}
