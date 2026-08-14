# Módulo: C.U.S. e I.S.A. (autogestión familia)

## Propósito

Impresión en PDF del **Certificado Único de Salud (C.U.S.)** y del **Informe de Salud Anual (I.S.A.)** desde el Menú de Alumnos, con los datos del estudiante en sesión y el ciclo de autogestión (`ento.idTerlecVerNotas`).

Misma plantilla TCPDF que Secretaría (Certificados → C.U.S. / I.S.A. / Voz-Imagen), un alumno por documento.

## Modalidades / variantes

Activación por tenant. Defaults en `config/tenant.php` (ambos **deshabilitados**). Override en `config/tenants/{slug}.php`:

```php
'autogestion' => [
    'cus' => ['habilitado' => true],
    'isa' => ['habilitado' => true],
],
```

Opcional: `niveles_habilitados` / `niveles_deshabilitados` (IDs de `niveles`). Vacío = todos los niveles.

Hoy activo en **San José** (`config/tenants/sanjose.php`). Otros colegios no ven los ítems hasta que activen el flag.

Helpers: `tenantAutogestionCusHabilitada()`, `tenantAutogestionIsaHabilitada()`.

No ramificar por `tenantSlug() === 'sanjose'` en Blade ni en el controlador.

## Actores y permisos

- Auth guard `alumno`; middleware `student.context`.
- Datos filtrados por `studentCtx()` (legajo, nivel, ciclo). No hay permiso IA: el flag de tenant basta.
- No aplica bloqueo pedagógico/administrativo de ficha y datos personales: son formularios de salud para llevar al médico.

## Tablas y campos críticos

- `matricula` + `legajos` + `cursos` (curso `cursec`, DNI, domicilio, teléfonos de padres, sexo, fecha de nacimiento)
- `ento` (nombre institucional y logo del nivel)
- Plantillas: `public/img/certificados/cus.jpg`, `public/img/certificados/isa.jpg`

## Flujo principal

1. Familia abre **Imprimir C.U.S.** o **Imprimir I.S.A.** (sidebar o escritorio).
2. El PDF se abre en pestaña nueva (`alumnos.cus` / `alumnos.isa`).
3. Si no hay matrícula en el ciclo de autogestión: pantalla `errors.alumno-pdf`.

## Fuente de verdad

- Datos: `CusIsaVozImagenDatos::alumnoParaAutogestion()` (misma fila que el lote de Secretaría).
- PDF: `CertificadoUnicoSaludTcpdf` / `InformeSaludAnualTcpdf`.

## Archivos clave

- `app/Http/Controllers/Alumnos/CusIsaAutogestionPdfController.php`
- `app/Support/Certificados/CusIsaVozImagenDatos.php`
- `resources/views/layouts/alumno.blade.php`
- `app/Support/Alumnos/PortalFamiliaDashboard.php`
- `config/tenant.php` → `autogestion.cus` / `autogestion.isa`
- `config/tenants/sanjose.php`

## Qué no hacer / reglas de negocio

- No poner IDs de legajo ni matrícula en la URL (sesión + `studentCtx()`).
- No calcular calificaciones ni tocar otras tablas.
- No usar `schoolCtx()` / `schoolPdfHeaderData()` en este flujo: logo e institución van con `studentPdfHeaderData()`.
- No habilitar por defecto en `config/tenant.php`.

## Checklist al modificar

- [ ] Flag del tenant y helpers siguen alineados.
- [ ] El PDF de Secretaría (lote) no se rompe al tocar `CusIsaVozImagenDatos` / `CertificadoUnicoSaludTcpdf`.
- [ ] Rate-limit en el controlador de autogestión.
- [ ] Otro colegio no ve los ítems sin `habilitado => true` en su `config/tenants/{slug}.php`.
