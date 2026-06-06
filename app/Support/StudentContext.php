<?php

namespace App\Support;

use App\Models\Legajo;
use App\Models\Nivel;
use App\Models\Terlec;

class StudentContext
{
    public ?int $idLegajo = null;
    public ?int $idNivel  = null;
    public ?int $idTerlec = null;

    private ?Legajo $_legajo = null;
    private ?Nivel  $_nivel  = null;
    private ?Terlec $_terlec = null;

    public static function fromSession(): static
    {
        $ctx = new static();
        $ctx->idLegajo = session('student.idLegajo');
        $ctx->idNivel  = session('student.idNivel');
        $ctx->idTerlec = session('student.idTerlec');
        return $ctx;
    }

    public static function set(int $idLegajo, int $idNivel, int $idTerlec): void
    {
        session([
            'student.idLegajo' => $idLegajo,
            'student.idNivel'  => $idNivel,
            'student.idTerlec' => $idTerlec,
        ]);
    }

    public static function clear(): void
    {
        session()->forget(['student.idLegajo', 'student.idNivel', 'student.idTerlec']);
    }

    public function isValid(): bool
    {
        return $this->idLegajo !== null
            && $this->idNivel  !== null
            && $this->idTerlec !== null;
    }

    public function alumno(): ?Legajo
    {
        if ($this->_legajo === null && $this->idLegajo) {
            $this->_legajo = Legajo::find($this->idLegajo);
        }
        return $this->_legajo;
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

