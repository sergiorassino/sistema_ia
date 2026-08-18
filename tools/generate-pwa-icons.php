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
 * Bounding box de tinta sobre fondo blanco (incluye anti-alias).
 *
 * @return array{0: int, 1: int, 2: int, 3: int}|null minX, minY, maxX, maxY
 */
function seInkBounds($im): ?array
{
    $w = imagesx($im);
    $h = imagesy($im);
    $minX = $w;
    $minY = $h;
    $maxX = -1;
    $maxY = -1;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgb = imagecolorat($im, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            if ($r < 250 || $g < 250 || $b < 250) {
                if ($x < $minX) {
                    $minX = $x;
                }
                if ($y < $minY) {
                    $minY = $y;
                }
                if ($x > $maxX) {
                    $maxX = $x;
                }
                if ($y > $maxY) {
                    $maxY = $y;
                }
            }
        }
    }

    if ($maxX < 0) {
        return null;
    }

    return [$minX, $minY, $maxX, $maxY];
}

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

    // FreeType bbox incluye ascendente extra: las letras quedan altas. Se centra
    // con la tinta real de "SE" (mismo fondo blanco que el interior del círculo).
    $probe = imagecreatetruecolor($big, $big);
    $probeWhite = imagecolorallocate($probe, SE_WHITE[0], SE_WHITE[1], SE_WHITE[2]);
    $probeInk = imagecolorallocate($probe, SE_LETTER[0], SE_LETTER[1], SE_LETTER[2]);
    imagefilledrectangle($probe, 0, 0, $big - 1, $big - 1, $probeWhite);
    $originX = (int) round($big * 0.25);
    $originY = (int) round($big * 0.65);
    imagettftext($probe, $fontPx, 0, $originX, $originY, $probeInk, $fontBold, 'SE');
    $bounds = seInkBounds($probe);
    imagedestroy($probe);

    if ($bounds !== null) {
        [$minX, $minY, $maxX, $maxY] = $bounds;
        $inkCx = ($minX + $maxX + 1) / 2.0;
        $inkCy = ($minY + $maxY + 1) / 2.0;
        $drawX = (int) round($originX + ($cx - $inkCx));
        $drawY = (int) round($originY + ($cy - $inkCy));
        imagettftext($im, $fontPx, 0, $drawX, $drawY, $letter, $fontBold, 'SE');
    }

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
    32 => ['favicon-32.png'],
    180 => ['apple-touch-icon.png', 'apple-touch-icon-se.png'],
    192 => ['icon-192.png', 'icon-se-192.png'],
    512 => ['icon-512.png', 'icon-se-512.png'],
];

foreach ($sizes as $size => $names) {
    $first = $names[0];
    if ($size === 512) {
        foreach ($names as $name) {
            seWritePng($source, $publicImg.'/'.$name);
        }
        continue;
    }
    $dst = imagecreatetruecolor($size, $size);
    imagecopyresampled($dst, $source, 0, 0, 0, 0, $size, $size, 512, 512);
    foreach ($names as $name) {
        seWritePng($dst, $publicImg.'/'.$name);
    }
    imagedestroy($dst);
}

imagedestroy($source);
seWriteIcoFromPng($publicImg.'/favicon-32.png', $root.'/public/favicon.ico');

echo "done\n";
