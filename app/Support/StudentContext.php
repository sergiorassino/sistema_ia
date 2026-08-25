<?php

namespace App\Support;

use App\Models\Ento;
use App\Models\Legajo;
use App\Models\Matricula;
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
        $ctx->idLegajo = self::idPositivo(session('student.idLegajo'));
        $ctx->idNivel  = self::idPositivo(session('student.idNivel'));
        $ctx->idTerlec = self::idPositivo(session('student.idTerlec'));
        return $ctx;
    }

    public static function set(int $idLegajo, int $idNivel, int $idTerlec): void
    {
        session([
            'student.idLegajo' => $idLegajo,
            'student.idNivel'  => $idNivel,
            'student.idTerlec' => $idTerlec,
        ]);
        self::olvidarInstanciaResuelta();
    }

    public static function clear(): void
    {
        session()->forget(['student.idLegajo', 'student.idNivel', 'student.idTerlec']);
        self::olvidarInstanciaResuelta();
    }

    /**
     * Completa nivel y ciclo (ento.idTerlecVerNotas) a partir del legajo autenticado.
     */
    public static function establecerDesdeLegajo(Legajo $alumno): bool
    {
        $idNivel = (int) ($alumno->idnivel ?? 0);
        if ($idNivel <= 0) {
            $idNivel = (int) (Matricula::query()
                ->where('idLegajos', (int) $alumno->id)
                ->orderByDesc('idTerlec')
                ->orderByDesc('id')
                ->value('idNivel') ?? 0);
        }

        $idTerlec = (int) (Ento::query()
            ->where('idNivel', $idNivel)
            ->value('idTerlecVerNotas') ?? 0);

        if ($idNivel <= 0 || $idTerlec <= 0 || ! Terlec::query()->whereKey($idTerlec)->exists()) {
            return false;
        }

        self::set(
            idLegajo: (int) $alumno->id,
            idNivel: $idNivel,
            idTerlec: $idTerlec,
        );

        return true;
    }

    public static function olvidarInstanciaResuelta(): void
    {
        if (app()->resolved(static::class)) {
            app()->forgetInstance(static::class);
        }
    }

    private static function idPositivo(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
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

