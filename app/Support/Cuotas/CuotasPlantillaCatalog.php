<?php

namespace App\Support\Cuotas;

use App\Models\Cuota;
use App\Models\CuotasMes;
use App\Models\CuotasTipo;
use App\Models\Terlec;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Catálogos y reglas del ABM de plantillas (`cuotas`).
 */
final class CuotasPlantillaCatalog
{
    /** @return array<int, string> */
    public static function opcionesSinConBeca(): array
    {
        return [
            0 => 'No aplica Beca',
            1 => 'Aplica Beca',
        ];
    }

    public static function idTerlecActivo(): int
    {
        return (int) schoolCtx()->idTerlec;
    }

    /**
     * @return Collection<int, Terlec>
     */
    public static function terlecsParaSelector(): Collection
    {
        $id = self::idTerlecActivo();

        return Terlec::query()
            ->whereKey($id)
            ->get(['id', 'ano']);
    }

    /**
     * @return Collection<int, CuotasMes>
     */
    public static function mesesOrdenados(): Collection
    {
        return CuotasMes::query()->orderBy('id')->get(['id', 'mes']);
    }

    /**
     * @return Collection<int, CuotasTipo>
     */
    public static function tiposOrdenados(): Collection
    {
        return CuotasTipo::query()->orderBy('id')->get(['id', 'nombre']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function reglasFila(string $key, array $data): array
    {
        $mesIds = self::mesesOrdenados()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $tipoIds = self::tiposOrdenados()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sinIds = array_map('intval', array_keys(self::opcionesSinConBeca()));

        return [
            "draft.{$key}.nombre" => ['required', 'string', 'max:120'],
            "draft.{$key}.idCuotasmeses" => ['required', 'integer', Rule::in($mesIds)],
            "draft.{$key}.idCuotastipo" => ['required', 'integer', Rule::in($tipoIds)],
            "draft.{$key}.idTerlec" => ['required', 'integer', 'in:'.self::idTerlecActivo()],
            "draft.{$key}.venc1" => ['required', 'date'],
            "draft.{$key}.venc2" => ['nullable', 'date'],
            "draft.{$key}.venc3" => ['nullable', 'date'],
            "draft.{$key}.sinConBeca" => ['required', 'integer', Rule::in($sinIds)],
            "draft.{$key}.orden" => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public static function cuentaCuotasEnCicloActivo(): int
    {
        return Cuota::query()
            ->where('idTerlec', self::idTerlecActivo())
            ->count();
    }

    /**
     * Plantillas del ciclo activo para elegir como modelo de fórmulas.
     *
     * @return Collection<int, Cuota>
     */
    public static function cuotasDelCicloParaSelector(): Collection
    {
        return Cuota::query()
            ->where('idTerlec', self::idTerlecActivo())
            ->with(['cuotasMes:id,mes', 'cuotasTipo:id,nombre'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    public static function etiquetaCuota(Cuota $cuota): string
    {
        $partes = array_filter([
            trim((string) ($cuota->nombre ?? '')),
            trim((string) ($cuota->cuotasMes?->mes ?? '')),
            trim((string) ($cuota->cuotasTipo?->nombre ?? '')),
        ]);

        return $partes !== [] ? implode(' · ', $partes) : 'Cuota #'.$cuota->id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function reglasAltaModal(array $data, bool $permiteCopiarDesdeModelo): array
    {
        $mesIds = self::mesesOrdenados()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $tipoIds = self::tiposOrdenados()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sinIds = array_map('intval', array_keys(self::opcionesSinConBeca()));

        $reglas = [
            'alta.nombre' => ['required', 'string', 'max:120'],
            'alta.idCuotasmeses' => ['required', 'integer', Rule::in($mesIds)],
            'alta.idCuotastipo' => ['required', 'integer', Rule::in($tipoIds)],
            'alta.venc1' => ['required', 'date'],
            'alta.venc2' => ['nullable', 'date'],
            'alta.venc3' => ['nullable', 'date'],
            'alta.sinConBeca' => ['required', 'integer', Rule::in($sinIds)],
            'alta.orden' => ['required', 'integer', 'min:0', 'max:9999'],
            'origenFormulas' => ['required', 'string', Rule::in(['defaults', 'modelo'])],
        ];

        if ($permiteCopiarDesdeModelo && ($data['origenFormulas'] ?? '') === 'modelo') {
            $cuotaIds = self::cuotasDelCicloParaSelector()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $reglas['idCuotaModeloFormulas'] = ['required', 'integer', Rule::in($cuotaIds)];
        }

        return $reglas;
    }

    /**
     * @param  array<string, mixed>  $alta
     * @return array<string, mixed>
     */
    public static function payloadAltaDesdeFormulario(array $alta): array
    {
        return [
            'idTerlec' => self::idTerlecActivo(),
            'nombre' => trim((string) ($alta['nombre'] ?? '')),
            'idCuotasmeses' => (int) ($alta['idCuotasmeses'] ?? 0),
            'idCuotastipo' => (int) ($alta['idCuotastipo'] ?? 0),
            'venc1' => $alta['venc1'] ?: null,
            'venc2' => ($alta['venc2'] ?? '') !== '' ? $alta['venc2'] : null,
            'venc3' => ($alta['venc3'] ?? '') !== '' ? $alta['venc3'] : null,
            'sinConBeca' => (int) ($alta['sinConBeca'] ?? 0),
            'orden' => (int) ($alta['orden'] ?? 0),
        ];
    }
}
