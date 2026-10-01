<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { gruposApi } from '@/api'
import type { GrupoCurso } from '@/types'
import PageHeader from '@/components/ui/PageHeader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'

const grupos = ref<GrupoCurso[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await gruposApi.misGrupos()
    grupos.value = res.data.data as GrupoCurso[]
  } catch (error) {
    console.error('Error loading groups', error)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page-shell space-y-6">
    <PageHeader 
      eyebrow="Cátedras asignadas" 
      title="Mis Grupos y Cátedras" 
      description="Accede al aula virtual, registro de contenidos, control de tareas y acta oficial de calificaciones de cada curso a tu cargo." 
    />
    
    <!-- Hero Banner ambiental para el docente -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 dark:from-slate-950 dark:via-emerald-950 dark:to-slate-950 text-white p-6 sm:p-7 shadow-md border border-slate-800 dark:border-emerald-900/40">
      <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-white/10 backdrop-blur-sm border border-white/15 text-[11px] font-medium text-emerald-200 mb-2">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>Semestre Académico 2026-I</span>
          </div>
          <h2 class="text-xl sm:text-2xl font-bold font-headline tracking-tight text-white">
            Gestión Académica de Cátedras
          </h2>
          <p class="text-xs sm:text-sm text-slate-300 dark:text-slate-400 mt-1 max-w-lg">
            Sube recursos pedagógicos, programa evaluaciones interactivas y sincroniza calificaciones oficiales en tiempo real.
          </p>
        </div>
        <div class="shrink-0 bg-white/10 dark:bg-white/5 backdrop-blur-md border border-white/20 dark:border-white/10 rounded-xl px-5 py-3 text-center sm:text-right">
          <p class="text-[11px] uppercase tracking-wider text-emerald-300 font-semibold">Grupos a cargo</p>
          <p class="text-2xl font-bold text-white font-headline mt-0.5">{{ grupos.length }}</p>
        </div>
      </div>
      <div class="absolute -right-12 -top-12 h-48 w-48 rounded-full bg-emerald-500/15 blur-2xl" aria-hidden="true"></div>
    </div>

    <LoadingState v-if="loading" label="Cargando tus grupos…" />
    
    <EmptyState 
      v-else-if="grupos.length === 0" 
      title="Aún no tienes grupos asignados" 
      description="Cuando se te asigne un grupo académico aparecerá aquí con acceso a su aula virtual." 
    />

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div 
        v-for="grupo in grupos" 
        :key="grupo.id" 
        class="flex flex-col justify-between rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-xs hover:shadow-md hover:border-emerald-300 dark:hover:border-emerald-700/60 transition-all"
      >
        <div>
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-bold font-mono uppercase tracking-wide bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200/60 dark:border-slate-700/60">
              {{ grupo.codigo_grupo }}
            </span>
            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-md border border-emerald-200/60 dark:border-emerald-800/60">
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
              {{ grupo.periodo?.nombre ?? 'Período Activo' }}
            </span>
          </div>

          <h3 class="text-base sm:text-lg font-bold font-headline text-slate-900 dark:text-slate-100 leading-snug mb-1">
            {{ grupo.curso?.nombre }}
          </h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mb-4 font-mono">
            {{ grupo.curso?.codigo ? `Código Curso: ${grupo.curso.codigo}` : `Sección: ${grupo.codigo_grupo}` }}
          </p>

          <div class="space-y-2 text-xs text-slate-600 dark:text-slate-300 bg-slate-50/80 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-100 dark:border-slate-800 mb-5">
            <div class="flex items-center gap-2">
              <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span><strong>Período:</strong> {{ grupo.periodo?.nombre }}</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              <span><strong>Alumnos matriculados:</strong> {{ grupo.matriculas_count ?? 0 }} / {{ grupo.cupo ?? grupo.cupos ?? 0 }}</span>
            </div>
          </div>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 grid grid-cols-3 gap-2">
          <RouterLink 
            :to="`/docente/grupos/${grupo.id}/aula`" 
            class="btn-primary text-center py-2 text-xs font-semibold rounded-xl flex items-center justify-center gap-1"
            title="Ir al Aula Virtual"
          >
            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            Aula
          </RouterLink>
          <RouterLink 
            :to="`/docente/grupos/${grupo.id}/tareas`" 
            class="btn-secondary text-center py-2 text-xs font-semibold rounded-xl flex items-center justify-center gap-1"
            title="Ver Tareas y Entregas"
          >
            <svg class="h-3.5 w-3.5 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            Tareas
          </RouterLink>
          <RouterLink 
            :to="`/docente/grupos/${grupo.id}/calificaciones`" 
            class="btn-secondary text-center py-2 text-xs font-semibold rounded-xl flex items-center justify-center gap-1"
            title="Ver Registro de Calificaciones"
          >
            <svg class="h-3.5 w-3.5 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Notas
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>
