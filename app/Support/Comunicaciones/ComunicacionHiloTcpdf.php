<?php

namespace App\Support\Comunicaciones;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * PDF de conversación (hilo) de comunicados institucionales — A4 vertical, TCPDF, Arial.
 */
final class ComunicacionHiloTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 12.0;

    private const MARGEN_DER = 12.0;

    private const MARGEN_SUP = 10.0;

    private const MARGEN_INF = 10.0;

    private const ANCHO_UTIL = 186.0;

    /** @var array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string} */
    private array $header;

    /**
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    private function __construct(array $header)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->header = $header;
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Conversación de comunicados');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, self::MARGEN_INF + 4);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{insti: string, direccion: string, localidad: string, cue: string, ee: string, logo_file: ?string}  $header
     */
    public static function generar(array $datos, array $header): self
    {
        $pdf = new self($header);
        $pdf->AddPage();
        $pdf->dibujarDocumento($datos);

        return $pdf;
    }

    public static function respuestaHttp(self $pdf, string $nombreArchivo): \Illuminate\Http\Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $binario = $pdf->Output($nombreArchivo, 'S');

        return response($binario, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombreArchivo.'"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'              => 'no-cache',
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarDocumento(array $datos): void
    {
        $y = $this->dibujarMarcoCabecera(self::MARGEN_SUP);
        $y = $this->dibujarTituloHilo($y, $datos);
        $y = $this->dibujarMetaHilo($y, $datos);

        /** @var list<array<string, mixed>> $mensajes */
        $mensajes = $datos['mensajes'] ?? [];
        $n = count($mensajes);
        foreach ($mensajes as $i => $msg) {
            $y = $this->asegurarEspacio($y, 28);
            $y = $this->dibujarMensaje($y, $msg, $i + 1, $n);
            $y += 2;
        }

        $this->dibujarPieImpresion($datos);
    }

    private function dibujarMarcoCabecera(float $y): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;
        $h = 16.0;

        $this->SetDrawColor(64, 132, 141);
        $this->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        $logo = $this->header['logo_file'] ?? null;
        if (is_string($logo) && $logo !== '' && is_file($logo)) {
            $this->Image($logo, $x + 2, $y + 1.5, 11, 13, '', '', '', false, 300);
        }

        $insti = trim((string) ($this->header['insti'] ?? ''));
        $direccion = trim((string) ($this->header['direccion'] ?? ''));
        $localidad = trim((string) ($this->header['localidad'] ?? ''));
        $lineaDir = trim($direccion.($direccion !== '' && $localidad !== '' ? ' — ' : '').$localidad);

        $this->SetXY($x, $y + 2);
        TcpdfFuenteArial::aplicar($this, 'B', 9);
        $this->Cell($w, 4, $insti !== '' ? $insti : 'Institución', 0, 2, 'C');

        if ($lineaDir !== '') {
            TcpdfFuenteArial::aplicar($this, '', 6.5);
            $this->Cell($w, 3, $lineaDir, 0, 2, 'C');
        }

        return $y + $h + 2;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarTituloHilo(float $y, array $datos): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;

        TcpdfFuenteArial::aplicar($this, 'B', 8);
        $this->SetXY($x, $y);
        $this->Cell($w, 4, 'CONVERSACIÓN — COMUNICADOS INSTITUCIONALES', 0, 2, 'C');

        TcpdfFuenteArial::aplicar($this, 'B', 7.5);
        $asunto = trim((string) ($datos['asunto'] ?? ''));
        $this->MultiCell($w, 3.8, $asunto !== '' ? $asunto : '—', 0, 'C', false, 1);

        return $this->GetY() + 1;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarMetaHilo(float $y, array $datos): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;

        $this->SetFillColor(244, 248, 249);
        $this->SetDrawColor(193, 215, 218);
        $this->Rect($x, $y, $w, 0.1);

        $lineas = [
            'Alcance: '.trim((string) ($datos['scopeLabel'] ?? '—')),
            'Estado: '.trim((string) ($datos['estadoLabel'] ?? '—')),
            'Iniciado: '.trim((string) ($datos['iniciado'] ?? '—')),
        ];

        $ciclo = trim((string) ($datos['cicloAno'] ?? ''));
        if ($ciclo !== '') {
            $lineas[] = 'Ciclo lectivo: '.$ciclo;
        }

        $para = trim((string) ($datos['paraCompleto'] ?? ''));
        if ($para !== '') {
            $lineas[] = 'Para: '.$para;
        }

        $informativo = trim((string) ($datos['informativo'] ?? ''));
        if ($informativo !== '') {
            $lineas[] = $informativo;
        }

        TcpdfFuenteArial::aplicar($this, '', 6.5);
        $this->SetXY($x + 2, $y + 1.5);
        foreach ($lineas as $linea) {
            $this->MultiCell($w - 4, 3.2, $linea, 0, 'L', false, 1);
        }

        $yFin = $this->GetY() + 2;
        $this->Rect($x, $y, $w, $yFin - $y, 'D');

        return $yFin + 2;
    }

    /**
     * @param  array<string, mixed>  $msg
     */
    private function dibujarMensaje(float $y, array $msg, int $indice, int $total): float
    {
        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;

        $this->SetDrawColor(64, 132, 141);
        $this->SetFillColor(64, 132, 141);
        $this->Rect($x, $y, $w, 5.5, 'DF');

        $remitente = trim((string) ($msg['remitente'] ?? '—'));
        $vinculo = trim((string) ($msg['vinculo'] ?? ''));
        if ($vinculo !== '') {
            $remitente .= ' ('.$vinculo.')';
        }

        TcpdfFuenteArial::aplicar($this, 'B', 7);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY($x + 2, $y + 1.2);
        $this->Cell($w - 4, 3.5, 'Mensaje '.$indice.' de '.$total.' — '.$remitente, 0, 0, 'L');
        $this->SetTextColor(0, 0, 0);

        $y += 5.5;

        $fechaHora = trim((string) ($msg['fechaHora'] ?? ''));
        $lectura = trim((string) ($msg['lecturaResumen'] ?? ''));

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY($x + 2, $y + 1);
        $meta = 'Fecha y hora: '.($fechaHora !== '' ? $fechaHora : '—');
        if ($lectura !== '') {
            $meta .= '   ·   Lectura: '.$lectura;
        }
        $this->Cell($w - 4, 3, $meta, 0, 1, 'L');
        $y = $this->GetY() + 0.5;

        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->SetXY($x + 2, $y);
        $this->Cell($w - 4, 3, 'Contenido', 0, 1, 'L');
        $y = $this->GetY();

        TcpdfFuenteArial::aplicar($this, '', 6.5);
        $this->SetXY($x + 2, $y);
        $contenido = trim((string) ($msg['contenido'] ?? ''));
        $this->MultiCell($w - 4, 3.4, $contenido !== '' ? $contenido : '—', 0, 'L', false, 1);
        $y = $this->GetY() + 1;

        /** @var list<array<string, mixed>> $destinatarios */
        $destinatarios = $msg['destinatarios'] ?? [];
        if ($destinatarios !== []) {
            $y = $this->dibujarSubtitulo($y, 'Destinatarios y lectura');
            $y = $this->dibujarTablaDestinatarios($y, $destinatarios);
        }

        /** @var list<array<string, mixed>> $envios */
        $envios = $msg['envios'] ?? [];
        if ($envios !== []) {
            $y = $this->dibujarSubtitulo($y, 'Envíos por canal');
            $y = $this->dibujarTablaEnvios($y, $envios);
        }

        /** @var list<array<string, mixed>> $auditoria */
        $auditoria = $msg['auditoria'] ?? [];
        $y = $this->dibujarSubtitulo($y, 'Auditoría');
        if ($auditoria === []) {
            TcpdfFuenteArial::aplicar($this, '', 6);
            $this->SetXY($x + 2, $y);
            $this->Cell($w - 4, 3.2, 'Sin registros de auditoría para este mensaje.', 0, 1, 'L');

            return $this->GetY();
        }

        return $this->dibujarTablaAuditoria($y, $auditoria);
    }

    private function dibujarSubtitulo(float $y, string $titulo): float
    {
        $x = self::MARGEN_IZQ;

        TcpdfFuenteArial::aplicar($this, 'B', 6.5);
        $this->SetTextColor(64, 132, 141);
        $this->SetXY($x + 2, $y);
        $this->Cell(self::ANCHO_UTIL - 4, 3.5, mb_strtoupper($titulo), 0, 1, 'L');
        $this->SetTextColor(0, 0, 0);

        return $this->GetY() + 0.5;
    }

    /**
     * @param  list<array<string, mixed>>  $destinatarios
     */
    private function dibujarTablaDestinatarios(float $y, array $destinatarios): float
    {
        $x = self::MARGEN_IZQ;
        $wNom = 72.0;
        $wTipo = 28.0;
        $wLect = self::ANCHO_UTIL - $wNom - $wTipo;

        $this->dibujarFilaEncabezado($y, $x, [
            [$wNom, 'Destinatario'],
            [$wTipo, 'Tipo'],
            [$wLect, 'Lectura'],
        ]);
        $y = $this->GetY();

        TcpdfFuenteArial::aplicar($this, '', 6);
        foreach ($destinatarios as $d) {
            $y = $this->asegurarEspacio($y, 5);
            $this->SetXY($x, $y);
            $this->Cell($wNom, 4, $this->truncar((string) ($d['nombre'] ?? ''), 48), 1, 0, 'L');
            $this->Cell($wTipo, 4, (string) ($d['tipo'] ?? ''), 1, 0, 'L');
            $lectura = (bool) ($d['leido'] ?? false)
                ? (string) ($d['fecha_lectura'] ?? 'Leído')
                : 'Sin leer';
            $this->Cell($wLect, 4, $lectura, 1, 1, 'L');
            $y = $this->GetY();
        }

        return $y + 1;
    }

    /**
     * @param  list<array<string, mixed>>  $envios
     */
    private function dibujarTablaEnvios(float $y, array $envios): float
    {
        $x = self::MARGEN_IZQ;
        $wDest = 58.0;
        $wMed = 24.0;
        $wEst = 28.0;
        $wMot = self::ANCHO_UTIL - $wDest - $wMed - $wEst;

        $this->dibujarFilaEncabezado($y, $x, [
            [$wDest, 'Destinatario'],
            [$wMed, 'Medio'],
            [$wEst, 'Estado'],
            [$wMot, 'Motivo'],
        ]);
        $y = $this->GetY();

        TcpdfFuenteArial::aplicar($this, '', 6);
        foreach ($envios as $e) {
            $y = $this->asegurarEspacio($y, 5);
            $this->SetXY($x, $y);
            $this->Cell($wDest, 4, $this->truncar((string) ($e['destinatario'] ?? ''), 38), 1, 0, 'L');
            $this->Cell($wMed, 4, (string) ($e['medio'] ?? ''), 1, 0, 'L');
            $this->Cell($wEst, 4, (string) ($e['estado'] ?? ''), 1, 0, 'L');
            $motivo = trim((string) ($e['motivo'] ?? ''));
            $this->Cell($wMot, 4, $motivo !== '' ? $this->truncar($motivo, 42) : '—', 1, 1, 'L');
            $y = $this->GetY();
        }

        return $y + 1;
    }

    /**
     * @param  list<array<string, mixed>>  $auditoria
     */
    private function dibujarTablaAuditoria(float $y, array $auditoria): float
    {
        $x = self::MARGEN_IZQ;
        $wFecha = 30.0;
        $wActor = 42.0;
        $wAcc = 30.0;
        $wPortal = 22.0;
        $wDet = self::ANCHO_UTIL - $wFecha - $wActor - $wAcc - $wPortal;

        $this->dibujarFilaEncabezado($y, $x, [
            [$wFecha, 'Fecha'],
            [$wActor, 'Usuario'],
            [$wAcc, 'Acción'],
            [$wPortal, 'Portal'],
            [$wDet, 'Detalle'],
        ]);
        $y = $this->GetY();

        TcpdfFuenteArial::aplicar($this, '', 5.5);
        foreach ($auditoria as $a) {
            $actor = trim((string) ($a['actor'] ?? ''));
            $dni = trim((string) ($a['dni'] ?? ''));
            if ($dni !== '') {
                $actor .= ' · DNI '.$dni;
            }
            $cat = trim((string) ($a['categoria'] ?? ''));
            if ($cat !== '') {
                $actor .= ' ('.$cat.')';
            }

            $detalle = [];
            $rem = trim((string) ($a['remitente'] ?? ''));
            $dest = trim((string) ($a['destinatario'] ?? ''));
            if ($rem !== '') {
                $detalle[] = 'De: '.$this->truncar($rem, 40);
            }
            if ($dest !== '') {
                $detalle[] = 'A: '.$this->truncar($dest, 40);
            }
            $ip = trim((string) ($a['ip'] ?? ''));
            if ($ip !== '') {
                $detalle[] = 'IP: '.$ip;
            }
            $detalleTxt = $detalle !== [] ? implode(' | ', $detalle) : '—';

            $altura = max(4.0, $this->alturaCeldaMultilinea($wDet, $detalleTxt, 2.8));

            $y = $this->asegurarEspacio($y, $altura + 1);
            $yInicio = $y;

            $this->SetXY($x, $yInicio);
            $this->Cell($wFecha, $altura, (string) ($a['fecha'] ?? ''), 1, 0, 'L');
            $this->Cell($wActor, $altura, $this->truncar($actor, 36), 1, 0, 'L');
            $this->Cell($wAcc, $altura, (string) ($a['accion'] ?? ''), 1, 0, 'L');
            $this->Cell($wPortal, $altura, (string) ($a['portal'] ?? ''), 1, 0, 'L');

            $xDet = $x + $wFecha + $wActor + $wAcc + $wPortal;
            $this->Rect($xDet, $yInicio, $wDet, $altura);
            $this->SetXY($xDet + 0.5, $yInicio + 0.4);
            $this->MultiCell($wDet - 1, 2.8, $detalleTxt, 0, 'L', false, 0);

            $y = $yInicio + $altura;
            $this->SetY($y);
        }

        return $y + 1;
    }

    /**
     * @param  list<array{0: float, 1: string}>  $columnas
     */
    private function dibujarFilaEncabezado(float $y, float $x, array $columnas): void
    {
        $this->SetFillColor(193, 215, 218);
        $this->SetDrawColor(150, 170, 175);
        TcpdfFuenteArial::aplicar($this, 'B', 6);
        $this->SetXY($x, $y);
        foreach ($columnas as [$ancho, $texto]) {
            $this->Cell($ancho, 4.2, $texto, 1, 0, 'C', true);
        }
        $this->Ln();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function dibujarPieImpresion(array $datos): void
    {
        $y = $this->GetY() + 4;
        $y = $this->asegurarEspacio($y, 12);

        $x = self::MARGEN_IZQ;
        $w = self::ANCHO_UTIL;

        $this->SetDrawColor(193, 215, 218);
        $this->Line($x, $y, $x + $w, $y);
        $y += 2;

        TcpdfFuenteArial::aplicar($this, '', 6);
        $this->SetXY($x, $y);
        $this->Cell(
            $w,
            3,
            'Documento generado el '.trim((string) ($datos['generado'] ?? '')).' por '.trim((string) ($datos['impresoPor'] ?? '—')),
            0,
            1,
            'R'
        );
    }

    private function asegurarEspacio(float $y, float $minimo): float
    {
        $limite = $this->getPageHeight() - self::MARGEN_INF - $minimo;
        if ($y > $limite) {
            $this->AddPage();
            $y = self::MARGEN_SUP;
        }

        return $y;
    }

    private function truncar(string $texto, int $max): string
    {
        $texto = trim($texto);
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, max(0, $max - 1)).'…';
    }

    private function alturaCeldaMultilinea(float $ancho, string $texto, float $altoLinea): float
    {
        $lineas = max(1, (int) ceil($this->GetStringWidth($texto) / max(1.0, $ancho - 2)));

        return $lineas * $altoLinea + 1.2;
    }
}
