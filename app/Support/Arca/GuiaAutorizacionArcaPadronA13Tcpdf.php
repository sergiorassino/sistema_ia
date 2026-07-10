<?php

namespace App\Support\Arca;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Guía para autorizar ws_sr_padron_a13 en ARCA (A4 vertical, TCPDF, paleta SE).
 */
final class GuiaAutorizacionArcaPadronA13Tcpdf extends TCPDF
{
    private const MARGEN_IZQ = 14.0;

    private const MARGEN_DER = 14.0;

    private const MARGEN_SUP = 12.0;

    private const MARGEN_INF = 12.0;

    /** #40848D — primario SE */
    private const COLOR_PRIMARIO = [64, 132, 141];

    /** #739FA5 — moonstone */
    private const COLOR_SECUNDARIO = [115, 159, 165];

    /** #333333 — jet */
    private const COLOR_TEXTO = [51, 51, 51];

    /** #F4F8F9 — fondo suave */
    private const COLOR_CAJA = [244, 248, 249];

    /** #C1D7DA — light blue */
    private const COLOR_CALLOUT = [193, 215, 218];

    /** @var array{titulo:string,subtitulo:string,version:string,generado:string,colegio:?string} */
    private array $meta;

    /** @var array<string, int> */
    private array $sectionLinks = [];

    /**
     * @param  array{titulo:string,subtitulo:string,version:string,generado:string,colegio:?string}  $meta
     */
    private function __construct(array $meta)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->meta = $meta;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle($meta['titulo']);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, self::MARGEN_INF);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
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
            'titulo' => 'Guía ARCA — Autorizar Padrón Alcance 13',
            'subtitulo' => 'Servicio ws_sr_padron_a13 · Consulta CUIT/CUIL por DNI',
            'version' => '1.0',
            'generado' => now()->format('d/m/Y'),
            'colegio' => $colegio,
        ];

        $pdf = new self($meta);
        $pdf->crearLinks();

        $pdf->AddPage();
        $pdf->renderPortada();

        $pdf->AddPage();
        $pdf->renderIndice();

        $pdf->AddPage();
        $pdf->renderSeccion('1. Antes de empezar', 'requisitos', function () use ($pdf): void {
            $pdf->p(
                'Para consultar CUIT o CUIL a partir de un DNI, la institución debe autorizar en ARCA el web service ' .
                'Padrón Alcance 13 (identificador técnico ws_sr_padron_a13). Este trámite es independiente de la ' .
                'autorización de Facturación Electrónica (wsfe).',
            );

            $pdf->box('Requisitos previos', [
                ['Clave Fiscal', 'Del representante legal o administrador de relaciones, nivel 3 o superior.'],
                ['Rol', 'Administrador de Relaciones del CUIT de la institución.'],
                ['Certificado digital', 'Vigente en Administración de Certificados Digitales (el mismo que usa wsfe).'],
                ['CUIT institución', 'El configurado para facturación (cuitFact o cuit en Parámetros del sistema).'],
                ['Sistema', 'Certificados cargados en afipSE/cert/ y permiso orden 84 en el Menú de Administración.'],
            ]);

            $pdf->h2('Servicios que deben estar en el escritorio ARCA');
            $pdf->bullets([
                'Administración de Certificados Digitales (ARCA → Servicios interactivos).',
                'Administrador de Relaciones de Clave Fiscal.',
                'Solo para pruebas: WSASS - Autogestión Certificados Homologación (clave fiscal de persona física).',
            ]);

            $pdf->callout(
                'Importante',
                'Tener autorizado wsfe (facturación) NO habilita automáticamente ws_sr_padron_a13. ' .
                'Cada servicio requiere una relación nueva en el Administrador de Relaciones.',
            );
        });

        $pdf->AddPage();
        $pdf->renderSeccion('2. Conceptos clave', 'conceptos', function () use ($pdf): void {
            $pdf->box('Comparación de servicios', [
                ['wsfe', 'Facturación electrónica: emisión y consulta de comprobantes.'],
                ['ws_sr_padron_a13', 'Padrón Alcance 13: consulta datos tributarios y CUIT/CUIL por DNI.'],
                ['WSAA', 'Autenticación: genera token y sign. El TRA debe pedir el servicio correcto.'],
                ['Computador fiscal', 'Alias del certificado digital registrado en ARCA.'],
                ['CUIT representada', 'CUIT de la institución que realiza la consulta en su nombre.'],
            ]);

            $pdf->h2('Flujo técnico en el sistema');
            $pdf->numbered([
                'Se firma un TRA en WSAA solicitando acceso a ws_sr_padron_a13.',
                'ARCA devuelve token y sign (ticket de acceso).',
                'Se invoca getIdPersonaListByDocumento con el DNI.',
                'ARCA responde con uno o más CUIT/CUIL asociados.',
            ]);

            $pdf->callout(
                'En este sistema',
                'El módulo ARCA → Consulta CUIT por DNI guarda un ticket aparte (TA_ws_sr_padron_a13.xml) ' .
                'distinto del de facturación (TA.xml con wsfe).',
            );
        });

        $pdf->AddPage();
        $pdf->renderSeccion('3. Autorización en producción', 'produccion', function () use ($pdf): void {
            $pdf->p(
                'En ambiente productivo se usa el Administrador de Relaciones de Clave Fiscal con el certificado ' .
                'emitido desde Administración de Certificados Digitales (no WSASS).',
            );

            $pdf->h2('Paso 1 — Ingresar a ARCA');
            $pdf->numbered([
                'Ir a www.arca.gob.ar o auth.afip.gob.ar.',
                'Ingresar con CUIT y Clave Fiscal del administrador de relaciones.',
                'Si administra varios contribuyentes, elegir el CUIT de la institución.',
            ]);

            $pdf->h2('Paso 2 — Nueva relación');
            $pdf->numbered([
                'Abrir Administrador de Relaciones de Clave Fiscal.',
                'Clic en Nueva Relación.',
                'En Representado: verificar el CUIT de la institución (mismo que facturación).',
            ]);

            $pdf->h2('Paso 3 — Elegir el servicio');
            $pdf->numbered([
                'Clic en Buscar (primer botón, lado del servicio).',
                'Navegar: ARCA → Web Services.',
                'Seleccionar Consulta Padrón Alcance 13 o ws_sr_padron_a13.',
                'Tipo: Web Service (no Servicio interactivo). No elegir Facturación Electrónica / wsfe.',
            ]);

            $pdf->h2('Paso 4 — Vincular el certificado');
            $pdf->numbered([
                'Clic en el segundo Buscar (computador fiscal / representante).',
                'En Computador Fiscal: elegir el alias del certificado usado por el sistema.',
                'No completar CUIT de tercero salvo que se delegue a otra persona.',
                'Confirmar dos veces.',
            ]);

            $pdf->h2('Paso 5 — Verificar');
            $pdf->bullets([
                'La relación figura activa en Administrador de Relaciones.',
                'El certificado no está vencido en Administración de Certificados Digitales.',
                'Representado, servicio y computador fiscal coinciden con la configuración del sistema.',
            ]);
        });

        $pdf->AddPage();
        $pdf->renderSeccion('4. Homologación (testing)', 'homologacion', function () use ($pdf): void {
            $pdf->p(
                'Para pruebas con produccion => false en la configuración del tenant, se usa WSASS. ' .
                'Los certificados de WSASS no sirven en producción.',
            );

            $pdf->h2('Adherir WSASS (una vez)');
            $pdf->numbered([
                'Ingresar con clave fiscal de persona física (no delegable).',
                'Administrador de Relaciones → Adherir servicio.',
                'ARCA → Servicios interactivos → WSASS - Autogestión Certificados Homologación.',
                'Cerrar sesión y volver a ingresar; WSASS aparece en Mis Servicios.',
            ]);

            $pdf->h2('Crear autorización a servicio');
            $pdf->box('Campos del formulario WSASS', [
                ['1. Nombre simbólico DN', 'Alias del certificado de testing creado en WSASS.'],
                ['2. CUIT del DN', 'Se completa automáticamente.'],
                ['3. CUIT representado', 'CUIT de la institución (o de prueba).'],
                ['4. CUIT autorizante', 'CUIT del usuario conectado (automático).'],
                ['5. Servicio', 'Elegir ws_sr_padron_a13 en el desplegable.'],
            ]);

            $pdf->p('Presionar Crear autorización de acceso y verificar en WSASS → Autorizaciones.');
        });

        $pdf->AddPage();
        $pdf->renderSeccion('5. Configuración en el sistema', 'sistema', function () use ($pdf): void {
            $pdf->box('Parámetros del sistema (ento)', [
                ['Carpeta certificado', 'afipCertCarpeta — subcarpeta en afipSE/cert/.'],
                ['Clave privada', 'afipCertKey — archivo .key.'],
                ['Certificado', 'afipCertCrt — archivo .crt.'],
                ['CUIT facturación', 'cuitFact o cuit — CUIT representada en ARCA.'],
            ]);

            $pdf->h2('Configuración tenant (opcional)');
            $pdf->p('En config/tenants/{slug}.php, sección arca.padron_a13:');
            $pdf->bullets([
                'habilitado => true — fuerza habilitación del módulo.',
                'produccion => true — ambiente productivo ARCA.',
                'simular => false — consultas reales (en local, simular_local controla pruebas).',
            ]);

            $pdf->h2('Permisos');
            $pdf->bullets([
                'Permiso IA orden 84: Consulta CUIT por DNI (ARCA).',
                'Asignar el bit 84 en profesores.permisos_ia a los usuarios autorizados.',
                'Menú: Administración → grupo ARCA → Consulta CUIT por DNI.',
            ]);
        });

        $pdf->AddPage();
        $pdf->renderSeccion('6. Verificación y errores frecuentes', 'errores', function () use ($pdf): void {
            $pdf->h2('Cómo comprobar que quedó bien');
            $pdf->bullets([
                'Relación activa en Administrador de Relaciones (servicio + certificado + CUIT).',
                'Certificado vigente y archivos correctos en el servidor.',
                'Permiso 84 activo para el usuario de prueba.',
                'Consulta DNI de prueba en el módulo ARCA del sistema.',
            ]);

            $pdf->h2('Errores frecuentes');
            $pdf->errorTable([
                ['Computador no autorizado', 'Falta relación ws_sr_padron_a13 para ese alias de certificado.'],
                ['Token OK, falla padrón', 'Ticket WSAA pedido para wsfe en lugar de ws_sr_padron_a13.'],
                ['Certificado vencido', 'Renovar en Administración de Certificados y reautorizar servicios.'],
                ['Factura OK, padrón no', 'Solo está autorizado wsfe; falta ws_sr_padron_a13.'],
                ['Error en homologación', 'Certificado de producción usado en ambiente de testing o viceversa.'],
            ]);

            $pdf->callout(
                'Soporte ARCA',
                'Certificados / WSAA: webservices-desa@arca.gob.ar · ' .
                'Técnico del WS: sri@arca.gob.ar · Consultas Web: servicioscf.arca.gob.ar/publico/crmcit/consulta.aspx. ' .
                'Indicar servicio ws_sr_padron_a13, ambiente, CUIT representada, alias del certificado y XML request/response.',
            );
        });

        $pdf->AddPage();
        $pdf->renderSeccion('7. Checklist y referencias', 'checklist', function () use ($pdf): void {
            $pdf->h2('Checklist producción');
            $pdf->numbered([
                'Certificado vigente en Administración de Certificados Digitales.',
                'Mismo certificado configurado en Parámetros del sistema.',
                'Nueva relación en Administrador de Relaciones.',
                'Servicio: ARCA → Web Services → Padrón Alcance 13 (ws_sr_padron_a13).',
                'Computador fiscal: alias del certificado del sistema.',
                'Representado: CUIT de la institución.',
                'Confirmar dos veces la relación.',
                'Permiso 84 asignado a usuarios del módulo.',
                'Probar consulta DNI en Menú de Administración → ARCA.',
            ]);

            $pdf->h2('Documentación oficial');
            $pdf->bullets([
                'Manual Padrón A13: www.arca.gob.ar/ws/ws-padron-a13/',
                'WSASS (homologación): www.afip.gob.ar/ws/WSASS/',
                'Certificados producción: www.afip.gob.ar/ws/wsaa/wsaa.obtenercertificado.pdf',
                'Web Services ARCA: www.arca.gob.ar/ws/',
            ]);
        });

        return $pdf;
    }

    public static function respuestaHttp(self $pdf, string $nombreArchivo): \Illuminate\Http\Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $binario = $pdf->Output($nombreArchivo, 'S');

        return response($binario, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombreArchivo.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function crearLinks(): void
    {
        foreach ([
            'requisitos',
            'conceptos',
            'produccion',
            'homologacion',
            'sistema',
            'errores',
            'checklist',
        ] as $k) {
            $this->sectionLinks[$k] = $this->AddLink();
        }
    }

    private function renderPortada(): void
    {
        $w = $this->getPageWidth();
        $h = $this->getPageHeight();

        $this->SetFillColor(...self::COLOR_PRIMARIO);
        $this->Rect(0, 0, $w, 62, 'F');

        $this->SetFillColor(...self::COLOR_SECUNDARIO);
        $this->Rect(0, 58, $w, 6, 'F');

        $this->SetXY(self::MARGEN_IZQ, 16);
        $this->SetTextColor(255, 255, 255);
        TcpdfFuenteArial::aplicar($this, 'B', 19);
        $this->MultiCell($w - self::MARGEN_IZQ - self::MARGEN_DER, 9, $this->meta['titulo'], 0, 'L', false, 1);

        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->MultiCell($w - self::MARGEN_IZQ - self::MARGEN_DER, 6, $this->meta['subtitulo'], 0, 'L', false, 1);

        $this->SetTextColor(...self::COLOR_TEXTO);
        $this->SetFillColor(...self::COLOR_CAJA);
        $this->RoundedRect(self::MARGEN_IZQ, 82, $w - self::MARGEN_IZQ - self::MARGEN_DER, 48, 3.0, '1111', 'F');
        $this->SetXY(self::MARGEN_IZQ + 6, 90);

        TcpdfFuenteArial::aplicar($this, 'B', 10.5);
        $this->Cell(0, 6, 'Datos del documento', 0, 1, 'L');
        TcpdfFuenteArial::aplicar($this, '', 9.5);

        if ($this->meta['colegio'] !== null) {
            $this->lineaMeta('Institución', $this->meta['colegio']);
        }
        $this->lineaMeta('Versión', $this->meta['version']);
        $this->lineaMeta('Generado', $this->meta['generado']);
        $this->lineaMeta('Servicio ARCA', 'ws_sr_padron_a13');

        $this->SetY($h - 30);
        $this->SetTextColor(115, 159, 165);
        TcpdfFuenteArial::aplicar($this, '', 8.5);
        $this->MultiCell(
            $w - self::MARGEN_IZQ - self::MARGEN_DER,
            4.5,
            'Documento de uso interno para personal autorizado. Los nombres en el portal ARCA pueden variar levemente según actualizaciones del organismo.',
            0,
            'L',
        );
    }

    private function renderIndice(): void
    {
        $this->SetTextColor(...self::COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($this, 'B', 16);
        $this->Cell(0, 10, 'Índice', 0, 1, 'L');

        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->SetTextColor(115, 159, 165);
        $this->MultiCell(0, 5.5, 'Hacé clic sobre un ítem para ir a la sección (visores PDF compatibles).', 0, 'L', false, 1);
        $this->Ln(2);

        $this->SetTextColor(...self::COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($this, 'B', 11);

        $this->tocItem('1. Antes de empezar', $this->sectionLinks['requisitos']);
        $this->tocItem('2. Conceptos clave', $this->sectionLinks['conceptos']);
        $this->tocItem('3. Autorización en producción', $this->sectionLinks['produccion']);
        $this->tocItem('4. Homologación (testing)', $this->sectionLinks['homologacion']);
        $this->tocItem('5. Configuración en el sistema', $this->sectionLinks['sistema']);
        $this->tocItem('6. Verificación y errores frecuentes', $this->sectionLinks['errores']);
        $this->tocItem('7. Checklist y referencias', $this->sectionLinks['checklist']);
    }

    /**
     * @param  callable():void  $contenido
     */
    private function renderSeccion(string $titulo, string $keyLink, callable $contenido): void
    {
        if (isset($this->sectionLinks[$keyLink])) {
            $this->SetLink($this->sectionLinks[$keyLink], 0, -1);
        }

        $this->Bookmark($titulo, 0, 0, '', 'B', self::COLOR_PRIMARIO);

        $y = $this->GetY();
        $w = $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER;
        $this->SetFillColor(...self::COLOR_PRIMARIO);
        $this->Rect(self::MARGEN_IZQ, $y, 3, 9, 'F');

        $this->SetXY(self::MARGEN_IZQ + 6, $y);
        $this->SetTextColor(...self::COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($this, 'B', 14);
        $this->MultiCell($w - 6, 7.5, $titulo, 0, 'L', false, 1);
        $this->Ln(2);

        $contenido();
    }

    private function tocItem(string $label, int $linkId): void
    {
        $x = $this->GetX();
        $y = $this->GetY();
        $this->Write(6.5, $label, $linkId, false, 'L', true);
        $this->Link($x, $y, 180, 6.5, $linkId);
    }

    private function h2(string $text): void
    {
        $this->Ln(2);
        $this->SetTextColor(...self::COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($this, 'B', 11.5);
        $this->MultiCell(0, 6, $text, 0, 'L', false, 1);
        $this->Ln(0.5);
    }

    private function p(string $text): void
    {
        $this->SetTextColor(...self::COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->MultiCell(0, 5.6, $text, 0, 'L', false, 1);
        $this->Ln(1);
    }

    /**
     * @param  list<string>  $items
     */
    private function bullets(array $items): void
    {
        $this->SetTextColor(...self::COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($this, '', 10);
        foreach ($items as $it) {
            $this->MultiCell(0, 5.4, '• '.$it, 0, 'L', false, 1);
        }
        $this->Ln(1);
    }

    /**
     * @param  list<string>  $items
     */
    private function numbered(array $items): void
    {
        $this->SetTextColor(...self::COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $n = 1;
        foreach ($items as $it) {
            $this->MultiCell(0, 5.4, $n.'. '.$it, 0, 'L', false, 1);
            $n++;
        }
        $this->Ln(1);
    }

    /**
     * @param  list<array{0:string,1:string}>  $rows
     */
    private function box(string $title, array $rows): void
    {
        $x = $this->GetX();
        $y = $this->GetY();
        $w = $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER;
        $altoEstimado = 8 + (count($rows) * 6.2) + 2;

        if ($this->GetY() + $altoEstimado > ($this->getPageHeight() - self::MARGEN_INF)) {
            $this->AddPage();
            $x = $this->GetX();
            $y = $this->GetY();
        }

        $this->SetDrawColor(...self::COLOR_CALLOUT);
        $this->SetFillColor(...self::COLOR_CAJA);
        $this->RoundedRect($x, $y, $w, $altoEstimado, 2.5, '1111', 'DF');
        $this->SetXY($x + 5, $y + 4);

        $this->SetTextColor(...self::COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($this, 'B', 10.5);
        $this->Cell(0, 6, $title, 0, 1, 'L');
        $this->Ln(0.2);

        foreach ($rows as [$k, $v]) {
            TcpdfFuenteArial::aplicar($this, 'B', 9.3);
            $this->SetTextColor(64, 132, 141);
            $this->MultiCell(50, 5.6, $k.':', 0, 'L', false, 0);
            TcpdfFuenteArial::aplicar($this, '', 9.3);
            $this->SetTextColor(...self::COLOR_TEXTO);
            $this->MultiCell($w - 50 - 8, 5.6, $v, 0, 'L', false, 1);
        }

        $this->SetXY($x, $y + $altoEstimado + 3);
    }

    private function callout(string $title, string $text): void
    {
        $x = $this->GetX();
        $y = $this->GetY();
        $w = $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER;

        if ($y + 22 > ($this->getPageHeight() - self::MARGEN_INF)) {
            $this->AddPage();
            $x = $this->GetX();
            $y = $this->GetY();
        }

        $this->SetFillColor(225, 237, 239);
        $this->SetDrawColor(...self::COLOR_PRIMARIO);
        $this->RoundedRect($x, $y, $w, 20, 2.5, '1111', 'DF');
        $this->SetXY($x + 5, $y + 4);

        $this->SetTextColor(...self::COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($this, 'B', 10.2);
        $this->Cell(0, 5.5, $title, 0, 1, 'L');

        $this->SetTextColor(...self::COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($this, '', 9.5);
        $this->MultiCell($w - 10, 5.2, $text, 0, 'L', false, 1);

        $this->Ln(3);
    }

    /**
     * @param  list<array{0:string,1:string}>  $rows
     */
    private function errorTable(array $rows): void
    {
        $x = self::MARGEN_IZQ;
        $w = $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER;
        $col1 = 52.0;
        $col2 = $w - $col1;

        if ($this->GetY() + 8 + (count($rows) * 7) > ($this->getPageHeight() - self::MARGEN_INF)) {
            $this->AddPage();
        }

        $y = $this->GetY();

        $this->SetFillColor(...self::COLOR_PRIMARIO);
        $this->SetTextColor(255, 255, 255);
        TcpdfFuenteArial::aplicar($this, 'B', 9.5);
        $this->SetXY($x, $y);
        $this->Cell($col1, 7, 'Síntoma / mensaje', 1, 0, 'L', true);
        $this->Cell($col2, 7, 'Causa probable', 1, 1, 'L', true);

        TcpdfFuenteArial::aplicar($this, '', 9);
        foreach ($rows as $i => [$sintoma, $causa]) {
            $fill = $i % 2 === 0;
            $this->SetFillColor(...($fill ? self::COLOR_CAJA : [255, 255, 255]));
            $this->SetTextColor(...self::COLOR_TEXTO);
            $yRow = $this->GetY();
            $this->SetXY($x, $yRow);
            $this->MultiCell($col1, 6.5, $sintoma, 1, 'L', $fill, 0);
            $hRow = max(6.5, $this->GetY() - $yRow);
            $this->SetXY($x + $col1, $yRow);
            $this->MultiCell($col2, 6.5, $causa, 1, 'L', $fill, 1);
            if ($this->GetY() - $yRow < $hRow) {
                $this->SetY($yRow + $hRow);
            }
        }

        $this->Ln(2);
    }

    private function lineaMeta(string $k, string $v): void
    {
        TcpdfFuenteArial::aplicar($this, 'B', 9.5);
        $this->SetTextColor(115, 159, 165);
        $this->MultiCell(34, 5.5, $k.':', 0, 'L', false, 0);
        TcpdfFuenteArial::aplicar($this, '', 9.5);
        $this->SetTextColor(...self::COLOR_TEXTO);
        $this->MultiCell(0, 5.5, $v, 0, 'L', false, 1);
    }
}
