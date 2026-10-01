---
name: docker-multitenant
description: Directivas de arquitectura e infraestructura híbrida multi-tenant y SaaS con Docker, Laravel Sail y PostgreSQL.
---

# Agent Skill: Docker Híbrido Multi-Tenant para Aula Virtual

## Meta
- **tech_stack**: [Laravel 11, Vue 3, Pinia, PostgreSQL 16, Docker, Laravel Sail]
- **architecture**: SaaS-MultiTenant-SingleDatabase-RLS

## Directivas
- Todas las migraciones de PostgreSQL deben incluir `institucion_id` (o `tenant_id`) indexado para el aislamiento institucional.
- Cada usuario pertenece obligatoriamente a una institución (`institucion_id`) y a un programa de estudios (`unidad_academica_id` / `programa_estudio_id`).
- El rol `COORDINADOR` debe filtrar todos los datos académicos (docentes, estudiantes, grupos, cursos, actas) mediante el scope del programa (`programa_estudio_id` / `unidad_academica_id`).
- No implementar pasarelas de pago ni módulos de caja; el sistema es puramente pedagógico y de gestión académica.
- Configurar las variables del `.env` de Laravel para mapear la URL local de la Intranet cuando se corra en Docker o Laravel Sail.
- Mantener los 5 roles fundamentales: `SUPERADMIN`, `ADMINISTRADOR`, `COORDINADOR`, `DOCENTE`, `ESTUDIANTE`.
