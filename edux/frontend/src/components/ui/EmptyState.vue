<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  description?: string
}>(), { description: '' })
</script>

<template>
  <div class="empty-state" role="status">
    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-600" aria-hidden="true">
      <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5V5a2 2 0 0 1 2-2h14v18H6.5A2.5 2.5 0 0 1 4 18.5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </div>
    <h2 class="text-base font-semibold text-gray-900">{{ title }}</h2>
    <p v-if="description" class="mt-1 max-w-md text-sm text-gray-500">{{ description }}</p>
    <div v-if="$slots.default" class="mt-5"><slot /></div>
  </div>
</template>
