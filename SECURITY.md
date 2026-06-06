## Seguridad del sistema (PHP/MySQL/Laravel/Livewire)

### Alcance

Este documento define un baseline de seguridad para todos los módulos del sistema.

### Controles implementados en módulos ABM actuales

- **ABM Legajos**: el listado y las operaciones por ID se limitan al `idNivel` del `schoolCtx()` (evita fuga de datos entre niveles).
- **ABM Niveles / Términos lectivos**:
  - Validación server-side.
  - Corrección de reglas `unique` al editar (evita colisiones o bypass por regla mal armada).
  - Rate limit en acciones `save` y `delete`.

### Reglas obligatorias para módulos futuros (resumen)

- Autenticación y manejo correcto de sesión.
- Autorización / scoping por contexto en consultas y acciones por ID.
- Validación + normalización.
- Escape en Blade, no renderizar HTML sin sanitizar.
- Nada de SQL crudo con input.
- Rate limit en ABM.
