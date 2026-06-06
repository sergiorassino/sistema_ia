<?php

if (! extension_loaded('gd')) {
    fwrite(STDERR, "PHP GD extension required.\n");
    exit(1);
}

/** Verde oscuro institucional (primary-700). */
const SE_GREEN_DARK = [44, 90, 96];

/**
 * Monograma SE con tipografía de bloques (misma geometría que los SVG).
 * Coordenadas en espacio 32×32; se escalan al canvas PNG.
 *
 * @param  array<int, array{0: float, 1: float, 2: float, 3: float}>  $rects
 */
function drawBlockRects($im, array $rects, int $textColor, float $scale): void
{
    foreach ($rects as [$x, $y, $w, $h]) {
        imagefilledrectangle(
            $im,
            (int) round($x * $scale),
            (int) round($y * $scale),
            (int) round(($x + $w) * $scale) - 1,
            (int) round(($y + $h) * $scale) - 1,
            $textColor
        );
    }
}

/** @return array<int, array{0: float, 1: float, 2: float, 3: float}> */
function seBlockLetterRects(): array
{
    return [
        // S
        [5.6, 7.8, 8.9, 2.75],
        [11.75, 7.8, 2.75, 5.65],
        [5.6, 13.45, 8.9, 2.75],
        [5.6, 13.45, 2.75, 6.3],
        [5.6, 19, 8.9, 2.75],
        // E
        [15.85, 7.8, 2.75, 13.95],
        [15.85, 7.8, 9.55, 2.75],
        [15.85, 13.45, 7.55, 2.75],
        [15.85, 19, 9.55, 2.75],
    ];
}

function drawFilledCircle($im, int $cx, int $cy, int $diameter, int $color): void
{
    imagealphablending($im, true);
    imagefilledellipse($im, $cx, $cy, $diameter, $diameter, $color);
}

/**
 * @param  array{circle: array{0: int, 1: int, 2: int}, text: array{0: int, 1: int, 2: int}}  $palette
 */
function writeSePng(string $path, array $palette): void
{
    $size = 64;
    $scale = $size / 32;
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $transparent);
    imagealphablending($im, true);

    [$circleR, $circleG, $circleB] = $palette['circle'];
    [$textR, $textG, $textB] = $palette['text'];
    $circleColor = imagecolorallocate($im, $circleR, $circleG, $circleB);
    $textColor = imagecolorallocate($im, $textR, $textG, $textB);
    $cx = (int) ($size / 2);

    drawFilledCircle($im, $cx, $cx, 62, $circleColor);
    drawBlockRects($im, seBlockLetterRects(), $textColor, $scale);

    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagepng($im, $path);
    imagedestroy($im);
}

$dir = __DIR__.'/../public/img';

writeSePng($dir.'/favicon-se-32-light.png', [
    'circle' => SE_GREEN_DARK,
    'text' => [255, 255, 255],
]);

writeSePng($dir.'/favicon-se-32-dark.png', [
    'circle' => [255, 255, 255],
    'text' => SE_GREEN_DARK,
]);

echo "Generated favicon PNGs in public/img/\n";
