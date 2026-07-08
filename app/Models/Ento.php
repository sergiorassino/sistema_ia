<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ento extends Model
{
    protected $table = 'ento';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'idNivel',

        // Institucional (legacy)
        'insti',
        'cue',
        'ee',
        'cuit',
        'domicFact',
        'condIvaInst',
        'aporteEstatal',
        'ptoVta',
        'afipCertCarpeta',
        'afipCertKey',
        'afipCertCrt',
        'condicionIva',
        'ingresosBrutos',
        'fechaInicioAct',
        'categoria',
        'direccion',
        'localidad',
        'departamento',
        'provincia',
        'telefono',
        'mail',
        'replegal',
        'siroIniPrim',
        'siroSecu',
        'siroMje',
        'siroPrefijoCPE',
        'siroIdentCuenta',

        // Logo (nuevo)
        'logo_path',
        'logo_original_name',

        // Matrícula web — nombre del PDF vigente por documento (legacy)
        'documAcept1',
        'documAcept2',
        'documAcept3',
        'documAcept4',

        // Parámetros operativos (legacy ento)
        'idTerlecVerNotas',
        'cargaNotasOff',
        'notasOffMensaje',
        'verNotasOff',
        'verOffMensaje',
        'verBimesOff',
        'bimesOffMensaje',
        'imprBoleOff',
    ];

    protected $casts = [
        'ptoVta' => 'integer',
    ];

    /**
     * Domicilio comercial para comprobantes AFIP.
     * Prioriza `domicFact` (domicilio registrado en AFIP); si está vacío, dirección + localidad.
     */
    public function domicilioComercialCompleto(): string
    {
        $domicAfip = trim((string) ($this->domicFact ?? ''));
        if ($domicAfip !== '') {
            return $domicAfip;
        }

        $direccion = trim((string) ($this->direccion ?? ''));
        $localidad = trim((string) ($this->localidad ?? ''));

        if ($direccion !== '' && $localidad !== '') {
            return $direccion.' - '.$localidad;
        }

        return $direccion !== '' ? $direccion : $localidad;
    }
}

