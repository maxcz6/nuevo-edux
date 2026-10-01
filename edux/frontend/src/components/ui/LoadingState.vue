<script setup lang="ts">
import SkeletonPixel from './SkeletonPixel.vue'

withDefaults(
  defineProps<{
    label?: string
    variant?: 'spinner' | 'cards' | 'table' | 'text' | 'stat-cards'
    count?: number
  }>(),
  {
    label: 'Cargando información…',
    variant: 'spinner',
    count: 3,
  }
)
</script>

<template>
  <!-- Skeleton Cards Variant -->
  <div v-if="variant === 'cards'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <SkeletonPixel v-for="i in count" :key="i" variant="card" />
  </div>

  <!-- Skeleton Stat Cards Variant -->
  <div v-else-if="variant === 'stat-cards'" class="grid grid-cols-2 gap-4 xl:grid-cols-4">
    <SkeletonPixel v-for="i in (count || 4)" :key="i" variant="stat-card" />
  </div>

  <!-- Skeleton Table Variant -->
  <div v-else-if="variant === 'table'">
    <SkeletonPixel variant="table" :lines="count || 5" />
  </div>

  <!-- Skeleton Text Lines Variant -->
  <div v-else-if="variant === 'text'">
    <SkeletonPixel variant="text" :lines="count || 4" />
  </div>

  <!-- Default Spinner Variant -->
  <div v-else class="flex min-h-48 flex-col items-center justify-center gap-3" role="status" aria-live="polite">
    <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-200 border-t-primary-600" aria-hidden="true" />
    <p class="text-sm text-gray-500 dark:text-slate-400">{{ label }}</p>
  </div>
</template>
