# Directiva de Afiliación Obligatoria y Aislamiento de Información (EDUX)

Este documento detalla la implementación y resolución de las dos directivas críticas de arquitectura para el sistema **EDUX**:
1. **Afiliación institucional y de programa obligatoria con facultades globales para el SuperAdmin.**
2. **Aislamiento en profundidad (Defense-in-Depth): Scopes de Eloquent y PostgreSQL Row-Level Security (RLS) como mecanismos complementarios.**

---

## 1. Relación Obligatoria de Usuarios, Instituciones y Programas

### 1.1 Regla de Negocio e Integridad Referencial
En EDUX, ningún usuario puede existir huérfano en la base de datos:
- `usuarios.institucion_id`: Clave foránea obligatoria hacia la tabla `instituciones`.
- `usuarios.unidad_academica_id`: Clave foránea que asocia al usuario con un programa de estudios (o unidad académica base).

### 1.2 Representación del SuperAdministrador Global
Para evitar romper la integridad referencial y las claves foráneas, el **SUPERADMIN** no se modela con valores nulos:
1. **Afiliación Base**: Se afilia a la institución matriz / sede central y a la unidad de administración general.
2. **Autorizaciones Globales Desacopladas**:
   - En la capa de aplicación, `InstitutionScope` detecta `$user->hasRole('SUPERADMIN')` y no aplica la restricción `WHERE institucion_id = ?`, permitiéndole consultar, auditar y operar en cualquier institución del SaaS.
   - En `EnsureInstitution`, se verifica que el usuario cuente con su afiliación registrada.
   - Si un usuario común carece de `institucion_id`, la solicitud es rechazada de inmediato con código HTTP `403 Forbidden` (`no_institution`).

```
┌────────────────────────────────────────────────────────┐
│                      SUPERADMIN                        │
│  - institucion_id: 1 (Sede Matriz / Central)           │
│  - unidad_academica_id: 1 (Dirección General)          │
│  - Roles / Permisos: SUPERADMIN (Global)               │
└──────────────────────────┬─────────────────────────────┘
                           │
       ┌───────────────────┴───────────────────┐
       ▼                                       ▼
┌───────────────┐                       ┌───────────────┐
│ Institución A │                       │ Institución B │
│  (Sede Lima)  │                       │ (Sede Arequ.) │
└───────────────┘                       └───────────────┘
```

---

## 2. Aislamiento de Información en Profundidad (Defense-in-Depth)

Los filtros de Eloquent por `institucion_id` son una capa de aplicación muy conveniente, pero **no equivalen a PostgreSQL Row-Level Security (RLS)**. En EDUX se implementan como **mecanismos complementarios**.

### 2.1 Comparación Arquitectónica

| Característica | Capa 1: Eloquent Scopes (Aplicación) | Capa 2: PostgreSQL RLS (Motor DB) |
|---|---|---|
| **Punto de Ejecución** | Framework Laravel / PHP antes de enviar la consulta SQL | Motor relacional PostgreSQL 16 al evaluar cada fila física |
| **Implementación** | `InstitutionScope`, `ProgramaScope` | `ALTER TABLE ... ENABLE ROW LEVEL SECURITY; CREATE POLICY ...` |
| **Protección contra Bypass** | Protege llamadas estándar de Eloquent; vulnerable si se invoca `withoutGlobalScopes()` indebidamente | Protege consultas directas, SQL crudo (`DB::select`) y accesos de consola |
| **Variables de Contexto** | Obtenidas del token de sesión (`auth()->user()->institucion_id`) | Inyectadas vía `SET LOCAL app.current_tenant_id` y `app.is_superadmin` |

### 2.2 Flujo de Ejecución Complementaria

```text
 [ Solicitud HTTP ]
         │
         ▼
 [ Middleware: EnsureInstitution ]
         │ 1. Valida institucion_id obligatoria
         │ 2. Ejecuta: SET LOCAL app.current_tenant_id = ?
         │             SET LOCAL app.is_superadmin = ?
         │             SET LOCAL app.current_programa_id = ?
         ▼
 [ Eloquent ORM: InstitutionScope & ProgramaScope ]
         │ Inyecta cláusulas:
         │ WHERE institucion_id = ?
         │ WHERE unidad_academica_id = ? (para COORDINADOR)
         ▼
 [ PostgreSQL 16 Engine ]
         │ Evalúa Políticas RLS por fila:
         │ USING (app.is_superadmin = 'true' OR institucion_id = app.current_tenant_id)
         ▼
 [ Resultado Seguro ]
```

---

## 3. Pruebas de Seguridad Automatizadas

Las suites de pruebas en `backend/tests/Feature/` verifican formalmente el aislamiento:

1. **`MultiInstitutionIsolationTest.php`**:
   - `test_admin_A_no_puede_ver_periodos_de_institucion_B()`
   - `test_admin_A_no_puede_ver_usuarios_de_institucion_B()`
   - `test_admin_A_no_puede_modificar_usuario_de_institucion_B()`
   - `test_header_x_institution_id_no_permite_cruzar_instituciones()` (prueba de anti-spoofing)
   - `test_superadmin_tiene_afiliacion_obligatoria_y_administra_globalmente()`
   - `test_usuario_sin_institucion_es_bloqueado_estrictamente()`

2. **`ProgramaIsolationTest.php`**:
   - Aislamiento estricto de coordinadores: el coordinador de Enfermería Técnica no puede ver ni modificar grupos, asignaciones ni estudiantes de Diseño y Programación Web.
