# Módulo: Libre Deuda Familiar

## Propósito

Constancia PDF de que un estudiante **no registra cuotas con saldo** en el sistema de aranceles interno. Menú de Administración → Gestión de mora.

No usa Áulica. La constancia del portal familia contra Áulica sigue en [libre-deuda.md](libre-deuda.md).

El texto y la maqueta replican el FPDF legacy: membrete (institución, domicilio, CUIT, IVA e ingresos brutos), título **CONSTANCIA DE LIBRE DEUDA**, párrafo del estudiante, lugar y fecha, y firma del representante legal.

## Actores y permisos

- Menú de Administración, grupo **Gestión de aranceles**, ítem **Libre Deuda Familiar** (debajo de Aranceles por estudiante).
- Permiso IA **orden 111** (`PermisosIaCatalog::ADMIN_MORA_LIBRE_DEUDA_FAMILIAR`).
- El ítem no aparece si el usuario no tiene el permiso. El grupo de mora se muestra si tiene este ítem o cualquiera de los otros.
- Alcance: matrículas del ciclo de sesión y niveles pedagógicos de Administración (`SchoolAlcancePedagogico`).

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|-------|
| `matricula` / `legajos` / `cursos` | ciclo, nivel, apellido, nombre, DNI | Solo matriculados del ciclo activo. |
| `cuotasgeneradas` | `faltapa`, `importe`, `idLegajos` | Hay deuda si `faltapa > 0` e `importe > 0` (cualquier ciclo). |
| `ento` | `insti`, `direccion`, `cuit`, `condicionIva`, `ingresosBrutos`, `localidad`, `replegal`, `logo_path` | Membrete del **nivel de la matrícula** del estudiante. El logo del header es `logo_path` de ese nivel; si no hay archivo, el de otro nivel o `logo_login_path`. |

Firma escaneada, opcional y con el mismo nombre en todos los colegios: `public/img/firmaRepLegal.jpg`. Debajo de la firma va el nombre de `ento.replegal` (el del nivel de la matrícula o, si ese está vacío, el de otro nivel que lo tenga) y el cargo «Representante Legal».

## Flujo principal

1. Búsqueda por apellido, nombre, DNI, familia o responsable. Filtro de nivel. Casilla **Solo sin deuda**.
2. La columna Deuda muestra el total a pagar (saldo + interés al día), igual que Estado de Deuda por Estudiante.
3. **Emitir PDF** solo si el estudiante no tiene cuotas con saldo. Si tiene, la fila dice «Con deuda» y no hay enlace.
4. El PDF abre en otra pestaña, con `{ref}` opaco. El controlador vuelve a comprobar permiso, alcance y deuda.

## Fuente de verdad

Listado: matrícula del ciclo activo. Deuda: `cuotasgeneradas` del legajo. Textos institucionales: `ento` del nivel de esa matrícula. Fecha: hoy, `d/m/Y`.

## Archivos clave

- `app/Livewire/Mora/LibreDeudaFamiliarIndex.php`
- `resources/views/livewire/mora/libre-deuda-familiar-index.blade.php`
- `app/Support/Mora/LibreDeudaFamiliarDatos.php`
- `app/Support/Mora/LibreDeudaFamiliarTcpdf.php`
- `app/Http/Controllers/Mora/LibreDeudaFamiliarPdfController.php`
- Permiso: orden **111**

## Qué no hacer / reglas de negocio

- No emitir la constancia si hay cuotas con `faltapa > 0` e `importe > 0`.
- No consultar Áulica desde este módulo.
- No hardcodear domicilio, CUIT, localidad ni representante: salen de `ento`.
- No poner el id de legajo en la URL.
- PDF: TCPDF + Arial (`TcpdfFuenteArial`).

## Checklist al modificar

- [ ] Sin el permiso 111 el ítem no se ve y la ruta responde 403.
- [ ] Un estudiante con saldo no tiene botón y el PDF directo responde 403.
- [ ] El párrafo nombra apellido y nombre, DNI, curso y nivel; si el curso es `0`, no se imprime el tramo «de …».
- [ ] Fecha `d/m/Y`. Lugar = `ento.localidad` del nivel del estudiante.
