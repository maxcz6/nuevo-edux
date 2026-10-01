<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { gruposApi, tareasApi } from '@/api'
import type { GrupoCurso, Tarea } from '@/types'
import PageHeader from '@/components/ui/PageHeader.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface TareaConGrupo extends Tarea {
  grupoNombre?: string
}

const tareas = ref<TareaConGrupo[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

function formatFecha(fecha: string | null | undefined): string {
  if (!fecha) return 'Sin fecha límite'
  try {
    return new Date(fecha).toLocaleString('es-PE', {
      dateStyle: 'medium',
      timeStyle: 'short',
    })
  } catch {
    return fecha
  }
}

async function loadTareas() {
  loading.value = true
  error.value = null
  try {
    const resGrupos = await gruposApi.misGrupos()
    const misGrupos = resGrupos.data.data as GrupoCurso[]

    const allTareas: TareaConGrupo[] = []
    await Promise.all(
      misGrupos.map(async (grupo) => {
        try {
          const resT = await tareasApi.list(grupo.id)
          for (const t of resT.data.data) {
            allTareas.push({
              ...t,
              grupoNombre: `${grupo.curso?.nombre ?? 'Curso'} · Grupo ${grupo.codigo_grupo || grupo.codigo || 'A'}`,
            })
          }
        } catch {
          // Omitir grupos cuyo endpoint de tareas falle
        }
      })
    )
    // Ordenar: primero las más próximas a vencer
    tareas.value = allTareas.sort((a, b) => {
      if (!a.fecha_limite) return 1
      if (!b.fecha_limite) return -1
      return new Date(a.fecha_limite).getTime() - new Date(b.fecha_limite).getTime()
    })
  } catch {
    error.value = 'Error al cargar las tareas pendientes. Intenta de nuevo.'
  } finally {
    loading.value = false
  }
}

onMounted(loadTareas)
</script>

<template>
  <div class="page-shell">
    <PageHeader
      eyebrow="Actividades académicas"
      title="Mis Tareas y Asignaciones"
      description="Listado ordenado por fecha límite de todas las actividades académicas de tus cursos."
    />

    <LoadingState v-if="loading" label="Cargando tus tareas…" />

    <div
      v-else-if="error"
      class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-800"
      role="alert"
    >
      {{ error }}
    </div>

    <EmptyState
      v-else-if="tareas.length === 0"
      title="Sin tareas asignadas"
      description="No tienes tareas pendientes ni asignadas por el momento. Revisa luego."
    />

    <div v-else class="space-y-4">
      <article
        v-for="tarea in tareas"
        :key="tarea.id"
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition-shadow"
        :aria-label="`Tarea: ${tarea.titulo}`"
      >
        <div class="space-y-1.5 min-w-0 flex-1">
          <div class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400 truncate">
            {{ tarea.grupoNombre }}
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ tarea.titulo }}</h3>
            <span
              v-if="tarea.tiene_archivo_adjunto || tarea.archivo_adjunto_nombre"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60"
            >
              📎 {{ tarea.archivo_tipo || 'PDF/Word' }} adjunto
            </span>
          </div>
          <p class="text-sm text-slate-600 dark:text-slate-300 line-clamp-2">{{ tarea.descripcion || 'Sin descripción adicional.' }}</p>
          <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400 pt-1">
            <span class="inline-flex items-center gap-1">
              <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span class="sr-only">Fecha límite:</span>
              {{ formatFecha(tarea.fecha_limite) }}
            </span>
            <span class="inline-flex items-center gap-1">
              <svg class="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
              </svg>
              <span class="sr-only">Puntaje máximo:</span>
              {{ tarea.puntaje_maximo }} pts
            </span>
          </div>
        </div>

        <div class="flex flex-row sm:flex-col items-center sm:items-end gap-2 flex-shrink-0">
          <RouterLink
            :to="`/estudiante/cursos/${tarea.grupo_curso_id}`"
            :class="tarea.mi_entrega ? 'btn-secondary text-xs whitespace-nowrap' : 'btn-primary text-xs whitespace-nowrap'"
          >
            {{ tarea.mi_entrega ? (tarea.mi_entrega.estado === 'CALIFICADA' ? 'Ver Calificación' : 'Ver / Modificar') : 'Ir a entregar' }}
          </RouterLink>
          <span
            v-if="tarea.mi_entrega?.estado === 'CALIFICADA'"
            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-800"
          >
            Calificada ({{ tarea.mi_entrega.nota }}/{{ tarea.puntaje_maximo }})
          </span>
          <span
            v-else-if="tarea.mi_entrega?.estado === 'ENTREGADA'"
            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-blue-100 text-blue-800"
          >
            Entregada
          </span>
          <span
            v-else
            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800"
          >
            Pendiente
          </span>
        </div>
      </article>
    </div>
  </div>
</template>
