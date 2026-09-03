<?php

namespace App\Support\Listados;

use App\Support\Alumnos\FotoCarnetLegajo;
use App\Support\Pdf\TcpdfFuenteArial;
use App\Support\Pdf\TcpdfImagenPng;
use Illuminate\Support\Collection;
use TCPDF;

/**
 * Listado de fotos carnet — TCPDF A4 vertical.
 * Cada tarjeta: foto cuadrada (2×2 / 4×4 / 8×8 cm) y debajo apellido y nombre,
 * curso y sección, año lectivo.
 */
final class ListadoEstudiantesFormatoFotosTcpdf extends TCPDF
{
    use ListadoEstudiantesFormatoTcpdfComun;

    private const TITULO = 'Listado de fotos';

    private const GAP = 2.0;

    private bool $primeraPaginaDocumento = true;

    private string $tamanoFoto = ListadoEstudiantesFormatoTamanoFoto::MEDIANO;

    /**
     * @param  array<string, mixed>  $datos
     */
    private function __construct(array $datos)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->tamanoFoto = ListadoEstudiantesFormatoTamanoFoto::normalize(
            isset($datos['tamanoFoto']) ? (string) $datos['tamanoFoto'] : null
        );
        $this->formatoInicializarTcpdf($datos, self::TITULO);
        $this->formatoFuentesListadoAmpliadas = true;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): self
    {
        $pdf = new self($datos);

        /** @var list<array{cursoLabel: string, curso?: string, seccion?: string, alumnos: Collection<int, object>}> $bloques */
        $bloques = $datos['bloques'] ?? [];

        foreach ($bloques as $idx => $bloque) {
            if ($idx > 0) {
                $pdf->formatoNuevaPagina();
            } else {
                $pdf->AddPage('P', 'A4');
                $pdf->primeraPaginaDocumento = false;
            }
            $pdf->renderBloqueCurso($bloque);
        }

        if ($pdf->primeraPaginaDocumento) {
            $pdf->AddPage('P', 'A4');
            $pdf->dibujarCabeceraPagina('—');
            $pdf->formatoDibujarMensajeVacio(collect());
        }

        return $pdf;
    }

    /**
     * @param  array{cursoLabel: string, curso?: string, seccion?: string, alumnos: Collection<int, object>}  $bloque
     */
    private function renderBloqueCurso(array $bloque): void
    {
        $cursoLabel = (string) ($bloque['cursoLabel'] ?? '—');
        $alumnos = $bloque['alumnos'] ?? collect();

        $this->dibujarCabeceraPagina($cursoLabel);

        if ($alumnos->isEmpty()) {
            $this->formatoDibujarMensajeVacio($alumnos);

            return;
        }

        $medidas = $this->medidasGrilla();
        $yFila = $this->GetY();
        $indice = 0;

        foreach ($alumnos as $alumno) {
            $col = $indice % $medidas['columnas'];
            if ($indice > 0 && $col === 0) {
                $yFila += $medidas['altoCelda'];
                if ($yFila + $medidas['altoCelda'] > $this->formatoYMax) {
                    $this->formatoNuevaPagina();
                    $this->dibujarCabeceraPagina($cursoLabel);
                    $yFila = $this->GetY();
                }
            }

            $x = self::FORMATO_MARGEN_IZQ + ($col * $medidas['anchoCelda']);
            $this->dibujarTarjeta($x, $yFila, $medidas, $alumno, $bloque);
            $indice++;
        }

        $this->SetXY(self::FORMATO_MARGEN_IZQ, $yFila + $medidas['altoCelda']);
    }

    private function dibujarCabeceraPagina(string $cursoLabel): void
    {
        $this->formatoDibujarEncabezadoInstitucional();
        $this->formatoDibujarTituloDocumento(self::TITULO);
        $this->formatoDibujarLineaCurso(
            $cursoLabel,
            ListadoEstudiantesFormatoTamanoFoto::etiqueta($this->tamanoFoto)
        );
    }

    /**
     * @return array{lado: float, columnas: int, anchoCelda: float, altoCelda: float, altoTexto: float, fuenteNombre: float, fuenteMeta: float}
     */
    private function medidasGrilla(): array
    {
        $lado = ListadoEstudiantesFormatoTamanoFoto::ladoMm($this->tamanoFoto);
        $altoTexto = match ($this->tamanoFoto) {
            ListadoEstudiantesFormatoTamanoFoto::GRANDE => 20.0,
            ListadoEstudiantesFormatoTamanoFoto::PEQUENO => 11.0,
            default => 16.0,
        };
        $fuenteNombre = match ($this->tamanoFoto) {
            ListadoEstudiantesFormatoTamanoFoto::GRANDE => 9.0,
            ListadoEstudiantesFormatoTamanoFoto::PEQUENO => 5.5,
            default => 7.0,
        };
        $fuenteMeta = match ($this->tamanoFoto) {
            ListadoEstudiantesFormatoTamanoFoto::GRANDE => 8.0,
            ListadoEstudiantesFormatoTamanoFoto::PEQUENO => 5.0,
            default => 6.5,
        };

        $anchoMinimo = $lado + self::GAP;
        $columnas = max(1, (int) floor(self::FORMATO_ANCHO_UTIL / $anchoMinimo));
        $anchoCelda = self::FORMATO_ANCHO_UTIL / $columnas;
        $altoCelda = $lado + $altoTexto + self::GAP;

        return [
            'lado' => $lado,
            'columnas' => $columnas,
            'anchoCelda' => $anchoCelda,
            'altoCelda' => $altoCelda,
            'altoTexto' => $altoTexto,
            'fuenteNombre' => $fuenteNombre,
            'fuenteMeta' => $fuenteMeta,
        ];
    }

    /**
     * @param  array{lado: float, columnas: int, anchoCelda: float, altoCelda: float, altoTexto: float, fuenteNombre: float, fuenteMeta: float}  $medidas
     * @param  array{cursoLabel: string, curso?: string, seccion?: string, alumnos?: Collection<int, object>}  $bloque
     */
    private function dibujarTarjeta(float $x, float $y, array $medidas, object $alumno, array $bloque): void
    {
        $lado = $medidas['lado'];
        $anchoCelda = $medidas['anchoCelda'];
        $fotoX = $x + (($anchoCelda - $lado) / 2);
        $fotoY = $y;

        $this->dibujarFotoCuadrada($fotoX, $fotoY, $lado, $alumno);

        $textoX = $x + 0.4;
        $textoW = $anchoCelda - 0.8;
        $textoY = $fotoY + $lado + 0.6;
        $altoLineaNombre = match ($this->tamanoFoto) {
            ListadoEstudiantesFormatoTamanoFoto::GRANDE => 5.0,
            ListadoEstudiantesFormatoTamanoFoto::PEQUENO => 3.2,
            default => 4.0,
        };
        $altoNombreMax = $this->tamanoFoto === ListadoEstudiantesFormatoTamanoFoto::PEQUENO
            ? $altoLineaNombre
            : $altoLineaNombre * 2;
        $altoLineaMeta = match ($this->tamanoFoto) {
            ListadoEstudiantesFormatoTamanoFoto::GRANDE => 4.5,
            ListadoEstudiantesFormatoTamanoFoto::PEQUENO => 3.0,
            default => 3.6,
        };

        $this->SetTextColor(51, 51, 51);
        TcpdfFuenteArial::aplicar($this, 'B', $medidas['fuenteNombre']);
        $this->SetXY($textoX, $textoY);
        $this->MultiCell(
            $textoW,
            $altoLineaNombre,
            $this->formatoNombreAlumno($alumno),
            0,
            'C',
            false,
            1,
            $textoX,
            $textoY,
            true,
            0,
            false,
            true,
            $altoNombreMax,
            'T',
            false,
        );

        $yMeta = $this->GetY() + 0.2;
        TcpdfFuenteArial::aplicar($this, '', $medidas['fuenteMeta']);
        $this->SetXY($textoX, $yMeta);
        $this->Cell($textoW, $altoLineaMeta, $this->etiquetaCursoSeccion($bloque), 0, 2, 'C', false);

        $ano = $this->formatoDatos['ano'] ?? null;
        $anoTxt = $ano !== null && $ano !== '' ? (string) $ano : '—';
        $leyendaAno = $this->tamanoFoto === ListadoEstudiantesFormatoTamanoFoto::PEQUENO
            ? $anoTxt
            : 'Año lectivo '.$anoTxt;
        $this->Cell($textoW, $altoLineaMeta, $leyendaAno, 0, 2, 'C', false);
        $this->SetTextColor(0, 0, 0);
    }

    private function etiquetaCursoSeccion(array $bloque): string
    {
        $curso = trim((string) ($bloque['curso'] ?? ''));
        $seccion = trim((string) ($bloque['seccion'] ?? ''));
        $texto = trim($curso.($seccion !== '' ? ' '.$seccion : ''));
        if ($texto !== '') {
            return $texto;
        }

        $label = trim((string) ($bloque['cursoLabel'] ?? ''));

        return $label !== '' ? $label : '—';
    }

    private function dibujarFotoCuadrada(float $x, float $y, float $lado, object $alumno): void
    {
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(193, 215, 218);
        $this->SetFillColor(244, 248, 249);
        $this->Rect($x, $y, $lado, $lado, 'DF');

        $pathRel = trim((string) ($alumno->{FotoCarnetLegajo::COLUMNA} ?? ''));
        $dni = $alumno->dni ?? $alumno->DNI ?? null;
        $abs = FotoCarnetLegajo::rutaAbsolutaConRespaldo($pathRel !== '' ? $pathRel : null, $dni);
        $ruta = FotoCarnetLegajo::rutaParaTcpdf($abs);

        if ($ruta !== null) {
            $this->dibujarFotoProporcional($ruta, $x, $y, $lado);
        }

        $this->SetDrawColor(120, 120, 120);
        $this->SetLineWidth(0.2);
    }

    private function dibujarFotoProporcional(string $ruta, float $huecoX, float $huecoY, float $lado): bool
    {
        $info = @getimagesize($ruta);
        if ($info === false || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            $bin = @file_get_contents($ruta);
            $info = is_string($bin) && $bin !== '' ? @getimagesizefromstring($bin) : false;
        } else {
            $bin = null;
        }

        if ($info === false || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            return false;
        }

        $escala = min($lado / (float) $info[0], $lado / (float) $info[1]);
        $drawW = (float) $info[0] * $escala;
        $drawH = (float) $info[1] * $escala;
        $drawX = $huecoX + (($lado - $drawW) / 2);
        $drawY = $huecoY + (($lado - $drawH) / 2);

        $src = TcpdfImagenPng::fuenteTcpdf($ruta);
        $ok = $this->intentarImagenEnHueco($src, $huecoX, $huecoY, $lado, $drawX, $drawY, $drawW, $drawH);

        if (! $ok) {
            if (! is_string($bin) || $bin === '') {
                $bin = @file_get_contents($ruta);
            }
            if (! is_string($bin) || $bin === '') {
                return false;
            }
            $ok = $this->intentarImagenEnHueco('@'.$bin, $huecoX, $huecoY, $lado, $drawX, $drawY, $drawW, $drawH);
        }

        if (! $ok) {
            return false;
        }

        $this->SetLineWidth(0.2);
        $this->SetDrawColor(51, 51, 51);
        $this->Rect($drawX, $drawY, $drawW, $drawH);

        return true;
    }

    private function intentarImagenEnHueco(
        string $src,
        float $huecoX,
        float $huecoY,
        float $lado,
        float $drawX,
        float $drawY,
        float $drawW,
        float $drawH,
    ): bool {
        try {
            $this->StartTransform();
            $this->Rect($huecoX, $huecoY, $lado, $lado, 'CNZ');
            $this->Image($src, $drawX, $drawY, $drawW, $drawH, '', '', '', false, 150);
            $this->StopTransform();

            return true;
        } catch (\Throwable) {
            $this->StopTransform();

            return false;
        }
    }
}
