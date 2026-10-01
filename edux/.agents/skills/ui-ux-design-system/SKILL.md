---
name: ui-ux-design-system
description: Guía de diseño visual, sistema de diseño modular, accesibilidad WCAG 2.1 AA y buenas prácticas UI/UX con Tailwind CSS.
---

# UI/UX Design System & Accessibility Guide

## 1. Principios de Diseño Visual
- **Consistencia de Espaciados y Escala**: Emplear la escala de espaciado estándar de Tailwind (4, 6, 8, 12, 16) y jerarquía tipográfica armónica.
- **Paleta de Colores Funcional**: Colores semánticos para estados: Primary (Índigo/Azul), Success (Esmeralda), Warning (Ámbar), Danger (Rojo), Neutral (Slate/Zinc).
- **Contraste y Accesibilidad (WCAG 2.1 Nivel AA)**: Asegurar un ratio de contraste mínimo de 4.5:1 para texto normal y 3:1 para texto grande o elementos gráficos.
- **Soporte de Tema Claro y Oscuro**: Clases `dark:` de Tailwind implementadas coherentemente en fondos, bordes y textos.

## 2. Componentes UI Modulares
- Estados claros para cada interacción: normal, hover, focus-visible, active, disabled y loading (spinners/skeletons).
- Empty States informativos con iconos descriptivos y llamadas a la acción (CTA).
- Feedback inmediato al usuario mediante notificaciones toast y modales accesibles con foco atrapado.
- Formularios con etiquetas legibles (`label`), mensajes de validación claros y atributos accesibles (`aria-describedby`, `aria-invalid`).
