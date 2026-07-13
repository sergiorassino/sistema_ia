<?php

namespace App\Support\Arca;

use TCPDF;

/**
 * Guía ARCA — configuración de facturación electrónica para colegios (A4, TCPDF, paleta SE).
 *
 * Solo trámites en el portal ARCA: servicios, certificados, Web Services y punto de venta.
 */
final class GuiaConfiguracionArcaFacturacionTcpdf extends TCPDF
{
    use ArcaGuiaTcpdfLayout;

    private function __construct()
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
    }

    /**
     * @param  array{colegio:?string}  $ctx
     */
    public static function generar(array $ctx = []): self
    {
        $colegio = isset($ctx['colegio']) && is_string($ctx['colegio']) && trim($ctx['colegio']) !== ''
            ? trim($ctx['colegio'])
            : null;

        $meta = [
            'titulo' => 'Configuración ARCA para facturación electrónica',
            'subtitulo' => 'Guía para colegios sin FE habilitada · Certificados, Web Services y punto de venta',
            'version' => '1.3',
            'generado' => now()->format('d/m/Y'),
            'colegio' => $colegio,
        ];

        $pdf = new self();
        $pdf->guiaInicializar($pdf, $meta);
        $pdf->guiaCrearLinks($pdf, [
            'requisitos',
            'panorama',
            'servicios',
            'certificado',
            'webservices',
            'puntoventa',
            'homologacion',
            'datosfiscales',
            'padron',
            'checklist',
            'entrega',
            'errores',
            'contador',
        ]);

        $pdf->AddPage();
        $pdf->guiaRenderPortada($pdf, 'Solo trámites en ARCA');

        $pdf->AddPage();
        $pdf->guiaRenderIndice($pdf, [
            ['1. Antes de empezar', $pdf->guiaLinkId('requisitos')],
            ['2. Panorama general', $pdf->guiaLinkId('panorama')],
            ['3. Adherir servicios en ARCA', $pdf->guiaLinkId('servicios')],
            ['4. Certificado digital', $pdf->guiaLinkId('certificado')],
            ['5. Autorizar Web Services', $pdf->guiaLinkId('webservices')],
            ['6. Punto de venta Web Services', $pdf->guiaLinkId('puntoventa')],
            ['7. Homologación (recomendado)', $pdf->guiaLinkId('homologacion')],
            ['8. Datos fiscales del emisor', $pdf->guiaLinkId('datosfiscales')],
            ['9. Consulta CUIT por DNI', $pdf->guiaLinkId('padron')],
            ['10. Checklist final', $pdf->guiaLinkId('checklist')],
            ['11. Qué entregar al implementador', $pdf->guiaLinkId('entrega')],
            ['12. Problemas frecuentes', $pdf->guiaLinkId('errores')],
            ['13. Delegación al contador', $pdf->guiaLinkId('contador')],
        ]);

        // Contenido continuo: sin AddPage por sección (solo portada + índice arriba).
        $pdf->AddPage();
        $pdf->guiaRenderSeccion($pdf, '1. Antes de empezar', 'requisitos', function () use ($pdf): void {
            $pdf->guiaP(
                $pdf,
                'Esta guía describe únicamente los trámites que debe realizar el colegio en el portal ARCA ' .
                '(www.arca.gob.ar) para habilitar la facturación electrónica y, opcionalmente, la consulta de ' .
                'CUIT/CUIL por DNI. No incluye la configuración técnica del sistema escolar.',
            );

            $pdf->guiaBox($pdf, 'Requisitos previos', [
                ['CUIT activo', 'Del colegio o entidad que factura (asociación civil, fundación, etc.).'],
                ['Clave Fiscal nivel 3+', 'Verificar en ARCA → Mi cuenta → Datos personales.'],
                ['Quién opera', 'Representante legal, apoderado con Clave Fiscal o contador delegado.'],
                ['Condición IVA', 'Exento, Monotributo o Responsable Inscripto — define el tipo de punto de venta.'],
                ['Ayuda técnica mínima', 'Alguien que genere la clave privada (.key) y el pedido CSR fuera de ARCA.'],
            ]);

            $pdf->guiaCallout(
                $pdf,
                'Colegios privados — RG 5824/2026',
                'Desde el 1/07/2026 muchas instituciones educativas privadas quedan obligadas a emitir ' .
                'comprobantes electrónicos con CAE. Conviene iniciar estos trámites con anticipación: ' .
                'certificados y autorizaciones pueden demorar.',
            );
        });

        $pdf->guiaRenderSeccion($pdf, '2. Panorama general', 'panorama', function () use ($pdf): void {
            $pdf->guiaP(
                $pdf,
                'Para que el sistema escolar pueda facturar y consultar el padrón, ARCA debe tener configurados ' .
                'cuatro bloques en orden. Cada uno depende del anterior.',
            );

            $pdf->guiaFlujoPasos($pdf, [
                'Adherir los servicios administrativos en ARCA (certificados, puntos de venta, relaciones).',
                'Generar y descargar el certificado digital de producción (.key + .crt).',
                'Autorizar los Web Services al certificado: wsfe (facturar) y ws_sr_padron_a13 (CUIT por DNI).',
                'Crear un punto de venta de tipo Web Services y anotar su número.',
            ]);

            $pdf->guiaBox($pdf, 'Servicios Web que usa el sistema', [
                ['wsfe', 'Facturación Electrónica: emisión y consulta de comprobantes con CAE.'],
                ['ws_sr_padron_a13', 'Padrón Alcance 13: consulta CUIT/CUIL asociados a un DNI.'],
                ['WSAA', 'Autenticación (automática): cada servicio requiere autorización aparte.'],
            ]);

            $pdf->guiaCallout(
                $pdf,
                'Importante',
                'Autorizar wsfe (facturación) NO habilita automáticamente ws_sr_padron_a13 (consulta por DNI). ' .
                'Cada servicio requiere una relación nueva en el Administrador de Relaciones.',
            );
        });

        $pdf->guiaRenderSeccion($pdf, '3. Adherir servicios en ARCA', 'servicios', function () use ($pdf): void {
            $pdf->guiaNumbered($pdf, [
                'Ingresar a www.arca.gob.ar con CUIT y Clave Fiscal.',
                'Abrir Administrador de Relaciones de Clave Fiscal.',
                'Clic en Adherir servicio (o Nueva adhesión).',
                'Elegir organismo ARCA (puede figurar también como AFIP).',
                'Adherir cada servicio de la tabla inferior y confirmar.',
                'Si ARCA lo pide, cerrar sesión y volver a ingresar para que aparezcan en el menú.',
            ]);

            $pdf->guiaBox($pdf, 'Servicios a adherir', [
                ['Certificados Digitales', 'Crear el certificado que identifica al sistema ante ARCA.'],
                ['Puntos de Venta y Domicilios', 'Dar de alta el punto de venta Web Services.'],
                ['Administrador de Relaciones', 'Si no figura ya, para vincular certificado ↔ Web Services.'],
            ]);
        });

        $pdf->guiaRenderSeccion($pdf, '4. Certificado digital (producción)', 'certificado', function () use ($pdf): void {
            $pdf->guiaP(
                $pdf,
                'El certificado digital es la identidad del sistema ante ARCA. Un mismo certificado sirve ' .
                'para facturar (wsfe) y para consultar el padrón (ws_sr_padron_a13), siempre que cada ' .
                'servicio esté autorizado por separado.',
            );

            $pdf->guiaH2($pdf, '4.1 Crear el alias en ARCA');
            $pdf->guiaNumbered($pdf, [
                'ARCA → Administración de Certificados Digitales.',
                'Agregar alias (nombre interno, ej.: SistemaEscolar, Cuotas2026).',
                'ARCA mostrará instrucciones para generar un CSR (pedido de certificado).',
            ]);

            $pdf->guiaH2($pdf, '4.2 Generar clave y CSR (fuera de ARCA)');
            $pdf->guiaP(
                $pdf,
                'Este paso se realiza en una ventana de comandos del servidor o PC donde quedarán los archivos ' .
                '(no dentro del portal ARCA). Se necesita OpenSSL instalado y accesible desde la terminal.',
            );

            $pdf->guiaBox($pdf, 'Datos a tener a mano antes de ejecutar', [
                ['Alias (CN)', 'El mismo nombre creado en ARCA al agregar el alias (ej. SistemaEscolar).'],
                ['CUIT', '11 dígitos, sin guiones (ej. 30111222333).'],
                ['Organización (O)', 'Nombre de la institución (ej. Instituto Ramallo).'],
                ['Carpeta de trabajo', 'Directorio vacío donde guardar .key y .csr (ej. C:\\afip-cert).'],
            ]);

            $pdf->guiaH2($pdf, '4.2.1 Verificar que OpenSSL está disponible');
            $pdf->guiaP($pdf, 'Abrir CMD o PowerShell, ir a la carpeta de trabajo y ejecutar:');
            $pdf->guiaCodeBlock($pdf, 'Windows — CMD o PowerShell', [
                'cd C:\\afip-cert',
                'openssl version',
            ], 'Debe mostrar la versión de OpenSSL. Si no se reconoce el comando, instalar OpenSSL o usar Git Bash.');

            $pdf->guiaH2($pdf, '4.2.2 Generar la clave privada (.key)');
            $pdf->guiaP($pdf, 'Ejecutar una sola vez por certificado. El archivo .key no debe compartirse ni subirse a ARCA.');
            $pdf->guiaCodeBlock($pdf, 'Comando 1 — clave privada RSA 2048 bits', [
                'openssl genrsa -out privada_prod.key 2048',
            ]);

            $pdf->guiaH2($pdf, '4.2.3 Generar el pedido de certificado (.csr)');
            $pdf->guiaP(
                $pdf,
                'Reemplazar los valores del ejemplo (alias, nombre de institución y CUIT) por los del colegio. ' .
                'El campo serialNumber debe decir literalmente CUIT seguido de un espacio y los 11 dígitos.',
            );
            $pdf->guiaCodeBlock($pdf, 'Comando 2 — CSR para ARCA (producción)', [
                'openssl req -new -key privada_prod.key -out pedido_prod.csr ^',
                '  -subj "/C=AR/O=Instituto Ejemplo/CN=SistemaEscolar/serialNumber=CUIT 30111222333"',
            ], 'En PowerShell usar comilla simple externa: -subj \'/C=AR/O=.../CN=.../serialNumber=CUIT 30111222333\'');

            $pdf->guiaCodeBlock($pdf, 'Linux / macOS / Git Bash (misma operación)', [
                'openssl req -new -key privada_prod.key -out pedido_prod.csr \\',
                '  -subj "/C=AR/O=Instituto Ejemplo/CN=SistemaEscolar/serialNumber=CUIT 30111222333"',
            ]);

            $pdf->guiaH2($pdf, '4.2.4 Comprobar archivos generados');
            $pdf->guiaNumbered($pdf, [
                'En la carpeta de trabajo deben existir privada_prod.key y pedido_prod.csr.',
                'El .csr es el único archivo que se sube a ARCA en el paso 4.3.',
                'El .key queda en el servidor del sistema (carpeta afipSE/cert/…); ARCA no lo devuelve nunca.',
                'Hacer copia de seguridad del .key en lugar seguro: si se pierde, hay que generar certificado nuevo.',
            ]);

            $pdf->guiaCallout(
                $pdf,
                'Importante — seguridad de la clave privada',
                'No enviar el archivo .key por correo ni subirlo a ARCA. Solo el .csr viaja al portal. ' .
                'Quien implemente el sistema escolar necesitará ambos archivos (.key y el .crt que devuelve ARCA).',
            );

            $pdf->guiaH2($pdf, '4.3 Subir CSR y descargar certificado');
            $pdf->guiaNumbered($pdf, [
                'En Administración de Certificados Digitales → alias → Agregar certificado / subir el .csr.',
                'ARCA firma y habilita el certificado.',
                'Descargar el archivo .crt (certificado firmado).',
                'Anotar la fecha de vencimiento: antes de que venza hay que renovar y reautorizar Web Services.',
            ]);

            $pdf->guiaCallout(
                $pdf,
                'Resultado de este paso',
                'Dos archivos para el implementador del sistema: clave privada (.key) y certificado (.crt). ' .
                'Más el alias elegido en ARCA (computador fiscal).',
            );
        });

        $pdf->guiaRenderSeccion($pdf, '5. Autorizar Web Services', 'webservices', function () use ($pdf): void {
            $pdf->guiaP(
                $pdf,
                'Sin este paso el certificado existe pero no puede facturar ni consultar el padrón. ' .
                'Repetir el procedimiento una vez por cada servicio.',
            );

            $pdf->guiaH2($pdf, '5.1 Facturación Electrónica — wsfe (obligatorio)');
            $pdf->guiaNumbered($pdf, [
                'Administrador de Relaciones → Nueva relación.',
                'Representado: CUIT del colegio (el que factura).',
                'Buscar → ARCA → Web Services → Facturación Electrónica (wsfe).',
                'Segundo Buscar → Computador fiscal: alias del certificado del paso 4.',
                'Confirmar dos veces. Verificar relación activa.',
            ]);

            $pdf->guiaH2($pdf, '5.2 Padrón A13 — ws_sr_padron_a13 (consulta CUIT por DNI)');
            $pdf->guiaNumbered($pdf, [
                'Mismo procedimiento: Nueva relación.',
                'ARCA → Web Services → WS de Consulta Padrón Alcance 13 (ws_sr_padron_a13).',
                'Mismo alias de certificado en Computador fiscal.',
                'Confirmar dos veces.',
            ]);

            $pdf->guiaP($pdf, 'Si el colegio no usará la consulta CUIT por DNI, este servicio es opcional.');
        });

        $pdf->guiaRenderSeccion($pdf, '6. Punto de venta Web Services', 'puntoventa', function () use ($pdf): void {
            $pdf->guiaP(
                $pdf,
                'El sistema usa un número de punto de venta que debe existir en ARCA y ser de tipo ' .
                'Web Services — distinto del de Comprobantes en línea o talonarios.',
            );

            $pdf->guiaNumbered($pdf, [
                'ARCA → Administración de Puntos de Venta y Domicilios.',
                'A/B/M de Puntos de Venta → Agregar.',
                'Código: número de 1 a 4 dígitos (ej. 3, 5, 12). Anotarlo: se carga luego en Parámetros del sistema.',
                'Nombre de fantasía: libre (solo referencia interna en ARCA).',
                'Sistema / tipo según condición IVA del colegio (ver tabla).',
                'Confirmar. El punto debe quedar activo.',
            ]);

            $pdf->guiaBox($pdf, 'Tipo de punto de venta según condición IVA', [
                ['Exento en IVA', 'Facturación Electrónica – Exento en IVA – Web Services'],
                ['Monotributo', 'Factura Electrónica – Monotributo – Web Services'],
                ['Responsable Inscripto', 'RECE para aplicativo y Web Services'],
            ]);

            $pdf->guiaCallout(
                $pdf,
                'Atención',
                'No usar un punto de venta de Comprobantes en línea para el sistema automatizado. ' .
                'Puede convivir con otros puntos (caja manual, otro software), cada uno con su número.',
            );
        });

        $pdf->guiaRenderSeccion($pdf, '7. Homologación (recomendado)', 'homologacion', function () use ($pdf): void {
            $pdf->guiaP(
                $pdf,
                'ARCA tiene un ambiente de prueba (homologación) separado del real. Se recomienda probar ' .
                'antes de operar en producción.',
            );

            $pdf->guiaBox($pdf, 'Ambientes', [
                ['Homologación', 'Certificados de prueba vía WSASS (Autogestión Certificados Homologación).'],
                ['Producción', 'Certificados reales vía Administración de Certificados Digitales.'],
            ]);

            $pdf->guiaH2($pdf, 'Flujo sugerido');
            $pdf->guiaNumbered($pdf, [
                'Adherir WSASS en el Administrador de Relaciones (clave fiscal de persona física).',
                'En WSASS: generar certificado de prueba y autorizar wsfe y ws_sr_padron_a13.',
                'Crear punto de venta de prueba en homologación.',
                'Cuando las pruebas estén OK, repetir pasos 4 a 6 en producción con certificado real.',
            ]);

            $pdf->guiaCallout(
                $pdf,
                'No mezclar ambientes',
                'Los certificados de WSASS no sirven en producción y viceversa.',
            );
        });

        $pdf->guiaRenderSeccion($pdf, '8. Datos fiscales del emisor', 'datosfiscales', function () use ($pdf): void {
            $pdf->guiaP(
                $pdf,
                'Para que los comprobantes salgan correctos, conviene verificar en ARCA / Sistema Registral ' .
                'que estén al día los datos del emisor. Deben coincidir con Parámetros del sistema del colegio.',
            );

            $pdf->guiaBullets($pdf, [
                'CUIT de facturación (puede coincidir con el institucional).',
                'Domicilio fiscal registrado ante ARCA.',
                'Condición frente al IVA (Exento, Monotributo, etc.).',
                'Inicio de actividades.',
                'Ingresos brutos (o leyenda Exento si corresponde).',
                'Actividad económica vinculada a servicios educativos / aranceles.',
            ]);
        });

        $pdf->guiaRenderSeccion($pdf, '9. Consulta CUIT por DNI', 'padron', function () use ($pdf): void {
            $pdf->guiaBox($pdf, 'Requisitos en ARCA', [
                ['Servicio', 'ws_sr_padron_a13 autorizado al certificado del colegio.'],
                ['CUIT representada', 'CUIT de la institución que realiza la consulta.'],
                ['Qué devuelve', 'CUIT/CUIL asociados al DNI (puede haber más de uno).'],
            ]);

            $pdf->guiaH2($pdf, 'Limitaciones habituales');
            $pdf->guiaBullets($pdf, [
                'Solo personas que figuren en el padrón de ARCA.',
                'Menores o personas sin trámite tributario pueden no devolver resultado.',
                'La consulta no reemplaza la validación de datos del responsable de pago en el legajo.',
            ]);
        });

        $pdf->guiaRenderSeccion($pdf, '10. Checklist final', 'checklist', function () use ($pdf): void {
            $pdf->guiaNumbered($pdf, [
                'Clave Fiscal nivel 3 o superior activa.',
                'Servicios adheridos: Certificados Digitales, Puntos de Venta, Administrador de Relaciones.',
                'Certificado de producción generado (.key + .crt) y vigente.',
                'Relación Facturación Electrónica (wsfe) → certificado → confirmada.',
                'Relación Padrón A13 (ws_sr_padron_a13) → certificado → confirmada (si aplica).',
                'Punto de venta Web Services dado de alta y activo.',
                'Número de punto de venta y CUIT de facturación anotados.',
                'Datos fiscales del emisor verificados en ARCA.',
            ]);
        });

        $pdf->guiaRenderSeccion($pdf, '11. Qué entregar al implementador', 'entrega', function () use ($pdf): void {
            $pdf->guiaP(
                $pdf,
                'Sin entrar en detalle técnico de programación, el colegio debe entregar al equipo que ' .
                'configura el sistema escolar:',
            );

            $pdf->guiaBox($pdf, 'Datos y archivos', [
                ['Punto de venta', 'Número asignado en ARCA (ej. 5).'],
                ['CUIT facturación', 'CUIT del emisor ante ARCA.'],
                ['Clave privada', 'Archivo .key generado en el paso 4.'],
                ['Certificado', 'Archivo .crt descargado de ARCA.'],
                ['Servicios', 'Confirmación de wsfe activo; ws_sr_padron_a13 activo (si aplica).'],
                ['Condición IVA', 'Ej. Exento, Monotributo, Responsable Inscripto.'],
                ['Ambiente', 'Homologación para pruebas o producción para operar en vivo.'],
            ]);
        });

        $pdf->guiaRenderSeccion($pdf, '12. Problemas frecuentes', 'errores', function () use ($pdf): void {
            $pdf->guiaErrorTable($pdf, [
                ['No autorizado al facturar', 'Certificado no vinculado a wsfe en Administrador de Relaciones.'],
                ['Error de punto de venta', 'PV inexistente, inactivo o tipo incorrecto (debe ser Web Services).'],
                ['Consulta DNI no funciona', 'Falta autorizar ws_sr_padron_a13 para el mismo certificado.'],
                ['Certificado vencido', 'Renovar en Certificados Digitales y reautorizar Web Services.'],
                ['Clave Fiscal insuficiente', 'Subir a nivel 3 (sucursal ARCA o reconocimiento facial).'],
                ['Factura OK, padrón no', 'Solo está autorizado wsfe; falta ws_sr_padron_a13.'],
            ]);
        });

        $pdf->guiaRenderSeccion($pdf, '13. Delegación al contador', 'contador', function () use ($pdf): void {
            $pdf->guiaNumbered($pdf, [
                'El representante del colegio puede delegar en el Administrador de Relaciones los servicios al CUIT del estudio contable.',
                'El contador realiza certificado, punto de venta y autorizaciones en nombre del colegio.',
                'Al finalizar debe entregar al colegio los archivos .key / .crt y el número de punto de venta.',
            ]);

            $pdf->guiaH2($pdf, 'Documentación oficial');
            $pdf->guiaBullets($pdf, [
                'Web Services ARCA: www.arca.gob.ar/ws/',
                'Manual Padrón A13: www.arca.gob.ar/ws/ws-padron-a13/',
                'Certificados producción: www.afip.gob.ar/ws/wsaa/wsaa.obtenercertificado.pdf',
                'WSASS (homologación): www.afip.gob.ar/ws/WSASS/',
            ]);
        });

        return $pdf;
    }
}
