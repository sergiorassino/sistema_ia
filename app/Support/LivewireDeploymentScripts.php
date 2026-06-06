<?php

namespace App\Support;

use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

/**
 * Livewire en subcarpeta: el tag &lt;script&gt; no debe llevar data-update-uri duplicada ni con prefijo repetido.
 */
final class LivewireDeploymentScripts
{
    public static function render(): string
    {
        $html = FrontendAssets::scripts();

        $appUrl = rtrim((string) config('app.url'), '/');
        $appPath = rtrim((string) (parse_url($appUrl, PHP_URL_PATH) ?: ''), '/');

        if ($appPath === '') {
            return $html;
        }

        $moduleUrl = $appUrl.EndpointResolver::prefix();
        $updateUrl = $moduleUrl.'/update';

        $html = preg_replace('/\sdata-module-url="[^"]*"/', '', $html) ?? $html;
        $html = preg_replace('/\sdata-update-uri="[^"]*"/', '', $html) ?? $html;

        $injection = 'data-module-url="'.e($moduleUrl).'" data-update-uri="'.e($updateUrl).'" ';

        $html = preg_replace('/<script /', '<script '.$injection, $html, 1) ?? $html;

        // Respaldo: /vendor/livewire/ → 403 en LiteSpeed; usar ruta /livewire-{hash}/livewire.js
        $livewireJs = config('app.debug') ? 'livewire.js' : 'livewire.min.js';
        $scriptBase = $appUrl.EndpointResolver::prefix().'/'.$livewireJs;
        if (preg_match('#\bsrc="([^"]*vendor/livewire/[^"]*)"#i', $html, $m)) {
            $qs = parse_url($m[1], PHP_URL_QUERY);
            $html = preg_replace(
                '#\bsrc="[^"]*vendor/livewire/[^"]*"#i',
                'src="'.e($scriptBase).($qs !== null && $qs !== '' ? '?'.e($qs) : '').'"',
                $html,
                1,
            ) ?? $html;
        }

        return $html;
    }
}
