---
name: laravel-best-practices
description: Reglas de arquitectura limpia, buenas prácticas backend en Laravel 11 y PostgreSQL (Clean Architecture, Services, DTOs, N+1 prevention).
---

# Laravel Best Practices & Clean Architecture (PHP 8.3 & PostgreSQL)

## 1. Principios de Arquitectura
- **Controladores delgados (Slim Controllers)**: Los controladores solo reciben la solicitud HTTP, delegan a una clase de servicio o caso de uso, y devuelven una respuesta JSON estandarizada.
- **Clases de Servicio y Dominio**: Encapsular la lógica de negocio en `app/Services/` o `Domain/`.
- **Data Transfer Objects (DTOs) & Form Requests**: Validar estrictamente las peticiones en Form Requests (`app/Http/Requests`) antes de alcanzar la capa de servicio.
- **Prevención de Consultas N+1**: Usar siempre Eager Loading explícito (`with(['relacion1', 'relacion2'])`) en consultas Eloquent sobre colecciones y relaciones.
- **Transacciones de Base de Datos**: Encapsular operaciones que afecten múltiples tablas (p. ej. matrículas, inscripciones, evaluaciones masivas) dentro de `DB::transaction(function () { ... })`.

## 2. PostgreSQL & Persistencia
- Uso de claves foráneas con restricciones claras (`cascadeOnDelete`, `nullOnDelete`).
- Índices compuestos en consultas frecuentes, especialmente combinando `institucion_id` con campos de búsqueda como `codigo`, `documento_numero` y `estado`.
- Scopes globales para aislamiento multi-tenant (`InstitutionScope`) y scopes locales para filtros por programa (`ProgramaScope`).
- Uso de transacciones ACID estrictas y validaciones seguras contra inyección SQL.

## 3. Manejo de Errores y API
- Formato de respuesta JSON uniforme:
  ```json
  {
    "data": ...,
    "message": "...",
    "meta": { "current_page": 1, ... }
  }
  ```
- Códigos HTTP semánticos: 200 (OK), 201 (Created), 400 (Bad Request), 401 (Unauthenticated), 403 (Forbidden), 404 (Not Found), 422 (Unprocessable Entity).
