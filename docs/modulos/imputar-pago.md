# Módulo: Imputar pago

## Propósito

Registrar un pago (una o varias cuotas) sobre `cuotasgeneradas` del estudiante en contexto: medio de pago, saldo, interés/bonificación según la fórmula de `cuotasimportes`, fecha/hora y comprobante (recibo interno o AFIP según tenant).

## Modalidades / variantes

- **Una cuota** vs **varias** (grilla con saldo y % por fila).
- Interés de mora: `tenant.cuotas.interes_mora_modo` (`diario` / `total`) cuando `porcan` es `%`.
- Tipos `porcan`: `%` (porcentaje), `$` (pesos fijos), `p` (% mensual acumulado desde el 1.er venc.), `m` ($ mensual acumulado).
- CSC (y fórmulas tipo CSC): tramo 1 = `+ $ 0` (al día); tramos 2–4 = `+ p 10` (10 % mensual).
- Comprobante PDF post-imputación: una copia por hoja (default). **SFQ y EPQ** (`cuotas.comprobante_imputacion.dos_copias_por_hoja`): dos talonarios idénticos en la misma hoja A4, con espacios compactados, para cortar y entregar la mitad al pagador. Si el detalle de muchas cuotas no cabe en media hoja, se imprime una sola copia.

## Actores y permisos

Menú de Secretaría / Administración → Gestión de aranceles por estudiante. Gate: `PermisosCuotas::puedeArancelesPorEstudiante()`. Ruta `cuotas.cuota.imputar`.

## Tablas y campos críticos

| Tabla | Campos | Notas |
|-------|--------|-------|
| `cuotasgeneradas` | `faltapa`, `venc1`/`venc2`/`venc3`, `nueVenc`, `idCuotas`, `idCursos` | Tramos 1–3 = vencimientos originales. `nueVenc` = 4.º venc. (fórmula «después 3º»). |
| `cuotas` | `venc1` | Plantilla: 1.er vencimiento real si la generada tiene `venc1` = `nueVenc`. |
| `cuotasimportes` | `signoNv`, `valorNv`, `porcanNv` (N=1..4) | Fórmula por cuota+curso. `porcan` vacío se normaliza a `%`. |
| `cuotaspagos` | importe, interés, bonificación, fecha | Alta del pago. |

## Flujo principal

1. Abrir imputación desde el estudiante (cuotas en sesión).
2. Elegir medio de pago y fecha/hora. El % se sugiere con la fórmula del tramo de **hoy**.
3. Al cambiar fecha, saldo o %, se recalcula interés / a pagar.
4. Registrar: validación, rate-limit, persistencia y comprobante.

## Fuente de verdad

`ImputacionPagoCalculo` (misma lógica de recargo que el cupón, con override manual del %). Persistencia: `ImputacionPagoService`.

## Archivos clave

- `app/Livewire/Cuotas/ImputarPagoForm.php`
- `resources/views/livewire/cuotas/imputar-pago.blade.php`
- `app/Support/Cuotas/ImputacionPagoCalculo.php`
- `app/Support/Cuotas/ImputacionPagoService.php`
- `app/Support/Cuotas/ComprobantePagoImputacionTcpdf.php` — maquetación del comprobante (dos copias en SFQ/EPQ)
- `app/Http/Controllers/Cuotas/ComprobantePagoImputacionPdfController.php`

## Qué no hacer / reglas de negocio

- **`nueVenc` (vencimiento actualizado) es un 4.º vencimiento**, no el 1.º. Sirve cuando ya vencieron los tres originales, para que la familia pueda pagar. El interés de esa fecha es el de **DESPUÉS 3º VTO** (`signo4v` / `valor4v` / `porcan4v`, en CSC `+ p 10`).
- Si en `cuotasgeneradas` el campo `venc1` quedó copiado igual a `nueVenc` y es posterior a venc2/venc3, el cálculo debe tomar el 1.er vencimiento de la **plantilla** (`cuotas.venc1`), no el actualizado.
- No tratar el número del campo **% INTERÉS** como pesos cuando el tramo actual es `$ 0` (al día) y el usuario trae un % de mora (p. ej. 10 → debe ser 10 % del saldo, no $ 10).
- No calcular el % de `p`/`m` sin los meses desde el **1.er vencimiento real** (`mesesMoraAcumuladaDesdeVenc1`).
- URLs de comprobante con `{ref}` opaco.

## Checklist al modificar

- [ ] Recargo `%` / `p` / `$` / `m` cubierto por tests en `ImputacionPagoCalculoTest`.
- [ ] `nueVenc` no se usa como 1.er vencimiento; «después 3º» aplica `p`/`%`/`$` del tramo 4.
- [ ] Cambio de fecha de pago no convierte un % de mora en pesos.
- [ ] Filtro por `schoolCtx` / legajo de sesión; rate-limit al guardar.
- [ ] SFQ/EPQ: dos copias del comprobante en la misma hoja A4 (`ComprobantePagoImputacionTcpdf`).
