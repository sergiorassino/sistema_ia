<?php

namespace App\Console\Commands;

use App\Support\ManualSistema\ManualSistemaCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerarManualSistemaPdfCommand extends Command
{
    protected $signature = 'se:manual-pdf
                            {--output= : Ruta del PDF (por defecto: storage/app/manual-sistema.pdf)}
                            {--colegio= : Nombre del establecimiento en la portada}';

    protected $description = 'Genera el PDF con la descripción del sistema y el uso de cada módulo.';

    public function handle(): int
    {
        $output = $this->option('output') ?: storage_path('app/manual-sistema.pdf');
        $colegio = $this->option('colegio');

        if ($colegio === null || $colegio === '') {
            $colegio = (string) config('tenants.'.config('app.tenant_slug').'.nombre', '');
            if ($colegio === '') {
                $colegio = (string) config('app.name', '');
            }
        }

        $pdf = Pdf::loadView('pdf.manual-sistema', [
            'meta'    => ManualSistemaCatalog::meta(),
            'intro'   => ManualSistemaCatalog::introduccion(),
            'indice'  => ManualSistemaCatalog::indice(),
            'grupos'  => ManualSistemaCatalog::grupos(),
            'colegio' => $colegio !== '' ? $colegio : null,
        ])->setPaper('a4', 'portrait');

        $dir = dirname($output);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $pdf->save($output);

        $this->info('Manual generado: '.$output);
        $this->line('Tamaño: '.number_format(filesize($output) / 1024, 1).' KB');

        return self::SUCCESS;
    }
}
