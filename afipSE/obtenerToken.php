<?php

$usr_idUsuarios = getenv('USR_IDUSUARIOS');
$usr_key = getenv('USR_KEY');
$usr_crt = getenv('USR_CRT');

//echo 'Depuración en ObtenerToken:  <br>';
//echo 'usr_idUsuarios: '.$usr_idUsuarios.'<br>';
//echo 'usr_key: '.$usr_key.'<br>';
//echo 'usr_crt: '.$usr_crt.'<br>';


date_default_timezone_set('UTC');

// === Configuración ===
//$CUIT = '20139646674';   // CUIT emisor
$SERVICE = 'wsfe';     // Servicio 
$CERT = __DIR__ . '/cert/'.$usr_idUsuarios.'/'.$usr_crt;
$PRIVATEKEY = __DIR__ . '/cert/'.$usr_idUsuarios.'/'.$usr_key;
$TRA = __DIR__  .'/cert/'.$usr_idUsuarios.'/'. '/TRA.xml';
$TA = __DIR__  .'/cert/'.$usr_idUsuarios.'/'. '/TA.xml';




// === Generar TRA.xml ===
$unique_id = time();
$generation_time = (new DateTime('now', new DateTimeZone('UTC')))->modify('-1 minutes')->format('Y-m-d\TH:i:s') . 'Z';
$expiration_time = (new DateTime('now', new DateTimeZone('UTC')))->modify('+5 minutes')->format('Y-m-d\TH:i:s') . 'Z';

$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
  <header>
    <uniqueId>$unique_id</uniqueId>
    <generationTime>$generation_time</generationTime>
    <expirationTime>$expiration_time</expirationTime>
  </header>
  <service>$SERVICE</service>
</loginTicketRequest>
XML;

file_put_contents($TRA, $xml);

// === Firmar el TRA.xml usando OpenSSL ===
$CMS_tmp = tempnam(sys_get_temp_dir(), 'CMS');

exec("openssl smime -sign -signer $CERT -inkey $PRIVATEKEY -in $TRA -out $CMS_tmp -outform DER -nodetach 2>&1", $output, $retval);
if ($retval !== 0) {
    echo "❌ Error al firmar el TRA:\n";
    echo implode("\n", $output);
    exit(1);
}

$cms = file_get_contents($CMS_tmp);
$cms_base64 = base64_encode($cms);

// === Enviar CMS a WSAA Homologación === 
//cambiar la url de abajo para produccion por ésta: https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL
//$client = new SoapClient("https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL", [
$client = new SoapClient("https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL", [
    'trace' => 1,
    'exceptions' => 1,
]);

try {
    echo "🕓 Hora actual UTC: " . gmdate("c") . "\n";
    echo "📄 TRA.xml generado\n";
    echo "🕒 generationTime: $generation_time\n";
    echo "⏰ expirationTime: $expiration_time\n";
    echo "🔢 uniqueId: $unique_id\n";
    echo "✅ TRA firmado (CMS en DER)\n";
    echo "✅ CMS codificado en BASE64\n";
    echo "📨 Enviando CMS a AFIP homologación...\n";

    $result = $client->loginCms([
        'in0' => $cms_base64
    ]);

    file_put_contents($TA, $result->loginCmsReturn);
    echo "✅ Token recibido y guardado en TA.xml\n";

} catch (SoapFault $e) {
    echo "❌ Error al obtener el token: {$e->faultstring}\n";
}