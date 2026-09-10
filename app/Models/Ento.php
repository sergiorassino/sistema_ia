<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
        'codCol',
        'cuitFact',
        'domicFact',
        'condIvaInst',
        'aporteEstatal',
        'ptoVta',
        'PtoVta', // legacy ScriptCase / AFIP (P mayúscula; no ptoVta)
        'afipCertCarpeta',
        'afipCertKey',
        'afipCertCrt',
        'condicionIva',
        'ingresosBrutos',
        'fechaInicioAct',
        'obsFactura',
        'categoria',
        'direccion',
        'localidad',
        'departamento',
        'provincia',
        'telefono',
        'mail',
        'ctaEnvioMail',
        'passEnvioMail',
        'replegal',
        'siroIniPrim',
        'siroSecu',
        'siroMje',
        'siroPrefijoCPE',
        'siroIdentCuenta',

        // Logo (nuevo)
        'logo_path',
        'logo_original_name',
        'logo_login_path',
        'logo_login_original_name',

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

        // Menú de Alumnos — Actualización de Datos + Ficha de Matrícula (mismo flag)
        'verDatosFicha',

        // Bloqueos de matrícula — mensajes por nivel
        'mensajeBloqPeda',
        'mensajeBloqAdmi',
    ];

    /** @var array<string, string> */
    private static array $columnaPuntoVentaCache = [];

    protected $casts = [
        'cargaNotasOff' => 'integer',
        'verNotasOff' => 'integer',
        'verBimesOff' => 'integer',
        'imprBoleOff' => 'integer',
        'verDatosFicha' => 'integer',
    ];

    /**
     * Nombre real de la columna de punto de venta en este tenant (`PtoVta` o `ptoVta`).
     */
    public static function columnaPuntoVenta(): string
    {
        $schema = Schema::getConnection()->getDatabaseName();
        if (isset(self::$columnaPuntoVentaCache[$schema])) {
            return self::$columnaPuntoVentaCache[$schema];
        }

        $nombre = 'ptoVta';
        if (Schema::hasTable('ento')) {
            foreach (Schema::getColumnListing('ento') as $columna) {
                if (strcasecmp((string) $columna, 'ptovta') === 0) {
                    $nombre = (string) $columna;
                    break;
                }
            }
        }

        return self::$columnaPuntoVentaCache[$schema] = $nombre;
    }

    /**
     * Punto de venta AFIP. En varios tenants la columna legacy es `PtoVta` (no `ptoVta`).
     */
    protected function ptoVta(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): mixed {
                foreach ($attributes as $key => $attrValue) {
                    if (strcasecmp((string) $key, 'ptovta') === 0) {
                        return $attrValue === null || $attrValue === '' ? null : (int) $attrValue;
                    }
                }

                return $value === null || $value === '' ? null : (int) $value;
            },
            set: function (mixed $value): array {
                $columna = self::columnaPuntoVenta();
                if ($value === null || $value === '') {
                    return [$columna => null];
                }

                return [$columna => (int) $value];
            },
        );
    }

    /**
     * CUIT emisor para WSFE / comprobantes AFIP (`ento.cuitFact`).
     * No usa el CUIT institucional: si falta o está vacío, retorna cadena vacía.
     */
    public function cuitParaFacturar(): string
    {
        if (! Schema::hasColumn('ento', 'cuitFact')) {
            return '';
        }

        return trim((string) ($this->cuitFact ?? ''));
    }

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

