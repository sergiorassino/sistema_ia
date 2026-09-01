<?php

namespace App\Support\Alumnos;

use App\Support\Alumnos\PortalFamiliaBoletinIpe;
use App\Support\Alumnos\PortalFamiliaBoletinPrimEpq;
use App\Support\Alumnos\PortalFamiliaBoletinEpqSecundario;
use App\Support\Alumnos\PortalFamiliaBoletinInicialSfq;
use App\Support\Alumnos\PortalFamiliaInformeProgresoInicial;
use App\Comunicaciones\ComunicacionesRepository;
use App\Models\Ento;
use App\Support\EntoVerNotasOff;
use App\Support\InformeInasistencias;
use App\Support\MatriculaBloqueos;

/**
 * Escritorio del portal familia — accesos rápidos y datos para widgets.
 * Nuevos widgets: ampliar `accesosRapidos()` o `widgets()` según el tipo.
 */
final class PortalFamiliaDashboard
{
    /**
     * @return list<array{
     *   id: string,
     *   titulo: string,
     *   descripcion: string,
     *   url: string,
     *   externo: bool,
     *   icono: string,
     *   aviso?: string,
     *   aviso_titulo?: string
     * }>
     */
    public static function accesosRapidos(): array
    {
        $accesos = [];
        $bloqueoVerNotas = EntoVerNotasOff::paraEstudianteActual();

        if (PortalFamiliaBoletinPrimEpq::habilitadoEnMenu()) {
            foreach (PortalFamiliaBoletinPrimEpq::items() as $item) {
                $accesos[] = EntoVerNotasOff::aplicarAvisoAAcceso([
                    'id' => 'boletin-prim-epq-'.$item['cara'],
                    'titulo' => $item['titulo'],
                    'descripcion' => 'Boletín de calificaciones del ciclo lectivo activo.',
                    'url' => $item['url'],
                    'externo' => true,
                    'icono' => 'calificaciones',
                ], $bloqueoVerNotas);
            }
        } elseif (PortalFamiliaBoletinEpqSecundario::habilitadoEnMenu()) {
            $accesos[] = EntoVerNotasOff::aplicarAvisoAAcceso([
                'id' => 'consulta-calificaciones-epq-sec',
                'titulo' => PortalFamiliaBoletinEpqSecundario::tituloMenu(),
                'descripcion' => 'Informe de calificaciones del ciclo lectivo activo.',
                'url' => PortalFamiliaBoletinEpqSecundario::urlPdf(),
                'externo' => true,
                'icono' => 'calificaciones',
            ], $bloqueoVerNotas);
        } elseif (PortalFamiliaBoletinIpe::habilitadoEnMenu()) {
            foreach (PortalFamiliaBoletinIpe::itemsEtapa() as $item) {
                $accesos[] = EntoVerNotasOff::aplicarAvisoAAcceso([
                    'id' => 'boletin-ipe-etapa-'.$item['etapa'],
                    'titulo' => $item['titulo'],
                    'descripcion' => 'Boletín de calificaciones del ciclo lectivo activo.',
                    'url' => $item['url'],
                    'externo' => true,
                    'icono' => 'calificaciones',
                ], $bloqueoVerNotas);
            }
        } elseif (PortalFamiliaBoletinInicialSfq::habilitadoEnMenu()) {
            foreach (PortalFamiliaBoletinInicialSfq::items() as $item) {
                $accesos[] = EntoVerNotasOff::aplicarAvisoAAcceso([
                    'id' => 'informe-pedagogico-inicial-sfq-'.$item['tipo'],
                    'titulo' => $item['titulo'],
                    'descripcion' => 'Informe pedagógico del ciclo lectivo activo.',
                    'url' => $item['url'],
                    'externo' => true,
                    'icono' => 'calificaciones',
                ], $bloqueoVerNotas);
            }
        } elseif (PortalFamiliaInformeProgresoInicial::habilitadoEnMenu()) {
            foreach (PortalFamiliaInformeProgresoInicial::itemsEtapa() as $item) {
                $accesos[] = EntoVerNotasOff::aplicarAvisoAAcceso([
                    'id' => 'informe-progreso-inicial-'.$item['etapa'],
                    'titulo' => $item['titulo'],
                    'descripcion' => 'Informe de progreso escolar del ciclo lectivo activo.',
                    'url' => $item['url'],
                    'externo' => true,
                    'icono' => 'calificaciones',
                ], $bloqueoVerNotas);
            }
        } elseif (PortalFamiliaBoletinIpe::consultaSecundariaVisible()) {
            $accesos[] = EntoVerNotasOff::aplicarAvisoAAcceso([
                'id' => 'calificaciones',
                'titulo' => 'Consulta de calificaciones',
                'descripcion' => 'Calificaciones del ciclo lectivo activo.',
                'url' => se_route_url('alumnos.calificaciones'),
                'externo' => true,
                'icono' => 'calificaciones',
            ], $bloqueoVerNotas);
        }

        if (tenantAutogestionInformeInasistenciasHabilitada()) {
            $accesos[] = [
                'id' => 'inasistencias',
                'titulo' => 'Informe de Inasistencias',
                'descripcion' => 'Resumen de inasistencias del estudiante.',
                'url' => se_route_url('alumnos.inasistencias.informe'),
                'externo' => true,
                'icono' => 'inasistencias',
            ];
        }

        $restriccionFichaYDatos = MatriculaBloqueos::paraEstudianteActual();

        if (tenantAutogestionActualizacionDatosHabilitada()) {
            $accesoDatos = [
                'id' => 'actualizacion-datos',
                'titulo' => 'Actualización de datos personales',
                'descripcion' => 'Revise y actualice los datos del legajo.',
                'url' => se_route_url('alumnos.actualizacion-datos'),
                'externo' => false,
                'icono' => 'datos',
            ];
            if ($restriccionFichaYDatos['bloqueada']) {
                $accesoDatos['aviso'] = $restriccionFichaYDatos['mensaje'];
                $accesoDatos['aviso_titulo'] = 'Actualización de datos';
            }
            $accesos[] = $accesoDatos;
        }

        if (tenantAutogestionFichaMatriculaHabilitada()) {
            $accesoFicha = [
                'id' => 'ficha-matricula',
                'titulo' => 'Imprimir ficha de matrícula',
                'descripcion' => 'Descargue la ficha en PDF.',
                'url' => se_route_url('alumnos.ficha-matricula'),
                'externo' => true,
                'icono' => 'ficha',
            ];
            if ($restriccionFichaYDatos['bloqueada']) {
                $accesoFicha['aviso'] = $restriccionFichaYDatos['mensaje'];
                $accesoFicha['aviso_titulo'] = 'Ficha de matrícula';
                $accesoFicha['externo'] = false;
            }
            $accesos[] = $accesoFicha;
        }

        if (tenantAutogestionCusHabilitada()) {
            $accesos[] = [
                'id' => 'cus',
                'titulo' => 'Imprimir C.U.S.',
                'descripcion' => 'Descargue el Certificado Único de Salud en PDF.',
                'url' => se_route_url('alumnos.cus'),
                'externo' => true,
                'icono' => 'cus',
            ];
        }

        if (tenantAutogestionIsaHabilitada()) {
            $accesos[] = [
                'id' => 'isa',
                'titulo' => 'Imprimir I.S.A.',
                'descripcion' => 'Descargue el Informe de Salud Anual en PDF.',
                'url' => se_route_url('alumnos.isa'),
                'externo' => true,
                'icono' => 'isa',
            ];
        }

        if (tenantAutogestionArancelesEscolaresHabilitada()) {
            $accesos[] = [
                'id' => 'aranceles-escolares',
                'titulo' => tenantAutogestionArancelesEscolaresMenuEtiqueta(),
                'descripcion' => 'Cuotas pendientes y comprobantes de pago.',
                'url' => se_route_url('alumnos.aranceles-escolares'),
                'externo' => false,
                'icono' => 'aranceles',
            ];
        }

        $aulicaUrl = trim((string) config('tenant.autogestion.aranceles_aulica_url', ''));
        if ($aulicaUrl !== '') {
            $accesos[] = [
                'id' => 'aranceles-aulica',
                'titulo' => 'Gestión de aranceles escolares',
                'descripcion' => 'Portal externo de pagos y aranceles.',
                'url' => $aulicaUrl,
                'externo' => true,
                'icono' => 'aranceles',
            ];
        }

        if (tenantAutogestionHorarioClaseHabilitada()) {
            $accesos[] = [
                'id' => 'horario-clase',
                'titulo' => 'Horario de clase',
                'descripcion' => 'Horario del curso en PDF.',
                'url' => se_route_url('alumnos.horario-clase'),
                'externo' => true,
                'icono' => 'horario',
            ];
        }

        if (tenantAutogestionComunicacionesHabilitada()) {
            $accesos[] = [
                'id' => 'comunicaciones',
                'titulo' => 'Bandeja de comunicados',
                'descripcion' => 'Mensajes con la institución.',
                'url' => se_route_url('alumnos.comunicaciones.index'),
                'externo' => false,
                'icono' => 'comunicaciones',
            ];
        }

        return $accesos;
    }

    /**
     * Widgets de panel (estadísticas, avisos, etc.) para el escritorio.
     *
     * @return list<array{id: string, vista: string, datos: array<string, mixed>}>
     */
    public static function widgets(): array
    {
        $widgets = [];

        if (tenantAutogestionComunicacionesHabilitada()) {
            $ctx = studentCtx();
            $widgets[] = [
                'id' => 'comunicaciones',
                'vista' => 'alumnos.dashboard.widgets.comunicaciones',
                'datos' => [
                    'bandeja' => ComunicacionesRepository::resumenBandejaFamilia(
                        (int) $ctx->idLegajo,
                        (int) $ctx->idNivel,
                        (int) $ctx->idTerlec,
                    ),
                ],
            ];
        }

        return $widgets;
    }

    public static function nombreInstitucion(): string
    {
        $idNivel = (int) (studentCtx()->idNivel ?? 0);

        if ($idNivel > 0) {
            $insti = trim((string) (Ento::query()
                ->where('idNivel', $idNivel)
                ->value('insti') ?? ''));

            if ($insti !== '') {
                return $insti;
            }
        }

        return (string) config('tenant.nombre', 'Colegio');
    }

    public static function nombreEstudiante(): string
    {
        $alumno = studentCtx()->alumno();
        $nombre = trim(((string) ($alumno?->nombre ?? '')).' '.((string) ($alumno?->apellido ?? '')));

        return $nombre !== '' ? $nombre : 'Estudiante';
    }

    /**
     * DNI y curso/sección del ciclo activo (idTerlecVerNotas) para el escritorio.
     * El curso sale solo de la matrícula de autogestión (sin fallback a cuotas).
     *
     * @return array{dni: string, curso: string}
     */
    public static function datosSesion(): array
    {
        $legajo = studentCtx()->alumno();
        $dni = $legajo !== null
            ? ArancelesEscolares::formatearDni($legajo->dni ?? '')
            : '';

        $curso = '';
        if (InformeInasistencias::tieneMatriculaCursoAutogestion()) {
            $curso = InformeInasistencias::cursoNombreAutogestion();
        }

        return [
            'dni' => $dni !== '' ? $dni : '—',
            'curso' => $curso !== '' ? mb_strtoupper($curso) : '',
        ];
    }
}
