<script setup lang="ts">
withDefaults(defineProps<{ label?: string }>(), { label: 'Cargando información…' })
</script>

<template>
  <div class="flex min-h-48 flex-col items-center justify-center gap-3" role="status" aria-live="polite">
    <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-200 border-t-primary-600" aria-hidden="true" />
    <p class="text-sm text-gray-500">{{ label }}</p>
  </div>
</template>
