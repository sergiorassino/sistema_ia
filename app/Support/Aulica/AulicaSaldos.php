<?php

namespace App\Support\Aulica;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * POST /alumnos/ctacte/saldos — deuda del DNI y, si es tutor, de los alumnos a cargo.
 */
final class AulicaSaldos
{
    public function __construct(private readonly AulicaCliente $cliente = new AulicaCliente) {}

    /**
     * @return list<AulicaSaldoPersona>
     */
    public function porDocumento(string $nroDoc, string $tipoDoc = 'DNI'): array
    {
        $nroDoc = AulicaDni::normalizar($nroDoc);
        if ($nroDoc === null) {
            return [];
        }

        $tipoDoc = strtoupper(trim($tipoDoc));
        if ($tipoDoc === '') {
            $tipoDoc = 'DNI';
        }

        $clave = 'aulica:'.AulicaConfig::slugCache().':saldos:'.$tipoDoc.':'.$nroDoc;
        $cacheado = Cache::get($clave);
        if (is_array($cacheado)) {
            return $this->hidratarCache($cacheado);
        }

        $personas = $this->consultar([
            'TipoDoc' => $tipoDoc,
            'NroDoc' => $nroDoc,
        ]);

        Cache::put($clave, array_map(
            fn (AulicaSaldoPersona $p) => [
                'idPersona' => $p->idPersona,
                'saldo' => $p->saldo,
                'nroDoc' => $p->nroDoc,
                'tipoDoc' => $p->tipoDoc,
                'nombre' => $p->nombre,
                'apellido' => $p->apellido,
            ],
            $personas,
        ), AulicaConfig::cacheSaldosSegundos());

        return $personas;
    }

    /**
     * @param  array{TipoDoc?: string, NroDoc?: string, idPersona?: int}  $filtro
     * @return list<AulicaSaldoPersona>
     */
    private function consultar(array $filtro): array
    {
        $response = $this->cliente->postApi('alumnos/ctacte/saldos', $filtro);

        if ($response->status() === 404) {
            return [];
        }

        if ($response->status() === 400) {
            throw new AulicaClienteException('Áulica: faltan datos para buscar el saldo.');
        }

        if (! $response->successful()) {
            Log::warning('Áulica: saldos HTTP no exitoso', ['status' => $response->status()]);

            throw new AulicaClienteException('Áulica devolvió HTTP '.$response->status().' al consultar saldos.');
        }

        $json = $response->json();
        if (! is_array($json)) {
            return [];
        }

        if ($this->esObjetoAsociativo($json)) {
            $json = [$json];
        }

        $out = [];
        foreach ($json as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $out[] = AulicaSaldoPersona::desdeRespuesta($fila);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<AulicaSaldoPersona>
     */
    private function hidratarCache(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            if (is_array($fila)) {
                $out[] = AulicaSaldoPersona::desdeRespuesta($fila);
            }
        }

        return $out;
    }

    /**
     * @param  array<mixed>  $json
     */
    private function esObjetoAsociativo(array $json): bool
    {
        return $json !== [] && array_keys($json) !== range(0, count($json) - 1);
    }
}
