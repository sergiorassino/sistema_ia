<?php

namespace App\Support\Aulica;

/**
 * Resultado de consultar deuda del estudiante y del grupo familiar en Áulica.
 */
final class AulicaDeudaResultado
{
    /**
     * @param  list<AulicaSaldoPersona>  $estudiante
     * @param  list<AulicaSaldoPersona>  $grupoFamiliar
     */
    public function __construct(
        public readonly string $dniEstudiante,
        public readonly string $dniResponsable,
        public readonly array $estudiante,
        public readonly array $grupoFamiliar,
        public readonly bool $consultaOk,
        public readonly string $error = '',
    ) {}

    public static function deshabilitado(): self
    {
        return new self('', '', [], [], true);
    }

    public static function error(string $mensaje, string $dniEstudiante = '', string $dniResponsable = ''): self
    {
        return new self($dniEstudiante, $dniResponsable, [], [], false, $mensaje);
    }

    public function tieneDeuda(): bool
    {
        return $this->saldoEstudiante() > 0.009 || $this->saldoGrupoFamiliar() > 0.009;
    }

    public function saldoEstudiante(): float
    {
        return $this->sumaSaldos($this->estudiante);
    }

    public function saldoGrupoFamiliar(): float
    {
        return $this->sumaSaldos($this->grupoFamiliar);
    }

    /**
     * @return list<AulicaSaldoPersona>
     */
    public function hermanosConDeuda(): array
    {
        $dniAlumno = $this->dniEstudiante;

        return array_values(array_filter(
            $this->grupoFamiliar,
            function (AulicaSaldoPersona $p) use ($dniAlumno): bool {
                if (! $p->tieneDeuda()) {
                    return false;
                }

                $dni = AulicaDni::normalizar($p->nroDoc);

                return $dni === null || $dni !== $dniAlumno;
            },
        ));
    }

    public function mensajeVisible(): string
    {
        if (! $this->consultaOk && $this->error !== '') {
            return $this->error;
        }

        if (! $this->tieneDeuda()) {
            return '';
        }

        $lineas = [];

        $saldoAlumno = $this->saldoEstudiante();
        if ($saldoAlumno > 0.009) {
            $lineas[] = 'El estudiante registra deuda en Áulica: '.$this->formatear($saldoAlumno).'.';
        } else {
            $lineas[] = 'El estudiante no registra deuda propia en Áulica.';
        }

        $saldoFamilia = $this->saldoGrupoFamiliar();
        if ($saldoFamilia > 0.009) {
            $responsable = $this->dniResponsable !== ''
                ? ' (responsable DNI '.$this->dniResponsable.')'
                : '';
            $lineas[] = 'El grupo familiar'.$responsable.' registra deuda: '.$this->formatear($saldoFamilia).'.';
            foreach ($this->grupoFamiliar as $persona) {
                if ($persona->tieneDeuda()) {
                    $lineas[] = '• '.$persona->etiquetaListado();
                }
            }
        }

        $lineas[] = 'Regularice la situación en el portal de aranceles o comuníquese con administración.';

        return implode("\n", $lineas);
    }

    public function etiquetaCorta(): string
    {
        if (! $this->consultaOk) {
            return 'Sin consulta';
        }

        if (! $this->tieneDeuda()) {
            return 'Sin deuda';
        }

        $partes = [];
        if ($this->saldoEstudiante() > 0.009) {
            $partes[] = 'Alumno '.$this->formatear($this->saldoEstudiante());
        }
        $hermanos = $this->hermanosConDeuda();
        if ($hermanos !== []) {
            $partes[] = 'Familia '.$this->formatear($this->saldoGrupoFamiliar());
        } elseif ($this->saldoGrupoFamiliar() > 0.009 && $this->saldoEstudiante() <= 0.009) {
            $partes[] = 'Familia '.$this->formatear($this->saldoGrupoFamiliar());
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  list<AulicaSaldoPersona>  $personas
     */
    private function sumaSaldos(array $personas): float
    {
        $total = 0.0;
        foreach ($personas as $persona) {
            $total += $persona->saldo;
        }

        return $total;
    }

    /**
     * Datos para el modal de Libre Deuda (enviado a la API y saldos recibidos).
     *
     * @return array{
     *     ambiente: string,
     *     metodo: string,
     *     endpoint: string,
     *     consultas: list<array{rol: string, tipo_doc: string, nro_doc: string, origen: string}>,
     *     consulta_ok: bool,
     *     error: string,
     *     estudiante: list<array<string, mixed>>,
     *     grupo_familiar: list<array<string, mixed>>,
     *     saldo_estudiante: float,
     *     saldo_estudiante_texto: string,
     *     saldo_grupo: float,
     *     saldo_grupo_texto: string,
     *     tiene_deuda: bool,
     *     puede_emitir: bool,
     *     mensaje: string
     * }
     */
    public function paraModal(string $origenResponsable = ''): array
    {
        $consultas = [];
        if ($this->dniEstudiante !== '') {
            $consultas[] = [
                'rol' => 'Estudiante',
                'tipo_doc' => 'DNI',
                'nro_doc' => $this->dniEstudiante,
                'origen' => 'DNI del estudiante (legajo)',
            ];
        }
        if ($this->dniResponsable !== '') {
            $mismo = $this->dniResponsable === $this->dniEstudiante;
            $consultas[] = [
                'rol' => $mismo ? 'Grupo familiar (mismo DNI)' : 'Grupo familiar',
                'tipo_doc' => 'DNI',
                'nro_doc' => $this->dniResponsable,
                'origen' => $origenResponsable !== ''
                    ? $origenResponsable
                    : 'DNI del responsable familiar',
            ];
        }

        $saldoAlumno = $this->saldoEstudiante();
        $saldoGrupo = $this->saldoGrupoFamiliar();
        $puedeEmitir = $this->consultaOk
            && ! $this->tieneDeuda()
            && $this->dniEstudiante !== '';

        $mensaje = '';
        if (! $this->consultaOk) {
            $mensaje = $this->error !== ''
                ? $this->error
                : 'No se pudo consultar la deuda en Áulica.';
        } elseif ($this->dniEstudiante === '' && $this->dniResponsable === '') {
            $mensaje = 'El legajo no tiene DNI para consultar Áulica.';
        } elseif ($this->tieneDeuda()) {
            $mensaje = $this->mensajeVisible();
        } else {
            $mensaje = 'Áulica no informa deuda. Puede emitir la constancia.';
        }

        return [
            'ambiente' => AulicaConfig::ambiente(),
            'metodo' => 'POST',
            'endpoint' => rtrim(AulicaConfig::urlApi(), '/').'/alumnos/ctacte/saldos',
            'consultas' => $consultas,
            'consulta_ok' => $this->consultaOk,
            'error' => $this->error,
            'estudiante' => array_map(
                fn (AulicaSaldoPersona $p) => $p->aArray(),
                $this->estudiante,
            ),
            'grupo_familiar' => array_map(
                fn (AulicaSaldoPersona $p) => $p->aArray(),
                $this->grupoFamiliar,
            ),
            'saldo_estudiante' => $saldoAlumno,
            'saldo_estudiante_texto' => $this->formatear($saldoAlumno),
            'saldo_grupo' => $saldoGrupo,
            'saldo_grupo_texto' => $this->formatear($saldoGrupo),
            'tiene_deuda' => $this->tieneDeuda(),
            'puede_emitir' => $puedeEmitir,
            'mensaje' => $mensaje,
        ];
    }

    private function formatear(float $importe): string
    {
        return '$ '.number_format($importe, 2, ',', '.');
    }
}
