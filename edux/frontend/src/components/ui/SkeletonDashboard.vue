<script setup lang="ts">
import SkeletonBase from './SkeletonBase.vue'
import SkeletonCard from './SkeletonCard.vue'

withDefaults(
  defineProps<{
    showHero?: boolean
    statsCount?: number
    quickLinksCount?: number
  }>(),
  {
    showHero: true,
    statsCount: 4,
    quickLinksCount: 6,
  }
)
</script>

<template>
  <div class="space-y-6" role="status" aria-label="Cargando panel de control...">
    <!-- Hero Banner ambiental Esqueleto -->
    <div
      v-if="showHero"
      class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-primary-950 to-slate-900 text-white p-6 sm:p-8 shadow-lg border border-slate-800"
    >
      <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="space-y-3 max-w-xl">
          <SkeletonBase variant="badge" height="24px" width="210px" rounded="rounded-full" className="bg-white/20" />
          <SkeletonBase variant="text" height="28px" width="320px" rounded="rounded-lg" className="bg-white/30" />
          <SkeletonBase variant="text" height="14px" width="440px" rounded="rounded" className="bg-white/20" />
        </div>
        <div class="shrink-0 bg-white/10 backdrop-blur-md border border-white/15 rounded-xl p-4 w-48 space-y-2">
          <SkeletonBase variant="text" height="10px" width="90px" rounded="rounded" className="bg-white/30" />
          <SkeletonBase variant="text" height="18px" width="140px" rounded="rounded" className="bg-white/40" />
        </div>
      </div>
      <!-- Luces ambientales de fondo -->
      <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-primary-600/20 blur-3xl" aria-hidden="true" />
      <div class="absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-indigo-600/20 blur-3xl" aria-hidden="true" />
    </div>

    <!-- 4 Stats Cards Ribbon -->
    <SkeletonCard variant="stat" :count="statsCount" />

    <!-- Accesos Rápidos Section -->
    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <SkeletonBase variant="text" height="18px" width="180px" rounded="rounded" />
        <SkeletonBase variant="text" height="14px" width="100px" rounded="rounded" />
      </div>
      <SkeletonCard variant="action" :count="quickLinksCount" />
    </div>
  </div>
</template>
