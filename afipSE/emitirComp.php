<?php
session_start();

require_once 'verificarTA.php';
$archivo_ta = __DIR__ . "/cert/$usr_idUsuarios/TA.xml";

if (!verificar_o_generar_TA($archivo_ta)) {
    exit("❌ No se pudo generar TA.xml");
}



$CUIT       = $CUIT;
$PTO_VTA    = $PtoVta;
$TIPO_CMP   = $CbteTipo;               // 15 = Recibo C, 12 = Nota de Crédito C  11=factura C
$CONCEPTO   = $Concepto;                // Servicios
$DOC_TIPO   = $DocTipo;               // 96=DNI   80=cuit
$DOC_NRO    = $DocNro;
$IMPORTE    = $devengado;
$MONEDA_ID  = 'PES';
$COTIZACION = 1.000;
$FECHA      = $fechaComprobante;
$FECHADESDE = $FchServDesde;
$FECHAHASTA = $FchServHasta;
$CO_IVA_REC = $CondicionIVAReceptorId; //  6=resp monotr  1=resp inscripto  4=exento  5=consumidor final

	// depuracion
//	echo '<br>';
// 	echo 'DEPURACION DE emitirComp<br>';	
//    	echo 'glo_CUIT: '.$CUIT.'<br>';	
//	echo 'glo_PtoVta: '.$PTO_VTA.'<br>'	;
//	echo 'glo_CbteTipo: '.$TIPO_CMP.'<br>'	;
//	echo 'glo_Concepto: '.$CONCEPTO.'<br>'	;
//	echo 'glo_DocTipo: '.$DOC_TIPO.'<br>'	;
//	echo 'glo_CondicionIVAReceptorId: '.$CO_IVA_REC.'<br>'	;

//	echo 'glo_DocNro: '.$DOC_NRO.'<br>';
//	echo 'glo_devengado: '.$IMPORTE.'<br>';	





// Cargar Ticket de Acceso
$archivo_ta = __DIR__ . "/cert/$usr_idUsuarios/TA.xml";
$ta = simplexml_load_file($archivo_ta);

//$ta    = simplexml_load_file('TA.xml');

$token = (string) $ta->credentials->token;
$sign  = (string) $ta->credentials->sign;

$wsdl = __DIR__ . '/wsdl/WSFEv1.wsdl';

try {
    $client = new SoapClient($wsdl, [
        'soap_version' => SOAP_1_2,
        //'location' => "https://wswhomo.afip.gov.ar/wsfev1/service.asmx",
        'location' => "https://servicios1.afip.gov.ar/wsfev1/service.asmx", // Producción
        'trace' => 1,
        'exceptions' => true,
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
        'allow_self_signed' => true,
        'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        'ciphers'           => 'DEFAULT:@SECLEVEL=1'
            ]
        ])
    ]);
//    echo "SOAP Client creado con éxito\n";
} catch (Exception $e) {
    echo "Error al crear SoapClient: " . $e->getMessage() . "\n";
    exit;
}

// Obtener último comprobante autorizado del tipo actual
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
} catch (SoapFault $e) {
    echo "Error SOAP en FECompUltimoAutorizado: " . $e->getMessage() . PHP_EOL;
    echo "Request:\n" . $client->__getLastRequest() . PHP_EOL;
    echo "Response:\n" . $client->__getLastResponse() . PHP_EOL;
    exit;
}

$nro_siguiente = $ultimo->FECompUltimoAutorizadoResult->CbteNro + 1;
//echo '<br>'.$nro_siguiente.'<br>';

// Datos base comunes
$detalles = [
    'Concepto'     => $CONCEPTO,
    'DocTipo'      => $DOC_TIPO,
    'DocNro'       => $DOC_NRO,
    'CbteDesde'    => $nro_siguiente,
    'CbteHasta'    => $nro_siguiente,
    'CbteFch'      => $FECHA,
    'FchServDesde' => $FECHADESDE,
    'FchServHasta' => $FECHAHASTA,
    'FchVtoPago'   => $FECHA,
    'ImpTotal'     => $IMPORTE,
    'ImpTotConc'   => 0.00,
    'ImpNeto'      => $IMPORTE,
    'ImpOpEx'      => 0.00,
    'ImpIVA'       => 0.00,
    'ImpTrib'      => 0.00,
    'MonId'        => $MONEDA_ID,
    'MonCotiz'     => $COTIZACION,
    'CondicionIVAReceptorId' => $CO_IVA_REC // por ejemplo, 6=responsable monotributo, 1=Responsable Inscripto 4=Sujeto Exento  5=consumidor final    
];

// Si es una nota de crédito, agregar comprobante asociado
if ($TIPO_CMP === 12) {
    $detalles['CbtesAsoc'] = [
        'CbteAsoc' => [
            [
                'Tipo'   => 15,                       // Tipo de comprobante a anular (Recibo C)
                'PtoVta' => $PTO_VTA,
                'Nro'    => $nro_siguiente - 1        // Se asume que es el anterior
            ]
        ]
    ];
    // Motivo (campo opcional, útil para registrar por qué se emite)
    $detalles['Motivo'] = 'Anulación de recibo C por error de facturación';
}

// Armar solicitud completa
$datos = [
    'FeCAEReq' => [
        'FeCabReq' => [
            'CantReg'  => 1,
            'PtoVta'   => $PTO_VTA,
            'CbteTipo' => $TIPO_CMP,
        ],
        'FeDetReq' => [
            'FECAEDetRequest' => $detalles
        ]
    ]
];

// Enviar solicitud
try {
    $respuesta = $client->FECAESolicitar([
        'Auth' => [
            'Token' => $token,
            'Sign'  => $sign,
            'Cuit'  => $CUIT
        ],
        'FeCAEReq' => $datos['FeCAEReq']
    ]);

    $detalle = $respuesta->FECAESolicitarResult->FeDetResp->FECAEDetResponse;



///// depuracion
//echo "<hr><h3>🔍 Detalle completo de la respuesta AFIP:</h3><pre>";
//print_r($detalle);
//echo "</pre>";

//echo "<hr><h3>📦 Respuesta completa de AFIP:</h3><pre>";
//print_r($respuesta);
//echo "</pre>";
//////////////fin depuracion






//    echo "✅ CAE otorgado: " . $detalle->CAE . "<br>";
//    echo "📅 Vencimiento CAE: " . $detalle->CAEFchVto . "<br>";
//    echo "📅 Nro Comprobante: " . $detalle->CbteHasta . "<br>";

    $CAE = $detalle->CAE;
    $CAEFchVto = $detalle->CAEFchVto;    
    $CbteHasta = $detalle->CbteHasta;


} catch (SoapFault $e) {
    echo "❌ Error al emitir comprobante: " . $e->getMessage() . PHP_EOL;
    echo "Request:\n" . $client->__getLastRequest() . PHP_EOL;
    echo "Response:\n" . $client->__getLastResponse() . PHP_EOL;
    exit;
}



//echo '<script>window.location.href = "../../../pagos";</script>';
//exit;