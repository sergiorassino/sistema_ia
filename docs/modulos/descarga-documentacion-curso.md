# Módulo: Descarga de documentación por curso

## Propósito

Armar un ZIP con la documentación ya cargada de los estudiantes regulares de un curso.

## Actores y permisos

Menú de Secretaría y Menú de Administración, grupo **Estudiantes**.

Permiso `permisos_ia` orden **117** (`PermisosIaCatalog::DESCARGA_DOCUMENTACION_CURSO`).
SQL: `database/sql/permiso_ia_orden_117_descarga_documentacion_curso.sql`.
El alta del catálogo no concede el permiso: hay que activarlo en Permisos por usuario.

## Flujo principal

1. Elegir el curso del ciclo y del alcance de `schoolCtx()` (en Administración, los niveles pedagógicos del ciclo).
2. La pantalla lista solo estudiantes regulares que tienen al menos un archivo.
3. **Descargar ZIP**. La URL lleva un token opaco (`OpaqueRouteToken`), sin id de curso.

Estructura del ZIP:

```text
{nombre del curso}/
  {apellido} {nombre} {dni}/
    {etiqueta del tipo}.jpg
    {etiqueta del tipo} 2.pdf
```

- El nombre del curso es `cursos.cursec` (o el nombre de listado si `cursec` está vacío).
- El DNI de la carpeta son solo los dígitos.
- El archivo usa la etiqueta del tipo activo. Si hay más de una parte, la segunda lleva ` 2`.
- No se crean carpetas vacías. Quien no tiene archivos no entra al ZIP.

## Tablas y archivos

- Estudiantes: `matricula` regular (`idCondiciones` 1, sin `fechaBaja`) del curso, ciclo y nivel.
- Tipos: `doc_estudiante_tipos` activos (los mismos de Documentos a subir / solapa DOCUMENTACIÓN).
- Disco `privado`: `ento/doc-estudiante/{tenant}/`.

No incluye la foto carnet.

## Archivos clave

- `app/Livewire/Listados/DescargaDocumentacionCurso.php`
- `app/Support/Alumnos/DescargaDocumentacionCurso.php`
- `app/Http/Controllers/DescargaDocumentacionCursoZipController.php`
- `app/Support/Alumnos/DocumentosEstudianteAutogestion.php`

## Qué no hacer

- No poner el id del curso, el DNI ni el id de legajo en la URL de descarga.
- No incluir alumnos salidos ni cursos de otro nivel o ciclo.
- No meter archivos cuya ruta real quede fuera de `ento/doc-estudiante/{tenant}/`.
- No calcular promedios ni tocar calificaciones.
