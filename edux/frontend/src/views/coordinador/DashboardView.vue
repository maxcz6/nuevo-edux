<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { dashboardApi } from '@/api'
import LoadingState from '@/components/ui/LoadingState.vue'
import AlertMessage from '@/components/ui/AlertMessage.vue'

const loading = ref(true)
const error = ref<string | null>(null)

interface DashboardStats {
  total_docentes: number
  total_estudiantes: number
  grupos_activos: number
  periodo_activo?: { id: number; nombre: string; codigo: string } | null
  programa?: { id: number; nombre: string; codigo: string } | null
}

const stats = ref<DashboardStats>({
  total_docentes: 0,
  total_estudiantes: 0,
  grupos_activos: 0,
})

async function fetchStats() {
  loading.value = true
  error.value = null
  try {
    const res = await dashboardApi.stats()
    stats.value = res.data.data as any
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cargar las métricas de coordinación'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchStats()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 dark:border-slate-700 pb-5">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Panel de Coordinación</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Supervisión académica, avance curricular y gestión del programa:
          <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ stats.programa?.nombre || 'Cargando...' }}</span>
        </p>
      </div>
      <div v-if="stats.periodo_activo" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-semibold">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        Período: {{ stats.periodo_activo.nombre }}
      </div>
    </div>

    <LoadingState v-if="loading" />
    <AlertMessage v-else-if="error" type="error" :message="error" />

    <div v-else class="space-y-6">
      <!-- KPI Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <!-- Docentes Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Docentes Asignados</p>
            <p class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ stats.total_docentes }}</p>
            <RouterLink to="/coordinador/docentes" class="inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline mt-2">
              Ver docentes &rarr;
            </RouterLink>
          </div>
          <div class="p-3.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
            </svg>
          </div>
        </div>

        <!-- Alumnos Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Alumnos del Programa</p>
            <p class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ stats.total_estudiantes }}</p>
            <RouterLink to="/coordinador/estudiantes" class="inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline mt-2">
              Ver estudiantes &rarr;
            </RouterLink>
          </div>
          <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
            </svg>
          </div>
        </div>

        <!-- Grupos Activos -->
        <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Grupos / Secciones Activas</p>
            <p class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ stats.grupos_activos }}</p>
            <RouterLink to="/coordinador/grupos" class="inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline mt-2">
              Ver grupos &rarr;
            </RouterLink>
          </div>
          <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Quick Actions Grid -->
      <div class="bg-white dark:bg-slate-800 rounded-xl p-6 border border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Acciones de Coordinación</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <RouterLink
            to="/coordinador/asignaciones"
            class="flex flex-col p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition-all group"
          >
            <span class="font-semibold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
              Asignación Docente
            </span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Vincular profesores a las unidades didácticas y cursos del ciclo.
            </span>
          </RouterLink>

          <RouterLink
            to="/coordinador/actas"
            class="flex flex-col p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition-all group"
          >
            <span class="font-semibold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
              Cierre de Actas
            </span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Revisar notas finales de los cursos y aprobar actas consolidadas.
            </span>
          </RouterLink>

          <RouterLink
            to="/coordinador/matriculas"
            class="flex flex-col p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition-all group"
          >
            <span class="font-semibold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
              Matrícula por Sección
            </span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Registrar o verificar inscripciones de los estudiantes a sus grupos.
            </span>
          </RouterLink>

          <RouterLink
            to="/coordinador/tramites"
            class="flex flex-col p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition-all group"
          >
            <span class="font-semibold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
              Trámites de Carrera
            </span>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Convalidaciones, traslados y reservas de matrícula del programa.
            </span>
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>
