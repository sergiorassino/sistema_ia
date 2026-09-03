<?php

namespace App\Console\Commands;

use App\Support\Aulica\AulicaConfig;
use App\Support\Aulica\AulicaDeudaConsulta;
use App\Support\Aulica\AulicaDni;
use Illuminate\Console\Command;

/**
 * Prueba de integración Áulica: deuda por DNI de estudiante y/o responsable familiar.
 *
 *   php artisan aulica:consultar-deuda 30111222
 *   php artisan aulica:consultar-deuda 30111222 --familia=20111222
 */
class AulicaConsultarDeudaCommand extends Command
{
    protected $signature = 'aulica:consultar-deuda
                            {dni : DNI del estudiante}
                            {--familia= : DNI del responsable de la familia (tutor)}';

    protected $description = 'Consulta deuda en Áulica por DNI (estudiante y, opcional, grupo familiar).';

    public function handle(): int
    {
        if (! AulicaConfig::habilitada()) {
            $this->error('Áulica no está habilitada. Active tenant.aulica_deuda y cargue AULICA_USERNAME, AULICA_PASSWORD y AULICA_CODIGO en el .env.');

            return self::FAILURE;
        }

        $dni = AulicaDni::normalizar($this->argument('dni'));
        if ($dni === null) {
            $this->error('DNI de estudiante inválido.');

            return self::FAILURE;
        }

        $familiaRaw = $this->option('familia');
        $dniFamilia = is_string($familiaRaw) && $familiaRaw !== ''
            ? AulicaDni::normalizar($familiaRaw)
            : $dni;

        $this->info('Ambiente: '.AulicaConfig::ambiente());
        $this->info('API: '.AulicaConfig::urlApi());

        $resultado = (new AulicaDeudaConsulta)->paraDnis($dni, $dniFamilia);

        if (! $resultado->consultaOk) {
            $this->error($resultado->error !== '' ? $resultado->error : 'Consulta fallida.');
            if ($this->output->isVerbose()) {
                $this->comment('Revisá storage/logs/laravel.log (líneas Áulica).');
            }

            return self::FAILURE;
        }

        $this->line('Estudiante DNI '.$resultado->dniEstudiante.': '.$resultado->etiquetaCorta());
        if ($resultado->mensajeVisible() !== '') {
            $this->line($resultado->mensajeVisible());
        } else {
            $this->info('Sin deuda en Áulica.');
        }

        return self::SUCCESS;
    }
}
