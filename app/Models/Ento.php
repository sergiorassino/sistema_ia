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
        'ptoVta',
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

        // Logo (nuevo)
        'logo_path',
        'logo_original_name',

        // Matrícula web — nombre del PDF vigente por documento (legacy)
        'documAcept1',
        'documAcept2',
        'documAcept3',
        'documAcept4',
    ];

    protected $casts = [
        'ptoVta' => 'integer',
    ];

    /**
     * Domicilio comercial para comprobantes AFIP (dirección + localidad).
     */
    public function domicilioComercialCompleto(): string
    {
        $direccion = trim((string) ($this->direccion ?? ''));
        $localidad = trim((string) ($this->localidad ?? ''));

        if ($direccion !== '' && $localidad !== '') {
            return $direccion.' - '.$localidad;
        }

        return $direccion !== '' ? $direccion : $localidad;
    }
}

