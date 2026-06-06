# Modelo de Datos — Etapa 1 (Núcleo)

> Referencia completa del esquema: `schema.sql` en la raíz del proyecto.
> Datos de ejemplo: `bd_con_datos.sql`.

---

## 1. Tablas de Parametrización

| Tabla      | Descripción                                                         | PK           |
|------------|---------------------------------------------------------------------|--------------|
| `terlec`   | Ciclos lectivos (años) disponibles en el sistema                    | `id`         |
| `niveles`  | Niveles del colegio: Inicial, Primario, Secundario, Administración  | `id`         |
| `planes`   | Planes de estudio por nivel                                         | `id`         |
| `curplan`  | Cursos modelo por nivel (plantilla para crear cursos reales)        | `id`         |
| `matplan`  | Materias modelo por curso modelo (plantilla)                        | `id`         |
| `ento`     | Entorno del sistema — un registro por nivel (ver sección 3)         | `idNivel`    |

### Relaciones de parametrización

```
niveles ─1:N─► planes ─1:N─► curplan ─1:N─► matplan
niveles ─1:1─► ento
terlec (independiente, referenciada por ento y matricula)
```

---

## 2. Tablas de Alumnos

| Tabla            | Descripción                                                        | PK      |
|------------------|--------------------------------------------------------------------|---------|
| `legajos`        | Datos personales de cada alumno                                    | `id`    |
| `matricula`      | Un registro por alumno por año cursado                             | `id`    |
| `calificaciones` | Un registro por alumno × materia. Campos multipropósito por nivel  | `id`    |

### Relaciones de alumnos

```
legajos ─1:N─► matricula (un registro por año cursado)
matricula ─1:N─► calificaciones (una por materia cursada)
matricula ──► terlec (ciclo lectivo)
matricula ──► niveles (nivel)
```

---

## 3. Tabla `ento` — Entorno del Sistema

La tabla `ento` almacena la configuración institucional, con **un registro por nivel**
(FK → `niveles.id`).

### Campos clave

| Campo              | Tipo     | Descripción                                              |
|--------------------|----------|----------------------------------------------------------|
| `idNivel`          | int (FK) | Nivel al que pertenece este registro de entorno          |
| (institucionales)  | varios   | Nombre del colegio, dirección, CUIT, etc.                |
| `idTerlecVerNotas` | int (FK) | **Campo crítico**: ciclo lectivo activo para autogestión  |

### Comportamiento de `idTerlecVerNotas`

- Determina qué ciclo lectivo se muestra en las **plataformas de autogestión** 
  (tanto de profesores como de alumnos).
- Toda la información de autogestión se filtra por este campo,
  **independientemente** del ciclo lectivo seleccionado en el login de gestión.
- Es el mecanismo para que la institución controle qué año ven los usuarios 
  de autogestión.

---

## 4. Tablas de Autenticación y Permisos

| Tabla               | Descripción                                                  |
|---------------------|--------------------------------------------------------------|
| `profesores`        | Usuarios del sistema de gestión (profesores, secretarios, admin) |
| `permisosusuarios`  | Catálogo de permisos con campo `orden`                       |

### Detalle en [03-autenticacion-y-permisos.md](03-autenticacion-y-permisos.md)

---

## 5. Convenciones de Eloquent para tablas legacy

Los modelos Eloquent deben respetar la estructura existente:

```php
// Ejemplo: modelo para tabla legacy
class Legajo extends Model
{
    protected $table = 'legajos';   // nombre explícito
    public $timestamps = false;     // sin created_at/updated_at
    protected $primaryKey = 'id';   // confirmar PK real

    protected $fillable = [
        // listar campos explícitamente — NO usar $guarded = []
    ];
}
```

- **No usar** convenciones de timestamps automáticos.
- **No usar** pluralización automática — definir `$table` explícitamente.
- **Definir `$fillable`** explícitamente en cada modelo (seguridad mass-assignment).
