# Aula Virtual — Sistema de Gestión Académica

Sistema de Aula Virtual pedagógico con **5 roles** construido con Laravel 11, Vue 3 + TypeScript, PostgreSQL 16 y Docker.

## Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| Backend API | Laravel 11 / PHP 8.3 FPM |
| Frontend SPA | Vue 3 + Vite + TypeScript |
| Base de Datos | PostgreSQL 16 |
| Caché / Colas | Redis 7 |
| Servidor Web | Nginx 1.27 |
| Contenedores | Docker Compose v2 |

## Roles del Sistema

| # | Rol | Código | Alcance |
|---|-----|--------|---------|
| 1 | Super Administrador | `SUPERADMIN` | Acceso global total. Gestión multi-institución. |
| 2 | Administrador | `ADMINISTRADOR` | Control integral de su institución. |
| 3 | Coordinador | `COORDINADOR` | Gestión de su programa de estudios. |
| 4 | Docente | `DOCENTE` | Sus grupos asignados y aula virtual. |
| 5 | Estudiante | `ESTUDIANTE` | Sus cursos, tareas, evaluaciones y notas. |

## Instalación Rápida

### Prerrequisitos
- Docker Engine 24+
- Docker Compose v2

### Pasos

```bash
# 1. Clonar / posicionarse en el directorio
cd "nuevo edux/edux"

# 2. Copiar variables de entorno
cp .env.example backend/.env

# 3. Generar clave de aplicación
docker compose run --rm backend php artisan key:generate

# 4. Levantar servicios
docker compose up -d

# 5. Ejecutar migraciones y seeders
docker compose exec backend php artisan migrate --seed

# 6. Acceder
# - Aplicación: http://localhost:8080
# - Mailpit:    http://localhost:8025
```

## Credenciales Demo

| Rol | Email | Contraseña |
|-----|-------|-----------|
| SUPERADMIN | `superadmin@aula.demo` | `SuperAdmin2026!` |
| ADMINISTRADOR | `admin@aula.demo` | `Admin2026!` |
| COORDINADOR | `coord.enf@aula.demo` | `Coord2026!` |
| DOCENTE | `docente01@aula.demo` | `Docente2026!` |
| ESTUDIANTE | `est001@aula.demo` | `Alumno2026!` |

## Arquitectura

```
[ Navegador ]
      │ HTTP
      ▼
[ Nginx Gateway :8080 ]
   ├── /api/* → Backend Laravel (PHP-FPM :9000)
   └── /*     → Frontend Vue (Nginx :80)
      │
      ├── PostgreSQL 16
      ├── Redis 7
      └── Worker (queue:work)
```

## Seguridad

- Autenticación con Laravel Sanctum (Bearer Tokens)
- Multi-tenancy lógica por `institucion_id`
- Scope adicional por `unidad_academica_id` para COORDINADOR
- RBAC con permisos granulares por módulo
- Rate limiting en endpoints de autenticación
- Sanitización de inputs y prevención CRLF injection
