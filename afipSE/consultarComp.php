<?php
require_once 'verificarTA.php';
// USO DE LA FUNCIÓN
if (!verificar_o_generar_TA()) {
    exit("❌ No se pudo generar TA.xml");
}

// a este modulo hay que darle las siguientes variables
echo '<br>';
echo 'nro_comprobante '.$nro_comprobante.'<br>';
echo 'CUIT '.$CUIT.'<br>';
echo 'PTO_VTA'.$PTO_VTA.'<br>';
echo 'TIPO_CMP'.$TIPO_CMP.'<br>';


// Cargar TA.xml
$ta = simplexml_load_file('TA.xml');
$token = (string) $ta->credentials->token;
$sign  = (string) $ta->credentials->sign;

// Cliente SOAP
$client = new SoapClient(__DIR__ . "/wsdl/WSFEv1.wsdl", [
    'soap_version' => SOAP_1_2,
    'location' => "https://servicios1.afip.gov.ar/wsfev1/service.asmx", // Homologación
    // 'location' => "https://servicios1.afip.gov.ar/wsfev1/service.asmx", // Producción
    'trace' => 1,
    'exceptions' => true,
]);

try {
    // Paso 1: Consultar último comprobante
    $ultimo = $client->FECompUltimoAutorizado([
        'Auth' => [
            'Token' => $token,
            'Sign'  => $sign,
            'Cuit'  => $CUIT
        ],
        'PtoVta'    => $PTO_VTA,
        'CbteTipo'  => $TIPO_CMP,
    ]);

 //   $nro_comprobante = $ultimo->FECompUltimoAutorizadoResult->CbteNro;
 //   echo "Último comprobante autorizado: $nro_comprobante" . PHP_EOL;


    // Paso 2: Consultar detalle de ese comprobante
    $consulta = $client->FECompConsultar([
        'Auth' => [
            'Token' => $token,
            'Sign'  => $sign,
            'Cuit'  => $CUIT
        ],
        'FeCompConsReq' => [
            'CbteTipo' => $TIPO_CMP,
            'PtoVta'   => $PTO_VTA,
            'CbteNro'  => $nro_comprobante,
        ]
    ]);

    $detalle = $consulta->FECompConsultarResult->ResultGet;


// llamo  a la funcion de mostrar 
mostrarDetalleComprobante($detalle);



//    echo "CAE: " . $detalle->CodAutorizacion . PHP_EOL;
//    echo "Fecha Vto CAE: " . $detalle->FchVto . PHP_EOL;
//    echo "Fecha Comprobante: " . $detalle->CbteFch . PHP_EOL;


} catch (Exception $e) {
    echo "Error al consultar comprobante: " . $e->getMessage() . PHP_EOL;
}


//echo '<script>window.location.href = "../../../pagos";</script>';
//exit;




function mostrarDetalleComprobante($detalle) {
    echo '<br>';
    echo "📄 Detalle del Comprobante" . '<br>';
    echo "----------------------------" . '<br>';
    echo "Punto de Venta       : {$detalle->PtoVta}" . '<br>';
    echo "Tipo Comprobante     : {$detalle->CbteTipo}" . '<br>';
    echo "Nro Comprobante      : {$detalle->CbteDesde}" . '<br>';
    echo "Fecha Emisión        : {$detalle->CbteFch}" . '<br>';
    echo "CUIT Comprador       : {$detalle->DocNro}" . '<br>';
    echo "Tipo Doc. Comprador  : {$detalle->DocTipo}" . '<br>';
    echo "Nombre Comprador     : {$detalle->Nombre}" . '<br>';
    echo "Importe Total        : \${$detalle->ImpTotal}" . '<br>';
    echo "Importe Neto         : \${$detalle->ImpNeto}" . '<br>';
    echo "Importe Exento       : \${$detalle->ImpOpEx}" . '<br>';
    echo "Importe IVA          : \${$detalle->ImpIVA}" . '<br>';
    echo "Otros Tributos       : \${$detalle->ImpTrib}" . '<br>';
    echo "CAE                  : {$detalle->CodAutorizacion}" . '<br>';
    echo "Vto. CAE             : {$detalle->FchVto}" . '<br>';
    echo "Moneda               : {$detalle->MonId} (Cotización: {$detalle->MonCotiz})" . '<br>';
    echo "CondicionIVAReceptorId             : {$detalle->CondicionIVAReceptorId}" . '<br>';
    if (isset($detalle->FchServDesde)) {
        echo "Servicio Desde       : {$detalle->FchServDesde}" . '<br>';
        echo "Servicio Hasta       : {$detalle->FchServHasta}" . '<br>';
        echo "Vto. Pago            : {$detalle->FchVtoPago}" . '<br>';
    }

    if (isset($detalle->Iva)) {
        echo "🧾 IVA Discriminado:" . '<br>';
        foreach ($detalle->Iva->AlicIva as $iva) {
            echo "- ID: {$iva->Id}, Base: \${$iva->BaseImp}, Importe: \${$iva->Importe}" . '<br>';
        }
    }

    if (isset($detalle->Tributos)) {
        echo "💸 Tributos:" . '<br>';
        foreach ($detalle->Tributos->Tributo as $trib) {
            echo "- ID: {$trib->Id}, Desc: {$trib->Desc}, Base: \${$trib->BaseImp}, Importe: \${$trib->Importe}" . '<br>';
        }
    }

    if (isset($detalle->CbtesAsoc)) {
        echo "🔗 Comprobantes Asociados:" . '<br>';
        foreach ($detalle->CbtesAsoc->CbteAsoc as $cbte) {
            echo "- Tipo: {$cbte->Tipo}, PtoVta: {$cbte->PtoVta}, Nro: {$cbte->Nro}" . '<br>';
        }
    }

    echo "----------------------------" . '<br>';
}
