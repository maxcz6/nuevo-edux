<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { gruposApi, calificacionesApi } from '@/api'
import type { GrupoCurso, Calificacion } from '@/types'
import PageHeader from '@/components/ui/PageHeader.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const grupos = ref<GrupoCurso[]>([])
const calificaciones = ref<Record<number, Calificacion[]>>({})
const loading = ref(true)
const error = ref<string | null>(null)

// Umbral de aprobación IESTP: 11/20
const NOTA_APROBATORIA = 11

async function loadNotas() {
  loading.value = true
  error.value = null
  try {
    const resGrupos = await gruposApi.misGrupos()
    grupos.value = resGrupos.data.data as GrupoCurso[]
    
    // Cargar calificaciones en paralelo para mejor rendimiento
    await Promise.all(
      grupos.value.map(async (grupo) => {
        try {
          const resCal = await calificacionesApi.list(grupo.id)
          calificaciones.value[grupo.id] = resCal.data.data
        } catch {
          calificaciones.value[grupo.id] = []
        }
      })
    )
  } catch {
    error.value = 'Error al cargar las calificaciones. Intenta de nuevo.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadNotas()
})
</script>

<template>
  <div class="page-shell">
    <PageHeader
      eyebrow="Mis resultados académicos"
      title="Mis Notas"
      description="Historial de calificaciones por curso y período académico."
    />

    <LoadingState v-if="loading" label="Cargando tus calificaciones…" />

    <div v-else-if="error" class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-800" role="alert">
      {{ error }}
    </div>

    <EmptyState
      v-else-if="grupos.length === 0"
      title="Sin cursos matriculados"
      description="Tus calificaciones aparecerán aquí cuando estés matriculado en cursos del período activo."
    />

    <div v-else class="space-y-8">
      <div v-for="grupo in grupos" :key="grupo.id" class="card">
        <h2 class="text-xl font-bold text-gray-900 border-b border-gray-200 pb-3 mb-4">
          {{ grupo.curso?.nombre }}
          <span class="text-sm font-normal text-gray-500">({{ grupo.codigo_grupo }})</span>
        </h2>

        <div
          v-if="!calificaciones[grupo.id] || calificaciones[grupo.id].length === 0"
          class="text-sm text-gray-500 italic py-2"
        >
          Aún no hay calificaciones registradas en este curso.
        </div>

        <div v-else class="overflow-x-auto rounded-md border border-gray-200">
          <table class="min-w-full divide-y divide-gray-200" :aria-label="`Calificaciones de ${grupo.curso?.nombre}`">
            <caption class="sr-only">Calificaciones del curso {{ grupo.curso?.nombre }}</caption>
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Evaluación / Rubro
                </th>
                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Tipo
                </th>
                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Peso
                </th>
                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Nota
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="nota in calificaciones[grupo.id]" :key="nota.id">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                  {{ nota.nombre_rubro || nota.tipo_evaluacion || 'Evaluación' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-500">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                    {{ nota.tipo_origen || 'MANUAL' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-center text-gray-500">
                  {{ nota.peso_porcentual ? `${nota.peso_porcentual}%` : '—' }}
                </td>
                <td
                  class="px-4 py-3 text-sm text-right font-bold"
                  :class="Number(nota.nota ?? nota.valor ?? 0) >= NOTA_APROBATORIA ? 'text-green-600' : 'text-red-600'"
                >
                  {{ nota.nota ?? nota.valor ?? '—' }}
                  <span class="sr-only">
                    {{ Number(nota.nota ?? nota.valor ?? 0) >= NOTA_APROBATORIA ? '(aprobado)' : '(desaprobado)' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
