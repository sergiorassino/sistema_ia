<?php

namespace App\Support\ProyectosExtracurriculares;

use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfLogoInstitucional;
use App\Support\Pdf\TcpdfMultiCellJustificado;
use TCPDF;

/**
 * Autorización de padres o tutores para una actividad extracurricular. A4 vertical, una hoja por alumno.
 */
final class AutorizacionActividadTcpdf extends TCPDF
{
    private const MARGEN_L = 18.0;

    private const MARGEN_R = 18.0;

    private const MARGEN_T = 15.0;

    private const MARGEN_B = 15.0;

    /** Azul del membrete, próximo al impreso de referencia. */
    private const COLOR_MEMBRETE_R = 68;

    private const COLOR_MEMBRETE_G = 84;

    private const COLOR_MEMBRETE_B = 196;

    private string $institucion = '';

    private string $localidad = '';

    /** @var array<string, mixed> */
    private array $encabezado = [];

    private function __construct()
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle('Formulario de autorización de padres para viaje educativo');
        $this->SetSubject('Autorización de padres o tutores');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, self::MARGEN_B);
        $this->SetMargins(self::MARGEN_L, self::MARGEN_T, self::MARGEN_R);
    }

    /**
     * @param  array{insti: string, localidad: string, logo_file: ?string}  $encabezado
     * @param  array{
     *   nombre: string,
     *   lugar: string,
     *   localidad_salida: string,
     *   institucion: string,
     *   acompanantes: string,
     *   horario: string,
     *   participacion: string,
     *   jornadas: list<array{fecha: string, fecha_larga: string, inicio: string, fin: string}>
     * }  $viaje
     * @param  list<array{apellido: string, nombre: string, dni: string, grupo_sanguineo: string, curso: string, division: string, calle: string, numero: string, localidad: string}>  $alumnos
     */
    public static function generarLote(array $encabezado, array $viaje, array $alumnos): self
    {
        $pdf = new self;
        $pdf->encabezado = $encabezado;
        $pdf->institucion = trim((string) ($encabezado['insti'] ?? $viaje['institucion'] ?? ''));
        $pdf->localidad = trim((string) ($encabezado['localidad'] ?? $viaje['localidad_salida'] ?? ''));

        foreach ($alumnos as $alumno) {
            $pdf->AddPage();
            $pdf->dibujarHoja($viaje, $alumno);
        }

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

    /**
     * @param  array{
     *   nombre: string,
     *   lugar: string,
     *   localidad_salida: string,
     *   institucion: string,
     *   acompanantes: string,
     *   horario: string,
     *   participacion: string,
     *   jornadas: list<array{fecha: string, fecha_larga: string, inicio: string, fin: string}>
     * }  $viaje
     * @param  array{apellido: string, nombre: string, dni: string, grupo_sanguineo: string, curso: string, division: string, calle: string, numero: string, localidad: string}  $alumno
     */
    private function dibujarHoja(array $viaje, array $alumno): void
    {
        $ancho = $this->anchoUtil();
        $this->dibujarMembrete();
        $this->SetTextColor(0, 0, 0);

        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->MultiCell($ancho, 6, 'FORMULARIO DE AUTORIZACIÓN DE PADRES PARA VIAJE EDUCATIVO.', 0, 'C', false, 1);
        $this->Ln(1);

        $nombre = trim($viaje['nombre']);
        if ($nombre !== '') {
            TcpdfFuenteArial::aplicar($this, 'B', 11);
            $this->MultiCell($ancho, 5.5, '"'.$nombre.'"', 0, 'C', false, 1);
        }

        $centro = $this->institucion !== '' ? $this->institucion : '______________________________';
        $this->lineaEtiquetaValorCentrada($ancho, 'Centro Educativo: ', $centro);
        $localidad = $this->localidad !== '' ? $this->localidad : '______________________________';
        $this->lineaEtiquetaValorCentrada($ancho, 'Localidad: ', $localidad);
        $this->Ln(3);

        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->SetX(self::MARGEN_L);
        TcpdfMultiCellJustificado::escribir(
            $this,
            $ancho,
            5,
            AutorizacionActividadRedaccion::parrafoAutorizacion($alumno, $viaje)
        );
        $this->Ln(4);

        $this->tituloSeccion('CRONOGRAMA DE ACTIVIDADES.');
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->SetX(self::MARGEN_L);
        TcpdfMultiCellJustificado::escribir(
            $this,
            $ancho,
            5,
            AutorizacionActividadRedaccion::fraseTraslado($viaje)
        );
        $this->Ln(4);

        $this->tituloSeccion('Detalle del Viaje Educativo de acuerdo al lugar de arribo o descenso:');
        TcpdfFuenteArial::aplicar($this, '', 11);
        foreach (AutorizacionActividadRedaccion::lineasDetalle($viaje) as $linea) {
            $this->SetX(self::MARGEN_L);
            $this->MultiCell($ancho, 5.5, $linea, 0, 'L', false, 1);
        }
        $this->Ln(4);

        $this->asegurarEspacio(42);
        $this->tituloSeccion('AUTORIZACIÓN DEL/LOS PADRE/S O TUTOR.');
        $this->campoFirma($ancho, 'Lugar y Fecha');
        $this->campoFirma($ancho, 'Firma:');
        $this->campoFirma($ancho, 'DNI Nº');
        $this->campoFirma($ancho, 'Domicilio:');
        $this->campoFirma($ancho, 'Tel:');
    }

    private function dibujarMembrete(): void
    {
        $auto = $this->getAutoPageBreak();
        $margen = $this->getBreakMargin();
        $this->SetAutoPageBreak(false);

        $logo = $this->encabezado['logo_file'] ?? null;
        TcpdfLogoInstitucional::dibujarAjustado(
            $this,
            14,
            8,
            22,
            22,
            is_string($logo) && $logo !== '' ? $logo : null,
        );

        $x = 40.0;
        $ancho = $this->getPageWidth() - $x - 16;
        $this->SetXY($x, 8);

        $insti = trim((string) ($this->encabezado['insti'] ?? $this->institucion));
        $this->SetTextColor(self::COLOR_MEMBRETE_R, self::COLOR_MEMBRETE_G, self::COLOR_MEMBRETE_B);
        TcpdfFuenteArial::aplicar($this, 'B', 12);
        $this->MultiCell($ancho, 5.5, $insti !== '' ? mb_strtoupper($insti, 'UTF-8') : 'INSTITUCIÓN', 0, 'C', false, 1);

        $subtitulo = trim((string) ($this->encabezado['subtitulo'] ?? ''));
        if ($subtitulo === '') {
            $subtitulo = trim((string) ($this->encabezado['categoria'] ?? ''));
        }
        if ($subtitulo !== '') {
            TcpdfFuenteArial::aplicar($this, 'B', 9);
            $this->SetX($x);
            $this->MultiCell($ancho, 4.5, mb_strtoupper($subtitulo, 'UTF-8'), 0, 'C', false, 1);
        }

        $adscripcion = trim((string) ($this->encabezado['adscripcion'] ?? ''));
        $this->SetTextColor(0, 0, 0);
        if ($adscripcion !== '') {
            TcpdfFuenteArial::aplicar($this, '', 7.5);
            $this->SetX($x);
            $this->MultiCell($ancho, 3.6, $adscripcion, 0, 'C', false, 1);
        }

        TcpdfFuenteArial::aplicar($this, '', 8);
        $contacto = $this->lineaContacto();
        if ($contacto !== '') {
            $this->SetX($x);
            $this->MultiCell($ancho, 3.8, $contacto, 0, 'C', false, 1);
        }
        $lugar = $this->lineaLugar();
        if ($lugar !== '') {
            $this->SetX($x);
            $this->MultiCell($ancho, 3.8, $lugar, 0, 'C', false, 1);
        }

        $yLinea = max($this->GetY() + 1.2, 32.0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.35);
        $this->Line(self::MARGEN_L, $yLinea, $this->getPageWidth() - self::MARGEN_R, $yLinea);

        $this->SetAutoPageBreak($auto, $margen);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY(self::MARGEN_L, $yLinea + 3);
    }

    private function lineaContacto(): string
    {
        $direccion = trim((string) ($this->encabezado['direccion'] ?? ''));
        $telefono = trim((string) ($this->encabezado['telefono'] ?? ''));
        $mail = trim((string) ($this->encabezado['mail'] ?? ''));
        $partes = [];
        if ($direccion !== '') {
            $partes[] = rtrim($direccion, '.').'.';
        }
        $partes[] = 'Tel-Fax: '.$telefono;
        $partes[] = 'e-mail: '.$mail;

        return implode('  ', $partes);
    }

    private function lineaLugar(): string
    {
        $prefijo = [];
        $cue = trim((string) ($this->encabezado['cue'] ?? ''));
        $ee = trim((string) ($this->encabezado['ee'] ?? ''));
        if ($cue !== '') {
            $prefijo[] = 'CUE '.$cue;
        }
        if ($ee !== '') {
            $prefijo[] = 'EE '.$ee;
        }

        $localidad = trim((string) ($this->encabezado['localidad'] ?? $this->localidad));
        $departamento = trim((string) ($this->encabezado['departamento'] ?? ''));
        $provincia = trim((string) ($this->encabezado['provincia'] ?? ''));
        $lugar = $localidad;
        if ($departamento !== '' && mb_strtolower($departamento) !== mb_strtolower($localidad)) {
            $lugar = trim($lugar.($lugar !== '' ? ', ' : '').$departamento);
        }
        if ($provincia !== '') {
            $lugar = trim($lugar.($lugar !== '' ? ' ' : '').'('.$provincia.')');
        }

        return trim(implode('   ', $prefijo).($lugar !== '' ? '   '.$lugar : ''));
    }

    private function lineaEtiquetaValorCentrada(float $ancho, string $etiqueta, string $valor): void
    {
        $this->SetTextColor(0, 0, 0);
        TcpdfFuenteArial::aplicar($this, '', 11);
        $anchoEtiqueta = $this->GetStringWidth($etiqueta);
        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $anchoValor = $this->GetStringWidth($valor);
        $x = self::MARGEN_L + max(0, ($ancho - $anchoEtiqueta - $anchoValor) / 2);
        $y = $this->GetY();
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->SetXY($x, $y);
        $this->Cell($anchoEtiqueta, 5.5, $etiqueta, 0, 0, 'L');
        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->Cell($anchoValor + 1, 5.5, $valor, 0, 1, 'L');
    }

    private function tituloSeccion(string $titulo): void
    {
        $this->SetX(self::MARGEN_L);
        TcpdfFuenteArial::aplicar($this, 'B', 11);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell($this->anchoUtil(), 5.5, $titulo, 0, 'L', false, 1);
        $this->Ln(1);
    }

    private function campoFirma(float $ancho, string $etiqueta): void
    {
        TcpdfFuenteArial::aplicar($this, '', 11);
        $this->SetTextColor(0, 0, 0);
        $y = $this->GetY();
        $this->SetXY(self::MARGEN_L, $y);
        $this->Cell($ancho, 6, $etiqueta, 0, 1, 'L');
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $xLinea = self::MARGEN_L + $this->GetStringWidth($etiqueta.' ');
        $this->Line($xLinea, $y + 5, self::MARGEN_L + $ancho, $y + 5);
        $this->SetY($y + 8);
    }

    private function asegurarEspacio(float $alto): void
    {
        $limite = $this->getPageHeight() - self::MARGEN_B;
        if ($this->GetY() + $alto > $limite) {
            $this->AddPage();
            $this->SetXY(self::MARGEN_L, self::MARGEN_T);
        }
    }

    private function anchoUtil(): float
    {
        return $this->getPageWidth() - self::MARGEN_L - self::MARGEN_R;
    }
}
