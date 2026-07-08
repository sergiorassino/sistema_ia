# Sistemas Escolares — Sistema de Gestión Pedagógica

Sistema de información para la gestión pedagógica y de cuotas de escuelas
de nivel inicial, primario y secundario.

## Stack

- **Backend:** PHP 8.2+ · Laravel 11 · Livewire 4
- **Frontend:** Blade · Tailwind CSS 4 · Vite 5
- **Base de datos:** MySQL (legacy, existente)
- **Servidor local:** WAMP 64-bit

## Estructura del proyecto

```
sistemaPrueba/
├── SE/                    # Logos y paleta de colores
├── schema.sql             # Esquema completo de la BD
├── bd_con_datos.sql       # Datos de ejemplo
└── sistema/               # Proyecto Laravel 11
    ├── app/               # Lógica de aplicación
    ├── docs/              # Documentación del proyecto
    ├── resources/         # Vistas Blade + assets
    └── routes/            # Definición de rutas
```

## Documentación

Toda la documentación del proyecto está en `sistema/docs/`:

| #  | Archivo                              | Contenido                                  |
|----|--------------------------------------|--------------------------------------------|
| 01 | `01-descripcion-general.md`          | Visión general, stack, estructura          |
| 02 | `02-modelo-de-datos.md`              | Tablas del núcleo, relaciones, `ento`      |
| 03 | `03-autenticacion-y-permisos.md`     | Dos logins, passwords, permisos            |
| 04 | `04-identidad-visual.md`             | Paleta de colores, logos, design system    |
| 05 | `05-preferencias-y-convenciones.md`  | Convenciones de código, preferencias       |
| 06 | `06-reglas-de-seguridad.md`          | Baseline de seguridad obligatorio          |

## Flujo de ramas Git

| Rama | Uso |
|------|-----|
| **`sergio`** | Desarrollo diario (rama activa por defecto) |
| **`main`** | Resguardo en GitHub; refleja lo estable de `sergio` |

Ramas retiradas: `desarrolloSergioConTenant`, `ramaMain`.

**Trabajo habitual** (siempre en `sergio`):

```bash
git checkout sergio
git pull
# ... commits ...
git push
```

**Resguardo en `main`** (cuando querés dejar copia estable en GitHub):

```bash
git checkout main
git pull
git merge sergio
git push origin main
git checkout sergio
```

## Asistentes de código (Cursor, Copilot, etc.)

Políticas **versionadas** en el repo (incluye no ejecutar escrituras en la BD desde el agente): ver **`AGENTS.md`** en esta carpeta.

## Setup local

```bash
# En sistema/
composer install
npm install
cp .env.example .env
php artisan key:generate

# Configurar BD en .env (apuntar a MySQL existente)
# Ejecutar schema.sql y bd_con_datos.sql en MySQL

# Desarrollo local (puertos por defecto; SILAVET usa 8001 / 5174 en la misma PC)
# Laravel: http://127.0.0.1:8000  ·  Vite: http://127.0.0.1:5173
npm run dev:all

# Alternativa: dos terminales
# php artisan serve --host=127.0.0.1 --port=8000
# npm run dev

# Atajo en Cursor: Terminal → Run Task → Dev: Laravel + Vite
```
