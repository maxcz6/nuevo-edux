<script setup lang="ts">
import SkeletonBase from './SkeletonBase.vue'

withDefaults(
  defineProps<{
    variant?: 'stat' | 'course' | 'action' | 'metric'
    count?: number
  }>(),
  {
    variant: 'stat',
    count: 4,
  }
)
</script>

<template>
  <!-- Tarjetas de Estadísticas / KPIs -->
  <div v-if="variant === 'stat' || variant === 'metric'" class="grid grid-cols-2 gap-4 xl:grid-cols-4">
    <div
      v-for="n in count"
      :key="`stat-skel-${n}`"
      class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="flex items-center justify-between">
        <SkeletonBase variant="text" height="12px" width="55%" rounded="rounded" />
        <SkeletonBase variant="circle" height="32px" width="32px" />
      </div>
      <div class="mt-4 flex items-baseline gap-2">
        <SkeletonBase variant="rect" height="32px" width="40%" rounded="rounded-lg" />
        <SkeletonBase variant="badge" height="18px" width="50px" rounded="rounded-full" />
      </div>
      <div class="mt-3">
        <SkeletonBase variant="text" height="10px" width="70%" rounded="rounded" />
      </div>
    </div>
  </div>

  <!-- Tarjetas de Cursos / Grupos Académicos -->
  <div v-else-if="variant === 'course'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    <div
      v-for="n in count"
      :key="`course-skel-${n}`"
      class="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
    >
      <div>
        <div class="flex items-center justify-between gap-2 mb-3">
          <SkeletonBase variant="badge" height="22px" width="80px" rounded="rounded-md" />
          <SkeletonBase variant="badge" height="20px" width="50px" rounded="rounded-full" />
        </div>
        <SkeletonBase variant="text" height="20px" width="90%" rounded="rounded-md" className="mb-2" />
        <SkeletonBase variant="text" height="14px" width="60%" rounded="rounded" className="mb-4" />
        <div class="space-y-2 border-t border-slate-100 dark:border-slate-800 pt-3">
          <div class="flex items-center justify-between">
            <SkeletonBase variant="text" height="12px" width="30%" rounded="rounded" />
            <SkeletonBase variant="text" height="12px" width="40%" rounded="rounded" />
          </div>
          <div class="flex items-center justify-between">
            <SkeletonBase variant="text" height="12px" width="25%" rounded="rounded" />
            <SkeletonBase variant="text" height="12px" width="35%" rounded="rounded" />
          </div>
        </div>
      </div>
      <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <SkeletonBase variant="text" height="12px" width="45%" rounded="rounded" />
        <SkeletonBase variant="button" height="32px" width="95px" rounded="rounded-xl" />
      </div>
    </div>
  </div>

  <!-- Tarjetas de Enlaces Rápidos / Acciones -->
  <div v-else-if="variant === 'action'" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <div
      v-for="n in count"
      :key="`action-skel-${n}`"
      class="flex items-start gap-3.5 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900"
    >
      <SkeletonBase variant="circle" height="40px" width="40px" />
      <div class="flex-1 space-y-2">
        <SkeletonBase variant="text" height="15px" width="60%" rounded="rounded" />
        <SkeletonBase variant="text" height="12px" width="85%" rounded="rounded" />
      </div>
    </div>
  </div>
</template>
