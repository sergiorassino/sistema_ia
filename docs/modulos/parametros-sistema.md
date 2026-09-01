# Módulo: Parámetros del sistema

## Propósito

Configurar datos institucionales, bloqueos operativos y correo de envío del **nivel activo** (fila de `ento` según `schoolCtx()->idNivel`).

## Modalidades / variantes

Por tenant: SIRO y facturación AFIP se muestran si `config('tenant.cuotas…')` lo habilita. El logo de login (`logo_login_path`) se replica en todas las filas de `ento`; el resto es por nivel.

## Actores y permisos

Menú de Secretaría. Permiso de configuración `PermisosConfiguracion::PARAMETROS_SISTEMA` (orden 31). Ruta autenticada.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|--------|
| `ento` | `insti`, CUE, dirección, logos, SIRO, AFIP | Una fila por `idNivel`. |
| `ento` | `cargaNotasOff`, `verNotasOff`, `verBimesOff`, `imprBoleOff` | Flags 1/0 del **nivel activo**, no de toda la escuela. |
| `ento` | `verDatosFicha`, `mensajeBloqPeda`, `mensajeBloqAdmi` | Autogestión familia. |
| `ento` | `cuitFact`, `PtoVta`, certificados AFIP | Emisor de comprobantes. Vacío no debe impedir guardar otros parámetros. |
| `ento` | `ctaEnvioMail`, `passEnvioMail` | Solapa Correo institucional (guardado aparte). |

## Flujo principal

1. Solapa **Datos de la institución**: nombre, domicilio, logos, AFIP/SIRO si aplica.
2. Solapa **Parámetros**: ciclo de autogestión y bloqueos. La solapa permanece en el DOM (oculta) para que los checkboxes lleguen al Guardar.
3. **Guardar** persiste la fila de `ento` del nivel activo y verifica columnas con `PersistenciaColumnas`.
4. Solapa **Correo institucional**: `saveMailConfig()` independiente.

## Fuente de verdad

`ento` del `idNivel` de contexto. Los bloqueos de notas se leen con `EntoCargaNotas` / `EntoVerNotasOff` de esa misma fila.

## Archivos clave

- `app/Livewire/Parametrizacion/ParametrosSistemaForm.php`
- `resources/views/livewire/parametrizacion/parametros-sistema-form.blade.php`
- `app/Models/Ento.php`
- `app/Support/Database/PersistenciaColumnas.php`

## Qué no hacer / reglas de negocio

- No exigir `cuitFact` para guardar bloqueos u otros campos: en varios colegios solo Administración tiene CUIT de facturación; inicial/primario/secundario lo tienen vacío.
- No tratar `UPDATE` con 0 filas afectadas como “no existe el registro”: si los flags no cambiaron, MySQL no cuenta filas.
- No destruir la solapa Parámetros con `@elseif`: Livewire no envía los checkboxes al Guardar.
- Errores de guardado: SweetAlert y cambiar a la solapa del campo. No dejar el error solo en un input de otra solapa.

## Checklist al modificar

- [ ] Guardar con AFIP habilitado y `cuitFact` vacío (niveles pedagógicos) sigue persistiendo flags.
- [ ] Toggle de `cargaNotasOff` / `verNotasOff` desde la solapa Parámetros queda en `ento` al recargar.
- [ ] Si falla validación o persistencia, hay mensaje visible (`se-swal-error`).
