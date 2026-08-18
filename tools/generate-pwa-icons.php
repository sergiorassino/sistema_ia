<?php

declare(strict_types=1);

/**
 * Regenera favicon e iconos PWA al estilo SILAVET:
 * círculo blanco, borde fino negro, letras SE en gris oscuro (#333333).
 *
 * Uso: php tools/generate-pwa-icons.php
 */

if (! extension_loaded('gd')) {
    fwrite(STDERR, "Se requiere la extensión PHP GD.\n");
    exit(1);
}

if (! function_exists('imagettftext')) {
    fwrite(STDERR, "Se requiere FreeType (imagettftext).\n");
    exit(1);
}

$root = dirname(__DIR__);
$publicImg = $root.'/public/img';
$fontBold = $root.'/storage/fonts/arialbd.ttf';

if (! is_file($fontBold)) {
    fwrite(STDERR, "No se encontró Arial Bold: {$fontBold}\n");
    exit(1);
}

if (! is_dir($publicImg)) {
    mkdir($publicImg, 0775, true);
}

const SE_BG = [0xE6, 0xE7, 0xEB];
const SE_WHITE = [255, 255, 255];
const SE_BORDER = [0, 0, 0];
const SE_LETTER = [0x33, 0x33, 0x33];

/**
 * Dibuja el icono circular SE a un tamaño dado (super-muestreo interno).
 *
 * @return \GdImage
 */
function seDrawCircleIcon(int $size, string $fontBold)
{
    $ss = 4;
    $big = $size * $ss;
    $im = imagecreatetruecolor($big, $big);
    imagealphablending($im, true);
    imagesavealpha($im, false);

    $bg = imagecolorallocate($im, SE_BG[0], SE_BG[1], SE_BG[2]);
    $white = imagecolorallocate($im, SE_WHITE[0], SE_WHITE[1], SE_WHITE[2]);
    $border = imagecolorallocate($im, SE_BORDER[0], SE_BORDER[1], SE_BORDER[2]);
    $letter = imagecolorallocate($im, SE_LETTER[0], SE_LETTER[1], SE_LETTER[2]);
    imagefilledrectangle($im, 0, 0, $big - 1, $big - 1, $bg);

    $cx = (int) ($big / 2);
    $cy = (int) ($big / 2);
    $pad = (int) round($big * (29 / 512));
    $outerD = $big - (2 * $pad);
    $stroke = max($ss, (int) round($big * (4 / 512)));
    $innerD = $outerD - (2 * $stroke);

    imagefilledellipse($im, $cx, $cy, $outerD, $outerD, $border);
    imagefilledellipse($im, $cx, $cy, $innerD, $innerD, $white);

    $fontPx = $innerD * 0.46;
    $gap = (int) round($fontPx * 0.04);
    $sBox = imagettfbbox($fontPx, 0, $fontBold, 'S');
    $eBox = imagettfbbox($fontPx, 0, $fontBold, 'E');
    $sW = $sBox[2] - $sBox[0];
    $eW = $eBox[2] - $eBox[0];
    $totalW = $sW + $gap + $eW;

    $sHeight = $sBox[1] - $sBox[7];
    $eHeight = $eBox[1] - $eBox[7];
    $textH = max($sHeight, $eHeight);
    $baseline = (int) round($cy + ($textH / 2) - 0.18 * $fontPx);

    $xS = (int) round($cx - ($totalW / 2) - $sBox[0]);
    $xE = (int) round($xS + $sW + $gap - $eBox[0] + $sBox[0]);

    imagettftext($im, $fontPx, 0, $xS, $baseline, $letter, $fontBold, 'S');
    imagettftext($im, $fontPx, 0, $xE, $baseline, $letter, $fontBold, 'E');

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, false);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $big, $big);
    imagedestroy($im);

    return $out;
}

function seWritePng($im, string $path): void
{
    imagepng($im, $path, 6);
    echo basename($path), ' ', imagesx($im), 'x', imagesy($im), "\n";
}

function seWriteIcoFromPng(string $pngPath, string $icoPath): void
{
    $png = file_get_contents($pngPath);
    if ($png === false) {
        fwrite(STDERR, "No se pudo leer {$pngPath}\n");
        exit(1);
    }

    $dir = pack('v3', 0, 1, 1);
    $entry = pack('C4v2V2', 32, 32, 0, 0, 1, 32, strlen($png), 22);
    file_put_contents($icoPath, $dir.$entry.$png);
    echo basename($icoPath), "\n";
}

$source = seDrawCircleIcon(512, $fontBold);

$sizes = [
    32 => 'favicon-32.png',
    180 => 'apple-touch-icon.png',
    192 => 'icon-192.png',
    512 => 'icon-512.png',
];

foreach ($sizes as $size => $name) {
    if ($size === 512) {
        seWritePng($source, $publicImg.'/'.$name);
        continue;
    }
    $dst = imagecreatetruecolor($size, $size);
    imagecopyresampled($dst, $source, 0, 0, 0, 0, $size, $size, 512, 512);
    seWritePng($dst, $publicImg.'/'.$name);
    imagedestroy($dst);
}

imagedestroy($source);
seWriteIcoFromPng($publicImg.'/favicon-32.png', $root.'/public/favicon.ico');

echo "done\n";
