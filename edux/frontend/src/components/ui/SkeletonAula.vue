<script setup lang="ts">
import SkeletonBase from './SkeletonBase.vue'

withDefaults(
  defineProps<{
    unitsCount?: number
  }>(),
  {
    unitsCount: 3,
  }
)
</script>

<template>
  <div class="space-y-6" role="status" aria-label="Cargando aula virtual...">
    <!-- Header del Curso / Aula -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="space-y-2">
          <div class="flex items-center gap-2">
            <SkeletonBase variant="badge" height="22px" width="80px" rounded="rounded-md" />
            <SkeletonBase variant="badge" height="22px" width="100px" rounded="rounded-full" />
          </div>
          <SkeletonBase variant="text" height="24px" width="320px" rounded="rounded-lg" />
          <SkeletonBase variant="text" height="14px" width="220px" rounded="rounded" />
        </div>
        <div class="flex items-center gap-2">
          <SkeletonBase variant="button" height="38px" width="120px" rounded="rounded-xl" />
          <SkeletonBase variant="button" height="38px" width="120px" rounded="rounded-xl" />
        </div>
      </div>

      <!-- Barra de navegación de pestañas (Unidades, Evaluaciones, Asistencias, etc.) -->
      <div class="mt-6 flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <SkeletonBase variant="rect" height="34px" width="100px" rounded="rounded-lg" />
        <SkeletonBase variant="rect" height="34px" width="110px" rounded="rounded-lg" />
        <SkeletonBase variant="rect" height="34px" width="100px" rounded="rounded-lg" />
        <SkeletonBase variant="rect" height="34px" width="90px" rounded="rounded-lg" />
      </div>
    </div>

    <!-- Lista de Unidades y Sesiones -->
    <div class="space-y-4">
      <div
        v-for="u in unitsCount"
        :key="`unit-skel-${u}`"
        class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
      >
        <!-- Cabecera de la Unidad -->
        <div class="flex items-center justify-between border-b border-slate-100 p-4 bg-slate-50/60 dark:border-slate-800 dark:bg-slate-800/40">
          <div class="flex items-center gap-3 flex-1">
            <SkeletonBase variant="circle" height="28px" width="28px" />
            <div class="space-y-1 flex-1">
              <SkeletonBase variant="text" height="16px" :width="`${35 + (u * 15)}%`" rounded="rounded" />
              <SkeletonBase variant="text" height="12px" width="25%" rounded="rounded" />
            </div>
          </div>
          <SkeletonBase variant="button" height="28px" width="70px" rounded="rounded-lg" />
        </div>

        <!-- Contenido de Sesiones y Materiales dentro de la Unidad -->
        <div class="p-4 space-y-3">
          <div
            v-for="s in 2"
            :key="`sess-${u}-${s}`"
            class="flex items-center justify-between rounded-xl border border-slate-100 p-3.5 bg-slate-50/30 dark:border-slate-800 dark:bg-slate-800/20"
          >
            <div class="flex items-center gap-3 flex-1">
              <SkeletonBase variant="rect" height="36px" width="36px" rounded="rounded-lg" />
              <div class="space-y-1.5 flex-1">
                <SkeletonBase variant="text" height="14px" :width="`${40 + (s * 20)}%`" rounded="rounded" />
                <SkeletonBase variant="text" height="11px" width="30%" rounded="rounded" />
              </div>
            </div>
            <div class="flex items-center gap-2">
              <SkeletonBase variant="badge" height="20px" width="60px" rounded="rounded-full" />
              <SkeletonBase variant="button" height="28px" width="40px" rounded="rounded-lg" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
