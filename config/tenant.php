<?php

/*
 | Valores por defecto de `config('tenant.*')` para todos los despliegues.
 |
 | Personalización por colegio: archivo versionado `config/tenants/{TENANT_SLUG}.php`
 | (merge recursivo sobre este array). Así cada cliente documenta en git qué difiere
 | y un colegio nuevo puede partir copiando el archivo del más parecido.
 |
 | - slug: se toma de TENANT_SLUG en el entorno (identifica despliegue / BD / archivo tenants).
 | - nombre: fallback del nombre institucional si no hay dato en `ento`.
 | - autogestion: definir por colegio en `config/tenants/{slug}.php` cuando corresponda.
 */

return [

    'slug' => env('TENANT_SLUG', 'default'),

    'nombre' => 'Colegio',

    /**
     * Gestión de mora — notificación de deuda (PDF).
     * Personalizar en `config/tenants/{slug}.php` (imagen bajo `public/`).
     */
    'mora' => [
        'notificacion_deuda' => [
            'firma_imagen' => null,
            'firma_nombre' => null,
            'firma_cargo' => 'Representante Legal',
        ],
    ],

    /**
     * Portal alumno / familia — enlaces y módulos opcionales.
     * Activar solo en `config/tenants/{slug}.php` cuando corresponda.
     */
    'autogestion' => [
        'aranceles_aulica_url' => null,

        /**
         * Actualización de datos personales del legajo (portal familia).
         * Default habilitado para todos los colegios con variante `estandar`.
         * Desactivar en `config/tenants/{slug}.php` con `habilitado => false`.
         * `implementacion`: clave de variante en código (`estandar` | `sanfranciscoasis`).
         */
        'actualizacion_datos' => [
            'habilitado' => true,
            'implementacion' => 'estandar',
        ],

        /**
         * Impresión de ficha de matrícula en PDF (portal familia).
         * `implementacion`: clave de variante en código (ej. sanfranciscoasis).
         */
        'ficha_matricula' => [
            'habilitado' => false,
            'implementacion' => null,
        ],

        /**
         * Listado de cuotas pendientes y comprobante de pago (portal familia).
         * `implementacion`: clave de variante en código (ej. sanfranciscoasis).
         */
        'aranceles_escolares' => [
            'habilitado' => false,
            'implementacion' => null,
            /**
             * Banner + PDF de adhesión a débito automático (opcional, por tenant).
             * `banner`: ruta bajo `public/` servida con asset().
             * `formulario_pdf`: ruta bajo `resources/` servida por ruta autenticada.
             */
            'debito_automatico' => [
                'banner' => null,
                'formulario_pdf' => null,
            ],
            /**
             * Banner de medios de pago debajo del listado de cuotas (opcional, por tenant).
             * `banner`: ruta bajo `public/` servida con asset().
             * `url`: enlace al hacer clic en la imagen.
             */
            'medios_pago' => [
                'banner' => null,
                'url' => null,
            ],
            /** URL del servicio SIRO para QR (legacy obtenerQR). Opcional. */
            'siro_qr_url' => null,
        ],
    ],

    /**
     * Boletín / consulta de calificaciones (secundario).
     * Activar solo en `config/tenants/{slug}.php` para colegios que usan régimen TM.
     */
    'boletin' => [
        'mostrar_tercer_materia' => false,
    ],

    /**
     * Menú de Docentes: Cuaderno de Seguimiento Áulico (secundario).
     * Activar solo en `config/tenants/{slug}.php` para colegios que lo usan.
     */
    'portal_docente' => [
        'cuaderno_seguimiento_aulico' => false,
    ],

    /**
     * Menú de Secretaría — impresión de ficha de matrícula por curso (PDF en lote).
     * `implementacion`: variante en código (`sanfranciscoasis` = con aceptación de documentos;
     * `montecristo` = solicitud de matrícula solo datos).
     */
    'secretaria' => [
        'ficha_matricula' => [
            'habilitado' => false,
            'implementacion' => null,
        ],
    ],

    /**
     * Solicitud de evaluación (tabla evaluac) — Menú de Secretaría y Menú de Docentes.
     * Sin permiso IA: visible para todo el personal en secundario (tenant activo).
     * Activar solo en `config/tenants/{slug}.php` para colegios que lo usan.
     */
    'modulos' => [
        'solicitud_evaluacion' => false,
    ],

    /**
     * Descarga pública de programas de examen (sin login).
     * Lee las tablas legacy `pp{año}` (ej. pp2020) y enlaza los PDF alojados
     * en un servidor de archivos externo.
     *
     * Por defecto deshabilitado para todos los colegios. Activar solo en
     * `config/tenants/{slug}.php` con `habilitado => true` y el resto de claves.
     *
     *   - `habilitado`: registra la ruta pública /programas-examen (ausente si false).
     *   - `glo_codcol`: código de colegio usado en la ruta de archivos (fallback: slug).
     *   - `nivel`: segmento de carpeta del servidor de archivos (legacy `secu`).
     *   - `base_url`: dominio y ruta base del repositorio de archivos del colegio
     *     (sin barra final; ej. `https://sistemasescolares1.com/archivos` para NSSC).
     *   - `anios`: años ofrecidos en el menú; deben existir como tablas `pp{año}`.
     */
    'programas_examen' => [
        'habilitado' => false,
        'glo_codcol' => null,
        'nivel' => 'secu',
        'base_url' => 'https://sistesco.site/archivos',
        'anios' => [],
    ],

    /**
     * Plantillas de cuotas — fórmulas al crear una cuota («Valores por defecto del sistema»).
     * Corresponden a: hasta 1.er venc., 1.º→2.º, 2.º→3.º y después del 3.er vencimiento.
     * Override parcial en `config/tenants/{slug}.php` (solo claves que difieran).
     */
    'cuotas' => [
        'formulas_iniciales_plantilla' => [
            'importe' => 0.0,
            'signo1v' => '+',
            'valor1v' => 0.0,
            'porcan1v' => '%',
            'signo2v' => '+',
            'valor2v' => 0.0,
            'porcan2v' => '%',
            'signo3v' => '+',
            'valor3v' => 0.0,
            'porcan3v' => '%',
            'signo4v' => '+',
            'valor4v' => 0.0,
            'porcan4v' => '%',
        ],
    ],

];
