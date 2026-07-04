<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComprobanteAfip extends Model
{
    protected $table = 'comprobanteafip';

    protected $primaryKey = 'idComprobanteAfip';

    public $timestamps = false;

    protected $fillable = [
        'nombreInstitucion',
        'razonSocial',
        'cuitInstitucion',
        'domicilioComercial',
        'condicionIvaInstitucion',
        'puntoVenta',
        'ingresosBrutos',
        'fechaInicioActividades',
        'nombreAlumno',
        'dni',
        'nombreResp',
        'dniResp',
        'condicionIvaAlumno',
        'condicionVenta',
        'fechaDesde',
        'fechaHasta',
        'fechaEmision',
        'fechaVencimiento',
        'tipoComprobante',
        'codigoBarras',
        'nroRecibo',
        'cae',
        'vtoCae',
        'importePagado',
        'interesPagado',
        'idCbteAsoc',
        'concepto',
        'subConceptos',
        'importeSubConceptos',
        'saldoRestante',
        'idCuotasPagos',
        'telefonoInstitucion',
        'aporteEstatal',
        'cursoAlumno',
        'docTipoAfip',
    ];

    protected $casts = [
        'puntoVenta' => 'integer',
        'tipoComprobante' => 'integer',
        'docTipoAfip' => 'integer',
        'nroRecibo' => 'integer',
        'importePagado' => 'float',
        'interesPagado' => 'float',
        'idCbteAsoc' => 'integer',
        'idCuotasPagos' => 'integer',
    ];

    /**
     * Pago principal (`idCuotasPagos`) o cobro múltiple (`saldoRestante` = IDs separados por coma).
     */
    public function scopeVinculadoAPago(Builder $query, int $idCuotaPago): Builder
    {
        return $query->where(function (Builder $q) use ($idCuotaPago): void {
            $q->where('idCuotasPagos', $idCuotaPago)
                ->orWhereRaw('FIND_IN_SET(?, saldoRestante)', [$idCuotaPago]);
        });
    }
}
