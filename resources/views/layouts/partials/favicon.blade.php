@php
    $seFavicon = seMonogramFaviconUrls();
@endphp
<meta name="se-favicon-light" content="{{ $seFavicon['light'] }}">
<meta name="se-favicon-dark" content="{{ $seFavicon['dark'] }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ $seFavicon['light'] }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ $seFavicon['dark'] }}" media="(prefers-color-scheme: dark)">
<link rel="shortcut icon" type="image/png" href="{{ $seFavicon['light'] }}">
<script>
    (function () {
        var light = @json($seFavicon['light']);
        var dark = @json($seFavicon['dark']);

        function isLegacyFavicon(href) {
            if (!href) {
                return false;
            }
            if (/img\/1\.png/i.test(href) || /img\/2\.png/i.test(href)) {
                return false;
            }
            return /\/storage\//.test(href)
                || /\/ento\/logos\//i.test(href)
                || /img\/[345]\.png/i.test(href)
                || /favicon-se/i.test(href)
                || /icono-escuela/i.test(href)
                || /favicon\.ico/i.test(href);
        }

        function apply() {
            document.querySelector('meta[name="se-favicon"]')?.remove();

            document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]').forEach(function (link) {
                if (isLegacyFavicon(link.href)) {
                    link.remove();
                }
            });

            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var href = prefersDark ? dark : light;
            var hasPng = false;

            document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"]').forEach(function (link) {
                var media = link.getAttribute('media') || '';
                if (media.indexOf('prefers-color-scheme') !== -1) {
                    return;
                }
                link.type = 'image/png';
                link.href = href;
                hasPng = true;
            });

            if (!hasPng) {
                var link = document.createElement('link');
                link.rel = 'icon';
                link.type = 'image/png';
                link.sizes = '32x32';
                link.href = href;
                document.head.appendChild(link);
            }
        }

        apply();
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', apply);
        }
        document.addEventListener('DOMContentLoaded', apply);
        window.addEventListener('pageshow', apply);
    })();
</script>
