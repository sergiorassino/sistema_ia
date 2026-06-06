<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$idNivel = (int) ($argv[1] ?? 0);

if ($idNivel > 0) {
    $r = Illuminate\Support\Facades\DB::table('ento')
        ->where('idNivel', $idNivel)
        ->first(['idNivel', 'logo_path', 'logo_original_name']);

    var_export($r);
    echo PHP_EOL;

    if ($r && is_string($r->logo_path) && $r->logo_path !== '') {
        $full = storage_path('app/public/' . $r->logo_path);
        echo 'full=' . $full . PHP_EOL;
        echo 'exists=' . (file_exists($full) ? 'yes' : 'no') . PHP_EOL;
        echo 'url=' . Illuminate\Support\Facades\Storage::disk('public')->url($r->logo_path) . PHP_EOL;
    }
} else {
    $rows = Illuminate\Support\Facades\DB::table('ento')
        ->orderBy('idNivel')
        ->limit(20)
        ->get(['idNivel', 'logo_path', 'logo_original_name']);

    foreach ($rows as $r) {
        echo $r->idNivel
            . ' | ' . ($r->logo_path ?? 'NULL')
            . ' | ' . ($r->logo_original_name ?? 'NULL')
            . PHP_EOL;
    }
}

