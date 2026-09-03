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
     * Consulta de deuda en Áulica (API externa) al imprimir ficha de matrícula
     * o entrar a actualización de datos. Default off; Montecristo lo activa.
     * Credenciales: AULICA_USERNAME / AULICA_PASSWORD / AULICA_CODIGO en .env.
     * `ambiente`: test | produccion (AULICA_AMBIENTE en .env pisa este valor).
     */
    'aulica_deuda' => [
        'habilitado' => false,
        'ambiente' => 'test',
        'bloquear_autogestion' => false,
        'cache_saldos_segundos' => 300,
    ],

    /**
     * Logo institucional en sidebar, login y dashboard.
     * `horizontal`: apaisado (default). `emblema`: sello circular o cuadrado (EPQ, etc.).
     */
    'institucional' => [
        'logo_forma' => 'horizontal',
    ],

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
         * Visibilidad por nivel: `ento.verDatosFicha` (Parametrización → Parámetros; mismo flag que ficha).
         * `foto_carnet`: si la familia puede subir foto carnet en este formulario.
         * Independiente de la solapa del ABM de legajos (Secretaría). Default off;
         * activar en `config/tenants/{slug}.php` (sigue haciendo falta la solapa).
         * `requiere_documentos`: solo aplica a `implementacion = sanfranciscoasis`.
         * Si true, el formulario muestra y exige los cuatro PDF institucionales.
         * Si false, se mantiene el formulario SFA sin ese bloque (p. ej. EPQ).
         */
        'actualizacion_datos' => [
            'habilitado' => true,
            'implementacion' => 'estandar',
            'foto_carnet' => false,
            'requiere_documentos' => true,
        ],

        /**
         * Impresión de ficha de matrícula en PDF (portal familia).
         * `implementacion`: clave de variante en código (ej. sanfranciscoasis).
         * Visibilidad por nivel: `ento.verDatosFicha` (Parametrización → Parámetros).
         */
        'ficha_matricula' => [
            'habilitado' => false,
            'implementacion' => null,
        ],

        /**
         * Ítem «Inicio» del sidebar (escritorio).
         * Default habilitado; ocultar por nivel en `config/tenants/{slug}.php`.
         */
        'menu_inicio' => [
            'habilitado' => true,
            'niveles_deshabilitados' => [],
        ],

        /**
         * Listado de cuotas pendientes y comprobante de pago (portal familia).
         * `implementacion`: clave de variante en código:
         *   - `sanfranciscoasis` — UI SE (hero, historial, totales, banners opcionales).
         *   - `gestion_aranceles` — UI legacy (CPE, botón SIRO Roela, tabla compacta).
         */
        'aranceles_escolares' => [
            'habilitado' => false,
            'implementacion' => null,
            /**
             * Botón de pagos SIRO (solo variante `gestion_aranceles`).
             */
            'boton_pagos' => [
                'url' => 'https://siropagos.bancoroela.com.ar',
            ],
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
         * Incluye bandeja, nuevo comunicado y notificaciones push.
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
         * Boletín (Prim) EPQ — portada y calificaciones (portal familia, nivel primario).
         * Requiere `calificaciones_primario.boletin_prim.implementacion` = `epq`.
         * Default deshabilitado; activar en `config/tenants/{slug}.php`.
         */
        'boletin_prim_epq' => [
            'habilitado' => false,
        ],

        /**
         * Informe EPQ secundario — consulta de calificaciones (portal familia, nivel secundario).
         * Requiere `calificaciones_secundario.boletin.implementacion` = `epq`.
         * Default deshabilitado; activar en `config/tenants/{slug}.php`.
         */
        'boletin_sec_epq' => [
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
         * Informes pedagógicos inicial SFQ (diagnóstico, 1º/2º etapa y Bellas Artes) en autogestión familia.
         * Requiere `calificaciones_inicial.boletin.implementacion` = `sfq`.
         * Default deshabilitado; activar en `config/tenants/{slug}.php`.
         */
        'boletin_inicial_sfq' => [
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

        /**
         * Certificado Único de Salud (C.U.S.) en PDF (portal familia).
         * Default deshabilitado; activar en `config/tenants/{slug}.php`.
         * `niveles_habilitados`: IDs de `niveles` con el ítem. Vacío = todos los niveles.
         * `niveles_deshabilitados`: IDs de `niveles` sin el módulo.
         */
        'cus' => [
            'habilitado' => false,
            'niveles_habilitados' => [],
            'niveles_deshabilitados' => [],
        ],

        /**
         * Informe de Salud Anual (I.S.A.) en PDF (portal familia).
         * Default deshabilitado; activar en `config/tenants/{slug}.php`.
         * `niveles_habilitados`: IDs de `niveles` con el ítem. Vacío = todos los niveles.
         * `niveles_deshabilitados`: IDs de `niveles` sin el módulo.
         */
        'isa' => [
            'habilitado' => false,
            'niveles_habilitados' => [],
            'niveles_deshabilitados' => [],
        ],

        /**
         * Constancia de libre deuda (PDF) en el Menú de Alumnos.
         * Consulta Áulica; solo se emite si no hay deuda del estudiante ni del grupo familiar.
         * `lugar`: ciudad en el pie (p. ej. Monte Cristo). Vacío = localidad de `ento`.
         * `firma` / `sello`: rutas relativas a `public/` (PNG/JPG). Si faltan, no se dibujan.
         */
        'libre_deuda' => [
            'habilitado' => false,
            'niveles_habilitados' => [],
            'niveles_deshabilitados' => [],
            'lugar' => '',
            'firma' => null,
            'sello' => null,
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
     * `ipe_implementacion`: `estandar` (A4 vertical) | `sanjose` (A4 apaisado, matriz) | `montecristo` (extracurriculares) | `caixalsf` (A4 vertical, ciclo + inasistencias matrícula).
     */
    'boletin_primario' => [
        'ipe_implementacion' => 'estandar',
        'director_firma' => '',
        /** Texto del ítem en CALIFICACIONES (Primario) del Menú de Secretaría. */
        'menu_etiqueta_boletin_ipe' => 'IPE (Informe de Progreso Escolar)',
        /**
         * Membrete circular de la portada — Boletín (Prim), implementación epq.
         * Ruta relativa a `public/` (p. ej. `img/tenants/{slug}/boletin-prim-membrete.png`).
         * Cada colegio con variante `epq` define el suyo en `config/tenants/{slug}.php`.
         */
        'epq_membrete_portada' => null,
    ],

    /**
     * Calificaciones primario — variantes de carga/planilla por `implementacion`.
     * Claves conocidas: `montecristo`, `epq` (Escuelas Pías Quimilí).
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
        'boletin_prim' => [
            'implementacion' => null,
        ],
    ],

    /**
     * Calificaciones secundario — variantes por `implementacion`.
     * Claves conocidas: `estandar`, `epq` (Escuelas Pías Quimilí).
     */
    'calificaciones_secundario' => [
        'carga' => [
            'implementacion' => null,
        ],
    ],

    /**
     * Exámenes (previas) — actas volantes.
     * `acta_volante_previos_modalidad`:
     * - `curso_seccion`: una acta por materia del plan + condición + curso/sección (`cursos.Id`).
     * - `curso`: una acta por materia del plan + condición (reúne secciones del mismo año de plan).
     * Override en `config/tenants/{slug}.php` cuando el colegio use otra modalidad.
     */
    'examenes' => [
        'acta_volante_previos_modalidad' => 'curso_seccion',
    ],

    /**
     * Calificaciones inicial — variantes por `implementacion`.
     * Claves conocidas: `estandar` (Montecristo / flujo legacy), `sfq` (en desarrollo), `montecristo` (PDF sin aprendizajes ni cierre).
     */
    'calificaciones_inicial' => [
        'carga_notas' => [
            'implementacion' => null,
        ],
        'indicadores' => [
            'implementacion' => null,
        ],
        'observaciones' => [
            'implementacion' => null,
        ],
        'observaciones_materia' => [
            'implementacion' => null,
        ],
        'informe_progreso' => [
            'implementacion' => null,
        ],
        'boletin' => [
            'implementacion' => null,
        ],
    ],

    /**
     * Boletín / Informe Pedagógico — nivel inicial.
     * `membrete`: ruta relativa a `public/` (p. ej. `img/tenants/{slug}/boletin-inic-membrete.png`).
     * `titulo_institucion`: encabezado del PDF; si es null, usa `ento.insti`.
     */
    'boletin_inicial' => [
        'membrete' => null,
        'titulo_institucion' => null,
    ],

    /**
     * Menú de Docentes — ítems opcionales por nivel (sin permiso_ia en sidebar).
     * Cada ítem de primario exige además `calificaciones_primario.{modulo}.implementacion`.
     */
    'portal_docente' => [
        'menu' => [
            'inicial' => [
                'carga_notas' => false,
                'indicadores' => false,
                'observaciones' => false,
                'observaciones_materia' => false,
                'informe_progreso' => false,
                'boletin' => false,
                'listado_estudiantes' => true,
                'listado_estudiantes_formato' => true,
                'recursos_didacticos_nueva_reserva' => false,
                'recursos_didacticos_listado' => false,
                'proyectos_extracurriculares' => true,
                'calendario_escolar' => true,
                'libro_de_temas' => false,
            ],
            'primario' => [
                'carga_estudiante' => false,
                'carga_materia' => false,
                'boletin_ipe' => false,
                'planilla' => false,
                'listado_estudiantes' => true,
                'listado_estudiantes_formato' => true,
                'recursos_didacticos_nueva_reserva' => false,
                'recursos_didacticos_listado' => false,
                'proyectos_extracurriculares' => true,
                'calendario_escolar' => true,
                'libro_de_temas' => false,
            ],
            'secundario' => [
                'calificaciones' => true,
                'solicitud_evaluacion' => false,
                'cuaderno_seguimiento_aulico' => false,
                'listado_estudiantes' => true,
                'listado_estudiantes_formato' => true,
                'recursos_didacticos_nueva_reserva' => false,
                'recursos_didacticos_listado' => false,
                'proyectos_extracurriculares' => true,
                'calendario_escolar' => true,
                'libro_de_temas' => false,
            ],
        ],
        /** @deprecated Preferir `menu.secundario.cuaderno_seguimiento_aulico`. Se mantiene como fallback. */
        'cuaderno_seguimiento_aulico' => false,
    ],

    /**
     * Menú de Secretaría — impresión de ficha de matrícula por curso (PDF en lote).
     * `implementacion`: variante en código (`sanfranciscoasis` = con aceptación de documentos;
     * `montecristo` = solicitud de matrícula solo datos; `sanjose` = solicitud A4 San José;
     * `iess` = ficha con autorización de imágenes IESS VCP).
     * `niveles_deshabilitados`: IDs de `niveles` sin ítem ni PDF (p. ej. `[1, 2]` solo secundario).
     */
    'secretaria' => [
        'ficha_matricula' => [
            'habilitado' => false,
            'implementacion' => null,
            'niveles_deshabilitados' => [],
        ],
        /**
         * Informe de inasistencias por curso (Menú de Secretaría → ASISTENCIA ESTUDIANTES).
         * `niveles_deshabilitados`: IDs de `niveles` sin ítem ni PDF (p. ej. `[1, 2]` inicial y primario).
         */
        'informe_inasistencias' => [
            'niveles_deshabilitados' => [],
        ],
        /**
         * Registros TEA — tablas legacy únicas `reinco2025` y `reinco2025_tipo` (no varían por ciclo).
         *
         * - `implementacion`: generador TCPDF por tenant. Default `caixalsf` (Res. 11/25).
         *   Solo Montecristo usa `montecristo` (Res. 188/18); override en `config/tenants/montecristo.php`.
         * - `plantillas_pdf`: PDF estático por id de reinco2025_tipo (1–5), ruta relativa a resources/.
         *   Si hay plantilla para un tipo, tiene prioridad sobre `implementacion`.
         */
        'tea_registros' => [
            'implementacion' => 'caixalsf',
            'plantillas_pdf' => [
                1 => null,
                2 => null,
                3 => null,
                4 => null,
                5 => null,
            ],
        ],
        /**
         * Consulta de calificaciones (Menú de Secretaría → CALIFICACIONES Secundario).
         * Default habilitado; desactivar en `config/tenants/{slug}.php` con `habilitado => false`.
         */
        'consulta_calificaciones' => [
            'habilitado' => true,
        ],
        /**
         * Grupo sidebar «CALIFICACIONES (Inicial)» — flujo estándar (Montecristo).
         * Desactivar en tenants con variantes propias hasta registrar rutas/Livewire.
         */
        'calificaciones_inicial' => [
            'habilitado' => true,
        ],
        /** Grupo sidebar inicial SFQ — carga ic01–ic06 (desactivar menú estándar en el tenant). */
        'calificaciones_inicial_sfq' => [
            'habilitado' => false,
        ],
    ],

    /**
     * Solicitud de evaluación (tabla evaluac) — Menú de Secretaría y Menú de Docentes.
     * Sin permiso IA: visible para todo el personal en secundario (tenant activo).
     * Activar solo en `config/tenants/{slug}.php` para colegios que lo usan.
     */
    'modulos' => [
        'solicitud_evaluacion' => false,
        /** Libro de temas (tabla `librodetemas`). Default off; activar por tenant. */
        'libro_de_temas' => false,
    ],

    /**
     * Descarga pública de programas de examen (sin login).
     * Lee programas aprobados desde la tabla `doc_pp` (tipo prog, aprobado = 1).
     *
     * Por defecto deshabilitado. Activar en `config/tenants/{slug}.php` con `habilitado => true`.
     *
     * Resuelto en código (no va en tenant):
     *   - `ento.codCol` → segmento de colegio en la ruta de archivos
     *   - nivel pedagógico → inic / prim / secu / terc
     *
     * Opcional en tenant:
     *   - `base_url`: solo si los PDF se sirven desde otro dominio (legado).
     *     Si falta, la URL pública es `{APP_URL}/archivos/...` (carpeta public/archivos del sistema).
     *   - `anios`: años lectivos del selector público (ej. `[2026, 2025, 2024]`).
     *     Si está vacío, fallback a los años de `ento.idTerlecVerNotas` por nivel.
     */
    'programas_examen' => [
        'habilitado' => false,
        'base_url' => null,
        'anios' => [],
    ],

    /**
     * Módulo nuevo — planificaciones y programas (tabla doc_pp).
     * Activar en `config/tenants/{slug}.php` → `doc_pp.habilitado`.
     */
    'doc_pp' => [
        'habilitado' => false,
    ],

    /**
     * Cooperadora escolar — ingresos, egresos y recibos.
     * El correo de recibos es independiente del mailer pedagógico (MAIL_* / cuaderno de comunicados).
     */
    'cooperadora' => [
        /**
         * Recibos de ingreso (origen estudiantes) — correo al pagador con PDF adjunto.
         * Usa mailer `cooperadora` (COOP_MAIL_* en .env), distinto del cuaderno de comunicados (MAIL_*).
         * Override parcial en `config/tenants/{slug}.php` → `cooperadora.recibo_email`.
         */
        'recibo_email' => [
            /** Si false, no se intenta envío (ni simulado). */
            'habilitado' => true,
            /**
             * true: registra estado simulado y log, sin SMTP.
             * Poner false en el tenant al activar envío real (requiere COOP_MAIL_* en .env).
             */
            'simulado' => env('COOP_RECIBO_EMAIL_SIMULADO', true),
            /** Clave en config/mail.php → mailers.* */
            'mailer' => 'cooperadora',
            'asunto' => 'Recibo de pago',
            /**
             * Remitente visible. Dirección y credenciales SMTP: COOP_MAIL_* en .env.
             * Nombre: coop_config.nombre_institucion; fallback tenant / COOP_MAIL_FROM_NAME si vacío.
             */
            'from_name' => null,
        ],
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
         * Maquetación TCPDF del cupón de pago (aranceles).
         * Claves: `sanfranciscoasis` (default) | `epq` (Escuelas Pías — dos talonarios por hoja).
         */
        'comprobante_pago' => [
            'implementacion' => 'sanfranciscoasis',
        ],

        /**
         * Comprobante PDF tras imputar un pago (Gestión de aranceles).
         * `dos_copias_por_hoja`: dos talonarios idénticos en A4 para cortar y entregar uno al pagador.
         */
        'comprobante_imputacion' => [
            'dos_copias_por_hoja' => false,
        ],

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
         * Facturación AFIP (WSFE).
         * Activar solo en `config/tenants/{slug}.php`.
         *
         * `modo`:
         * - `devengamiento` (default): facturación masiva manual al devengar cuotas.
         * - `pago`: emite al imputar pago (legacy).
         */
        'facturacion_afip' => [
            'habilitado' => false,
            /** @var 'devengamiento'|'pago' */
            'modo' => 'devengamiento',
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

    /**
     * Registro de asistencia (Menú de Secretaría → ASISTENCIA ESTUDIANTES).
     * `por_nivel`: clave = `niveles.id`, valor = `con_datos` | `sin_datos`.
     * Default implícito si falta la clave: `con_datos` en todos los niveles.
     * Excepción típica: Montecristo (inicial/primario → `sin_datos`) en `config/tenants/montecristo.php`.
     */
    'registro_asistencia' => [
        'por_nivel' => [
            // 1 => 'sin_datos',
            // 2 => 'sin_datos',
            // 3 => 'con_datos',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Parte diario del preceptor — modelo de PDF
    |--------------------------------------------------------------------------
    |
    | `implementacion`:
    |   - `estandar` — DomPDF A4 / media hoja (default).
    |   - `sanfranciscoasis` — TCPDF Legal: listado de alumnos regulares + firmas por hora.
    | Override solo en `config/tenants/{slug}.php` cuando difiere.
    */
    'parte_diario' => [
        'implementacion' => 'estandar',
    ],

    /*
    |--------------------------------------------------------------------------
    | Seguimiento disciplinario — comunicado a la familia (PDF)
    |--------------------------------------------------------------------------
    |
    | `implementacion`:
    |   - `estandar` — DomPDF legacy (dos troqueles + acta en hoja aparte).
    |   - `iess` — TCPDF A4: textos y firmas del modelo IESS (ScriptCase).
    | El recuadro de totales (antes / después de la sanción) usa
    | `sanciontipo.enResumenComunicado` en ambas variantes.
    | Override solo en `config/tenants/{slug}.php` cuando difiere.
    */
    'seguimiento' => [
        'comunicado' => [
            'implementacion' => 'estandar',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ARCA — consultas al padrón tributario
    |--------------------------------------------------------------------------
    */
    'arca' => [
        'padron_a13' => [
            /** Activar en `config/tenants/{slug}.php` o cuando hay certificados en ento. */
            'habilitado' => false,
            'produccion' => true,
            'simular' => false,
            'simular_local' => true,
        ],
    ],

];
