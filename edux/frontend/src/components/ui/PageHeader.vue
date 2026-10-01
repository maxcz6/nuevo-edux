<script setup lang="ts">
defineProps<{
  title: string
  description?: string
  eyebrow?: string
}>()
</script>

<template>
  <header class="page-header !shadow-none mb-6 pb-2">
    <div>
      <div v-if="eyebrow" class="mb-2">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 border border-primary-200/60">
          <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
          {{ eyebrow }}
        </span>
      </div>
      <h1 class="page-title text-2xl sm:text-3xl font-bold font-headline text-slate-900 tracking-tight">{{ title }}</h1>
      <p v-if="description" class="page-description text-sm text-slate-500 max-w-3xl leading-relaxed mt-1.5">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2.5 shrink-0">
      <slot name="actions" />
    </div>
  </header>
</template>
