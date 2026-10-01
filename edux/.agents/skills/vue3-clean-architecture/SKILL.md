---
name: vue3-clean-architecture
description: Patrones de arquitectura desacoplada y limpia para Vue 3, Composition API, TypeScript estricto y Pinia.
---

# Vue 3 Clean Architecture & Desacoplamiento (TypeScript & Pinia)

## 1. Separación de Responsabilidades
- **Capa de API**: Módulos independientes en `src/api/` con cliente HTTP Axios configurado e interceptores para autenticación Bearer y manejo centralizado de errores.
- **Capa de Estado (Stores)**: Pinia modular en `src/stores/`. El store `auth` administra token, sesión de usuario, permisos y computadas reactivas para cada rol (`isSuperAdmin`, `isAdmin`, `isCoordinador`, `isDocente`, `isEstudiante`).
- **Capa de Presentación**: Componentes organizados por dominio funcional (`views/admin`, `views/coordinador`, `views/docente`, `views/estudiante`) y componentes UI reutilizables sin estado de negocio en `components/ui/`.
- **Composables**: Lógica compartida en `src/composables/` (temas, paginación, modales, formularios).

## 2. Tipado Estricto (TypeScript)
- Definición de interfaces exhaustivas en `src/types/index.ts` para todas las entidades de dominio y respuestas paginadas.
- Sin uso de tipo `any` indiscriminado.

## 3. Seguridad y Navegación
- Protección estricta en `router.beforeEach` validando tanto `requiresAuth` como los roles autorizados para la ruta.
- Redirección automática a dashboards según el rol activo.
- Sincronización multi-pestaña para cierre de sesión y saneamiento de entradas en formularios.
