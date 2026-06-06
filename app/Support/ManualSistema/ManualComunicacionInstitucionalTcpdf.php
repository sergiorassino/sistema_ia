<?php

namespace App\Support\ManualSistema;

use App\Support\Pdf\TcpdfFuenteArial;
use TCPDF;

/**
 * Manual de uso del módulo de Comunicación institucional (A4 vertical, TCPDF).
 *
 * Objetivo: documento autoexplicativo para personal que no conoce el sistema.
 */
final class ManualComunicacionInstitucionalTcpdf extends TCPDF
{
    private const MARGEN_IZQ = 14.0;
    private const MARGEN_DER = 14.0;
    private const MARGEN_SUP = 12.0;
    private const MARGEN_INF = 12.0;

    /** Azul institucional (aprox). */
    private const COLOR_PRIMARIO = [37, 99, 235];

    /** Gris texto. */
    private const COLOR_TEXTO = [17, 24, 39];

    /** Gris suave para cajas. */
    private const COLOR_CAJA = [245, 247, 250];

    /** @var array{titulo:string,subtitulo:string,version:string,generado:string,colegio:?string,base_url:?string} */
    private array $meta;

    /** @var array<string,int> */
    private array $sectionLinks = [];

    /**
     * @param  array{titulo:string,subtitulo:string,version:string,generado:string,colegio:?string,base_url:?string}  $meta
     */
    private function __construct(array $meta)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->meta = $meta;

        $this->SetCreator('Sistema Escolar');
        $this->SetAuthor('Sistema Escolar');
        $this->SetTitle((string) ($meta['titulo'] ?? 'Manual de comunicaciones'));

        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetAutoPageBreak(true, self::MARGEN_INF);
        $this->SetMargins(self::MARGEN_IZQ, self::MARGEN_SUP, self::MARGEN_DER);
    }

    /**
     * @param  array{colegio:?string,base_url:?string}  $ctx
     */
    public static function generar(array $ctx = []): self
    {
        $colegio = isset($ctx['colegio']) && is_string($ctx['colegio']) && trim($ctx['colegio']) !== ''
            ? trim($ctx['colegio'])
            : null;

        $meta = [
            'titulo' => 'Manual de uso — Comunicación institucional',
            'subtitulo' => 'Bandeja · Nuevo comunicado · Control de cuaderno · Canales',
            'version' => '1.0',
            'generado' => now()->format('d/m/Y'),
            'colegio' => $colegio,
            'base_url' => (isset($ctx['base_url']) && is_string($ctx['base_url']) && trim($ctx['base_url']) !== '')
                ? rtrim(trim($ctx['base_url']), '/')
                : null,
        ];

        $pdf = new self($meta);
        $pdf->crearLinks();

        $pdf->AddPage();
        $pdf->renderPortada();

        $pdf->AddPage();
        $pdf->renderIndice();

        $pdf->AddPage();
        $pdf->renderSeccion('Introducción y lógica general', 'intro', function () use ($pdf): void {
            $pdf->p(
                'El módulo de Comunicación institucional funciona como un “cuaderno de comunicados digital”: ' .
                'la escuela envía mensajes a familias (alumnos, cursos o todo el colegio) y también puede haber ' .
                'comunicaciones internas entre personal (docentes / preceptores / directivos), según la configuración.',
            );

            $pdf->box('Conceptos clave', [
                ['Hilo', 'Es la conversación agrupada por asunto. Un hilo contiene uno o más mensajes.'],
                ['Mensaje', 'Cada publicación dentro de un hilo (mensaje inicial o respuesta).'],
                ['Destinatario', 'Persona/familia que puede recibir notificación por uno o más medios.'],
                ['Lectura', 'Cada destinatario registra “leído” al abrir el mensaje.'],
                ['Respuesta', 'Si está habilitada, el destinatario puede responder, creando un nuevo mensaje en el hilo.'],
            ]);

            $pdf->box('Dónde se ve el módulo', [
                ['Gestión / Secretaría', 'Menú Comunicación institucional (bandeja, nuevo, revisión/control).'],
                ['Portal Docente', 'Menú Docentes → Comunicaciones (mismas pantallas, según permisos).'],
                ['Portal Familias', 'Portal → Comunicaciones (bandeja, nuevo y preferencias, según habilitación).'],
            ]);

            $pdf->h2('Permisos habituales (referencia)');
            $pdf->p(
                'El sistema muestra opciones según permisos. En instalaciones típicas se usan estos permisos internos:',
            );
            $pdf->bullets([
                'Bandeja: permiso 3.',
                'Nuevo comunicado: permiso 4 (además del 3).',
                'Control / Revisión: permiso 8 (además del 3).',
                'Canales (configuración): permiso 5 (se usa dentro del área de configuración).',
                'Borrar mensajes: permiso 6 (propios) y 7 (ajenos).',
            ]);
        });

        $pdf->AddPage();
        $pdf->renderSeccion('Bandeja de comunicados', 'bandeja', function () use ($pdf): void {
            $pdf->enlacesPantalla([
                ['Abrir bandeja (gestión)', '/comunicaciones'],
                ['Abrir bandeja (portal docente)', '/portal-docente/comunicaciones'],
                ['Abrir bandeja (familias)', '/alumnos/comunicaciones'],
            ]);
            $pdf->p(
                'La bandeja es el punto de entrada: lista los hilos del año lectivo en el contexto seleccionado (nivel y ciclo). ' .
                'Desde aquí se abre cada hilo para leer, responder, marcar como no leído o eliminar mensajes (si aplica).',
            );

            $pdf->h2('Filtros disponibles');
            $pdf->box('Año lectivo', [
                ['Actual', 'Muestra hilos del ciclo lectivo activo del contexto.'],
                ['Toda la historia', 'Incluye hilos de otros ciclos (útil para búsquedas históricas).'],
            ]);
            $pdf->box('Estado', [
                ['Todos', 'Muestra todo lo que entra en su bandeja.'],
                ['No leídos', 'Muestra hilos donde usted tiene al menos un destinatario pendiente de lectura.'],
            ]);

            $pdf->h2('Qué significa cada etiqueta');
            $pdf->bullets([
                'Recibido: el hilo lo inició una familia u otro usuario; usted figura como destinatario.',
                'Enviado: el hilo lo inició usted (u otro personal) y se envió hacia familias o hacia docentes.',
                '“Ver conversación”: indica que hay más de un mensaje en el hilo.',
                '“X no leído(s)”: cantidad de destinatarios/entradas pendientes para usted en ese hilo.',
                '“Solo informativo”: el hilo fue enviado sin permitir respuestas (familias o docentes, según el caso).',
            ]);

            $pdf->h2('Buenas prácticas de uso');
            $pdf->bullets([
                'Use el filtro “No leídos” como cola de pendientes.',
                'Antes de responder, revise el “Para / De” y el asunto para evitar respuestas en el hilo equivocado.',
                'Si un mensaje requiere seguimiento institucional, use el control de cuaderno (revisión) para auditoría.',
            ]);
        });

        $pdf->AddPage();
        $pdf->renderSeccion('Nuevo comunicado', 'nuevo', function () use ($pdf): void {
            $pdf->enlacesPantalla([
                ['Nuevo comunicado (gestión)', '/comunicaciones/nuevo'],
                ['Nuevo comunicado (portal docente)', '/portal-docente/comunicaciones/nuevo'],
                ['Nuevo comunicado (familias)', '/alumnos/comunicaciones/nuevo'],
            ]);
            $pdf->p(
                'Esta pantalla permite iniciar un hilo nuevo con un primer mensaje. El envío puede ser hacia familias (estudiantes) ' .
                'o hacia docentes/personal, según se elija el bloque de destinatarios.',
            );

            $pdf->h2('1) Elegir a quién se envía');
            $pdf->box('Bloque de destinatarios', [
                ['Estudiantes (familias)', 'Envía a alumnos puntuales, a cursos o a todo el colegio.'],
                ['Docentes', 'Envía a docentes o a personal institucional (p. ej. preceptores), según el selector.'],
            ]);

            $pdf->h2('2) Tipos de destino (familias)');
            $pdf->box('Destino', [
                ['Alumnos', 'Selecciona uno o varios alumnos matriculados en el contexto (nivel + ciclo).'],
                ['Cursos', 'Selecciona uno o varios cursos; el sistema calcula automáticamente los alumnos matriculados y envía a sus familias.'],
                ['Colegio', 'Envía a todos los alumnos matriculados del nivel y ciclo (envío masivo).'],
            ]);

            $pdf->h2('3) Redacción y límites');
            $pdf->bullets([
                'Asunto: obligatorio (límite configurable, típico 200 caracteres).',
                'Contenido: obligatorio (límite configurable, típico 2000 caracteres).',
                'Evite pegar texto con formatos raros: priorice mensajes claros, con fechas y acciones concretas.',
            ]);

            $pdf->h2('4) Respuestas permitidas');
            $pdf->box('Opciones de respuesta', [
                ['Familia puede responder', 'Si se desactiva, el hilo queda “solo informativo” para familias.'],
                ['Docentes destinatarios pueden responder', 'En envíos internos a docentes, permite o bloquea respuestas dentro del hilo.'],
            ]);

            $pdf->h2('5) Qué pasa al enviar');
            $pdf->bullets([
                'Se crea el hilo y el mensaje inicial.',
                'Se registran destinatarios (familia o profesor) y estados de lectura.',
                'El sistema distribuye por los “medios” habilitados en la configuración de canales (push, email, WhatsApp).',
                'Se muestra el “Informe de envío” con el detalle por medio y destinatario.',
            ]);

            $pdf->callout(
                'Tip operativo',
                'Si el envío incluye WhatsApp con modalidad manual, el sistema puede mostrar enlaces “wa.me” para completar el envío desde el dispositivo.',
            );
        });

        $pdf->AddPage();
        $pdf->renderSeccion('Informe de envío (seguimiento)', 'informe', function () use ($pdf): void {
            $pdf->enlacesPantalla([
                ['Informe de envío (gestión)', '/comunicaciones/informe-envio/{id}'],
                ['Informe de envío (portal docente)', '/portal-docente/comunicaciones/informe-envio/{id}'],
            ]);
            $pdf->p(
                'Luego de enviar un comunicado, el informe permite verificar qué medios se intentaron usar, si el envío está pendiente, falló o no aplica, y quién lo recibió.',
            );

            $pdf->box('Estados típicos', [
                ['Enviado', 'El medio reporta envío correcto o fue marcado como enviado.'],
                ['Pendiente', 'El envío está en cola o aún no se procesó.'],
                ['Fallido', 'Hubo error en el envío (ver motivo).'],
                ['No aplica', 'El destinatario no tiene ese medio disponible o no corresponde.'],
                ['(Envío manual)', 'Caso particular: WhatsApp manual (se completa fuera del sistema).'],
            ]);

            $pdf->h2('Cómo usarlo');
            $pdf->bullets([
                'Si un envío falló: revise el motivo y la configuración del canal.',
                'Si figura “No aplica”: confirme que el destinatario tenga el medio habilitado (por ejemplo email cargado).',
                'Use este informe para auditoría interna (acreditación de notificación).',
            ]);
        });

        $pdf->AddPage();
        $pdf->renderSeccion('Control de cuaderno de comunicados (revisión)', 'control', function () use ($pdf): void {
            $pdf->enlacesPantalla([
                ['Control / Revisión (gestión)', '/comunicaciones/revision'],
                ['Control / Revisión (portal docente)', '/portal-docente/comunicaciones/revision'],
            ]);
            $pdf->p(
                'El control/revisión es una bandeja institucional para supervisar comunicaciones del nivel y ciclo: ' .
                'sirve para auditoría, búsqueda y seguimiento, no solo para “mi bandeja”.',
            );

            $pdf->h2('Qué permite hacer');
            $pdf->bullets([
                'Ver todos los hilos del nivel/ciclo (no solo los que lo involucran).',
                'Filtrar por no leídos y por dirección (recibidos / enviados).',
                'Acotar la vista a un usuario: profesor o estudiante (búsqueda unificada).',
            ]);

            $pdf->callout(
                'Nota',
                'Esta pantalla requiere permisos adicionales (típicamente permiso 8). Si no la ve en el menú, su rol no tiene habilitada la revisión.',
            );
        });

        $pdf->AddPage();
        $pdf->renderSeccion('Configuración de canales y medios', 'canales', function () use ($pdf): void {
            $pdf->enlacesPantalla([
                ['Configuración de canales', '/parametrizacion/com-canales'],
            ]);
            $pdf->p(
                'Los canales definen qué combinaciones de roles pueden comunicarse (quién puede iniciar, quién puede responder) y por qué medios se distribuye el mensaje.',
            );

            $pdf->box('Qué se configura en un canal', [
                ['Emisor → Receptor', 'Ej.: Profesor → Familia, Directivo → Profesor, etc.'],
                ['Puede iniciar', 'Si el emisor puede crear un hilo nuevo hacia el receptor.'],
                ['Puede responder', 'Si el emisor puede responder en un hilo existente hacia el receptor.'],
                ['Medios permitidos', 'push, email, whatsapp (según disponibilidad).'],
                ['Activo', 'Si está inactivo, la combinación no se usa para distribución.'],
            ]);

            $pdf->h2('Recomendaciones');
            $pdf->bullets([
                'Antes de un envío masivo, verifique que el canal Profesor → Familia esté activo y con medios coherentes.',
                'Evite habilitar WhatsApp si no está definido el procedimiento (automático vs manual).',
                'Mantenga coherencia: si se permite iniciar, evalúe si también debe permitirse responder (política institucional).',
            ]);

            $pdf->callout(
                'Impacto directo',
                'Si no hay medios habilitados para un tipo de envío, el sistema puede bloquear el envío con un mensaje del estilo “No hay medios habilitados… Revise la parametrización de canales”.',
            );
        });

        $pdf->AddPage();
        $pdf->renderSeccion('Checklist rápido (para operar sin errores)', 'checklist', function () use ($pdf): void {
            $pdf->bullets([
                'Confirmar nivel y ciclo lectivo activos antes de enviar.',
                'Elegir correctamente el bloque de destinatarios (familias vs docentes).',
                'Para cursos/colegio: validar que haya matrícula cargada (si no, no habrá destinatarios).',
                'Definir si el comunicado admite respuestas (solo informativo vs conversacional).',
                'Luego de enviar: revisar el informe de envío y documentar fallos si los hubiera.',
                'Si hay dudas de alcance: usar Control de cuaderno para ver trazabilidad.',
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
            'intro',
            'bandeja',
            'nuevo',
            'informe',
            'control',
            'canales',
            'checklist',
        ] as $k) {
            $this->sectionLinks[$k] = $this->AddLink();
        }
    }

    private function renderPortada(): void
    {
        $this->SetTextColor(...self::COLOR_TEXTO);

        $w = $this->getPageWidth();
        $h = $this->getPageHeight();

        // Fondo superior
        $this->SetFillColor(...self::COLOR_PRIMARIO);
        $this->Rect(0, 0, $w, 58, 'F');

        // Título
        $this->SetXY(self::MARGEN_IZQ, 18);
        $this->SetTextColor(255, 255, 255);
        TcpdfFuenteArial::aplicar($this, 'B', 20);
        $this->MultiCell($w - self::MARGEN_IZQ - self::MARGEN_DER, 10, $this->meta['titulo'], 0, 'L', false, 1);

        TcpdfFuenteArial::aplicar($this, '', 11.5);
        $this->SetTextColor(255, 255, 255);
        $this->MultiCell($w - self::MARGEN_IZQ - self::MARGEN_DER, 6, $this->meta['subtitulo'], 0, 'L', false, 1);

        // Caja de meta
        $this->SetTextColor(...self::COLOR_TEXTO);
        $this->SetFillColor(...self::COLOR_CAJA);
        $this->RoundedRect(self::MARGEN_IZQ, 78, $w - self::MARGEN_IZQ - self::MARGEN_DER, 44, 3.0, '1111', 'F');
        $this->SetXY(self::MARGEN_IZQ + 6, 86);

        TcpdfFuenteArial::aplicar($this, 'B', 10.5);
        $this->Cell(0, 6, 'Datos del documento', 0, 1, 'L');
        TcpdfFuenteArial::aplicar($this, '', 9.5);

        $colegio = $this->meta['colegio'];
        if ($colegio !== null) {
            $this->lineaMeta('Institución', $colegio);
        }
        $this->lineaMeta('Versión', (string) $this->meta['version']);
        $this->lineaMeta('Generado', (string) $this->meta['generado']);

        // Pie
        $this->SetY($h - 28);
        $this->SetTextColor(107, 114, 128);
        TcpdfFuenteArial::aplicar($this, '', 8.5);
        $this->MultiCell(
            $w - self::MARGEN_IZQ - self::MARGEN_DER,
            4.5,
            'Documento de uso interno. Los nombres de pantallas pueden variar según permisos y configuración.',
            0,
            'L',
            false,
            1,
            self::MARGEN_IZQ,
        );
    }

    private function renderIndice(): void
    {
        $this->SetTextColor(...self::COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($this, 'B', 16);
        $this->Cell(0, 10, 'Índice', 0, 1, 'L');

        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->SetTextColor(55, 65, 81);
        $this->MultiCell(0, 5.5, 'Hacé clic sobre un ítem para ir a la sección correspondiente.', 0, 'L', false, 1);
        $this->Ln(2);

        $this->SetTextColor(...self::COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($this, 'B', 11);

        $this->tocItem('Introducción y lógica general', $this->sectionLinks['intro']);
        $this->tocItem('Bandeja de comunicados', $this->sectionLinks['bandeja']);
        $this->tocItem('Nuevo comunicado', $this->sectionLinks['nuevo']);
        $this->tocItem('Informe de envío (seguimiento)', $this->sectionLinks['informe']);
        $this->tocItem('Control de cuaderno de comunicados (revisión)', $this->sectionLinks['control']);
        $this->tocItem('Configuración de canales y medios', $this->sectionLinks['canales']);
        $this->tocItem('Checklist rápido', $this->sectionLinks['checklist']);

        $this->SetTextColor(...self::COLOR_TEXTO);
        $this->Ln(6);
        TcpdfFuenteArial::aplicar($this, '', 9.5);
        $this->box('Atajo', [
            ['Manual general', 'El manual general del sistema está disponible como PDF desde el menú lateral (si su perfil lo permite).'],
        ]);
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

        $this->SetTextColor(...self::COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($this, 'B', 15);
        $this->MultiCell(0, 8, $titulo, 0, 'L', false, 1);
        $this->Ln(1);

        $contenido();
    }

    private function tocItem(string $label, int $linkId): void
    {
        $x = $this->GetX();
        $y = $this->GetY();
        $this->Write(6.5, $label, $linkId, false, 'L', true);
        // Aumenta un poco el área clicable (por si el visor no toma el link del texto).
        $this->Link($x, $y, 180, 6.5, $linkId);
    }

    private function h2(string $text): void
    {
        $this->Ln(2.5);
        $this->SetTextColor(...self::COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($this, 'B', 11.5);
        $this->MultiCell(0, 6, $text, 0, 'L', false, 1);
        $this->Ln(0.5);
    }

    private function p(string $text): void
    {
        $this->SetTextColor(31, 41, 55);
        TcpdfFuenteArial::aplicar($this, '', 10);
        $this->MultiCell(0, 5.6, $text, 0, 'L', false, 1);
        $this->Ln(1.2);
    }

    /**
     * @param  list<string>  $items
     */
    private function bullets(array $items): void
    {
        $this->SetTextColor(31, 41, 55);
        TcpdfFuenteArial::aplicar($this, '', 10);
        foreach ($items as $it) {
            $this->MultiCell(0, 5.4, '• '.$it, 0, 'L', false, 1);
        }
        $this->Ln(1.2);
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

        $this->SetFillColor(...self::COLOR_CAJA);
        $this->RoundedRect($x, $y, $w, $altoEstimado, 2.5, '1111', 'F');
        $this->SetXY($x + 5, $y + 4);

        $this->SetTextColor(...self::COLOR_TEXTO);
        TcpdfFuenteArial::aplicar($this, 'B', 10.5);
        $this->Cell(0, 6, $title, 0, 1, 'L');
        $this->Ln(0.2);

        foreach ($rows as [$k, $v]) {
            TcpdfFuenteArial::aplicar($this, 'B', 9.3);
            $this->SetTextColor(55, 65, 81);
            $this->MultiCell(48, 5.6, $k.':', 0, 'L', false, 0);

            TcpdfFuenteArial::aplicar($this, '', 9.3);
            $this->SetTextColor(31, 41, 55);
            $this->MultiCell($w - 48 - 8, 5.6, $v, 0, 'L', false, 1);
        }

        $this->SetXY($x, $y + $altoEstimado + 3);
    }

    private function callout(string $title, string $text): void
    {
        $this->SetFillColor(239, 246, 255);
        $this->SetDrawColor(191, 219, 254);
        $this->RoundedRect($this->GetX(), $this->GetY(), $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER, 0, 2.5, '1111', 'DF');

        $x = $this->GetX();
        $y = $this->GetY();
        $w = $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER;
        $h = 18;

        $this->RoundedRect($x, $y, $w, $h, 2.5, '1111', 'F');
        $this->SetXY($x + 5, $y + 4);

        $this->SetTextColor(...self::COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($this, 'B', 10.2);
        $this->Cell(0, 5.5, $title, 0, 1, 'L');

        $this->SetTextColor(30, 58, 138);
        TcpdfFuenteArial::aplicar($this, '', 9.5);
        $this->MultiCell($w - 10, 5.2, $text, 0, 'L', false, 1);

        $this->Ln(2);
    }

    private function lineaMeta(string $k, string $v): void
    {
        TcpdfFuenteArial::aplicar($this, 'B', 9.5);
        $this->SetTextColor(55, 65, 81);
        $this->MultiCell(28, 5.5, $k.':', 0, 'L', false, 0);
        TcpdfFuenteArial::aplicar($this, '', 9.5);
        $this->SetTextColor(31, 41, 55);
        $this->MultiCell(0, 5.5, $v, 0, 'L', false, 1);
    }

    /**
     * @param  list<array{0:string,1:string}>  $items
     */
    private function enlacesPantalla(array $items): void
    {
        $base = $this->meta['base_url'] ?? null;
        if (! is_string($base) || $base === '') {
            return;
        }

        $rows = [];
        foreach ($items as [$label, $path]) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            $url = $base.(str_starts_with($path, '/') ? $path : '/'.$path);
            $rows[] = [$label, $url];
        }

        if ($rows === []) {
            return;
        }

        $this->SetFillColor(250, 250, 251);
        $this->RoundedRect($this->GetX(), $this->GetY(), $this->getPageWidth() - self::MARGEN_IZQ - self::MARGEN_DER, 0, 2.5, '1111', 'F');

        $this->box('Enlaces a pantallas (clicables)', array_map(function (array $r): array {
            $label = (string) ($r[0] ?? '');
            $url = (string) ($r[1] ?? '');
            return [$label, $url];
        }, $rows));

        // Reescribe el contenido de la caja como links reales (TCPDF no “auto-linkea” todo texto).
        // Colocamos links lineales justo debajo como alternativa segura.
        $this->SetTextColor(...self::COLOR_PRIMARIO);
        TcpdfFuenteArial::aplicar($this, 'B', 9.8);
        $this->MultiCell(0, 5.5, 'Accesos rápidos:', 0, 'L', false, 1);
        TcpdfFuenteArial::aplicar($this, '', 9.6);
        foreach ($rows as [$label, $url]) {
            $this->SetTextColor(55, 65, 81);
            $this->Write(5.2, $label.': ', '', false, 'L', false);
            $this->SetTextColor(...self::COLOR_PRIMARIO);
            $this->Write(5.2, $url, $url, false, 'L', true);
        }
        $this->Ln(2);
        $this->SetTextColor(...self::COLOR_TEXTO);
    }
}

