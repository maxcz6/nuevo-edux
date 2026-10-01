<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  description?: string
}>(), { description: '' })
</script>

<template>
  <div class="empty-state border border-dashed border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900/60 rounded-2xl p-8 sm:p-12 text-center" role="status">
    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 mx-auto" aria-hidden="true">
      <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5V5a2 2 0 0 1 2-2h14v18H6.5A2.5 2.5 0 0 1 4 18.5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </div>
    <h2 class="text-base font-semibold text-slate-900 dark:text-white font-headline">{{ title }}</h2>
    <p v-if="description" class="mt-1.5 max-w-md text-xs sm:text-sm text-slate-500 dark:text-slate-400 mx-auto leading-relaxed">{{ description }}</p>
    <div v-if="$slots.default" class="mt-5"><slot /></div>
  </div>
</template>
