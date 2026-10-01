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
    <PageHeader eyebrow="Aula virtual" title="Mis cursos" description="Revisa contenidos, tareas, evaluaciones y tu progreso académico en cada asignatura." />
    
    <!-- Hero Banner ambiental para el estudiante -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-primary-950 to-indigo-950 text-white p-6 sm:p-7 shadow-lg border border-slate-800">
      <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-white/10 backdrop-blur-sm border border-white/15 text-[11px] font-medium text-slate-200 mb-2">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>Semestre Académico Activo</span>
          </div>
          <h2 class="text-xl sm:text-2xl font-bold font-headline tracking-tight text-white">
            Tu espacio de aprendizaje
          </h2>
          <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-lg">
            Accede al material pedagógico, entrega tus tareas pendientes y realiza el seguimiento de tus calificaciones.
          </p>
        </div>
        <div class="shrink-0 bg-white/10 backdrop-blur-md border border-white/20 rounded-xl px-4 py-3 text-center sm:text-right">
          <p class="text-[11px] uppercase tracking-wider text-slate-300 font-semibold">Cursos matriculados</p>
          <p class="text-2xl font-bold text-white font-headline mt-0.5">{{ grupos.length }}</p>
        </div>
      </div>
      <div class="absolute -right-12 -top-12 h-44 w-44 rounded-full bg-primary-600/20 blur-2xl" aria-hidden="true"></div>
    </div>

    <LoadingState v-if="loading" label="Cargando tus cursos…" />
    
    <EmptyState v-else-if="grupos.length === 0" title="No tienes cursos disponibles" description="Tus cursos aparecerán aquí cuando la matrícula esté activa y vinculada a un grupo." />

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <RouterLink
        v-for="grupo in grupos"
        :key="grupo.id"
        :to="`/estudiante/cursos/${grupo.id}`"
        class="group flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs hover:shadow-lg hover:border-primary-200 transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
      >
        <div>
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-bold font-mono uppercase tracking-wide bg-slate-100 text-slate-700">
              {{ grupo.codigo_grupo }}
            </span>
            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200/60">
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
              {{ grupo.periodo?.nombre ?? 'Período Activo' }}
            </span>
          </div>

          <h2 class="text-lg font-bold font-headline text-slate-900 group-hover:text-primary-700 transition-colors leading-snug mb-3">
            {{ grupo.curso?.nombre }}
          </h2>

          <div class="space-y-1.5 text-xs text-slate-600 bg-slate-50/60 rounded-xl p-3 border border-slate-100">
            <div class="flex items-center gap-2">
              <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <span class="truncate"><strong>Docente:</strong> {{ grupo.docente?.nombres }} {{ grupo.docente?.apellido_paterno }}</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span class="truncate"><strong>Período:</strong> {{ grupo.periodo?.nombre }}</span>
            </div>
          </div>
        </div>

        <div class="mt-5 pt-3.5 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-primary-600 group-hover:text-primary-700 transition-colors">
          <span>Ingresar al Aula</span>
          <svg class="h-4 w-4 transform transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </RouterLink>
    </div>
  </div>
</template>
