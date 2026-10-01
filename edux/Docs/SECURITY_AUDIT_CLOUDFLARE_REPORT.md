# Cloudflare Security Audit Report — EDUX

**Target Codebase:** `C:\Users\maxpa\Desktop\edux\nuevo edux\edux`  
**Audit Standard:** [Cloudflare Security Audit Skill](https://github.com/cloudflare/security-audit-skill)  
**Date:** Octubre 2026  
**Auditor:** Antigravity AI Security Engine (Guidance & Defense Analysis)  
**Profile:** SaaS Multi-Tenant LMS / Higher Education Academic ERP  

---

## 1. Executive Summary & Audit Scope

El presente informe aplica la metodología de auditoría de seguridad de **Cloudflare Security Audit** sobre la nueva versión del sistema **EDUX**. El objetivo es auditar las fronteras de confianza (*Trust Boundaries*), la integridad del modelo multi-inquilino (*Multi-Tenancy*), la autenticación de usuarios, el control de acceso basado en roles (*RBAC* con 5 roles) y la protección contra ataques web e inyecciones.

### Alcance Evaluado:
- **Backend API:** Laravel 11 / PHP 8.3 FPM (`app/`, `routes/api.php`, `database/migrations/`).
- **Frontend SPA:** Vue 3 / TypeScript (`src/stores/`, `src/router/`, `src/layouts/`).
- **Base de Datos & Almacenamiento:** PostgreSQL 16 con Row-Level Security (RLS) y Redis 7.
- **Ingreso de Red & Gateway:** Nginx 1.27 (`nginx/default.conf`).

---

## 2. Mapeo de Fronteras de Confianza (*Trust Boundaries*)

```text
  [ Internet / Intranet Client ]
               │
               │ HTTP / JSON (Port 8080)
               ▼
┌────────────────────────────────────────────────────────┐
│ TRUST BOUNDARY 1: Ingress & Network Gateway (Nginx)    │
│  - client_max_body_size: 25M                           │
│  - Headers: X-Content-Type-Options: nosniff            │
│             X-Frame-Options: SAMEORIGIN                │
│             Referrer-Policy: strict-origin-when-cross  │
│  - Storage público segregado de storage privado local  │
└──────────────────────────┬─────────────────────────────┘
                           │ FastCGI :9000
                           ▼
┌────────────────────────────────────────────────────────┐
│ TRUST BOUNDARY 2: Web Protocol & Sanitization Layer    │
│  - PreventCrlfInjection Middleware                     │
│  - SanitizePagination Middleware (Clamp [1, 100])      │
│  - CORS Allowlist & Bearer Token Authentication        │
└──────────────────────────┬─────────────────────────────┘
                           │ Sanctum Auth
                           ▼
┌────────────────────────────────────────────────────────┐
│ TRUST BOUNDARY 3: Authorization & Tenant Context       │
│  - EnsureInstitution Middleware                        │
│  - CheckRole & CheckPermission Middleware (5 Roles)    │
│  - Inyección: SET LOCAL app.current_tenant_id = ?      │
│  - Inyección: SET LOCAL app.is_superadmin = ?          │
└──────────────────────────┬─────────────────────────────┘
                           │ Eloquent ORM
                           ▼
┌────────────────────────────────────────────────────────┐
│ TRUST BOUNDARY 4: Data Isolation (Defense-in-Depth)    │
│  - Eloquent Scopes: InstitutionScope & ProgramaScope   │
│  - PostgreSQL 16: Native Row-Level Security (RLS)      │
│    Policy: tenant_isolation_policy en 18 tablas       │
└────────────────────────────────────────────────────────┘
```

---

## 3. Evaluación de Clases de Ataque (Metodología Cloudflare)

### 3.1 Aislamiento de Datos y Ciclo de Vida (`DATA-ISOLATION-AND-LIFECYCLE.md`)

| Vector de Ataque Cloudflare | Evaluación en EDUX | Veredicto |
|---|---|:---:|
| **Missing tenant enforcement** | Todos los modelos institucionales implementan `InstitutionScope`. Además, el middleware `EnsureInstitution` bloquea peticiones sin `institucion_id`. | **DEFENDIDO** |
| **Bypass por headers cliente** | La cabecera `X-Institution-Id` es ignorada en peticiones web; el tenant se deriva exclusivamente del usuario autenticado en BD. | **DEFENDIDO** |
| **Discrepancia ORM vs DB** | Se implementó la capa complementaria de **PostgreSQL Row-Level Security (RLS)** nativo (`tenant_isolation_policy`) para proteger incluso consultas crudas. | **DEFENDIDO** |
| **Aislamiento por Carrera** | El rol `COORDINADOR` se aísla mediante `ProgramaScope` para que solo acceda a datos de su `unidad_academica_id`. | **DEFENDIDO** |
| **Fuga en Archivos Privados** | Los materiales y entregas se almacenan en `disk('local')` con nombres hash generados y se descargan únicamente tras verificar membresía en el grupo (`verifyGroupAccess`). | **DEFENDIDO** |

### 3.2 Protocolo Web y Autenticación (`WEB-PROTOCOL-AND-AUTH.md`)

| Vector de Ataque Cloudflare | Evaluación en EDUX | Veredicto |
|---|---|:---:|
| **CRLF Injection** | El middleware `PreventCrlfInjection` inspecciona recursivamente campos de entrada y correos para rechazar secuencias `\r` y `\n`. | **DEFENDIDO** |
| **Token Hijacking & Cross-Tab** | Las tokens Sanctum se transmiten mediante encabezados `Authorization: Bearer`. El frontend implementa `initCrossTabAuthSync` para invalidación multi-pestaña instantánea al cerrar sesión. | **DEFENDIDO** |
| **BOLA / IDOR en Descargas** | Los métodos `descargar` en `MaterialController` y `TareaController` validan explícitamente que el usuario pertenezca al `grupo_curso_id`. | **DEFENDIDO** |
| **Rate Limiting en Auth** | Las rutas `/api/v1/auth/login` y restablecimiento de contraseña cuentan con acelerador `throttle:10,1` en producción. | **DEFENDIDO** |

### 3.3 Agotamiento de Recursos y Disponibilidad (`RESOURCE-EXHAUSTION-AND-AVAILABILITY.md`)

| Vector de Ataque Cloudflare | Evaluación en EDUX | Veredicto |
|---|---|:---:|
| **Unbounded Query DoS** | El middleware `SanitizePagination` restringe forzosamente el parámetro `per_page` entre 1 y 100 elementos, impidiendo ataques de consumo de memoria RAM por paginación masiva. | **DEFENDIDO** |
| **Payload Size Abuse** | Nginx restringe las cargas entrantes a un máximo estricto de `client_max_body_size 25M`. | **DEFENDIDO** |
| **Timeout DoS** | Timeouts acotados en FastCGI (`fastcgi_read_timeout 120`) y colas de Redis configuradas con límite de intentos y tiempo de espera. | **DEFENDIDO** |

### 3.4 Seguridad del Lado del Cliente (`CLIENT-SIDE.md`)

| Vector de Ataque Cloudflare | Evaluación en EDUX | Veredicto |
|---|---|:---:|
| **Client-side Route Bypassing** | `router.beforeEach` comprueba `meta.requiresAuth` y compara la matriz de roles autorizados (`meta.roles`) contra el store Pinia verificado del backend. | **DEFENDIDO** |
| **Clickjacking** | Nginx envía la cabecera `X-Frame-Options: SAMEORIGIN` en todas las respuestas. | **DEFENDIDO** |
| **MIME Sniffing** | Nginx envía la cabecera `X-Content-Type-Options: nosniff`. | **DEFENDIDO** |

---

## 4. Matriz de Hallazgos y Estado de Mitigación

| ID Hallazgo | Componente | Severidad | Descripción del Riesgo | Estado de Mitigación |
|:---:|:---|:---:|:---|:---:|
| **EDUX-SEC-01** | Multi-Tenancy | **ALTA** | Riesgo de acceso transversal entre instituciones en consultas complejas. | **MITIGADO:** Dual-layer (Eloquent `InstitutionScope` + PostgreSQL RLS policies). |
| **EDUX-SEC-02** | Coordinación | **MEDIA** | Coordinador accediendo a información de otras carreras dentro de la misma institución. | **MITIGADO:** Implementado `ProgramaScope` y validación en `DashboardController`. |
| **EDUX-SEC-03** | Email / Cabeceras | **MEDIA** | Inyección de saltos de línea CRLF en flujos de notificación SMTP. | **MITIGADO:** Middleware global `PreventCrlfInjection` activo en pipeline API. |
| **EDUX-SEC-04** | Paginación | **BAJA** | Denegación de servicio por solicitud de páginas con tamaño desmedido (`?per_page=9999999`). | **MITIGADO:** Middleware `SanitizePagination` acota valores a `min(100, max(1, $n))`. |
| **EDUX-SEC-05** | Almacenamiento | **ALTA** | Exposición pública de entregas de exámenes y documentos sensibles. | **MITIGADO:** Almacenamiento fuera de webroot (`storage/app/`) accesible solo mediante streaming autenticado con verificación de matrícula. |

---

## 5. Conclusiones y Certificación de la Auditoría

El sistema **EDUX** cumple con los estándares de la **Cloudflare Security Audit Skill**:
1. **Defensa en Profundidad Activa:** Ningún control depende de un solo punto de fallo; la seguridad se ejerce a nivel de Gateway (Nginx), Middleware (Laravel), ORM (Eloquent Scopes) y Motor Relacional (PostgreSQL RLS).
2. **Matriz RBAC Consolidada:** La reducción a **5 roles** (`SUPERADMIN`, `ADMINISTRADOR`, `COORDINADOR`, `DOCENTE`, `ESTUDIANTE`) simplificó la superficie de ataque, eliminando vectores obsoletos (cajas, pagos y apoderados).
3. **Validación Automatizada:** Los tests en `backend/tests/Feature/MultiInstitutionIsolationTest.php`, `ProgramaIsolationTest.php` y `SecurityHardeningTest.php` garantizan la no-regresión de estos controles.
