# Identidad visual y sistema UI

La fuente operativa de verdad para la estetica del proyecto es:

- `.cursor/rules/ui-front-se.mdc`
- `resources/css/app.css`

Esta documentacion resume la identidad y debe mantenerse alineada con esa regla global.

---

## 1. Paleta institucional

Fuente visual: `public/img/PALETA COLORES LOGO.PNG`.

| Color | Hex | Uso |
| --- | --- | --- |
| Dark Cyan | `#40848D` | Primario, acciones, activos, links de enfasis |
| Jet | `#333333` | Texto fuerte, sidebar, bloques oscuros de contraste |
| Moonstone | `#739FA5` | Apoyos visuales y estados secundarios |
| Light Blue | `#C1D7DA` | Fondos suaves, bordes, separadores, hover |
| White | `#FFFFFF` | Superficies principales |

En Tailwind se usan las escalas definidas en `resources/css/app.css`:

- `primary-*` para Dark Cyan.
- `neutral-*` para Jet.
- `accent-*` para Light Blue.

---

## 2. Logos

Ubicacion actual: `public/img/`.

| Archivo | Uso recomendado |
| --- | --- |
| `1.png` | Icono compacto, sidebar colapsado |
| `2.png` | Variante clara del icono |
| `favicon-32.png` | Favicon de pestaña: círculo blanco, letras **SE** en gris oscuro (estilo SILAVET) |
| `icon-192.png` / `icon-512.png` / `apple-touch-icon.png` | Iconos PWA del mismo diseño |
| `3.png` | Logo principal/prominente para login, dashboard y fallback general |
| `4.png` | Variante horizontal para headers amplios |
| `5.png` | Variante compacta horizontal |

Regla de uso:

- Preferir `schoolLogoUrl()` cuando exista logo institucional dinamico.
- Usar `asset('img/3.png')` como fallback principal.
- El logo debe tener presencia real en login, dashboard y pantallas institucionales.
- **Pestañas del navegador:** favicon circular (blanco, borde fino, letras **SE** en `#333333`), vía `layouts/partials/favicon.blade.php` y `/favicon.ico`. Regenerar: `php tools/generate-pwa-icons.php`.

---

## 3. Componentes visuales actuales

Los componentes reutilizables viven en `resources/css/app.css`.

- Autenticacion: `se-auth-card`, `se-auth-label`, `se-auth-input`, `se-auth-select`, `se-auth-btn`.
- Dashboard: `se-dash-access`, `se-dash-access-icon`.
- Pantallas internas/ABM: `se-page`, `se-hero`, `se-hero-inner`, `se-card`, `se-toolbar`, `se-pill`, `se-icon-badge`.
- Formularios de tabs: `se-form-tabs`, `se-form-tab`, `se-form-tab-active`, `se-form-tab-idle`.
- Legajos: `se-legajo-form` suaviza campos largos con superficies, foco y radios amplios.
- Grillas legacy compactas: `gf-*`, solo cuando la pantalla realmente requiere formato tipo planilla.

---

## 4. Criterio general

- El sistema debe sentirse profesional, claro y operativo.
- Login y dashboard muestran la marca con mas presencia.
- Paginas internas priorizan lectura, busqueda, tablas y formularios ergonomicos.
- Los formularios no deben verse duros ni cuadriculados: usar campos redondeados, fondos suaves y `focus-within`.
- Las tablas deben ser limpias, con encabezados claros y bordes suaves.
- Modales: overlay neutral, contenedor blanco `rounded-2xl`, footer claro y acciones consistentes.
- Mobile siempre debe ser usable sin solapamientos.

Para nuevas pantallas o redisenos, seguir primero `.cursor/rules/ui-front-se.mdc`.
