<?php
session_start();


echo "<pre>";
print_r($facturas);
echo "</pre>";


require_once 'verificarTA.php';

if (!verificar_o_generar_TA()) {
    exit("❌ No se pudo generar TA.xml");
}

// ⚠️ IMPORTANTE: acá NO se usan variables globales ni $_SESSION.
// Este script espera que venga $facturas (array) desde Scriptcase.
// Ejemplo: $facturas = [ ["id"=>1,"DocTipo"=>96,"DocNro"=>12345678,"Importe"=>100, ...], ... ]

if (!isset($facturas) || empty($facturas)) {
    exit("⚠️ No se recibieron facturas para procesar.");
}

// Cargar Ticket de Acceso
$ta    = simplexml_load_file('TA.xml');
$token = (string) $ta->credentials->token;
$sign  = (string) $ta->credentials->sign;

// SOAP Client
$wsdl = __DIR__ . '/wsdl/WSFEv1.wsdl';
try {
    $client = new SoapClient($wsdl, [
        'soap_version' => SOAP_1_2,
        'location' => "https://servicios1.afip.gov.ar/wsfev1/service.asmx", // Producción
        //'location' => "https://wswhomo.afip.gov.ar/wsfev1/service.asmx", // Homologación
        'trace' => 1,
        'exceptions' => true,
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ])
    ]);
} catch (Exception $e) {
    exit("❌ Error al crear SoapClient: " . $e->getMessage());
}

// Tomo CUIT, PTO_VTA y TIPO_CMP de la primera factura (todas deberían ser iguales en un mismo request)
$CUIT     = $facturas[0]['CUIT'];
$PTO_VTA  = $facturas[0]['PtoVta'];
$TIPO_CMP = $facturas[0]['CbteTipo'];

// Obtener último comprobante autorizado
try {
    $ultimo = $client->FECompUltimoAutorizado([
        'Auth' => [
            'Token' => $token,
            'Sign'  => $sign,
            'Cuit'  => $CUIT
        ],
        'PtoVta'   => $PTO_VTA,
        'CbteTipo' => $TIPO_CMP,
    ]);
    $nro_siguiente = $ultimo->FECompUltimoAutorizadoResult->CbteNro + 1;
} catch (SoapFault $e) {
    echo "❌ Error en FECompUltimoAutorizado: " . $e->getMessage();
    exit;
}

// Construyo todos los FECAEDetRequest
$detalles = [];
foreach ($facturas as $i => $f) {
    $cbte_nro = $nro_siguiente + $i;

    $item = [
        'Concepto'     => (int) $f['Concepto'],
        'DocTipo'      => (int) $f['DocTipo'],
        'DocNro'       => (int) $f['DocNro'],
        'CbteDesde'    => $cbte_nro,
        'CbteHasta'    => $cbte_nro,
	'CbteFch'      => (int) $f['fechaComprobante'],
	'FchServDesde' => (int) $f['FchServDesde'],
	'FchServHasta' => (int) $f['FchServHasta'],
	'FchVtoPago'   => (int) $f['fechaComprobante'],
        'ImpTotal'     => (int) $f['importe'],
        'ImpTotConc'   => 0.00,
        'ImpNeto'      => (int) $f['importe'],
        'ImpOpEx'      => 0.00,
        'ImpIVA'       => 0.00,
        'ImpTrib'      => 0.00,
        'MonId'        => 'PES',
        'MonCotiz'     => 1.000,
    ];

//    if ($f['Concepto'] > 1) { // Servicios
//    $item['FchServDesde'] = (int) str_replace('-', '', $f['FchDesde']);
//    $item['FchServHasta'] = (int) str_replace('-', '', $f['FchHasta']);
//    $item['FchVtoPago']   = (int) str_replace('-', '', $f['Fecha']);
//    }


    $detalles[] = $item;
}

// Request completo
$datos = [
    'FeCAEReq' => [
        'FeCabReq' => [
            'CantReg'  => count($detalles),
            'PtoVta'   => $PTO_VTA,
            'CbteTipo' => $TIPO_CMP,
        ],
        'FeDetReq' => [
            'FECAEDetRequest' => $detalles
        ]
    ]
];

// Enviar a AFIP
try {

    $respuesta = $client->FECAESolicitar([
        'Auth' => [
            'Token' => $token,
            'Sign'  => $sign,
            'Cuit'  => $CUIT
        ],
        'FeCAEReq' => $datos['FeCAEReq']
    ]);



    $detResp = $respuesta->FECAESolicitarResult->FeDetResp->FECAEDetResponse;

    // Normalizo si devuelve 1 objeto en lugar de array
    if (!is_array($detResp)) {
        $detResp = [$detResp];
    }

    // Preparo array para Scriptcase
    $resultados = [];
    foreach ($detResp as $k => $d) {
        $resultados[] = [
            "idCuotaspagos"             => $facturas[$k]['idCuotaspagos'],
            "CAE"            => $d->CAE ?? null,
            "CAEFchVto"      => $d->CAEFchVto ?? null,
            "NroComprobante" => $d->CbteHasta ?? null,
            "Resultado"      => $d->Resultado ?? 'N'
        ];
    }


    // Mostrar toda la respuesta de AFIP
    echo "<pre>";
    print_r($respuesta);
    echo "</pre>";

    // Si querés, también podés usar var_dump para ver todos los detalles de tipos de datos
    // var_dump($respuesta);

    // Normalizo si devuelve 1 objeto en lugar de array
    $detResp = $respuesta->FECAESolicitarResult->FeDetResp->FECAEDetResponse;
    if (!is_array($detResp)) {
        $detResp = [$detResp];
    }




    // ✅ En este punto $resultados ya está listo para devolver a Scriptcase
    return $resultados;

} catch (SoapFault $e) {
    echo "❌ Error en FECAESolicitar: " . $e->getMessage();
    return false;
}
