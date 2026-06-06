# Fuentes para PDFs TCPDF

Colocar aquí los archivos TrueType de **Arial** (desarrollo local y producción):

- `arial.ttf` (regular) — obligatorio para Arial en PDFs TCPDF
- `arialbd.ttf` (negrita) — opcional

El código **solo** lee esta carpeta (`storage/fonts/`) o, en su defecto, `resources/fonts/`. No usa fuentes de Windows ni de `/usr/share/fonts/`.

Sin `arial.ttf`, TCPDF usará `helvetica` (sustituto limitado para tildes y ñ).

Uso en código: `App\Support\Pdf\TcpdfFuenteArial::aplicar($pdf, 'B', 9);`
