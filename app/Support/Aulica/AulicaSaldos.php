<?php

namespace App\Support\Aulica;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * POST /alumnos/ctacte/saldos — deuda del DNI y, si es tutor, de los alumnos a cargo.
 *
 * Áulica responde `{ "items": [ { idPersona, saldo, nroDoc, tipoDoc, nombre, apellido } ] }`.
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

        $clave = 'aulica:'.AulicaConfig::slugCache().':saldos:v2:'.$tipoDoc.':'.$nroDoc;
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

        $out = [];
        foreach ($this->filasDesdeJson($response->json()) as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $out[] = AulicaSaldoPersona::desdeRespuesta($fila);
        }

        return $out;
    }

    /**
     * @param  mixed  $json
     * @return list<mixed>
     */
    private function filasDesdeJson(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        foreach (['items', 'data', 'saldos', 'result'] as $clave) {
            if (isset($json[$clave]) && is_array($json[$clave])) {
                $json = $json[$clave];
                break;
            }
        }

        if ($json === []) {
            return [];
        }

        if ($this->esObjetoAsociativo($json)) {
            return $this->parecePersona($json) ? [$json] : [];
        }

        return array_values($json);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function parecePersona(array $fila): bool
    {
        foreach (['idPersona', 'IdPersona', 'nroDoc', 'NroDoc', 'nombre', 'Nombre', 'apellido', 'Apellido', 'saldo', 'Saldo'] as $clave) {
            if (array_key_exists($clave, $fila)) {
                return true;
            }
        }

        return false;
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
