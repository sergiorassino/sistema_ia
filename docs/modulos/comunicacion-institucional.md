# Módulo: Comunicación institucional

## Propósito

Bandeja de comunicados entre personal y familias (y entre roles de personal), con canales por nivel, medios (push / email / WhatsApp) y preferencias. **Grupos de destinatarios:** cada usuario de personal arma listas propias con nombre, para no volver a elegir uno por uno al enviar.

## Modalidades / variantes

| Superficie | Cómo se habilita | Qué hace |
|------------|------------------|----------|
| **Menú de Secretaría / Administración** | Permiso IA **3** bandeja; **4** nuevo comunicado y grupos | Mismos Livewire que docentes |
| **Menú de Docentes** | Portal docente (sin permiso 4 extra) | Bandeja, nuevo, **Mis grupos** |
| **Menú de Alumnos** | Autogestión familia | Bandeja y nuevo hacia personal; **sin grupos** (un destinatario por envío) |

## Actores y permisos

| Rol | Permiso / acceso | Alcance |
|-----|------------------|---------|
| Secretaría / Administración | `permiso:4` para nuevo y grupos (Livewire exige también 3) | Grupos del `id_profesor` + `id_nivel` de sesión |
| Portal docente | `ComunicacionesRutasGestion::accesoMisGrupos()` | Igual: dueño + nivel activo |
| Familia | No administra grupos | — |

Los grupos **no se comparten**. Si la misma persona tiene usuario en más de un nivel, crea (y usa) grupos en cada nivel.

## Tablas y campos críticos

Tablas nuevas (prefijo `com_`):

| Tabla | Campos | Notas |
|-------|--------|--------|
| `com_grupos` | `nombre`, `id_profesor`, `id_nivel`, `tipo_destinatario` | Único `(id_profesor, id_nivel, nombre)`. `tipo_destinatario` queda en `mixto` (estudiantes y personal en el mismo grupo) |
| `com_grupos_miembros` | `tipo_miembro`, `id_legajo` / `id_profesor`, `nombre_snapshot` | `legajo` o `profesor`; FK a `com_grupos` con cascade |

Al enviar, el grupo **se expande** a destinatarios individuales (familias y/o personal en el mismo hilo). No hay scope nuevo en `com_hilos`. Solo se envían miembros aún matriculados (estudiantes) o vigentes en el nivel (personal) y a los que el emisor **puede iniciar** según canales. El remitente no se incluye.

SQL: `database/sql/create_com_grupos.sql`.

## Flujo principal

1. **Mis grupos:** alta/edición (nombre + integrantes mixtos: estudiantes y personal del nivel) y baja con SweetAlert.
2. **Nuevo comunicado → Mis grupos:** opción de destinatario independiente; se eligen uno o varios grupos y el envío llega a todos los integrantes vigentes.
3. **Nuevo comunicado → Familias / un rol de personal:** sigue la selección individual (alumnos, cursos, colegio o personas de ese rol).

## Fuente de verdad

| Dato | Quién escribe | Quién solo lee |
|------|---------------|----------------|
| Grupos e integrantes | Dueño (`id_profesor` + `id_nivel`) en `MisGruposIndex` | Expansión en `NuevoComunicado` al enviar |
| Hilo / destinatarios del mensaje | `ComunicacionesRepository::crearHiloConMensaje` | Bandejas / informe de envío |

## Archivos clave

| Pieza | Ruta |
|-------|------|
| Livewire grupos | `app/Livewire/Comunicaciones/MisGruposIndex.php` |
| Vista grupos | `resources/views/comunicaciones/livewire/comunicaciones/mis-grupos-index.blade.php` |
| Repositorio | `app/Comunicaciones/ComGruposRepository.php` |
| Envío | `app/Livewire/Comunicaciones/NuevoComunicado.php` |
| Rutas | `comunicaciones.grupos` / `portalDocente.comunicaciones.grupos` |

## Qué no hacer / reglas de negocio

1. No listar ni editar grupos de otro usuario ni de otro `id_nivel`.
2. Un grupo puede mezclar estudiantes y personal de distintos roles.
3. No persistir un envío “al grupo”: siempre destinatarios concretos, revalidados al enviar.
4. No usar grupos en el portal familia.
5. No mostrar éxito si faltan las tablas `com_grupos` / `com_grupos_miembros`.
6. En `APP_ENV=local` el correo institucional **no sale por SMTP** (queda en `storage/logs`). Ver `docs/05-preferencias-y-convenciones.md` §15.

## Checklist al modificar

- [ ] Alcance por `id_profesor` + `id_nivel` en consultas y por ID.
- [ ] Tipo de grupo mixto: integrantes con `tipo` `legajo` o `profesor`.
- [ ] Confirmaciones con `seSwalConfirmar` / eventos `se-swal-*`.
- [ ] Paginación `se-compact` en el listado.
- [ ] Menú: ítem **Mis grupos** activo sin marcar la bandeja.
