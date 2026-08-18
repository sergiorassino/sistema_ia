<?php

$root = dirname(__DIR__);
$srcPath = $root.'/public/img/1.png';
$outDir = $root.'/public/img';

$src = imagecreatefrompng($srcPath);
if ($src === false) {
    fwrite(STDERR, "No se pudo leer 1.png\n");
    exit(1);
}

$sizes = [
    180 => 'apple-touch-icon.png',
    192 => 'icon-192.png',
    512 => 'icon-512.png',
];

foreach ($sizes as $size => $name) {
    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $bg = imagecolorallocate($dst, 0x40, 0x84, 0x8D);
    imagefilledrectangle($dst, 0, 0, $size, $size, $bg);
    imagealphablending($dst, true);
    $pad = (int) round($size * 0.12);
    $inner = $size - (2 * $pad);
    imagecopyresampled($dst, $src, $pad, $pad, 0, 0, $inner, $inner, imagesx($src), imagesy($src));
    imagepng($dst, $outDir.'/'.$name, 6);
    imagedestroy($dst);
    echo $name, ' ', $size, "x{$size}\n";
}

imagedestroy($src);
