<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $meta['titulo'] }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #333333;
            line-height: 1.45;
        }
        a { color: #40848D; text-decoration: none; }
        a:hover { text-decoration: underline; }

        h1 {
            font-size: 20pt;
            color: #40848D;
            margin: 0 0 4px 0;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        h2 {
            font-size: 11pt;
            color: #333333;
            margin: 14px 0 6px 0;
            padding-bottom: 4px;
            border-bottom: 1.5pt solid #40848D;
            page-break-after: avoid;
        }
        h3 {
            font-size: 9.5pt;
            color: #40848D;
            margin: 10px 0 4px 0;
            page-break-after: avoid;
        }

        .portada {
            text-align: center;
            padding: 32px 20px 28px 20px;
            margin-bottom: 18px;
            border: 1pt solid #C1D7DA;
            background: #f4f8f9;
            border-radius: 4px;
        }
        .portada .sub { font-size: 11pt; color: #555; margin: 6px 0 12px 0; }
        .portada .meta { font-size: 8.5pt; color: #739FA5; }
        .portada .badge {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 12px;
            background: #40848D;
            color: #fff;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border-radius: 20px;
        }

        .intro { margin-bottom: 12px; }
        .intro p { margin: 0 0 6px 0; text-align: justify; }
        .intro ul, .intro ol { margin: 4px 0 8px 0; padding-left: 18px; }
        .intro li { margin-bottom: 3px; }

        /* Índice interactivo */
        .indice-wrap {
            margin: 0 0 16px 0;
            padding: 12px 14px 10px 14px;
            background: #f4f8f9;
            border: 1pt solid #C1D7DA;
            border-left: 4pt solid #40848D;
            page-break-inside: avoid;
        }
        .indice-wrap h2 {
            margin-top: 0;
            border-bottom-color: #C1D7DA;
        }
        .indice-hint {
            font-size: 8pt;
            color: #739FA5;
            margin: 0 0 10px 0;
        }
        .indice-grupo {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .indice-grupo-titulo {
            margin: 0 0 4px 0;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #333333;
        }
        .indice-grupo-titulo a { color: #333333; }
        .indice-grupo-desc {
            margin: 0 0 5px 0;
            font-size: 7.5pt;
            color: #666;
            line-height: 1.35;
        }
        .indice-modulos {
            margin: 0;
            padding: 0 0 0 14px;
            list-style: none;
        }
        .indice-modulos li {
            margin: 0 0 3px 0;
            padding: 3px 6px 3px 8px;
            border-left: 2pt solid #C1D7DA;
            font-size: 8.5pt;
        }
        .indice-modulos li a {
            font-weight: 600;
            color: #40848D;
        }

        /* Bloques de grupo (como el menú lateral) */
        .grupo-bloque {
            margin: 18px 0 6px 0;
            page-break-before: auto;
        }
        .grupo-header {
            margin: 0 0 8px 0;
            padding: 10px 12px;
            background: #333333;
            color: #ffffff;
            page-break-after: avoid;
        }
        .grupo-header-inner {
            border-left: 4pt solid #40848D;
            padding-left: 10px;
        }
        .grupo-titulo {
            margin: 0;
            font-size: 11pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #ffffff;
        }
        .grupo-titulo a { color: #C1D7DA; font-size: 8pt; font-weight: 600; margin-left: 8px; }
        .grupo-desc {
            margin: 4px 0 0 0;
            font-size: 8pt;
            color: #C1D7DA;
            line-height: 1.35;
        }
        .grupo-accent {
            height: 3pt;
            background: #40848D;
            margin: 0 0 10px 0;
        }

        .modulo {
            margin-bottom: 10px;
            padding: 10px 12px 10px 12px;
            background: #ffffff;
            border: 0.75pt solid #C1D7DA;
            border-radius: 3px;
            page-break-inside: avoid;
        }
        .modulo-titulo-row {
            margin: 0 0 6px 0;
            padding-bottom: 5px;
            border-bottom: 0.75pt solid #e8f0f2;
        }
        .modulo-nombre {
            font-weight: 700;
            font-size: 10.5pt;
            color: #333333;
            margin: 0;
        }
        .modulo-nombre a { color: #333333; }
        .modulo-meta {
            font-size: 8pt;
            color: #555;
            margin: 0 0 6px 0;
            padding: 5px 8px;
            background: #f4f8f9;
            border-radius: 2px;
        }
        .modulo-meta strong { color: #40848D; }
        .modulo-objetivo {
            margin: 0 0 6px 0;
            text-align: justify;
        }
        .modulo-pasos-title,
        .modulo-consejos-title {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #40848D;
            margin: 8px 0 3px 0;
        }
        .modulo ol, .modulo ul {
            margin: 0 0 4px 0;
            padding-left: 16px;
        }
        .modulo li {
            margin-bottom: 3px;
            text-align: justify;
        }
        .modulo-permiso {
            margin: 8px 0 0 0;
            padding: 4px 8px;
            font-size: 7.5pt;
            color: #555;
            background: #f0f6f7;
            border-left: 2pt solid #739FA5;
        }

        .pie {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1pt solid #C1D7DA;
            font-size: 7.5pt;
            color: #777;
            text-align: center;
        }
        .volver-indice {
            font-size: 7.5pt;
            color: #739FA5;
            margin-top: 4px;
        }
        .volver-indice a { color: #40848D; font-weight: 600; }
    </style>
</head>
<body>

<div class="portada">
    <h1>{{ $meta['titulo'] }}</h1>
    <p class="sub">{{ $meta['subtitulo'] }}</p>
    @if(!empty($colegio))
        <p class="sub" style="font-weight:700;color:#333;">{{ $colegio }}</p>
    @endif
    <p class="meta">Versión {{ $meta['version'] }} · Generado el {{ $meta['generado'] }}</p>
    <span class="badge">Guía por módulos del menú</span>
</div>

<div class="intro">
    <h2>Cómo usar esta guía</h2>
    <p>{{ $intro['resumen'] }}</p>

    <h3>Antes de empezar</h3>
    <ol>
        @foreach($intro['antes_de_empezar'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ol>

    <h3>Portales de acceso</h3>
    @foreach($intro['portales'] as $portal)
        <p><strong>{{ $portal['titulo'] }}:</strong> {{ $portal['texto'] }}</p>
    @endforeach

    <h3>Consejos generales</h3>
    <ul>
        @foreach($intro['consejos_generales'] as $consejo)
            <li>{{ $consejo }}</li>
        @endforeach
    </ul>
</div>

<div class="indice-wrap" id="indice" name="indice">
    <h2>Índice de módulos</h2>
    <p class="indice-hint">Los enlaces del índice llevan directamente a la explicación de cada pantalla. Los grupos coinciden con el menú lateral del sistema.</p>

    @foreach($indice as $entrada)
        <div class="indice-grupo">
            <p class="indice-grupo-titulo">
                <a href="#{{ $entrada['grupo_id'] }}">{{ $entrada['grupo'] }}</a>
            </p>
            <p class="indice-grupo-desc">{{ $entrada['descripcion'] }}</p>
            <ul class="indice-modulos">
                @foreach($entrada['modulos'] as $mod)
                    <li><a href="#{{ $mod['id'] }}">{{ $mod['nombre'] }}</a></li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>

@foreach($grupos as $grupo)
    <div class="grupo-bloque">
        <div class="grupo-header" id="{{ $grupo['grupo_id'] }}" name="{{ $grupo['grupo_id'] }}">
            <div class="grupo-header-inner">
                <p class="grupo-titulo">
                    {{ $grupo['grupo'] }}
                    <a href="#indice">↑ Índice</a>
                </p>
                <p class="grupo-desc">{{ $grupo['descripcion'] }}</p>
            </div>
        </div>
        <div class="grupo-accent"></div>

        @foreach($grupo['modulos'] as $modulo)
            <div class="modulo" id="{{ $modulo['id'] }}" name="{{ $modulo['id'] }}">
                <div class="modulo-titulo-row">
                    <p class="modulo-nombre">
                        <a href="#{{ $modulo['id'] }}">{{ $modulo['nombre'] }}</a>
                    </p>
                    <p class="volver-indice"><a href="#indice">Volver al índice</a></p>
                </div>
                <p class="modulo-meta"><strong>Dónde está en el menú:</strong> {{ $modulo['menu'] }}</p>
                <p class="modulo-objetivo">{{ $modulo['objetivo'] }}</p>

                @if(!empty($modulo['pasos']))
                    <p class="modulo-pasos-title">Pasos para usarlo</p>
                    <ol>
                        @foreach($modulo['pasos'] as $paso)
                            <li>{{ $paso }}</li>
                        @endforeach
                    </ol>
                @endif

                @if(!empty($modulo['consejos']))
                    <p class="modulo-consejos-title">Consejos</p>
                    <ul>
                        @foreach($modulo['consejos'] as $consejo)
                            <li>{{ $consejo }}</li>
                        @endforeach
                    </ul>
                @endif

                @if(!empty($modulo['permiso']))
                    <p class="modulo-permiso"><strong>Quién puede usarlo:</strong> {{ $modulo['permiso'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
@endforeach

<div class="pie">
    Manual generado el {{ $meta['generado'] }}. Si un ítem no aparece en su menú lateral, su usuario no tiene el permiso correspondiente.
    <br>
    <a href="#indice">Ir al índice</a> · Menú «Manual del sistema» o comando <code>php artisan se:manual-pdf</code>
</div>

</body>
</html>
