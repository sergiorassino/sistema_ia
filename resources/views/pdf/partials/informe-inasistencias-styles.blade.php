<style>
    /* DomPDF (legacy): DejaVu Sans — no Arial embebida; Helvetica/Arial solo como fallback visual. */
    @page { margin: 8mm 10mm 10mm 10mm; }
    body {
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        font-size: 7.5pt;
        color: #111;
        line-height: 1.1;
    }
    .cabecera-informe {
        width: calc(100% - 12px - 1.5pt);
        border: 0.75pt solid #111;
        border-radius: 6px;
        padding: 3px 5px 4px 5px;
        margin-bottom: 3px;
    }
    .cabecera-informe .pdf-header {
        border: 0;
        margin-bottom: 2px;
        width: 100%;
        padding: 2px 4px;
    }
    .cabecera-informe .pdf-header .logo-cell,
    .cabecera-informe .pdf-header .spacer-cell {
        width: 44px;
    }
    .cabecera-informe .pdf-header .logo-img {
        max-width: 44px;
        max-height: 44px;
    }
    .cabecera-informe .pdf-header .insti {
        font-size: 9pt;
        line-height: 1.05;
    }
    .cabecera-informe .pdf-header .line {
        font-size: 7pt;
        margin-top: 1px;
    }
    .cabecera-informe .pdf-header .line.ids {
        font-size: 6pt;
    }
    .cabecera-informe .titulo-informe {
        font-weight: 700;
        font-size: 8pt;
        text-align: center;
        text-transform: uppercase;
        margin: 3px 0 1px 0;
    }
    .cabecera-informe .alumno {
        font-weight: 700;
        font-size: 7.5pt;
        text-align: center;
        text-transform: uppercase;
        margin: 1px 0;
    }
    .cabecera-informe .curso {
        font-weight: 700;
        font-size: 7.5pt;
        text-align: center;
        text-transform: uppercase;
        margin: 0;
    }
    .cabecera-informe .periodo {
        font-weight: 400;
        font-size: 7pt;
        text-align: center;
        margin-top: 2px;
    }
    table.detalle {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4px;
        table-layout: fixed;
    }
    table.detalle thead {
        display: table-header-group;
    }
    table.detalle th,
    table.detalle td {
        border: 0.75pt solid #333;
        padding: 0.5px 2px;
        font-size: 6.5pt;
        line-height: 1.05;
        vertical-align: middle;
    }
    table.detalle th {
        font-weight: 700;
        text-align: center;
        background: #f5f5f5;
    }
    table.detalle .col-fecha { width: 18%; text-align: center; }
    table.detalle .col-cant { width: 12%; text-align: center; }
    table.detalle .col-tipo { width: 28%; }
    table.detalle .col-just { width: 10%; text-align: center; }
    table.detalle .col-obs { width: 32%; }
    .totales { margin: 2px 0 6px 0; font-size: 7pt; }
    .totales p { margin: 1px 0; }
    .totales .label { font-weight: 700; }
    .firmas {
        width: 100%;
        margin-top: 8px;
    }
    .firmas table { width: 100%; border-collapse: collapse; }
    .firmas td { width: 50%; vertical-align: top; text-align: center; padding: 0 8px; }
    .firmas .linea {
        border-top: 0.75pt dotted #333;
        margin: 0 auto 2px auto;
        width: 85%;
        height: 12px;
    }
    .firmas .etiqueta { font-size: 6.5pt; margin: 0; }
    /* Lote: varios informes en flujo; sin salto forzado entre alumnos si entra en la misma hoja */
    body.informe-inasistencias-lote .informe-hoja {
        margin-bottom: 3mm;
        page-break-inside: auto;
    }
    body.informe-inasistencias-lote .informe-hoja + .informe-hoja {
        page-break-before: auto;
    }
</style>
