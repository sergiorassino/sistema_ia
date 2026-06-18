<?php

//echo 'Depuración en VerificarTA:';
//echo 'usr_idUsuarios: '.$usr_idUsuarios.'<br>';
//echo 'usr_key: '.$usr_key.'<br>';
//echo 'usr_crt: '.$usr_crt.'<br>';

putenv("USR_IDUSUARIOS=$usr_idUsuarios");
putenv("USR_KEY=$usr_key");
putenv("USR_CRT=$usr_crt");


// FUNCION PARA VERIFICAR Y/O GENERAR TA.xml
function verificar_o_generar_TA($archivo_ta, $script_generador = 'obtenerToken.php') {

    if (!file_exists($archivo_ta)) {
        echo "🔄 TA.xml no encontrado. Generando nuevo...<br>";
        $script_generador = __DIR__ . '/' . $script_generador;
        exec("php $script_generador", $output, $exit_code);
        echo nl2br(implode(PHP_EOL, $output));
        return $exit_code === 0;
    }

    $ta = simplexml_load_file($archivo_ta);
    if (!$ta) {
        echo "❌ Error al cargar TA.xml. Generando nuevo...<br>";
        exec("php $script_generador", $output, $exit_code);
        echo nl2br(implode(PHP_EOL, $output));
        return $exit_code === 0;
    }

    $expiration = strtotime((string)$ta->header->expirationTime);

    if (time() >= $expiration) {
        echo "⏰ TA.xml vencido. Generando nuevo...<br>";
        exec("php $script_generador", $output, $exit_code);
        echo nl2br(implode(PHP_EOL, $output));
        return $exit_code === 0;
    }

    return true;
}
