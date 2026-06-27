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
     * Login de Secretaría / Docentes (`/loginUsuario`).
     * `niveles_ids`: IDs de `niveles` visibles en el desplegable. `null` = todos los registros de la tabla.
     * Override en `config/tenants/{slug}.php` (ej. `[1, 2, 3, 5]` sin terciario; agregar `6` si el colegio usa Adultos).
     */
    'login' => [
        'niveles_ids' => null,
    ],

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
        ],

        /**
         * Cuaderno de comunicados institucional (portal familia).
         * Incluye bandeja, nuevo comunicado, push y preferencias de contacto.
         * Default habilitado; desactivar en `config/tenants/{slug}.php` con `habilitado => false`.
         * `niveles_deshabilitados`: IDs de `niveles` sin módulo (p. ej. `[2]` solo primario).
         */
        'comunicaciones' => [
            'habilitado' => true,
            'niveles_deshabilitados' => [],
        ],

        /**
         * Boletín IPE / síntesis y calificaciones por etapa (portal familia, nivel primario).
         * Requiere `boletin_primario.ipe_implementacion` con selector de etapa (p. ej. montecristo).
         * Default deshabilitado; activar en `config/tenants/{slug}.php`.
         */
        'boletin_ipe_primario' => [
            'habilitado' => false,
        ],

        /**
         * Informe de progreso escolar por etapa (portal familia, nivel inicial).
         * Usa el mismo PDF que secretaría/docentes con marca «SIN VALOR LEGAL».
         * Default deshabilitado; activar en `config/tenants/{slug}.php`.
         */
        'informe_progreso_inicial' => [
            'habilitado' => false,
        ],

        /**
         * Consulta de calificaciones en autogestión (primario: boletín IPE; secundario: consulta PDF).
         * Default habilitado; desactivar en `config/tenants/{slug}.php` con `habilitado => false`.
         * `niveles_habilitados`: IDs de `niveles` con el módulo (p. ej. `[3]` solo secundario).
         * Si está habilitado y la lista está vacía, aplica a todos los niveles.
         */
        'consulta_calificaciones' => [
            'habilitado' => true,
            'niveles_habilitados' => [],
        ],

        /**
         * Informe de inasistencias en PDF (portal familia).
         * Default habilitado; desactivar en `config/tenants/{slug}.php` con `habilitado => false`.
         * `niveles_habilitados`: IDs de `niveles` con el módulo. Vacío = todos los niveles.
         * `niveles_deshabilitados`: IDs de `niveles` sin el módulo (p. ej. `[1, 2]` inicial y primario).
         */
        'informe_inasistencias' => [
            'habilitado' => true,
            'niveles_habilitados' => [],
            'niveles_deshabilitados' => [],
        ],

        /**
         * Horario de clase en PDF (portal familia).
         * Default deshabilitado; activar en `config/tenants/{slug}.php`.
         * `niveles_habilitados`: IDs de `niveles` con el ítem (p. ej. `[3]` solo secundario).
         * Si está habilitado y la lista está vacía, aplica a todos los niveles.
         */
        'horario_clase' => [
            'habilitado' => false,
            'niveles_habilitados' => [],
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
     * Boletín IPE — nivel primario.
     * `implementacion`: `estandar` (A4 vertical) | `sanjose` (A4 apaisado, matriz) | `montecristo` (extracurriculares institucionales).
     */
    'boletin_primario' => [
        'ipe_implementacion' => 'estandar',
        'director_firma' => '',
        /** Texto del ítem en CALIFICACIONES (Primario) del Menú de Secretaría. */
        'menu_etiqueta_boletin_ipe' => 'Boletines IPE',
    ],

    /**
     * Calificaciones primario — variantes de carga/planilla por `implementacion`.
     * Claves conocidas: `montecristo` (grilla ic01–ic03, parciales por materia, planilla TCPDF).
     * La implementación es reutilizable entre colegios; no confundir con `TENANT_SLUG`.
     */
    'calificaciones_primario' => [
        'carga_estudiante' => [
            'implementacion' => null,
        ],
        'carga_materia' => [
            'implementacion' => null,
        ],
        'planilla' => [
            'implementacion' => null,
        ],
    ],

    /**
     * Menú de Docentes — ítems opcionales por nivel (sin permiso_ia en sidebar).
     * Cada ítem de primario exige además `calificaciones_primario.{modulo}.implementacion`.
     */
    'portal_docente' => [
        'menu' => [
            'inicial' => [
                'indicadores' => false,
                'observaciones' => false,
                'observaciones_materia' => false,
                'informe_progreso' => false,
                'listado_estudiantes' => true,
                'recursos_didacticos_nueva_reserva' => false,
                'recursos_didacticos_listado' => false,
            ],
            'primario' => [
                'carga_estudiante' => false,
                'carga_materia' => false,
                'boletin_ipe' => false,
                'planilla' => false,
                'listado_estudiantes' => true,
                'recursos_didacticos_nueva_reserva' => false,
                'recursos_didacticos_listado' => false,
            ],
            'secundario' => [
                'calificaciones' => true,
                'solicitud_evaluacion' => false,
                'cuaderno_seguimiento_aulico' => false,
                'listado_estudiantes' => true,
                'recursos_didacticos_nueva_reserva' => false,
                'recursos_didacticos_listado' => false,
            ],
        ],
        /** @deprecated Preferir `menu.secundario.cuaderno_seguimiento_aulico`. Se mantiene como fallback. */
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
        /**
         * Informe de inasistencias por curso (Menú de Secretaría → ASISTENCIA ESTUDIANTES).
         * `niveles_deshabilitados`: IDs de `niveles` sin ítem ni PDF (p. ej. `[1, 2]` inicial y primario).
         */
        'informe_inasistencias' => [
            'niveles_deshabilitados' => [],
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
        /**
         * Interpretación de valor2v..valor4v con signo + y porcan % en mora:
         * - diario: % por día de mora (se multiplica por los días del tramo).
         * - total: % fijo sobre el saldo en ese tramo (sin multiplicar por días).
         * Override por colegio en config/tenants/{slug}.php.
         */
        'interes_mora_modo' => 'diario',

        /**
         * Medio de pago SIRO (código de pago electrónico, QR y código de barras en cupones).
         * Activar en `config/tenants/{slug}.php` cuando el colegio cobra por SIRO.
         */
        'siro' => [
            'habilitado' => false,
            /** URL del servicio legacy obtenerQR (solo si `habilitado` es true). */
            'qr_url' => null,
            /**
             * Descarga de rendición SIRO — alta de planilla.
             * `canales_planilla`: abrevs o nombres de cuotastipopago permitidos al crear planilla.
             * Vacío = todos los medios con abrev en BD.
             */
            'descarga_rendicion' => [
                'canales_planilla' => [],
            ],
        ],

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

        /**
         * Facturación AFIP al imputar pago (Aranceles por estudiante).
         * Activar solo en `config/tenants/{slug}.php` para colegios que facturan con WSFE.
         */
        'facturacion_afip' => [
            'habilitado' => false,
            'cert_usuario_id' => null,
            'cert_key' => null,
            'cert_crt' => null,
            /** Respaldo si no están en `ento` (Parámetros del sistema). Prioridad: ento → tenant. */
            'cbte_tipo' => 15,
            'concepto' => 2,
            'doc_tipo' => 96,
            'nota_credito_tipo' => 12,
            'cbte_tipo_asociado' => 15,
            'produccion' => true,
            /** Si true, no llama a AFIP (cualquier entorno). Para pruebas en un tenant concreto. */
            'simular' => false,
            /** En local, simula salvo que el tenant declare `simular => false` explícito. */
            'simular_local' => true,
            'condicion_iva_alumno' => 'Consumidor Final',
            'condicion_iva_receptor_id' => 5,
            'condicion_venta' => 'contado',
        ],
    ],

];
