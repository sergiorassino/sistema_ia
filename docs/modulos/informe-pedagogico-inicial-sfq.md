# Módulo: Informes pedagógicos inicial (SFQ)

## Propósito

PDF de informes pedagógicos del **nivel inicial** en SFQ: diagnóstico, 1.º etapa, 2.º etapa y Bellas Artes. Misma plantilla TCPDF en Secretaría, Docentes y autogestión familia. En familia solo se imprime el alumno de la sesión.

## Modalidades / variantes

Clave: `tenant.calificaciones_inicial.boletin.implementacion` = `sfq`.

Autogestión (Menú de Alumnos, nivel inicial):

```php
'autogestion' => [
    'boletin_inicial_sfq' => ['habilitado' => true],
],
```

Default **deshabilitado**. Hoy activo en **SFQ** (`config/tenants/sfq.php`). Helper: `tenantAutogestionBoletinInicialSfqHabilitada()`.

No ramificar por `tenantSlug() === 'sfq'` en Blade ni en el controlador.

## Actores y permisos

- Secretaría: autenticado con contexto escolar; **no** requiere permiso IA 71 (carga). Rutas `calificacionesInicialSfq.boletin*`.
- Docentes: portal docente si el tenant habilita el ítem (sin `permisos_ia`).
- Autogestión familia: flag del tenant + nivel inicial + implementación `sfq`. Guard `alumno`, `student.context`.
- `ento.verNotasOff` del nivel inicial bloquea menú y PDF en familia (`EntoVerNotasOff`).

## Tablas y campos críticos

- `calificaciones`: `ic01`–`ic03` + `obs01`–`obs03` (pedagógico); `ic04`–`ic06` + `baObs01`–`baObs03` (Bellas Artes).
- `indicadores` / `edani` por sala (`cursos.c`) y etapa.
- `matricula` del ciclo de autogestión (`ento.idTerlecVerNotas` vía `studentCtx()`).

Tipos de informe (`CalificacionesInicialSfqCatalogo::TIPOS_INFORME`): `diagnostico`, `etapa1`, `etapa2`, `bellas_artes`.

## Flujo principal

1. Secretaría/Docentes: curso + tipo de informe + alumnos → PDF individual o lote.
2. Familia: cuatro ítems en el sidebar y en el escritorio; cada uno abre el PDF del alumno logueado en pestaña nueva.
3. URL de familia: `/alumnos/informe-pedagogico-inicial/{tipo}` (tipo de informe, no ID de matrícula ni DNI).

## Fuente de verdad

- Datos: `BoletinInicialSfqDatos` (`buildForMatriculaEnContexto` / `buildDatosParaAlumno`).
- PDF: `BoletinInicialSfqTcpdf`. En autogestión se dibuja marca «SIN VALOR LEGAL»; Secretaría y Docentes no.

## Archivos clave

- Catálogo: `app/Support/CalificacionesInicial/Sfq/CalificacionesInicialSfqCatalogo.php`
- Datos / TCPDF: `BoletinInicialSfqDatos.php`, `BoletinInicialSfqTcpdf.php`
- Secretaría: `BoletinInicialSfqIndex`, `BoletinInicialSfqPdfController`
- Familia: `PortalFamiliaBoletinInicialSfq`, `BoletinInicialSfqFamiliaPdfController`
- Menú: `resources/views/layouts/partials/alumno-nav-calificaciones.blade.php`
- Tenant: `config/tenants/sfq.php` → `autogestion.boletin_inicial_sfq`

## Qué no hacer / reglas de negocio

- No poner IDs de legajo ni matrícula en la URL de familia.
- No usar `schoolCtx()` para armar el PDF de autogestión: nivel y ciclo salen de la matrícula de `studentCtx()`.
- No calcular calificaciones: solo se leen `ic*` y observaciones ya cargadas.
- No habilitar el flag por defecto en `config/tenant.php`.
- Primario SFQ sigue usando el boletín EPQ; estos cuatro PDF son solo nivel inicial.

## Checklist al modificar

- [ ] Los cuatro tipos del catálogo coinciden entre Secretaría (tabs) y familia (menú).
- [ ] El PDF de Secretaría/lote no lleva marca de agua al tocar `BoletinInicialSfqTcpdf`.
- [ ] Rate-limit y `verNotasOff` en el controlador de familia.
- [ ] Otro colegio no ve los ítems sin `habilitado => true` y `boletin.implementacion = sfq`.
