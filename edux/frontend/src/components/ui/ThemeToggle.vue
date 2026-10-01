<script setup lang="ts">
import { useTheme } from '@/composables/useTheme'

const { isDark, toggleTheme } = useTheme()
</script>

<template>
  <button
    type="button"
    class="theme-toggle group relative inline-flex h-10 w-10 flex-none items-center justify-center rounded-xl border border-slate-200/90 bg-white/90 text-slate-600 shadow-sm backdrop-blur-xs transition-all duration-200 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 active:scale-95 dark:border-slate-700/80 dark:bg-slate-800/90 dark:text-amber-300 dark:hover:border-slate-600 dark:hover:bg-slate-700/90 dark:hover:text-amber-200 dark:focus:ring-offset-slate-900"
    :aria-label="isDark ? 'Activar modo claro' : 'Activar modo oscuro'"
    :title="isDark ? 'Modo claro' : 'Modo oscuro'"
    @click="toggleTheme"
  >
    <!-- Sol (Dark active -> Switch to light) -->
    <svg
      v-if="isDark"
      class="h-5 w-5 transition-transform duration-300 ease-out group-hover:rotate-45"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="4" stroke-width="2" />
      <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" stroke-width="2" stroke-linecap="round" />
    </svg>
    <!-- Luna (Light active -> Switch to dark) -->
    <svg
      v-else
      class="h-5 w-5 text-slate-600 transition-transform duration-300 ease-out group-hover:-rotate-12 group-hover:text-primary-600"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      aria-hidden="true"
    >
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <span class="sr-only">{{ isDark ? 'Activar modo claro' : 'Activar modo oscuro' }}</span>
  </button>
</template>
