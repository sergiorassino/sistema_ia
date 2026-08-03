<?php

use App\Http\Controllers\EmailsMasivosAdjuntoDownloadController;
use App\Http\Controllers\Alumnos\BoletinIpePrimarioPdfController;
use App\Http\Controllers\Alumnos\BoletinPrimEpqFamiliaPdfController;
use App\Http\Controllers\Alumnos\BoletinEpqSecundarioFamiliaPdfController;
use App\Http\Controllers\Alumnos\CalificacionesController;
use App\Http\Controllers\Alumnos\DashboardController as AlumnosDashboardController;
use App\Http\Controllers\Alumnos\ComprobanteAfipPdfController as AlumnosComprobanteAfipPdfController;
use App\Http\Controllers\Alumnos\ComprobantePagoPdfController;
use App\Http\Controllers\Alumnos\CuotasAdeudadasPdfController;
use App\Http\Controllers\Alumnos\ResumenPagosPdfController;
use App\Http\Controllers\Alumnos\DocumentoEstudianteAutogestionPdfController;
use App\Http\Controllers\Alumnos\FormularioDebitoAutomaticoPdfController;
use App\Http\Controllers\Alumnos\FichaMatriculaPdfController;
use App\Http\Controllers\Alumnos\HorarioClasePdfController;
use App\Http\Controllers\Alumnos\InformeInasistenciasController;
use App\Http\Controllers\Alumnos\PushApiController;
use App\Http\Controllers\Alumnos\PushController;
use App\Http\Controllers\AntecedentesDisciplinariosPdfController;
use App\Http\Controllers\Aspirantes\RegistroAspiranteController;
use App\Http\Controllers\BoletinesSecundario\BoletinSecundarioLotePdfController;
use App\Http\Controllers\BoletinesSecundario\BoletinSecundarioPdfController;
use App\Http\Controllers\CalificacionesSecundario\ConsultaCalificacionesSecundarioPdfController;
use App\Http\Controllers\CalificacionesSecundario\PlanillaCalificacionesPdfController;
use App\Http\Controllers\CalificacionesSecundario\BoletinEpqSecundarioLotePdfController;
use App\Http\Controllers\CalificacionesSecundario\BoletinEpqSecundarioPdfController;
use App\Http\Controllers\CalificacionesSecundario\CargaCalificacionesEpqSecundarioPdfController;
use App\Http\Controllers\CalificacionesSecundario\PlanillaCalificacionesEpqSecundarioPdfController;
use App\Http\Controllers\PortalDocente\PortalDocentePlanillaCalificacionesPdfController;
use App\Http\Controllers\PortalDocente\PortalDocenteCargaCalificacionesEpqSecundarioPdfController;
use App\Http\Controllers\CalificacionesSecundario\ActaVolanteColoquiosPdfController;
use App\Http\Controllers\CalificacionesSecundario\PlanillaResumenCalificacionesPdfController;
use App\Http\Controllers\EstudiantesDatosExcelController;
use App\Http\Controllers\EstudiantesDatosPdfController;
use App\Http\Controllers\Viajes\SalidaViajePdfController;
use App\Http\Controllers\EstudiantesExcelController;
use App\Http\Controllers\InformeInasistenciasPdfController;
use App\Http\Controllers\InformeInasistenciasLotePdfController;
use App\Http\Controllers\ParteDiarioPreceptorPdfController;
use App\Http\Controllers\RegistroAsistenciaPdfController;
use App\Http\Controllers\FichaMatriculaSecretariaPdfController;
use App\Http\Controllers\FichaMatriculaSecretariaZipController;
use App\Http\Controllers\LibroMatriculaPdfController;
use App\Http\Controllers\ListadoCursoPdfController;
use App\Http\Controllers\ListadoEstudiantesFormatoPdfController;
use App\Http\Controllers\ListadoDocentesExcelController;
use App\Http\Controllers\ListadoDocentesPdfController;
use App\Http\Controllers\Push\SuscribirController;
use App\Http\Controllers\Horarios\HorarioCursoPdfController;
use App\Http\Controllers\Horarios\HorarioProfesorPdfController;
use App\Http\Controllers\Examenes\ActaVolantePreviosPdfController;
use App\Http\Controllers\Examenes\MateriasAdeudadasEntradaController;
use App\Http\Controllers\Examenes\MateriasAdeudadasPdfController;
use App\Http\Controllers\Examenes\ActaCompromisoTercerMateriaPdfController;
use App\Http\Controllers\Examenes\PermisoExamenPdfController;
use App\Http\Controllers\Examenes\TercerMateriaPdfController;
use App\Livewire\Examenes\ActaVolantePreviosIndex;
use App\Livewire\Examenes\PermisoExamenIndex;
use App\Livewire\Examenes\BorrarInscripcionesExamenIndex;
use App\Livewire\Examenes\MateriasAdeudadasCargaManualIndex;
use App\Livewire\Examenes\MateriasAdeudadasInscripcionIndex;
use App\Livewire\Examenes\MateriasAdeudadasNotasIndex;
use App\Livewire\Examenes\HistorialExamenesIndex;
use App\Livewire\Examenes\MateriasAdeudadasGestionIndex;
use App\Livewire\Examenes\MateriasAdeudadasListadoIndex;
use App\Livewire\Examenes\TercerMateriaIndex;
use App\Http\Controllers\SancionComunicadoPdfController;
use App\Livewire\Abm\Curplan\CurplanForm;
use App\Livewire\Aspirantes\AspirantesIndex;
use App\Livewire\Aspirantes\CursosModeloIndex as AspirantesCursosModeloIndex;
use App\Livewire\Aspirantes\InstanciaForm as AspirantesInstanciaForm;
use App\Livewire\Aspirantes\InstanciaIndex as AspirantesInstanciaIndex;
use App\Livewire\Parametrizacion\CamposAspirantesIndex;
use App\Livewire\Programas\ProgramasExamenPublico;
use App\Livewire\DocPp\DocPpForm;
use App\Livewire\DocPp\DocPpIndex;
use App\Http\Controllers\DocPp\DocPpArchivoController;
use App\Livewire\Abm\Curplan\CurplanIndex;
use App\Livewire\Abm\Cursos\CursosIndex;
use App\Livewire\Abm\CursosPorProfesor\CursosPorProfesorIndex;
use App\Livewire\Abm\Legajos\LegajoCargaPorCurso;
use App\Livewire\Abm\Legajos\LegajoBuscarFamilias;
use App\Livewire\Abm\Legajos\LegajoFamilia;
use App\Livewire\Abm\Legajos\LegajoForm;
use App\Livewire\Abm\Legajos\LegajosIndex;
use App\Livewire\Abm\LegajosProfesor\LegajoProfesorForm;
use App\Livewire\Abm\LegajosProfesor\LegajosProfesorIndex;
use App\Http\Controllers\Docentes\InformeInasistenciasDocentePdfController;
use App\Http\Controllers\Docentes\CertificacionServiciosPdfController;
use App\Livewire\Docentes\Inasistencias\CargosDocenteIndex;
use App\Livewire\Docentes\Inasistencias\InformeBimestreShow;
use App\Livewire\Docentes\Inasistencias\InasistenciaDocenteForm;
use App\Livewire\Docentes\Inasistencias\InasistenciasDocenteShow;
use App\Livewire\Docentes\Inasistencias\EnvioMasivoInasistenciasDocentes;
use App\Livewire\Docentes\Inasistencias\InasistenciasDocentesIndex;
use App\Livewire\Docentes\Inasistencias\RankingInasistenciasMateriasCursos;
use App\Livewire\Docentes\CertificacionServicios\CertificacionServiciosIndex;
use App\Livewire\Docentes\CertificacionServicios\CertificacionServiciosForm;
use App\Http\Controllers\Docentes\RankingInasistenciasMateriasCursosCsvController;
use App\Support\InasistenciasDocentes;
use App\Livewire\Abm\MateriasAnio\MateriasAnioIndex;
use App\Livewire\Abm\ProfesoresPorMateria\ProfesoresPorMateriaIndex;
use App\Livewire\Abm\Niveles\NivelesIndex;
use App\Livewire\Abm\Planes\PlanesForm;
use App\Livewire\Abm\Planes\PlanesIndex;
use App\Livewire\Abm\Terlec\TerlecIndex;
use App\Livewire\Administracion\Permisos\PermisosPorUsuarioIndex;
use App\Livewire\Administracion\Permisos\PermisosUsuariosIndex;
use App\Livewire\Alumnos\Auth\Login as AlumnosLogin;
use App\Livewire\Alumnos\Comunicaciones\BandejaFamilia;
use App\Http\Controllers\Alumnos\AbrirHiloComunicacionFamiliaController;
use App\Http\Controllers\Comunicaciones\AbrirHiloComunicacionGestionController;
use App\Http\Controllers\Comunicaciones\ComunicacionHiloPdfController;
use App\Livewire\Alumnos\Comunicaciones\HiloShowFamilia;
use App\Livewire\Alumnos\Comunicaciones\NuevoComunicadoFamilia;
use App\Livewire\Alumnos\Comunicaciones\PreferenciasMedios;
use App\Livewire\Alumnos\AceptacionDocumentoFamilia;
use App\Livewire\Auth\Login;
use App\Http\Controllers\Cuotas\ComprobanteAfipPdfController;
use App\Http\Controllers\Cuotas\ComprobantePagoCuotasPdfController;
use App\Http\Controllers\Cuotas\ComprobantePagoImputacionPdfController;
use App\Http\Controllers\Cuotas\LibroArancelesPdfController;
use App\Http\Controllers\Cuotas\ResumenBecasPorNivelCsvController;
use App\Http\Controllers\Cuotas\SiroCuponesVencidosArchivoController;
use App\Http\Controllers\Cuotas\SiroSubidaBaseDeudaArchivoController;
use App\Http\Controllers\Cuotas\ListadoEstudiantesPorCuotaPdfController;
use App\Http\Controllers\Cuotas\ListadoPagosPorFechaPdfController;
use App\Http\Controllers\Cuotas\CuotasAdeudadasEstudiantePdfController;
use App\Http\Controllers\Cuotas\ResumenPagosEstudiantePdfController;
use App\Http\Controllers\Cuotas\SolicitudAyudaFamiliarPdfController;
use App\Livewire\Cuotas\CuotaGeneradaForm;
use App\Livewire\Cuotas\EdicionCuotasGeneradasIndex;
use App\Livewire\Cuotas\CancelarTodasReservas;
use App\Livewire\Cuotas\EliminacionMasivaCuotas;
use App\Livewire\Cuotas\GeneracionMasivaCuotas;
use App\Livewire\Cuotas\FacturacionMasivaAfip;
use App\Livewire\Cuotas\LibroArancelesIndex;
use App\Livewire\Cuotas\ListadoEstudiantesPorCuotaIndex;
use App\Livewire\Cuotas\ListadoPagosPorFechaIndex;
use App\Livewire\Cuotas\GenerarCuotaEstudiante;
use App\Livewire\Cuotas\CuotasEstudianteShow;
use App\Livewire\Cuotas\CuotasIndex;
use App\Livewire\Cuotas\CuotasImportesForm;
use App\Livewire\Cuotas\CuotasImportesIndex;
use App\Livewire\Cuotas\CuotasPlantillaIndex;
use App\Livewire\Cuotas\TiposBecaIndex;
use App\Livewire\Cuotas\AsignacionBecasIndex;
use App\Livewire\Cuotas\ResumenBecasPorNivelIndex;
use App\Livewire\Cuotas\SolicitudAyudaFamiliarIndex;
use App\Livewire\Cuotas\HistorialPagosCuota;
use App\Livewire\Cuotas\ComprobantesAfipCuota;
use App\Livewire\Cuotas\ComprobantesAfipDevengamientoCuota;
use App\Http\Controllers\Arca\GuiaAutorizacionArcaPadronA13PdfController;
use App\Http\Controllers\Arca\GuiaConfiguracionArcaFacturacionPdfController;
use App\Livewire\Arca\ConsultaCuitPorDniIndex;
use App\Livewire\Arca\EditarObservacionFacturaForm;
use App\Livewire\Cuotas\ConsultaAfipComprobanteIndex;
use App\Livewire\Cuotas\SiroCuponesVencidosIndex;
use App\Livewire\Cuotas\SiroSubidaBaseDeudaIndex;
use App\Livewire\Cuotas\ImputarPagoForm;
use App\Http\Controllers\Cooperadora\MovimientosPdfController;
use App\Http\Controllers\Cooperadora\OrdenPagoPdfController;
use App\Http\Controllers\Cooperadora\PagosEstudianteCooperadoraPdfController;
use App\Http\Controllers\Cooperadora\ReciboPdfController;
use App\Livewire\Cooperadora\ConfigForm;
use App\Livewire\Cooperadora\EgresoForm;
use App\Livewire\Cooperadora\EgresosIndex;
use App\Livewire\Cooperadora\IngresoForm;
use App\Livewire\Cooperadora\IngresosIndex;
use App\Livewire\Cooperadora\ItemsIndex;
use App\Livewire\Cooperadora\MediosPagoIndex;
use App\Livewire\Cooperadora\PagosEstudianteCooperadoraIndex;
use App\Livewire\Cooperadora\PagosEstudianteCooperadoraShow;
use App\Livewire\Cooperadora\MovimientosIndex;
use App\Livewire\Cooperadora\ProveedoresForm;
use App\Livewire\Cooperadora\ProveedoresIndex;
use App\Livewire\Cooperadora\RubrosIndex;
use App\Http\Controllers\Mora\EstadoDeudaFamiliarPdfController;
use App\Http\Controllers\Mora\ListadoMorososPdfController;
use App\Http\Controllers\Mora\NotificacionDeudaPdfController;
use App\Livewire\Mora\EstadoDeudaFamiliarIndex;
use App\Livewire\Mora\GestionMorososIndex;
use App\Livewire\Mora\TextosNotificacionDeudaForm;
use App\Livewire\CalificacionesSecundario\CargaCalificacionesSecundario;
use App\Livewire\CalificacionesSecundario\Epq\BoletinEpqSecundarioIndex;
use App\Livewire\CalificacionesSecundario\Epq\CargaCalificacionesEpqSecundario;
use App\Livewire\CalificacionesSecundario\Epq\PlanillaCalificacionesEpqSecundario;
use App\Livewire\CalificacionesSecundario\CargaColoquiosSecundario;
use App\Livewire\CalificacionesSecundario\PlanillaCalificacionesSecundario;
use App\Livewire\CalificacionesSecundario\ActaVolanteColoquiosSecundario;
use App\Livewire\CalificacionesSecundario\PlanillaResumenCalificacionesSecundario;
use App\Livewire\CalificacionesSecundario\ConsultaCalificacionesSecundario;
use App\Livewire\CalificacionesSecundario\CierreAnualIndex;
use App\Livewire\CalificacionesSecundario\CierreAnualHistorial;
use App\Http\Controllers\Certificados\CertificadoAlumnoRegularPdfController;
use App\Http\Controllers\Certificados\CertificadoEstudiosTramitePdfController;
use App\Http\Controllers\Certificados\CertificadoAsistenciaProfesorPdfController;
use App\Http\Controllers\PdfPostVerController;
use App\Http\Controllers\Certificados\ConstanciaDocumentosPdfController;
use App\Http\Controllers\Certificados\PaseParcialPdfController;
use App\Http\Controllers\Certificados\SolicitudDePasePdfController;
use App\Http\Controllers\Certificados\CusIsaVozImagenPdfController;
use App\Http\Controllers\MatrizAnaliticos\AnaliticoFrentePdfController;
use App\Http\Controllers\MatrizAnaliticos\AnaliticoReversoPdfController;
use App\Http\Controllers\Navegacion\AutogestionDocenteController;
use App\Http\Controllers\Navegacion\EstablecerContextoEstudianteController;
use App\Livewire\Certificados\CertificadoAlumnoRegularIndex;
use App\Livewire\Certificados\CertificadoEstudiosTramiteIndex;
use App\Livewire\Certificados\CertificadoAsistenciaProfesorIndex;
use App\Livewire\Certificados\ConstanciaDocumentosIndex;
use App\Livewire\Certificados\PaseParcialIndex;
use App\Livewire\Certificados\SolicitudDePaseIndex;
use App\Livewire\Certificados\CusIsaVozImagenIndex;
use App\Livewire\MatrizAnaliticos\LibroMatrizDatosAdicionales;
use App\Livewire\MatrizAnaliticos\LibroMatrizEditar;
use App\Livewire\MatrizAnaliticos\LibroMatrizIndex;
use App\Livewire\BoletinesSecundario\BoletinesSecundarioIndex;
use App\Livewire\Estadistica\PorDocente as EstadisticaPorDocente;
use App\Livewire\Estadistica\PorEstudiante as EstadisticaPorEstudiante;
use App\Livewire\Estadistica\PorMateria as EstadisticaPorMateria;
use App\Livewire\Estadistica\RendimientoEscolarIndex;
use App\Http\Controllers\CalificacionesPrimario\BoletinIpeLotePdfController;
use App\Http\Controllers\CalificacionesPrimario\BoletinIpePdfController;
use App\Http\Controllers\CalificacionesPrimario\BoletinPrimEpqLotePdfController;
use App\Http\Controllers\CalificacionesPrimario\BoletinPrimEpqPdfController;
use App\Http\Controllers\CalificacionesPrimario\PlanillaCalificacionesEpqPdfController;
use App\Http\Controllers\CalificacionesPrimario\PlanillaCalificacionesPrimarioPdfController;
use App\Livewire\CalificacionesInicial\Sfq\BoletinInicialSfqIndex;
use App\Livewire\CalificacionesInicial\Sfq\CargaCalificacionesInicialSfqIndex;
use App\Livewire\CalificacionesInicial\Sfq\CargaCalificacionesInicialSfqIndicadoresForm;
use App\Livewire\CalificacionesInicial\Sfq\CargaCalificacionesInicialSfqObservacionesForm;
use App\Livewire\CalificacionesInicial\CargaObservacionesInicialAlumnos;
use App\Livewire\CalificacionesInicial\CargaObservacionesInicialForm;
use App\Livewire\CalificacionesInicial\CargaObservacionesInicialIndex;
use App\Livewire\CalificacionesInicial\CargaObservacionesInicialMateria;
use App\Livewire\CalificacionesInicial\SincroDesempenos as SincroDesempenosInicial;
use App\Livewire\CalificacionesInicial\SincroGe as SincroGeInicial;
use App\Http\Controllers\CalificacionesInicial\BoletinInicialSfqLotePdfController;
use App\Http\Controllers\CalificacionesInicial\BoletinInicialSfqPdfController;
use App\Http\Controllers\CalificacionesInicial\InformeProgresoInicialLotePdfController;
use App\Http\Controllers\CalificacionesInicial\InformeProgresoInicialPdfController;
use App\Livewire\CalificacionesInicial\EditarIndicadoresForm;
use App\Livewire\CalificacionesInicial\EditarIndicadoresIndex;
use App\Livewire\CalificacionesInicial\InformeProgresoInicialIndex;
use App\Livewire\CalificacionesPrimario\BoletinIpeIndex;
use App\Livewire\CalificacionesPrimario\Epq\BoletinPrimEpqIndex;
use App\Livewire\CalificacionesPrimario\Epq\CargaCalificacionesEpqForm;
use App\Livewire\CalificacionesPrimario\Epq\CargaCalificacionesEpqIndex;
use App\Livewire\CalificacionesPrimario\Epq\InfoAdicionalEpqForm;
use App\Livewire\CalificacionesPrimario\Epq\PlanillaCalificacionesEpq;
use App\Livewire\CalificacionesPrimario\PlanillaCalificacionesPrimario;
use App\Livewire\CalificacionesPrimario\CargaCalificacionesPrimarioForm;
use App\Livewire\CalificacionesPrimario\CargaCalificacionesPrimarioIndex;
use App\Livewire\CalificacionesPrimario\CargaCalificacionesPrimarioMateria;
use App\Livewire\CalificacionesPrimario\SincroDesempenos;
use App\Livewire\CalificacionesPrimario\SincroGe as SincroGePrimario;
use App\Livewire\CalificacionesSecundario\SincroGe;
use App\Livewire\Comunicaciones\BandejaGestion;
use App\Livewire\Comunicaciones\BandejaRevision;
use App\Livewire\Comunicaciones\ComAuditoriaIndex;
use App\Livewire\Comunicaciones\HiloShow;
use App\Livewire\Comunicaciones\InformeEnvioComunicado;
use App\Livewire\Comunicaciones\NuevoComunicado;
use App\Livewire\EmailsMasivos\EmailsMasivosCampanaShow;
use App\Livewire\EmailsMasivos\EmailsMasivosEnviar;
use App\Livewire\EmailsMasivos\EmailsMasivosForm;
use App\Livewire\EmailsMasivos\EmailsMasivosHistorial;
use App\Livewire\EmailsMasivos\EmailsMasivosIndex;
use App\Livewire\Listados\EstudiantesDatosExport;
use App\Livewire\Viajes\SalidaViajeForm;
use App\Livewire\Viajes\SalidaViajeImpresion;
use App\Livewire\Viajes\SalidasViajesIndex;
use App\Livewire\MaterialDidactico\ReservasDashboard;
use App\Livewire\MaterialDidactico\ReservaForm;
use App\Livewire\MaterialDidactico\RecursosAdmin;
use App\Livewire\Listados\FichaMatriculaSecretaria;
use App\Livewire\Listados\LibroMatricula;
use App\Livewire\Listados\ListadoDocentes;
use App\Livewire\Listados\ListadoEstudiantesFormato;
use App\Livewire\Listados\ListadoPorCurso;
use App\Livewire\Parametrizacion\CamposLegajoIndex;
use App\Livewire\Parametrizacion\CamposProfesorIndex;
use App\Livewire\Parametrizacion\ComCanalesIndex;
use App\Livewire\Parametrizacion\ParametrosSistemaForm;
use App\Livewire\MatriculaWeb\BloqueosMatriculaIndex;
use App\Livewire\MatriculaWeb\DocumentosAceptacionForm;
use App\Livewire\MatriculaWeb\DocumentosEstudianteTiposIndex;
use App\Http\Controllers\MatriculaWeb\DocumentoAceptacionArchivoController;
use App\Support\PermisosMatriculaWeb;
use App\Livewire\PortalDocente\CalificacionesIndex as PortalDocenteCalificacionesIndex;
use App\Livewire\SolicitudEvaluacion\SolicitudEvaluacionForm;
use App\Livewire\SolicitudEvaluacion\SolicitudEvaluacionIndex;
use App\Livewire\SolicitudEvaluacion\Gestion\GestionSolicitudEvaluacionForm;
use App\Livewire\SolicitudEvaluacion\Gestion\GestionSolicitudEvaluacionIndex;
use App\Livewire\PortalDocente\CuadernoSeguimientoIndex as PortalDocenteCuadernoSeguimientoIndex;
use App\Livewire\PortalDocente\RegistroSituacionAulicaIndex as PortalDocenteRegistroSituacionAulicaIndex;
use App\Livewire\PortalDocente\SituacionAulicaAlumnoShow as PortalDocenteSituacionAulicaAlumnoShow;
use App\Livewire\Parametrizacion\SolapaLegajoIndex;
use App\Livewire\Parametrizacion\SolapaLegajoProfesorIndex;
use App\Livewire\Seguimiento\Disciplinario\AntecedentesIndex;
use App\Livewire\Seguimiento\Disciplinario\DisciplinarioIndex;
use App\Livewire\Seguimiento\Disciplinario\SancionForm;
use App\Livewire\Seguimiento\Inasistencias\InasistenciaForm;
use App\Livewire\Horarios\HorariosCargaIndex;
use App\Livewire\Horarios\HorariosConfigIndex;
use App\Livewire\Horarios\HorariosImpresionIndex;
use App\Livewire\Seguimiento\Inasistencias\InasistenciasIndex;
use App\Livewire\Seguimiento\Inasistencias\SincroCidiInasistencias;
use App\Livewire\Seguimiento\Inasistencias\PartesDiariosIndex;
use App\Livewire\Seguimiento\Inasistencias\InformeInasistenciasLoteIndex;
use App\Livewire\Seguimiento\Inasistencias\TomaAsistenciaClaseIndex;
use App\Livewire\Seguimiento\RegistroAsistencia\FeriadosIndex;
use App\Livewire\Seguimiento\RegistroAsistencia\RegistroAsistenciaIndex;
use App\Livewire\Seguimiento\Tea\TeaForm;
use App\Livewire\Seguimiento\Tea\TeaIndex;
use App\Http\Controllers\TeaRegistroPdfController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstitutionalIconController;
use App\Http\Controllers\ManualComunicacionInstitucionalPdfController;
use App\Http\Controllers\ManualSistemaPdfController;
use App\Support\Auth\CerrarSesionAplicacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/icono-escuela.png', InstitutionalIconController::class)->name('institutional.icon');
Route::get('/favicon.ico', InstitutionalIconController::class);

// Registro público de aspirantes (sin auth, sin school.context).
// El token es opaco por instancia: no expone idNivel ni permite saltar de nivel.
// Límite amplio para abrir/recargar la página; el envío del formulario se limita en RegistroAspiranteForm::registrar().
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/aspirantes/r/{token}', [RegistroAspiranteController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{8,80}')
        ->name('aspirantes.publico.registro');
});

// Descarga pública de programas de examen (sin auth, sin school.context).
// Solo se registra si el tenant lo habilita explícitamente en config/tenants/{slug}.php.
if (tenantProgramasExamenHabilitado()) {
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/programas-examen', ProgramasExamenPublico::class)
            ->name('programas.examen.publico');
    });
}

// Login: siempre limpia sesión previa (equipos compartidos; no usar middleware `guest`).
Route::middleware(['login.limpiar-sesion', 'no-store'])->group(function () {
    Route::get('/loginUsuario', Login::class)->name('login');
    Route::get('/loginEstudiante', AlumnosLogin::class)->name('alumnos.login');
});

// Logout
Route::post('/logout', function () {
    CerrarSesionAplicacion::ejecutar();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

// Logout alumnos
Route::post('/alumnos/logout', function () {
    CerrarSesionAplicacion::ejecutar();

    return redirect()->route('alumnos.login');
})->middleware('auth:alumno')->name('alumnos.logout');

// Área alumnos (autogestión)
Route::middleware(['auth:alumno', 'student.context'])->prefix('alumnos')->group(function () {
    Route::get('/', AlumnosDashboardController::class)->name('alumnos.home');

    Route::get('/calificaciones', CalificacionesController::class)->name('alumnos.calificaciones');
    Route::get('/boletin-calificaciones-primario/{etapa}', BoletinIpePrimarioPdfController::class)
        ->whereNumber('etapa')
        ->name('alumnos.boletin-ipe-primario');
    Route::get('/boletin-calificaciones-primario-epq/{cara}', BoletinPrimEpqFamiliaPdfController::class)
        ->where('cara', 'portada|calificaciones')
        ->name('alumnos.boletin-prim-epq');
    Route::get('/boletin-calificaciones-secundario-epq', BoletinEpqSecundarioFamiliaPdfController::class)
        ->name('alumnos.boletin-sec-epq');
    Route::get('/informe-progreso-escolar/{etapa}', \App\Http\Controllers\Alumnos\InformeProgresoInicialPdfController::class)
        ->whereNumber('etapa')
        ->name('alumnos.informe-progreso-inicial');
    Route::get('/inasistencias/informe', InformeInasistenciasController::class)->name('alumnos.inasistencias.informe');
    Route::get('/horario-clase', HorarioClasePdfController::class)->name('alumnos.horario-clase');
    Route::get('/ficha-matricula', FichaMatriculaPdfController::class)->name('alumnos.ficha-matricula');
    Route::get('/aranceles-escolares', tenantAutogestionArancelesEscolaresLivewireComponent())
        ->name('alumnos.aranceles-escolares');
    Route::get('/aranceles-escolares/comprobante/{ref}', ComprobantePagoPdfController::class)
        ->where('ref', '[A-Za-z0-9_-]+')
        ->name('alumnos.aranceles-escolares.comprobante');
    Route::get('/aranceles-escolares/comprobante-afip/{ref}', AlumnosComprobanteAfipPdfController::class)
        ->where('ref', '[A-Za-z0-9_-]+')
        ->name('alumnos.aranceles-escolares.comprobante-afip');
    Route::get('/aranceles-escolares/resumen-pagos/{ref}', ResumenPagosPdfController::class)
        ->where('ref', '[A-Za-z0-9_-]+')
        ->name('alumnos.aranceles-escolares.resumen-pagos');
    Route::get('/aranceles-escolares/cuotas-adeudadas/{ref}', CuotasAdeudadasPdfController::class)
        ->where('ref', '[A-Za-z0-9_-]+')
        ->name('alumnos.aranceles-escolares.cuotas-adeudadas');
    Route::get('/aranceles-escolares/formulario-debito-automatico', FormularioDebitoAutomaticoPdfController::class)
        ->name('alumnos.aranceles-escolares.formulario-debito-automatico');

    Route::get('/actualizacion-datos', tenantAutogestionActualizacionDatosLivewireComponent())
        ->name('alumnos.actualizacion-datos');
    Route::get('/actualizacion-datos/documento-estudiante/{ref}', DocumentoEstudianteAutogestionPdfController::class)
        ->where('ref', '[A-Za-z0-9_-]+')
        ->name('alumnos.actualizacion-datos.documento-estudiante');
    Route::get('/actualizacion-datos/aceptacion/{tipo}', AceptacionDocumentoFamilia::class)
        ->where('tipo', 'compromiso|aec|normas|traslado')
        ->name('alumnos.actualizacion-datos.aceptacion');
    Route::get('/documentos-aceptacion/{tipo}/archivo', DocumentoAceptacionArchivoController::class)
        ->where('tipo', 'compromiso|aec|normas|traslado')
        ->name('alumnos.documentos-aceptacion.archivo');

    Route::middleware('autogestion.comunicaciones')->group(function () {
        Route::get('/notificaciones', [PushController::class, 'index'])->name('alumnos.push.index');

        Route::get('/comunicaciones', BandejaFamilia::class)->name('alumnos.comunicaciones.index');
        Route::get('/comunicaciones/nuevo', NuevoComunicadoFamilia::class)->name('alumnos.comunicaciones.nuevo');
        Route::get('/comunicaciones/preferencias', PreferenciasMedios::class)->name('alumnos.comunicaciones.preferencias');
        Route::get('/comunicaciones/hilo', HiloShowFamilia::class)->name('alumnos.comunicaciones.hilo');
        Route::get('/comunicaciones/abrir/{id}', AbrirHiloComunicacionFamiliaController::class)
            ->whereNumber('id')
            ->name('alumnos.comunicaciones.abrir');
    });
});

// API Push (sesión alumno o docente; fuera del prefix /alumnos para que el SW tenga scope simple)
Route::middleware(['auth:web,alumno'])->prefix('notificaciones-push/api')->group(function () {
    Route::post('/subscribe', [PushApiController::class, 'subscribe'])->name('push.api.subscribe');
    Route::post('/unsubscribe', [PushApiController::class, 'unsubscribe'])->name('push.api.unsubscribe');
    Route::post('/send', [PushApiController::class, 'send'])->name('push.api.send');
});

Route::middleware(['auth', 'school.context'])->post('/navegacion/contexto-estudiante', EstablecerContextoEstudianteController::class)
    ->name('navegacion.contexto-estudiante');

// Autogestión Docente: salto manual desde el Menú de Secretaría al Menú de Docentes
// para usuarios con rol no-docente (p. ej. Preceptor) que también tienen cursos
// asignados en `ppc`. Ver docs/08-menus-de-navegacion.md.
Route::middleware(['auth', 'school.context'])->post('/autogestion-docente/activar', AutogestionDocenteController::class)
    ->name('autogestion.docente.activar');

// Menú de Docentes — IdTipoProf = 6 (profesortipo «Profesor/a»)
Route::middleware(['auth', 'school.context', 'menu.portal:docente'])->prefix('portal-docente')->group(function () {
    Route::get('/', DashboardController::class)->name('portalDocente.home');

    Route::get('/calificaciones', PortalDocenteCalificacionesIndex::class)
        ->name('portalDocente.calificaciones');
    Route::get('/calificaciones/{curso}/{materia}', CargaCalificacionesSecundario::class)
        ->whereNumber(['curso', 'materia'])
        ->name('portalDocente.calificaciones.carga');
    Route::get('/calificaciones/{curso}/{materia}/pdf', PortalDocentePlanillaCalificacionesPdfController::class)
        ->whereNumber(['curso', 'materia'])
        ->name('portalDocente.calificaciones.pdf');

    Route::get('/calificaciones-epq/{curso}/{materia}', CargaCalificacionesEpqSecundario::class)
        ->whereNumber(['curso', 'materia'])
        ->name('portalDocente.calificacionesEpq.carga');
    Route::get('/calificaciones-epq/{curso}/{materia}/pdf', PortalDocenteCargaCalificacionesEpqSecundarioPdfController::class)
        ->whereNumber(['curso', 'materia'])
        ->name('portalDocente.calificacionesEpq.pdf');

    Route::get('/calificaciones-secundario-epq/planilla', PlanillaCalificacionesEpqSecundario::class)
        ->name('portalDocente.calificacionesSecundarioEpq.planilla');
    Route::get('/calificaciones-secundario-epq/planilla/pdf', PlanillaCalificacionesEpqSecundarioPdfController::class)
        ->name('portalDocente.calificacionesSecundarioEpq.planilla.pdf');

    Route::get('/calificaciones-primario/carga', CargaCalificacionesPrimarioIndex::class)
        ->name('portalDocente.calificacionesPrimario.carga');
    Route::get('/calificaciones-primario/carga/{matricula}', CargaCalificacionesPrimarioForm::class)
        ->whereNumber('matricula')
        ->name('portalDocente.calificacionesPrimario.carga.alumno');
    Route::get('/calificaciones-primario/carga-materia', CargaCalificacionesPrimarioMateria::class)
        ->name('portalDocente.calificacionesPrimario.cargaMateria');
    Route::get('/calificaciones-primario/boletin-ipe', BoletinIpeIndex::class)
        ->name('portalDocente.calificacionesPrimario.boletinIpe');
    Route::post('/calificaciones-primario/boletin-ipe/pdf', BoletinIpePdfController::class)
        ->name('portalDocente.calificacionesPrimario.boletinIpe.pdf');
    Route::post('/calificaciones-primario/boletin-ipe/pdf-lote', BoletinIpeLotePdfController::class)
        ->name('portalDocente.calificacionesPrimario.boletinIpe.pdfLote');
    Route::get('/calificaciones-primario/planilla', PlanillaCalificacionesPrimario::class)
        ->name('portalDocente.calificacionesPrimario.planilla');
    Route::get('/calificaciones-primario/planilla/pdf', PlanillaCalificacionesPrimarioPdfController::class)
        ->name('portalDocente.calificacionesPrimario.planilla.pdf');

    Route::get('/calificaciones-primario-epq/carga', CargaCalificacionesEpqIndex::class)
        ->name('portalDocente.calificacionesPrimarioEpq.carga');
    Route::get('/calificaciones-primario-epq/carga/{matricula}', CargaCalificacionesEpqForm::class)
        ->whereNumber('matricula')
        ->name('portalDocente.calificacionesPrimarioEpq.carga.alumno');
    Route::get('/calificaciones-primario-epq/info-adicional/{matricula}', InfoAdicionalEpqForm::class)
        ->whereNumber('matricula')
        ->name('portalDocente.calificacionesPrimarioEpq.infoAdicional');
    Route::get('/calificaciones-primario-epq/boletin', BoletinPrimEpqIndex::class)
        ->name('portalDocente.calificacionesPrimarioEpq.boletin');
    Route::post('/calificaciones-primario-epq/boletin/pdf', BoletinPrimEpqPdfController::class)
        ->name('portalDocente.calificacionesPrimarioEpq.boletin.pdf');
    Route::post('/calificaciones-primario-epq/boletin/pdf-lote', BoletinPrimEpqLotePdfController::class)
        ->name('portalDocente.calificacionesPrimarioEpq.boletin.pdfLote');
    Route::get('/calificaciones-primario-epq/planilla', PlanillaCalificacionesEpq::class)
        ->name('portalDocente.calificacionesPrimarioEpq.planilla');
    Route::get('/calificaciones-primario-epq/planilla/pdf', PlanillaCalificacionesEpqPdfController::class)
        ->name('portalDocente.calificacionesPrimarioEpq.planilla.pdf');

    Route::get('/cuaderno-seguimiento', PortalDocenteCuadernoSeguimientoIndex::class)
        ->name('portalDocente.cuadernoSeguimiento');
    Route::get('/cuaderno-seguimiento/{curso}/{materia}', PortalDocenteRegistroSituacionAulicaIndex::class)
        ->whereNumber(['curso', 'materia'])
        ->name('portalDocente.cuadernoSeguimiento.registro');
    Route::get('/cuaderno-seguimiento/{curso}/{materia}/alumno', PortalDocenteSituacionAulicaAlumnoShow::class)
        ->whereNumber(['curso', 'materia'])
        ->name('portalDocente.cuadernoSeguimiento.alumno');

    Route::get('/solicitud-evaluacion', SolicitudEvaluacionIndex::class)
        ->name('portalDocente.solicitudEvaluacion');
    Route::get('/solicitud-evaluacion/nueva', SolicitudEvaluacionForm::class)
        ->name('portalDocente.solicitudEvaluacion.create');

    Route::get('/listados/estudiantes-formato', ListadoEstudiantesFormato::class)
        ->name('portalDocente.listados.estudiantesFormato');
    Route::get('/listados/estudiantes-formato/pdf', ListadoEstudiantesFormatoPdfController::class)
        ->name('portalDocente.listados.estudiantesFormato.pdf');

    Route::get('/calificaciones-inicial/indicadores', EditarIndicadoresIndex::class)
        ->name('portalDocente.calificacionesInicial.indicadores');
    Route::get('/calificaciones-inicial/indicadores/{materia}', EditarIndicadoresForm::class)
        ->whereNumber('materia')
        ->name('portalDocente.calificacionesInicial.indicadores.materia');
    Route::get('/calificaciones-inicial/observaciones', CargaObservacionesInicialIndex::class)
        ->name('portalDocente.calificacionesInicial.observaciones');
    Route::get('/calificaciones-inicial/observaciones/{materia}', CargaObservacionesInicialAlumnos::class)
        ->whereNumber('materia')
        ->name('portalDocente.calificacionesInicial.observaciones.alumnos');
    Route::get('/calificaciones-inicial/observaciones/{materia}/{matricula}', CargaObservacionesInicialForm::class)
        ->whereNumber(['materia', 'matricula'])
        ->name('portalDocente.calificacionesInicial.observaciones.carga');
    Route::get('/calificaciones-inicial/observaciones-espacio-curricular', CargaObservacionesInicialMateria::class)
        ->name('portalDocente.calificacionesInicial.observacionesMateria');
    Route::get('/calificaciones-inicial/informe-progreso', InformeProgresoInicialIndex::class)
        ->name('portalDocente.calificacionesInicial.informeProgreso');
    Route::post('/calificaciones-inicial/informe-progreso/pdf', InformeProgresoInicialPdfController::class)
        ->name('portalDocente.calificacionesInicial.informeProgreso.pdf');
    Route::post('/calificaciones-inicial/informe-progreso/pdf-lote', InformeProgresoInicialLotePdfController::class)
        ->name('portalDocente.calificacionesInicial.informeProgreso.pdfLote');

    Route::get('/calificaciones-inicial-sfq/carga', CargaCalificacionesInicialSfqIndex::class)
        ->name('portalDocente.calificacionesInicialSfq.carga');
    Route::get('/calificaciones-inicial-sfq/carga/{matricula}/indicadores/{campo}', CargaCalificacionesInicialSfqIndicadoresForm::class)
        ->whereNumber('matricula')
        ->where('campo', 'ic0[1-6]')
        ->name('portalDocente.calificacionesInicialSfq.carga.indicadores');
    Route::get('/calificaciones-inicial-sfq/carga/{matricula}/observaciones', CargaCalificacionesInicialSfqObservacionesForm::class)
        ->whereNumber('matricula')
        ->name('portalDocente.calificacionesInicialSfq.carga.observaciones');

    Route::get('/calificaciones-inicial-sfq/boletin', BoletinInicialSfqIndex::class)
        ->name('portalDocente.calificacionesInicialSfq.boletin');
    Route::post('/calificaciones-inicial-sfq/boletin/pdf', BoletinInicialSfqPdfController::class)
        ->name('portalDocente.calificacionesInicialSfq.boletin.pdf');
    Route::post('/calificaciones-inicial-sfq/boletin/pdf-lote', BoletinInicialSfqLotePdfController::class)
        ->name('portalDocente.calificacionesInicialSfq.boletin.pdfLote');

    Route::get('/material-didactico', ReservasDashboard::class)
        ->name('portalDocente.materialDidactico.index');
    Route::get('/material-didactico/reservar', ReservaForm::class)
        ->name('portalDocente.materialDidactico.reservar');
    Route::get('/material-didactico/reservar/{id}/editar', ReservaForm::class)
        ->whereNumber('id')
        ->name('portalDocente.materialDidactico.reservar.edit');

    Route::get('/comunicaciones', BandejaGestion::class)->name('portalDocente.comunicaciones.index');
    Route::get('/comunicaciones/revision', BandejaRevision::class)->middleware(['permiso:3', 'permiso:8'])->name('portalDocente.comunicaciones.revision');
    Route::get('/comunicaciones/nuevo', NuevoComunicado::class)->name('portalDocente.comunicaciones.nuevo');
    Route::get('/comunicaciones/informe-envio/{id}', InformeEnvioComunicado::class)
        ->whereNumber('id')
        ->name('portalDocente.comunicaciones.informe-envio');
    Route::get('/comunicaciones/hilo', HiloShow::class)->name('portalDocente.comunicaciones.hilo');
    Route::get('/comunicaciones/hilo.pdf/{ref}', ComunicacionHiloPdfController::class)
        ->where('ref', '[A-Za-z0-9_-]+')
        ->name('portalDocente.comunicaciones.hilo.pdf');
    Route::get('/comunicaciones/abrir/{id}', AbrirHiloComunicacionGestionController::class)
        ->whereNumber('id')
        ->name('portalDocente.comunicaciones.abrir');

    Route::get('/notificaciones/push', SuscribirController::class)
        ->name('portalDocente.push.suscribir');
});

// Menú de Administración — cuotas, mora y módulos financieros (solo `niveles.id = 5`).
Route::middleware(['auth', 'school.context', 'menu.portal:administracion', 'administracion.nivel'])->group(function () {
    $pi = \App\Support\PermisosIaCatalog::class;

    Route::prefix('cuotas')->group(function () use ($pi) {
        Route::get('/tipos-beca', TiposBecaIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_BECAS_TIPOS)
            ->name('cuotas.tipos-beca');
        Route::get('/asignacion-becas', AsignacionBecasIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_BECAS_ASIGNACION)
            ->name('cuotas.asignacion-becas');
        Route::get('/resumen-becas-por-nivel', ResumenBecasPorNivelIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_BECAS_RESUMEN_NIVEL)
            ->name('cuotas.resumen-becas-por-nivel');
        Route::get('/resumen-becas-por-nivel/csv', ResumenBecasPorNivelCsvController::class)
            ->middleware('permiso:'.$pi::ADMIN_BECAS_RESUMEN_NIVEL)
            ->name('cuotas.resumen-becas-por-nivel.csv');
        Route::get('/solicitud-ayuda-familiar', SolicitudAyudaFamiliarIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_BECAS_SOLICITUD_AYUDA)
            ->name('cuotas.solicitud-ayuda-familiar');
        Route::get('/solicitud-ayuda-familiar/pdf/{ref}', SolicitudAyudaFamiliarPdfController::class)
            ->where('ref', '[A-Za-z0-9_-]+')
            ->middleware('permiso:'.$pi::ADMIN_BECAS_SOLICITUD_AYUDA)
            ->name('cuotas.solicitud-ayuda-familiar.pdf');
        Route::get('/', CuotasIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_ARANCELES_ESTUDIANTE)
            ->name('cuotas.index');
        Route::get('/plantillas', CuotasPlantillaIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_CUOTAS_PLANTILLAS)
            ->name('cuotas.plantillas');
        Route::get('/importes', CuotasImportesIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_CUOTAS_IMPORTES_CURSO)
            ->name('cuotas.importes.index');
        Route::get('/importes/editar', CuotasImportesForm::class)
            ->middleware('permiso:'.$pi::ADMIN_CUOTAS_IMPORTES_CURSO)
            ->name('cuotas.importes.editar');
        Route::get('/generacion-masiva', GeneracionMasivaCuotas::class)
            ->middleware('permiso:'.$pi::ADMIN_CUOTAS_GENERACION_MASIVA)
            ->name('cuotas.generacion-masiva');
        Route::get('/facturacion-masiva-afip', FacturacionMasivaAfip::class)
            ->middleware('permiso:'.$pi::ADMIN_ARANCELES_ESTUDIANTE)
            ->name('cuotas.facturacion-masiva-afip');
        Route::get('/eliminacion-masiva', EliminacionMasivaCuotas::class)
            ->middleware('permiso:'.$pi::ADMIN_CUOTAS_ELIMINACION_MASIVA)
            ->name('cuotas.eliminacion-masiva');
        Route::get('/edicion-cuotas-generadas', EdicionCuotasGeneradasIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_CUOTAS_EDICION_GENERADAS)
            ->name('cuotas.edicion-generadas');
        Route::get('/cancelar-todas-reservas', CancelarTodasReservas::class)
            ->middleware('permiso:'.$pi::ADMIN_CUOTAS_CANCELAR_RESERVAS)
            ->name('cuotas.cancelar-todas-reservas');
        Route::get('/libro-aranceles', LibroArancelesIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_LIBRO_ARANCELES)
            ->name('cuotas.libro-aranceles');
        Route::get('/libro-aranceles/pdf', LibroArancelesPdfController::class)
            ->middleware('permiso:'.$pi::ADMIN_LIBRO_ARANCELES)
            ->name('cuotas.libro-aranceles.pdf');
        Route::get('/listado-pagos-por-fecha', ListadoPagosPorFechaIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_LISTADO_PAGOS_FECHA)
            ->name('cuotas.listado-pagos-por-fecha');
        Route::get('/listado-pagos-por-fecha/pdf', ListadoPagosPorFechaPdfController::class)
            ->middleware('permiso:'.$pi::ADMIN_LISTADO_PAGOS_FECHA)
            ->name('cuotas.listado-pagos-por-fecha.pdf');
        Route::get('/listado-estudiantes-por-cuota', ListadoEstudiantesPorCuotaIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_LISTADO_ESTUDIANTES_CUOTA)
            ->name('cuotas.listado-estudiantes-por-cuota');
        Route::get('/listado-estudiantes-por-cuota/pdf', ListadoEstudiantesPorCuotaPdfController::class)
            ->middleware('permiso:'.$pi::ADMIN_LISTADO_ESTUDIANTES_CUOTA)
            ->name('cuotas.listado-estudiantes-por-cuota.pdf');
        Route::get('/consulta-afip-comprobante', ConsultaAfipComprobanteIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_ARANCELES_ESTUDIANTE)
            ->name('cuotas.consulta-afip-comprobante');
        Route::get('/siro-subida', SiroSubidaBaseDeudaIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_ARANCELES_ESTUDIANTE)
            ->name('cuotas.siro-subida');
        Route::get('/siro-subida/archivo', SiroSubidaBaseDeudaArchivoController::class)
            ->middleware('permiso:'.$pi::ADMIN_ARANCELES_ESTUDIANTE)
            ->name('cuotas.siro-subida.archivo');
        Route::get('/siro-cupones-vencidos', SiroCuponesVencidosIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_ARANCELES_ESTUDIANTE)
            ->name('cuotas.siro-cupones-vencidos');
        Route::get('/siro-cupones-vencidos/archivo', SiroCuponesVencidosArchivoController::class)
            ->middleware('permiso:'.$pi::ADMIN_ARANCELES_ESTUDIANTE)
            ->name('cuotas.siro-cupones-vencidos.archivo');
        Route::get('/siro-descarga', \App\Livewire\Cuotas\SiroDescargaRendicionPlanillasIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_ARANCELES_ESTUDIANTE)
            ->name('cuotas.siro-descarga');
        Route::get('/siro-descarga/{nroPlanilla}', \App\Livewire\Cuotas\SiroDescargaRendicionDetalle::class)
            ->middleware('permiso:'.$pi::ADMIN_ARANCELES_ESTUDIANTE)
            ->where('nroPlanilla', '[0-9]+')
            ->name('cuotas.siro-descarga.detalle');

        Route::middleware('permiso:'.$pi::ADMIN_ARANCELES_ESTUDIANTE)->group(function () {
            Route::get('/estudiante', CuotasEstudianteShow::class)->name('cuotas.estudiante');
            Route::get('/estudiante/generar', GenerarCuotaEstudiante::class)->name('cuotas.estudiante.generar');
            Route::get('/estudiante/cuota/editar', CuotaGeneradaForm::class)->name('cuotas.cuota.editar');
            Route::get('/estudiante/cuota/imputar', ImputarPagoForm::class)->name('cuotas.cuota.imputar');
            Route::get('/estudiante/cuota/historial-pagos', HistorialPagosCuota::class)->name('cuotas.cuota.historial-pagos');
            Route::get('/estudiante/cuota/comprobantes-afip', ComprobantesAfipCuota::class)->name('cuotas.cuota.comprobantes-afip');
            Route::get('/estudiante/cuota/comprobantes-afip-devengamiento', ComprobantesAfipDevengamientoCuota::class)->name('cuotas.cuota.comprobantes-afip-devengamiento');
            Route::get('/comprobante/{ref}', ComprobantePagoCuotasPdfController::class)
                ->where('ref', '[A-Za-z0-9_-]+')
                ->name('cuotas.comprobante');
            Route::get('/comprobante-imputacion/{ref}', ComprobantePagoImputacionPdfController::class)
                ->where('ref', '[A-Za-z0-9_-]+')
                ->name('cuotas.comprobante-imputacion');
            Route::get('/comprobante-afip/{ref}', ComprobanteAfipPdfController::class)
                ->where('ref', '[A-Za-z0-9_-]+')
                ->name('cuotas.comprobante-afip');
            Route::get('/resumen-pagos/{ref}', ResumenPagosEstudiantePdfController::class)
                ->where('ref', '[A-Za-z0-9_-]+')
                ->name('cuotas.resumen-pagos');
            Route::get('/cuotas-adeudadas/{ref}', CuotasAdeudadasEstudiantePdfController::class)
                ->where('ref', '[A-Za-z0-9_-]+')
                ->name('cuotas.cuotas-adeudadas');
        });
    });

    Route::prefix('mora')->group(function () use ($pi) {
        Route::get('/estado-deuda-familiar', EstadoDeudaFamiliarIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_MORA_ESTADO_DEUDA)
            ->name('mora.estado-deuda-familiar');
        Route::get('/estado-deuda-familiar/pdf/{ref}', EstadoDeudaFamiliarPdfController::class)
            ->where('ref', '[A-Za-z0-9_-]+')
            ->middleware('permiso:'.$pi::ADMIN_MORA_ESTADO_DEUDA)
            ->name('mora.estado-deuda-familiar.pdf');
        Route::get('/gestion-morosos', GestionMorososIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_MORA_GESTION_MOROSOS)
            ->name('mora.gestion-morosos');
        Route::get('/gestion-morosos/textos-notificacion', TextosNotificacionDeudaForm::class)
            ->middleware('permiso:'.$pi::ADMIN_MORA_GESTION_MOROSOS)
            ->name('mora.gestion-morosos.textos-notificacion');
        Route::get('/gestion-morosos/pdf/{ref}', ListadoMorososPdfController::class)
            ->where('ref', '[A-Za-z0-9_-]+')
            ->middleware('permiso:'.$pi::ADMIN_MORA_GESTION_MOROSOS)
            ->name('mora.gestion-morosos.pdf');
        Route::get('/gestion-morosos/notificacion/{ref}', NotificacionDeudaPdfController::class)
            ->where('ref', '[A-Za-z0-9_-]+')
            ->middleware('permiso:'.$pi::ADMIN_MORA_GESTION_MOROSOS)
            ->name('mora.gestion-morosos.notificacion');
    });

    Route::prefix('arca')->group(function () use ($pi) {
        Route::get('/consulta-cuit-dni', ConsultaCuitPorDniIndex::class)
            ->middleware('permiso:'.$pi::ADMIN_ARCA_CONSULTA_CUIT_DNI)
            ->name('arca.consulta-cuit-dni');
        Route::get('/observacion-factura', EditarObservacionFacturaForm::class)
            ->middleware('permiso:'.$pi::ADMIN_ARCA_OBS_FACTURA)
            ->name('arca.observacion-factura');
        Route::get('/guia-autorizacion-padron-a13.pdf', GuiaAutorizacionArcaPadronA13PdfController::class)
            ->middleware('permiso:'.$pi::ADMIN_ARCA_CONSULTA_CUIT_DNI)
            ->name('arca.guia-autorizacion-padron-a13.pdf');
        Route::get('/guia-configuracion-facturacion.pdf', GuiaConfiguracionArcaFacturacionPdfController::class)
            ->name('arca.guia-configuracion-facturacion.pdf');
    });
});

// Menú de Secretaría / Administración — módulos compartidos (legajos, comunicación, configuración, etc.).
Route::middleware(['auth', 'school.context', 'menu.portal:staff'])->group(function () {
    $pi = \App\Support\PermisosIaCatalog::class;

    Route::prefix('cooperadora')->group(function () use ($pi) {
        Route::get('/config', ConfigForm::class)
            ->middleware('permiso:'.$pi::COOP_PARAMETRIZACION)
            ->name('cooperadora.config');
        Route::get('/rubros', RubrosIndex::class)
            ->middleware('permiso:'.$pi::COOP_PARAMETRIZACION)
            ->name('cooperadora.rubros');
        Route::get('/items', ItemsIndex::class)
            ->middleware('permiso:'.$pi::COOP_PARAMETRIZACION)
            ->name('cooperadora.items');
        Route::get('/proveedores', ProveedoresIndex::class)
            ->middleware('permiso:'.$pi::COOP_PARAMETRIZACION)
            ->name('cooperadora.proveedores');
        Route::get('/proveedores/nuevo', ProveedoresForm::class)
            ->middleware('permiso:'.$pi::COOP_PARAMETRIZACION)
            ->name('cooperadora.proveedores.nuevo');
        Route::get('/proveedores/{id}/editar', ProveedoresForm::class)
            ->middleware('permiso:'.$pi::COOP_PARAMETRIZACION)
            ->whereNumber('id')
            ->name('cooperadora.proveedores.editar');
        Route::get('/medios-pago', MediosPagoIndex::class)
            ->middleware('permiso:'.$pi::COOP_PARAMETRIZACION)
            ->name('cooperadora.medios-pago');

        Route::get('/ingresos', IngresosIndex::class)
            ->middleware('permiso:'.$pi::COOP_INGRESOS)
            ->name('cooperadora.ingresos');
        Route::get('/ingresos/nuevo', IngresoForm::class)
            ->middleware('permiso:'.$pi::COOP_INGRESOS)
            ->name('cooperadora.ingresos.nuevo');
        Route::get('/recibo.pdf/{ref}', ReciboPdfController::class)
            ->where('ref', '[A-Za-z0-9_-]+')
            ->middleware('permiso:'.$pi::COOP_INGRESOS)
            ->name('cooperadora.recibo.pdf');
        Route::get('/pagos-estudiante', PagosEstudianteCooperadoraIndex::class)
            ->middleware('permiso:'.$pi::COOP_INGRESOS)
            ->name('cooperadora.pagos-estudiante');
        Route::get('/pagos-estudiante/ver', PagosEstudianteCooperadoraShow::class)
            ->middleware('permiso:'.$pi::COOP_INGRESOS)
            ->name('cooperadora.pagos-estudiante.ver');
        Route::get('/pagos-estudiante.pdf/{ref}', PagosEstudianteCooperadoraPdfController::class)
            ->where('ref', '[A-Za-z0-9_-]+')
            ->middleware('permiso:'.$pi::COOP_INGRESOS)
            ->name('cooperadora.pagos-estudiante.pdf');

        Route::get('/egresos', EgresosIndex::class)
            ->middleware('permiso:'.$pi::COOP_EGRESOS)
            ->name('cooperadora.egresos');
        Route::get('/egresos/nuevo', EgresoForm::class)
            ->middleware('permiso:'.$pi::COOP_EGRESOS)
            ->name('cooperadora.egresos.nuevo');
        Route::get('/orden-pago.pdf/{ref}', OrdenPagoPdfController::class)
            ->where('ref', '[A-Za-z0-9_-]+')
            ->middleware('permiso:'.$pi::COOP_EGRESOS)
            ->name('cooperadora.orden-pago.pdf');

        Route::get('/movimientos', MovimientosIndex::class)
            ->middleware('permiso:'.$pi::COOP_MOVIMIENTOS)
            ->name('cooperadora.movimientos');
        Route::get('/movimientos.pdf', MovimientosPdfController::class)
            ->middleware('permiso:'.$pi::COOP_MOVIMIENTOS)
            ->name('cooperadora.movimientos.pdf');
    });

    Route::get('/', function () {
        return redirect()->route(\App\Support\ProfesorMenuPortal::rutaInicio());
    });

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/pdf-post/ver/{token}', PdfPostVerController::class)
        ->where('token', '[A-Za-z0-9]{32,128}')
        ->name('pdfPost.ver');

    Route::get('/manual-sistema.pdf', ManualSistemaPdfController::class)->name('manual.sistema.pdf');
    Route::get('/manual-comunicacion-institucional.pdf', ManualComunicacionInstitucionalPdfController::class)
        ->middleware('permiso:3')
        ->name('manual.comunicacion.pdf');

    Route::get('/comunicaciones', BandejaGestion::class)->middleware('permiso:3')->name('comunicaciones.index');
    Route::get('/comunicaciones/revision', BandejaRevision::class)->middleware(['permiso:3', 'permiso:8'])->name('comunicaciones.revision');
    Route::get('/comunicaciones/auditoria', ComAuditoriaIndex::class)
        ->middleware('permiso:43')
        ->name('comunicaciones.auditoria');
    Route::get('/comunicaciones/nuevo', NuevoComunicado::class)->middleware('permiso:4')->name('comunicaciones.nuevo');
    Route::get('/comunicaciones/informe-envio/{id}', InformeEnvioComunicado::class)
        ->middleware(['permiso:3', 'permiso:4'])
        ->whereNumber('id')
        ->name('comunicaciones.informe-envio');
    Route::get('/comunicaciones/hilo', HiloShow::class)->middleware('permiso:3')->name('comunicaciones.hilo');
    Route::get('/comunicaciones/hilo.pdf/{ref}', ComunicacionHiloPdfController::class)
        ->middleware('permiso:3')
        ->where('ref', '[A-Za-z0-9_-]+')
        ->name('comunicaciones.hilo.pdf');
    Route::get('/comunicaciones/abrir/{id}', AbrirHiloComunicacionGestionController::class)
        ->middleware('permiso:3')
        ->whereNumber('id')
        ->name('comunicaciones.abrir');

    Route::middleware('permiso:'.\App\Support\PermisosIaCatalog::EMAILS_MASIVOS_ESTUDIANTES)->group(function () {
        Route::get('/emails-masivos', EmailsMasivosIndex::class)->name('emails-masivos.index');
        Route::get('/emails-masivos/form/{id?}', EmailsMasivosForm::class)->whereNumber('id')->name('emails-masivos.form');
        Route::get('/emails-masivos/enviar/{id}', EmailsMasivosEnviar::class)->whereNumber('id')->name('emails-masivos.enviar');
        Route::get('/emails-masivos/historial', EmailsMasivosHistorial::class)->name('emails-masivos.historial');
        Route::get('/emails-masivos/campana/{id}', EmailsMasivosCampanaShow::class)
            ->whereNumber('id')
            ->name('emails-masivos.campana');
        Route::get('/emails-masivos/adjunto/{ref}', EmailsMasivosAdjuntoDownloadController::class)
            ->where('ref', '[A-Za-z0-9_-]+')
            ->name('emails-masivos.adjunto');
    });

    // Administración: permisos del sistema (menú Configuración · subgrupo Permisos del sistema · orden 0)
    Route::get('/administracion/permisos', PermisosUsuariosIndex::class)
        ->middleware('permiso:0')
        ->name('admin.permisos');
    Route::get('/administracion/permisos-por-usuario', PermisosPorUsuarioIndex::class)
        ->middleware('permiso:14')
        ->name('admin.permisos-por-usuario');

    Route::get('/notificaciones/push', SuscribirController::class)
        ->middleware('permiso-config:32')
        ->name('push.suscribir');
    Route::get('/abm/terlec', TerlecIndex::class)->middleware('permiso-config:25')->name('abm.terlec');
    Route::get('/abm/niveles', NivelesIndex::class)->middleware('permiso-config:26')->name('abm.niveles');
    Route::get('/abm/cursos', CursosIndex::class)->middleware('permiso-config:35')->name('abm.cursos');
    Route::get('/abm/planes', PlanesIndex::class)->middleware('permiso-config:33')->name('abm.planes');
    Route::get('/abm/planes/nuevo', PlanesForm::class)->middleware('permiso-config:33')->name('abm.planes.create');
    Route::get('/abm/planes/{id}/editar', PlanesForm::class)->whereNumber('id')->middleware('permiso-config:33')->name('abm.planes.edit');
    Route::get('/abm/curplan', CurplanIndex::class)->middleware('permiso-config:34')->name('abm.curplan');
    Route::get('/abm/curplan/nuevo', CurplanForm::class)->middleware('permiso-config:34')->name('abm.curplan.create');
    Route::get('/abm/curplan/{id}/editar', CurplanForm::class)->whereNumber('id')->middleware('permiso-config:34')->name('abm.curplan.edit');
    Route::get('/abm/materias-anio', MateriasAnioIndex::class)->middleware('permiso-config:36')->name('abm.materias-anio');
    Route::get('/parametrizacion/parametros-sistema', ParametrosSistemaForm::class)
        ->middleware('permiso-config:31')
        ->name('param.parametros-sistema');
    Route::get('/parametrizacion/campos-legajo', CamposLegajoIndex::class)
        ->middleware('permiso-config:27')
        ->name('param.campos-listado-alumnos'); // nombre conservado para no romper enlaces existentes
    Route::get('/parametrizacion/solapas-legajo', SolapaLegajoIndex::class)
        ->middleware('permiso-config:28')
        ->name('param.solapas-legajo');
    Route::get('/parametrizacion/campos-legajo-profesor', CamposProfesorIndex::class)
        ->middleware('permiso-config:29')
        ->name('param.campos-legajo-profesor');
    Route::get('/parametrizacion/solapas-legajo-profesor', SolapaLegajoProfesorIndex::class)
        ->middleware('permiso-config:30')
        ->name('param.solapas-legajo-profesor');

    Route::get('/parametrizacion/campos-aspirantes', CamposAspirantesIndex::class)
        ->middleware('permiso-config:'.\App\Support\PermisosConfiguracion::ASPIRANTES_CAMPOS)
        ->name('param.campos-aspirantes');

    // Módulos pedagógicos (no disponibles en sesión Administración).
    Route::middleware('menu.portal:secretaria')->group(function () {

    Route::middleware('permiso:'.PermisosMatriculaWeb::DOCUMENTOS_ACEPTACION)->prefix('matricula-web')->group(function () {
        Route::get('/documentos', DocumentosAceptacionForm::class)->name('matricula-web.documentos');
        Route::get('/documentos/{tipo}/archivo', DocumentoAceptacionArchivoController::class)
            ->where('tipo', 'compromiso|aec|normas|traslado')
            ->name('matricula-web.documentos.archivo');
    });

    Route::middleware('permiso:'.PermisosMatriculaWeb::DOCUMENTOS_ESTUDIANTE_FAMILIA)->prefix('matricula-web')->group(function () {
        Route::get('/documentos-estudiante-tipos', DocumentosEstudianteTiposIndex::class)
            ->name('matricula-web.documentos-estudiante-tipos');
    });

    Route::middleware('permiso:'.PermisosMatriculaWeb::BLOQUEOS_MATRICULA)->prefix('matricula-web')->group(function () {
        Route::get('/bloqueos', BloqueosMatriculaIndex::class)->name('matricula-web.bloqueos');
    });

    // Gestión de Aspirantes (permiso orden 39)
    Route::middleware('permiso:'.\App\Support\PermisosIaCatalog::ASPIRANTES_GESTION)
        ->prefix('aspirantes')
        ->group(function () {
            Route::get('/cursos-modelo', AspirantesCursosModeloIndex::class)->name('aspirantes.cursos-modelo');
            Route::get('/instancia', AspirantesInstanciaIndex::class)->name('aspirantes.instancia');
            Route::get('/instancia/nueva', AspirantesInstanciaForm::class)->name('aspirantes.instancia.create');
            Route::get('/instancia/{id}/editar', AspirantesInstanciaForm::class)->whereNumber('id')->name('aspirantes.instancia.edit');
            Route::get('/listado', AspirantesIndex::class)->name('aspirantes.listado');
        });

    Route::get('/horarios/configuracion', HorariosConfigIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::HORARIOS)
        ->name('horarios.config');

    Route::middleware('permiso:'.\App\Support\PermisosIaCatalog::VIAJES_SALIDAS_EDUCATIVAS)->group(function () {
        Route::get('/listados/estudiantes-datos', EstudiantesDatosExport::class)
            ->name('listados.estudiantes-datos');
        Route::get('/listados/estudiantes-datos/excel', EstudiantesDatosExcelController::class)
            ->name('listados.estudiantes-datos.excel');
        Route::get('/listados/estudiantes-datos/pdf', EstudiantesDatosPdfController::class)
            ->name('listados.estudiantes-datos.pdf');

        Route::get('/viajes/salidas', SalidasViajesIndex::class)->name('viajes.salidas');
        Route::get('/viajes/salidas/nuevo', SalidaViajeForm::class)->name('viajes.salidas.create');
        Route::get('/viajes/salidas/{id}/editar', SalidaViajeForm::class)->whereNumber('id')->name('viajes.salidas.edit');
        Route::get('/viajes/salidas/{id}/imprimir', SalidaViajeImpresion::class)->whereNumber('id')->name('viajes.salidas.imprimir');
        Route::post('/viajes/salidas/pdf', SalidaViajePdfController::class)->name('viajes.salidas.pdf');
    });

    // Material Didáctico — acceso: Admin (68) | Profesor (69) | Solo Lectura (70)
    // Los órdenes separados por coma son OR (ver CheckPermiso con ...$ordenes).
    Route::prefix('material-didactico')->group(function () {
        $pi = \App\Support\PermisosIaCatalog::class;

        // Listado: cualquiera con algún permiso del módulo puede ver
        Route::get('/', ReservasDashboard::class)
            ->middleware('permiso:'.$pi::RESERVA_MATERIAL_ADMIN.','.$pi::RESERVA_MATERIAL_PROFESOR.','.$pi::RESERVA_MATERIAL_LECTURA)
            ->name('material-didactico.index');

        // Registrar y editar: Admin (68) o Profesor (69)
        Route::get('/reservar', ReservaForm::class)
            ->middleware('permiso:'.$pi::RESERVA_MATERIAL_ADMIN.','.$pi::RESERVA_MATERIAL_PROFESOR)
            ->name('material-didactico.reservar');
        Route::get('/reservar/{id}/editar', ReservaForm::class)
            ->whereNumber('id')
            ->middleware('permiso:'.$pi::RESERVA_MATERIAL_ADMIN.','.$pi::RESERVA_MATERIAL_PROFESOR)
            ->name('material-didactico.reservar.edit');

        // Recursos (ABM): solo Admin
        Route::get('/recursos', RecursosAdmin::class)
            ->middleware('permiso:'.$pi::RESERVA_MATERIAL_ADMIN)
            ->name('material-didactico.recursos');
    });

    Route::middleware('permiso:12')->group(function () {
        Route::get('/examenes/materias-adeudadas/entrar', [MateriasAdeudadasEntradaController::class, 'listado'])
            ->name('examenes.materias-adeudadas.entrar');
        Route::get('/examenes/materias-adeudadas', MateriasAdeudadasListadoIndex::class)
            ->name('examenes.materias-adeudadas');
        Route::get('/examenes/materias-adeudadas/pdf', MateriasAdeudadasPdfController::class)
            ->name('examenes.materias-adeudadas.pdf');
        Route::get('/examenes/materias-adeudadas/gestion/entrar', [MateriasAdeudadasEntradaController::class, 'gestion'])
            ->name('examenes.materias-adeudadas.gestion.entrar');
        Route::get('/examenes/materias-adeudadas/gestion', MateriasAdeudadasGestionIndex::class)
            ->name('examenes.materias-adeudadas.gestion');
        Route::get('/examenes/materias-adeudadas/gestion/carga', MateriasAdeudadasCargaManualIndex::class)
            ->name('examenes.materias-adeudadas.gestion.carga');
        Route::get('/examenes/materias-adeudadas/gestion/inscribir', MateriasAdeudadasInscripcionIndex::class)
            ->name('examenes.materias-adeudadas.gestion.inscribir');
        Route::get('/examenes/materias-adeudadas/gestion/notas', MateriasAdeudadasNotasIndex::class)
            ->name('examenes.materias-adeudadas.gestion.notas');
        Route::get('/examenes/materias-adeudadas/gestion/historial', HistorialExamenesIndex::class)
            ->name('examenes.materias-adeudadas.gestion.historial');
        Route::get('/examenes/borrar-inscripciones', BorrarInscripcionesExamenIndex::class)
            ->name('examenes.borrar-inscripciones');
        Route::get('/examenes/actas-volantes-previos/entrar', [MateriasAdeudadasEntradaController::class, 'actaVolante'])
            ->name('examenes.acta-volante-previos.entrar');
        Route::get('/examenes/actas-volantes-previos', ActaVolantePreviosIndex::class)
            ->name('examenes.acta-volante-previos');
        Route::get('/examenes/actas-volantes-previos/pdf', ActaVolantePreviosPdfController::class)
            ->name('examenes.acta-volante-previos.pdf');
        Route::get('/examenes/permiso-examen/entrar', [MateriasAdeudadasEntradaController::class, 'permisoExamen'])
            ->name('examenes.permiso-examen.entrar');
        Route::get('/examenes/permiso-examen', PermisoExamenIndex::class)
            ->name('examenes.permiso-examen');
        Route::post('/examenes/permiso-examen/pdf', [PermisoExamenPdfController::class, 'preparar'])
            ->name('examenes.permiso-examen.pdf.preparar');
        Route::get('/examenes/permiso-examen/pdf', PermisoExamenPdfController::class)
            ->name('examenes.permiso-examen.pdf');
        Route::get('/examenes/tercer-materia', TercerMateriaIndex::class)
            ->name('examenes.tercer-materia');
        Route::get('/examenes/tercer-materia/pdf', TercerMateriaPdfController::class)
            ->name('examenes.tercer-materia.pdf');
        Route::get('/examenes/tercer-materia/acta-compromiso/{idCalificacion}', ActaCompromisoTercerMateriaPdfController::class)
            ->whereNumber('idCalificacion')
            ->name('examenes.tercer-materia.acta-compromiso.pdf');
    });

    // Planificaciones y programas (doc_pp): permiso 86 — fuera del grupo de exámenes (12)
    Route::middleware('permiso:'.\App\Support\PermisosIaCatalog::PLANIFICACIONES_PROGRAMAS)->group(function () {
        Route::get('/doc-pp', DocPpIndex::class)->name('doc-pp.index');
        Route::get('/doc-pp/archivo/{ref}', DocPpArchivoController::class)
            ->where('ref', '[A-Za-z0-9_-]+')
            ->name('doc-pp.archivo');
        Route::get('/doc-pp/{id}/{tipo}', DocPpForm::class)
            ->whereNumber('id')
            ->whereIn('tipo', ['plan', 'prog'])
            ->name('doc-pp.form');
    });

    // Permiso 13 (HORARIOS): solo configuración y carga. Impresión y PDFs: sin ese permiso.
    Route::get('/horarios/carga', HorariosCargaIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::HORARIOS)
        ->name('horarios.carga');
    Route::get('/horarios/impresion', HorariosImpresionIndex::class)
        ->name('horarios.impresion');
    Route::get('/horarios/pdf/curso', HorarioCursoPdfController::class)
        ->name('horarios.pdf.curso');
    Route::get('/horarios/pdf/profesor', HorarioProfesorPdfController::class)
        ->name('horarios.pdf.profesor');

    // Calificaciones (nivel inicial): GE/CIDI, indicadores por materia y etapa
    Route::get('/calificaciones-inicial/sincro-ge', SincroGeInicial::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_SINCRO_CIDI)
        ->name('calificacionesInicial.sincroGe');
    Route::get('/calificaciones-inicial/sincro-desempenos', SincroDesempenosInicial::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_SINCRO_CIDI)
        ->name('calificacionesInicial.sincroDesempenos');

    Route::get('/calificaciones-inicial/indicadores', EditarIndicadoresIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesInicial.indicadores');
    Route::get('/calificaciones-inicial/indicadores/{materia}', EditarIndicadoresForm::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->whereNumber('materia')
        ->name('calificacionesInicial.indicadores.materia');

    Route::get('/calificaciones-inicial/observaciones', CargaObservacionesInicialIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesInicial.observaciones');
    Route::get('/calificaciones-inicial/observaciones/{materia}', CargaObservacionesInicialAlumnos::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->whereNumber('materia')
        ->name('calificacionesInicial.observaciones.alumnos');
    Route::get('/calificaciones-inicial/observaciones/{materia}/{matricula}', CargaObservacionesInicialForm::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->whereNumber(['materia', 'matricula'])
        ->name('calificacionesInicial.observaciones.carga');

    Route::get('/calificaciones-inicial/observaciones-espacio-curricular', CargaObservacionesInicialMateria::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesInicial.observacionesMateria');

    Route::get('/calificaciones-inicial/informe-progreso', InformeProgresoInicialIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesInicial.informeProgreso');
    Route::post('/calificaciones-inicial/informe-progreso/pdf', InformeProgresoInicialPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesInicial.informeProgreso.pdf');
    Route::post('/calificaciones-inicial/informe-progreso/pdf-lote', InformeProgresoInicialLotePdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesInicial.informeProgreso.pdfLote');

    Route::get('/calificaciones-inicial-sfq/carga', CargaCalificacionesInicialSfqIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesInicialSfq.carga');
    Route::get('/calificaciones-inicial-sfq/carga/{matricula}/indicadores/{campo}', CargaCalificacionesInicialSfqIndicadoresForm::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->whereNumber('matricula')
        ->where('campo', 'ic0[1-6]')
        ->name('calificacionesInicialSfq.carga.indicadores');
    Route::get('/calificaciones-inicial-sfq/carga/{matricula}/observaciones', CargaCalificacionesInicialSfqObservacionesForm::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->whereNumber('matricula')
        ->name('calificacionesInicialSfq.carga.observaciones');

    Route::get('/calificaciones-inicial-sfq/boletin', BoletinInicialSfqIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesInicialSfq.boletin');
    Route::post('/calificaciones-inicial-sfq/boletin/pdf', BoletinInicialSfqPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesInicialSfq.boletin.pdf');
    Route::post('/calificaciones-inicial-sfq/boletin/pdf-lote', BoletinInicialSfqLotePdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesInicialSfq.boletin.pdfLote');

    // Calificaciones (nivel primario): GE/CIDI y desempeños por etapa (CSV)
    Route::get('/calificaciones-primario/sincro-ge', SincroGePrimario::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_SINCRO_CIDI)
        ->name('calificacionesPrimario.sincroGe');
    Route::get('/calificaciones-primario/sincro-desempenos', SincroDesempenos::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_SINCRO_CIDI)
        ->name('calificacionesPrimario.sincroDesempenos');
    Route::get('/calificaciones-primario/carga', CargaCalificacionesPrimarioIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimario.carga');
    Route::get('/calificaciones-primario/carga/{matricula}', CargaCalificacionesPrimarioForm::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->whereNumber('matricula')
        ->name('calificacionesPrimario.carga.alumno');
    Route::get('/calificaciones-primario/carga-materia', CargaCalificacionesPrimarioMateria::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimario.cargaMateria');
    Route::get('/calificaciones-primario/boletin-ipe', BoletinIpeIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimario.boletinIpe');
    Route::post('/calificaciones-primario/boletin-ipe/pdf', BoletinIpePdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimario.boletinIpe.pdf');
    Route::post('/calificaciones-primario/boletin-ipe/pdf-lote', BoletinIpeLotePdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimario.boletinIpe.pdfLote');
    Route::get('/calificaciones-primario/planilla', PlanillaCalificacionesPrimario::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimario.planilla');
    Route::get('/calificaciones-primario/planilla/pdf', PlanillaCalificacionesPrimarioPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimario.planilla.pdf');

    Route::get('/calificaciones-primario-epq/carga', CargaCalificacionesEpqIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimarioEpq.carga');
    Route::get('/calificaciones-primario-epq/carga/{matricula}', CargaCalificacionesEpqForm::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->whereNumber('matricula')
        ->name('calificacionesPrimarioEpq.carga.alumno');
    Route::get('/calificaciones-primario-epq/info-adicional/{matricula}', InfoAdicionalEpqForm::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->whereNumber('matricula')
        ->name('calificacionesPrimarioEpq.infoAdicional');
    Route::get('/calificaciones-primario-epq/boletin', BoletinPrimEpqIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimarioEpq.boletin');
    Route::post('/calificaciones-primario-epq/boletin/pdf', BoletinPrimEpqPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimarioEpq.boletin.pdf');
    Route::post('/calificaciones-primario-epq/boletin/pdf-lote', BoletinPrimEpqLotePdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimarioEpq.boletin.pdfLote');
    Route::get('/calificaciones-primario-epq/planilla', PlanillaCalificacionesEpq::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimarioEpq.planilla');
    Route::get('/calificaciones-primario-epq/planilla/pdf', PlanillaCalificacionesEpqPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesPrimarioEpq.planilla.pdf');

    // Calificaciones (nivel secundario): sincro GE/CIDI, carga y consulta institucional
    Route::get('/calificaciones-secundario/sincro-ge', SincroGe::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_SINCRO_CIDI)
        ->name('calificacionesSecundario.sincroGe');
    Route::get('/calificaciones-secundario/carga', CargaCalificacionesSecundario::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesSecundario.carga');
    Route::get('/calificaciones-secundario-epq/carga', CargaCalificacionesEpqSecundario::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesSecundarioEpq.carga');
    Route::get('/calificaciones-secundario-epq/carga/pdf', CargaCalificacionesEpqSecundarioPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesSecundarioEpq.carga.pdf');
    Route::get('/calificaciones-secundario-epq/boletin', BoletinEpqSecundarioIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesSecundarioEpq.boletin');
    Route::post('/calificaciones-secundario-epq/boletin/pdf', BoletinEpqSecundarioPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesSecundarioEpq.boletin.pdf');
    Route::post('/calificaciones-secundario-epq/boletin/pdf-lote', BoletinEpqSecundarioLotePdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesSecundarioEpq.boletin.pdfLote');
    Route::get('/calificaciones-secundario-epq/planilla', PlanillaCalificacionesEpqSecundario::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesSecundarioEpq.planilla');
    Route::get('/calificaciones-secundario-epq/planilla/pdf', PlanillaCalificacionesEpqSecundarioPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_CARGA)
        ->name('calificacionesSecundarioEpq.planilla.pdf');
    Route::get('/calificaciones-secundario/coloquios', CargaColoquiosSecundario::class)
        ->middleware('permiso:10')
        ->name('calificacionesSecundario.coloquios');
    Route::get('/calificaciones-secundario/actas-volantes-coloquio', ActaVolanteColoquiosSecundario::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_ACTAS_VOLANTES_COLOQUIO)
        ->name('calificacionesSecundario.actaVolanteColoquios');
    Route::get('/calificaciones-secundario/actas-volantes-coloquio/pdf', ActaVolanteColoquiosPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_ACTAS_VOLANTES_COLOQUIO)
        ->name('calificacionesSecundario.actaVolanteColoquios.pdf');
    Route::get('/calificaciones-secundario/planilla', PlanillaCalificacionesSecundario::class)
        ->name('calificacionesSecundario.planilla');
    Route::get('/calificaciones-secundario/planilla/pdf', PlanillaCalificacionesPdfController::class)
        ->name('calificacionesSecundario.planilla.pdf');
    Route::get('/calificaciones-secundario/planilla-resumen', PlanillaResumenCalificacionesSecundario::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_PLANILLA_RESUMEN)
        ->name('calificacionesSecundario.planillaResumen');
    Route::get('/calificaciones-secundario/planilla-resumen/pdf', PlanillaResumenCalificacionesPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::CALIF_PLANILLA_RESUMEN)
        ->name('calificacionesSecundario.planillaResumen.pdf');
    Route::get('/calificaciones-secundario/consulta', ConsultaCalificacionesSecundario::class)
        ->name('calificacionesSecundario.consulta');
    Route::post('/calificaciones-secundario/consulta/pdf', ConsultaCalificacionesSecundarioPdfController::class)
        ->name('calificacionesSecundario.consulta.pdf');
    Route::get('/calificaciones-secundario/cierre-anual', CierreAnualIndex::class)
        ->middleware('permiso:15')
        ->name('calificacionesSecundario.cierreAnual');
    Route::get('/calificaciones-secundario/cierre-anual/historial', CierreAnualHistorial::class)
        ->middleware('permiso:15')
        ->name('calificacionesSecundario.cierreAnual.historial');
    Route::middleware('permiso:'.\App\Support\PermisosIaCatalog::SOLICITUDES_EVALUACION_GESTION)
        ->prefix('calificaciones-secundario/gestion-solicitudes-evaluacion')
        ->name('calificacionesSecundario.gestionSolicitudesEvaluacion.')
        ->group(function () {
            Route::get('/', GestionSolicitudEvaluacionIndex::class)->name('index');
            Route::get('/nueva', GestionSolicitudEvaluacionForm::class)->name('create');
            Route::get('/{id}/editar', GestionSolicitudEvaluacionForm::class)->whereNumber('id')->name('edit');
        });

    // Libro matriz / pase / analítico
    Route::get('/matriz-analiticos/libro-matriz', LibroMatrizIndex::class)
        ->middleware('permiso:16')
        ->name('matrizAnaliticos.libroMatriz');
    Route::get('/matriz-analiticos/libro-matriz/editar', LibroMatrizEditar::class)
        ->middleware('permiso:16')
        ->name('matrizAnaliticos.libroMatriz.editar');
    Route::get('/matriz-analiticos/libro-matriz/datos-adicionales', LibroMatrizDatosAdicionales::class)
        ->middleware('permiso:16')
        ->name('matrizAnaliticos.libroMatriz.datosAdicionales');
    Route::post('/matriz-analiticos/libro-matriz/pdf-frente', AnaliticoFrentePdfController::class)
        ->middleware('permiso:16')
        ->name('matrizAnaliticos.libroMatriz.pdfFrente');
    Route::post('/matriz-analiticos/libro-matriz/pdf-reverso', AnaliticoReversoPdfController::class)
        ->middleware('permiso:16')
        ->name('matrizAnaliticos.libroMatriz.pdfReverso');

    // Certificados — alumno regular
    Route::get('/certificados/alumno-regular', CertificadoAlumnoRegularIndex::class)
        ->middleware('permiso:17')
        ->name('certificados.alumnoRegular');
    Route::post('/certificados/alumno-regular/pdf', CertificadoAlumnoRegularPdfController::class)
        ->middleware('permiso:17')
        ->name('certificados.alumnoRegular.pdf');

    // Certificados — estudios en trámite
    Route::get('/certificados/estudios-tramite', CertificadoEstudiosTramiteIndex::class)
        ->middleware('permiso:18')
        ->name('certificados.estudiosTramite');
    Route::post('/certificados/estudios-tramite/pdf', CertificadoEstudiosTramitePdfController::class)
        ->middleware('permiso:18')
        ->name('certificados.estudiosTramite.pdf');

    // Certificados — constancia de documentos
    Route::get('/certificados/constancia-documentos', ConstanciaDocumentosIndex::class)
        ->middleware('permiso:19')
        ->name('certificados.constanciaDocumentos');
    Route::post('/certificados/constancia-documentos/pdf', ConstanciaDocumentosPdfController::class)
        ->middleware('permiso:19')
        ->name('certificados.constanciaDocumentos.pdf');

    // Certificados — asistencia del profesor
    Route::get('/certificados/asistencia-profesor', CertificadoAsistenciaProfesorIndex::class)
        ->middleware('permiso:20')
        ->name('certificados.asistenciaProfesor');
    Route::post('/certificados/asistencia-profesor/pdf', CertificadoAsistenciaProfesorPdfController::class)
        ->middleware('permiso:20')
        ->name('certificados.asistenciaProfesor.pdf');

    // Certificados — pase parcial
    Route::get('/certificados/pase-parcial', PaseParcialIndex::class)
        ->middleware('permiso:21')
        ->name('certificados.paseParcial');
    Route::post('/certificados/pase-parcial/pdf', PaseParcialPdfController::class)
        ->middleware('permiso:21')
        ->name('certificados.paseParcial.pdf');

    // Certificados — solicitud de pase
    Route::get('/certificados/solicitud-de-pase', SolicitudDePaseIndex::class)
        ->middleware('permiso:22')
        ->name('certificados.solicitudDePase');
    Route::post('/certificados/solicitud-de-pase/pdf', SolicitudDePasePdfController::class)
        ->middleware('permiso:22')
        ->name('certificados.solicitudDePase.pdf');

    // Certificados — C.U.S. / I.S.A. / Voz-Imagen
    Route::get('/certificados/cus-isa-voz-imagen', CusIsaVozImagenIndex::class)
        ->middleware('permiso:66')
        ->name('certificados.cusIsaVozImagen');
    Route::post('/certificados/cus-isa-voz-imagen/pdf/{tipo}', CusIsaVozImagenPdfController::class)
        ->where('tipo', 'cus|isa|voz-imagen')
        ->middleware('permiso:66')
        ->name('certificados.cusIsaVozImagen.pdf');

    // Boletines / informe de progreso escolar (nivel secundario)
    Route::get('/boletines-secundario', BoletinesSecundarioIndex::class)
        ->name('boletinesSecundario.index');
    Route::post('/boletines-secundario/pdf', BoletinSecundarioPdfController::class)
        ->name('boletinesSecundario.pdf');
    Route::post('/boletines-secundario/pdf-lote', BoletinSecundarioLotePdfController::class)
        ->name('boletinesSecundario.pdfLote');

    // Estadísticas — rendimiento escolar (nivel secundario, permiso orden 65)
    Route::middleware('permiso:65')->group(function () {
        Route::get('/estadistica/rendimiento-escolar', RendimientoEscolarIndex::class)
            ->name('estadistica.rendimiento');
        Route::get('/estadistica/rendimiento-escolar/por-materia', EstadisticaPorMateria::class)
            ->name('estadistica.rendimiento.porMateria');
        Route::get('/estadistica/rendimiento-escolar/por-docente', EstadisticaPorDocente::class)
            ->name('estadistica.rendimiento.porDocente');
        Route::get('/estadistica/rendimiento-escolar/por-estudiante', EstadisticaPorEstudiante::class)
            ->name('estadistica.rendimiento.porEstudiante');
    });

    // Seguimiento disciplinario (permiso orden 37)
    Route::middleware('permiso:37')->group(function () {
        Route::get('/seguimiento/disciplinario', DisciplinarioIndex::class)
            ->name('seguimiento.disciplinario');
        Route::get('/seguimiento/disciplinario/nuevo', SancionForm::class)
            ->name('seguimiento.disciplinario.create');
        Route::get('/seguimiento/disciplinario/{id}/editar', SancionForm::class)
            ->whereNumber('id')
            ->name('seguimiento.disciplinario.edit');

        Route::get('/seguimiento/disciplinario/{id}/imprimir', SancionComunicadoPdfController::class)
            ->whereNumber('id')
            ->name('seguimiento.disciplinario.print');

        Route::get('/seguimiento/disciplinario/antecedentes', AntecedentesIndex::class)
            ->name('seguimiento.disciplinario.antecedentes');

        Route::post('/seguimiento/disciplinario/antecedentes/pdf', AntecedentesDisciplinariosPdfController::class)
            ->name('seguimiento.disciplinario.antecedentes.pdf');
    });

    // Gestión de inasistencias (permiso orden 38)
    Route::middleware('permiso:38')->group(function () {
        Route::get('/seguimiento/inasistencias', InasistenciasIndex::class)
            ->name('seguimiento.inasistencias');
        Route::get('/seguimiento/inasistencias/nuevo', InasistenciaForm::class)
            ->name('seguimiento.inasistencias.create');
        Route::get('/seguimiento/inasistencias/{id}/editar', InasistenciaForm::class)
            ->whereNumber('id')
            ->name('seguimiento.inasistencias.edit');
        Route::post('/seguimiento/inasistencias/informe/pdf', InformeInasistenciasPdfController::class)
            ->name('seguimiento.inasistencias.informe.pdf');
    });
    Route::middleware('permiso:'.\App\Support\PermisosIaCatalog::TEA_ESTUDIANTES_GESTION)->group(function () {
        Route::get('/seguimiento/tea', TeaIndex::class)
            ->name('seguimiento.tea');
        Route::get('/seguimiento/tea/nuevo', TeaForm::class)
            ->name('seguimiento.tea.create');
        Route::get('/seguimiento/tea/{id}/editar', TeaForm::class)
            ->whereNumber('id')
            ->name('seguimiento.tea.edit');
        Route::get('/seguimiento/tea/registro/{id}/pdf', TeaRegistroPdfController::class)
            ->whereNumber('id')
            ->name('seguimiento.tea.registro.pdf');
    });
    Route::get('/seguimiento/inasistencias/sincro-cidi', SincroCidiInasistencias::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::INASISTENCIAS_SINCRO_CIDI)
        ->name('seguimiento.inasistencias.sincroCidi');
    Route::get('/seguimiento/toma-asistencia-clase', TomaAsistenciaClaseIndex::class)
        ->middleware('permiso:1')
        ->name('seguimiento.toma-asistencia-clase');
    Route::get('/seguimiento/inasistencias/informe', InformeInasistenciasLoteIndex::class)
        ->name('seguimiento.inasistencias.informe');
    Route::post('/seguimiento/inasistencias/informe/lote/pdf', InformeInasistenciasLotePdfController::class)
        ->name('seguimiento.inasistencias.informe.lote.pdf');

    Route::get('/seguimiento/partes-diarios', PartesDiariosIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::PARTE_DIARIO_PRECEPTOR)
        ->name('seguimiento.partes-diarios');
    Route::get('/seguimiento/partes-diarios/pdf', ParteDiarioPreceptorPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::PARTE_DIARIO_PRECEPTOR)
        ->name('seguimiento.partes-diarios.pdf');

    Route::get('/seguimiento/registro-asistencia', RegistroAsistenciaIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::REGISTRO_ASISTENCIA)
        ->name('seguimiento.registro-asistencia');
    Route::get('/seguimiento/registro-asistencia/pdf', RegistroAsistenciaPdfController::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::REGISTRO_ASISTENCIA)
        ->name('seguimiento.registro-asistencia.pdf');
    Route::get('/seguimiento/registro-asistencia/feriados', FeriadosIndex::class)
        ->middleware('permiso:'.\App\Support\PermisosIaCatalog::REGISTRO_ASISTENCIA)
        ->name('seguimiento.registro-asistencia.feriados');

    }); // fin menu.portal:secretaria (pedagógico)

    Route::get('/abm/profesores-por-materia', ProfesoresPorMateriaIndex::class)->middleware('permiso:'.\App\Support\PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO)->name('abm.profesores-por-materia');
    Route::get('/abm/cursos-por-profesor', CursosPorProfesorIndex::class)->middleware('permiso:'.\App\Support\PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO)->name('abm.cursos-por-profesor');
    Route::get('/parametrizacion/com-canales', ComCanalesIndex::class)
        ->middleware('permiso:5')
        ->name('param.com-canales');

    Route::get('/abm/legajos', LegajosIndex::class)->name('abm.legajos');
    Route::get('/abm/legajos/carga-por-curso', LegajoCargaPorCurso::class)->middleware('permiso:2')->name('abm.legajos.carga-por-curso');
    Route::get('/abm/legajos/nuevo', LegajoForm::class)->middleware('permiso:2')->name('abm.legajos.create');
    Route::get('/abm/legajos/editar', LegajoForm::class)->name('abm.legajos.edit');
    Route::get('/abm/legajos/familia', LegajoFamilia::class)->name('abm.legajos.familia');
    Route::get('/abm/legajos/buscar-familias', LegajoBuscarFamilias::class)->name('abm.legajos.buscar-familias');

    Route::get('/abm/legajos-profesor', LegajosProfesorIndex::class)->name('abm.legajos-profesor');
    Route::get('/abm/legajos-profesor/nuevo', LegajoProfesorForm::class)->middleware('permiso:'.\App\Support\PermisosIaCatalog::LEGAJOS_DOCENTES)->name('abm.legajos-profesor.create');
    Route::get('/abm/legajos-profesor/{id}/editar', LegajoProfesorForm::class)->whereNumber('id')->name('abm.legajos-profesor.edit');

    Route::middleware('permiso:'.InasistenciasDocentes::PERMISO_ORDEN)->prefix('docentes/inasistencias')->group(function () {
        Route::get('/', InasistenciasDocentesIndex::class)->name('docentes.inasistencias');
        Route::get('/envio-masivo', EnvioMasivoInasistenciasDocentes::class)->name('docentes.inasistencias.envio-masivo');
        Route::get('/ranking', RankingInasistenciasMateriasCursos::class)->name('docentes.inasistencias.ranking');
        Route::get('/ranking/exportar-csv', RankingInasistenciasMateriasCursosCsvController::class)->name('docentes.inasistencias.ranking.csv');
        Route::get('/{idProfesor}', InasistenciasDocenteShow::class)->whereNumber('idProfesor')->name('docentes.inasistencias.show');
        Route::get('/{idProfesor}/nuevo', InasistenciaDocenteForm::class)->whereNumber('idProfesor')->name('docentes.inasistencias.create');
        Route::get('/{idProfesor}/{id}/editar', InasistenciaDocenteForm::class)->whereNumber(['idProfesor', 'id'])->name('docentes.inasistencias.edit');
        Route::get('/{idProfesor}/cargos/{idCxp?}', CargosDocenteIndex::class)->whereNumber(['idProfesor', 'idCxp'])->name('docentes.inasistencias.cargos');
        Route::get('/{idProfesor}/informe/{bimestre}', InformeBimestreShow::class)->whereNumber(['idProfesor', 'bimestre'])->name('docentes.inasistencias.informe');
        Route::get('/{idProfesor}/informe/{bimestre}/pdf', InformeInasistenciasDocentePdfController::class)
            ->whereNumber(['idProfesor', 'bimestre'])
            ->name('docentes.inasistencias.informe.pdf');
    });

    Route::middleware('permiso:'.\App\Support\PermisosIaCatalog::CERTIFICACION_SERVICIOS)->prefix('docentes/certificacion-servicios')->group(function () {
        Route::get('/', CertificacionServiciosIndex::class)->name('docentes.certificacion-servicios');
        Route::post('/pdf', CertificacionServiciosPdfController::class)->name('docentes.certificacion-servicios.pdf');
        Route::get('/{idPersonal}', CertificacionServiciosForm::class)->whereNumber('idPersonal')->name('docentes.certificacion-servicios.form');
    });

    Route::get('/listados/por-curso', ListadoPorCurso::class)->name('listados.por-curso');
    Route::get('/listados/por-curso/listado', ListadoCursoPdfController::class)->name('listados.por-curso.pdf');
    Route::get('/listados/estudiantes-formato', ListadoEstudiantesFormato::class)->name('listados.estudiantes-formato');
    Route::get('/listados/estudiantes-formato/pdf', ListadoEstudiantesFormatoPdfController::class)
        ->name('listados.estudiantes-formato.pdf');
    Route::get('/listados/exportar-excel', EstudiantesExcelController::class)
        ->name('listados.exportar-excel');
    Route::get('/listados/libro-matricula', LibroMatricula::class)->name('listados.libro-matricula');
    Route::get('/listados/libro-matricula/pdf', LibroMatriculaPdfController::class)
        ->name('listados.libro-matricula.pdf');
    Route::get('/listados/ficha-matricula', FichaMatriculaSecretaria::class)->name('listados.ficha-matricula');
    Route::get('/listados/ficha-matricula/pdf', FichaMatriculaSecretariaPdfController::class)
        ->name('listados.ficha-matricula.pdf');
    Route::get('/listados/ficha-matricula/zip', FichaMatriculaSecretariaZipController::class)
        ->name('listados.ficha-matricula.zip');

    Route::get('/listados/docentes', ListadoDocentes::class)->name('listados.docentes');
    Route::get('/listados/docentes/listado', ListadoDocentesPdfController::class)->name('listados.docentes.pdf');
    Route::get('/listados/docentes/excel', ListadoDocentesExcelController::class)->name('listados.docentes.excel');
});
