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
     *
     * Si el alumno ya tiene matrícula del ciclo siguiente en otro nivel (sexto → 1.º
     * de secundario, sala de 5 → 1.º de primario), el contexto queda en la matrícula
     * del ciclo que la institución abrió para autogestión, no en la más nueva.
     * Si solo tiene una matrícula posterior (ingreso nuevo), el contexto queda en esa.
     */
    public static function establecerDesdeLegajo(Legajo $alumno): bool
    {
        $idLegajo = (int) $alumno->id;
        if ($idLegajo < 1) {
            return false;
        }

        $elegida = self::resolverMatriculaCicloAutogestion($alumno);
        if ($elegida !== null) {
            self::set(
                idLegajo: $idLegajo,
                idNivel: $elegida['idNivel'],
                idTerlec: $elegida['idTerlec'],
            );

            return true;
        }

        $idNivel = (int) ($alumno->idnivel ?? 0);
        if ($idNivel <= 0) {
            $idNivel = (int) (Matricula::query()
                ->where('idLegajos', $idLegajo)
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
            idLegajo: $idLegajo,
            idNivel: $idNivel,
            idTerlec: $idTerlec,
        );

        return true;
    }

    /**
     * Matrícula cuyo par (nivel, ciclo) coincide con `ento.idTerlecVerNotas` de ese nivel.
     *
     * @return array{id: int, idNivel: int, idTerlec: int, ano: int}|null
     */
    private static function resolverMatriculaCicloAutogestion(Legajo $alumno): ?array
    {
        $autorizados = [];
        foreach (Ento::query()->get(['idNivel', 'idTerlecVerNotas']) as $ento) {
            $idNivel = (int) $ento->idNivel;
            $idTerlec = (int) $ento->idTerlecVerNotas;
            if ($idNivel > 0 && $idTerlec > 0) {
                $autorizados[$idNivel] = $idTerlec;
            }
        }

        if ($autorizados === []) {
            return null;
        }

        $matriculas = Matricula::query()
            ->where('idLegajos', (int) $alumno->id)
            ->get(['id', 'idNivel', 'idTerlec']);

        if ($matriculas->isEmpty()) {
            return null;
        }

        $idsTerlec = $matriculas
            ->pluck('idTerlec')
            ->map(fn ($id) => (int) $id)
            ->merge(array_values($autorizados))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $anos = $idsTerlec === []
            ? collect()
            : Terlec::query()->whereIn('id', $idsTerlec)->pluck('ano', 'id');

        $anoAbiertoPorNivel = [];
        foreach ($autorizados as $idNivel => $idTerlec) {
            $ano = (int) ($anos[$idTerlec] ?? 0);
            if ($ano > 0) {
                $anoAbiertoPorNivel[(int) $idNivel] = $ano;
            }
        }

        $filas = [];
        foreach ($matriculas as $matricula) {
            $idTerlec = (int) $matricula->idTerlec;
            $filas[] = [
                'id' => (int) $matricula->id,
                'idNivel' => (int) $matricula->idNivel,
                'idTerlec' => $idTerlec,
                'ano' => (int) ($anos[$idTerlec] ?? 0),
            ];
        }

        $elegida = self::elegirMatriculaCicloAutogestion(
            (int) ($alumno->idnivel ?? 0),
            $filas,
            $autorizados,
            $anoAbiertoPorNivel,
        );
        if ($elegida === null || ! $anos->has($elegida['idTerlec'])) {
            return null;
        }

        return $elegida;
    }

    /**
     * Prioridad:
     * 1. Matrícula del ciclo abierto (`ento.idTerlecVerNotas` de su nivel). Si además
     *    hay preinscripción posterior, no la desplaza.
     * 2. Si no hay ninguna en el ciclo abierto, la matrícula posterior más próxima
     *    (ingreso nuevo que solo tiene el ciclo siguiente: actualizar datos y ficha).
     * Las matrículas de ciclos anteriores no habilitan el ingreso.
     *
     * @param  list<array{id: int, idNivel: int, idTerlec: int, ano: int}>  $matriculas
     * @param  array<int, int>  $idTerlecVerNotasPorNivel
     * @param  array<int, int>  $anoAbiertoPorNivel
     * @return array{id: int, idNivel: int, idTerlec: int, ano: int}|null
     */
    public static function elegirMatriculaCicloAutogestion(
        int $idNivelLegajo,
        array $matriculas,
        array $idTerlecVerNotasPorNivel,
        array $anoAbiertoPorNivel = [],
    ): ?array {
        $delCicloAbierto = [];
        $posteriores = [];

        foreach ($matriculas as $matricula) {
            $fila = self::filaMatriculaAutogestion($matricula);
            if ($fila === null) {
                continue;
            }

            $autorizado = (int) ($idTerlecVerNotasPorNivel[$fila['idNivel']] ?? 0);
            if ($autorizado > 0 && $autorizado === $fila['idTerlec']) {
                $delCicloAbierto[] = $fila;

                continue;
            }

            $anoAbierto = (int) ($anoAbiertoPorNivel[$fila['idNivel']] ?? 0);
            if ($anoAbierto > 0 && $fila['ano'] > $anoAbierto) {
                $posteriores[] = $fila;
            }
        }

        $elegibles = $delCicloAbierto !== [] ? $delCicloAbierto : $posteriores;
        if ($elegibles === []) {
            return null;
        }

        usort($elegibles, function (array $a, array $b) use ($idNivelLegajo): int {
            $anoA = $a['ano'] > 0 ? $a['ano'] : PHP_INT_MAX;
            $anoB = $b['ano'] > 0 ? $b['ano'] : PHP_INT_MAX;
            if ($anoA !== $anoB) {
                return $anoA <=> $anoB;
            }

            $prefA = $idNivelLegajo > 0 && $a['idNivel'] === $idNivelLegajo ? 0 : 1;
            $prefB = $idNivelLegajo > 0 && $b['idNivel'] === $idNivelLegajo ? 0 : 1;
            if ($prefA !== $prefB) {
                return $prefA <=> $prefB;
            }

            return $b['id'] <=> $a['id'];
        });

        return $elegibles[0];
    }

    /**
     * @param  array{id?: int, idNivel?: int, idTerlec?: int, ano?: int}  $matricula
     * @return array{id: int, idNivel: int, idTerlec: int, ano: int}|null
     */
    private static function filaMatriculaAutogestion(array $matricula): ?array
    {
        $idNivel = (int) ($matricula['idNivel'] ?? 0);
        $idTerlec = (int) ($matricula['idTerlec'] ?? 0);
        if ($idNivel < 1 || $idTerlec < 1) {
            return null;
        }

        return [
            'id' => (int) ($matricula['id'] ?? 0),
            'idNivel' => $idNivel,
            'idTerlec' => $idTerlec,
            'ano' => (int) ($matricula['ano'] ?? 0),
        ];
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

