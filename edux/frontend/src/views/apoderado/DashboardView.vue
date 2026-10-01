<script setup lang="ts">
import { ref, onMounted } from 'vue'
import http from '@/api/http'
import PageHeader from '@/components/ui/PageHeader.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

// Umbral de aprobación IESTP: 11/20
const NOTA_APROBATORIA = 11

interface CursoHijo {
  grupo_id: number
  curso_nombre: string
  docente: string
  promedio: number | null
  total_calificaciones: number
  asistencia_pct: number
  faltas: number
}

interface Hijo {
  estudiante_id: number
  codigo: string
  nombre_completo: string
  parentesco: string
  es_principal: boolean
  cursos: CursoHijo[]
}

const hijos = ref<Hijo[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

async function loadHijos() {
  loading.value = true
  error.value = null
  try {
    const res = await http.get<{ data: Hijo[] }>('/apoderado/mis-hijos')
    hijos.value = res.data.data
  } catch {
    error.value = 'Error al cargar los datos de seguimiento. Intenta de nuevo.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadHijos()
})
</script>

<template>
  <div class="page-shell">
    <PageHeader
      eyebrow="Portal familiar"
      title="Seguimiento Académico"
      description="Supervise el progreso, notas y asistencias de sus hijos matriculados."
    />

    <LoadingState v-if="loading" label="Cargando información académica…" />

    <div
      v-else-if="error"
      class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-800"
      role="alert"
    >
      {{ error }}
    </div>

    <EmptyState
      v-else-if="hijos.length === 0"
      title="Sin estudiantes vinculados"
      description="No se encontraron estudiantes asociados a su cuenta. Comuníquese con la secretaría académica de la institución."
    />

    <div v-else class="space-y-8">
      <div
        v-for="hijo in hijos"
        :key="hijo.estudiante_id"
        class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm"
      >
        <!-- Cabecera del Estudiante -->
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
          <div>
            <h2 class="text-lg font-bold text-gray-900">{{ hijo.nombre_completo }}</h2>
            <p class="text-xs text-gray-500">Código: {{ hijo.codigo }} · Parentesco: {{ hijo.parentesco }}</p>
          </div>
          <span
            v-if="hijo.es_principal"
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800"
          >
            Apoderado Principal
          </span>
        </div>

        <!-- Cursos del Estudiante -->
        <div class="p-6">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">
            Cursos Matriculados
          </h3>

          <div v-if="hijo.cursos.length" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div
              v-for="curso in hijo.cursos"
              :key="curso.grupo_id"
              class="border border-gray-200 rounded-lg p-4 bg-gray-50/50 space-y-3"
            >
              <div class="flex justify-between items-start gap-2">
                <div class="min-w-0 flex-1">
                  <h4 class="font-bold text-gray-900 text-sm leading-tight truncate">{{ curso.curso_nombre }}</h4>
                  <p class="text-xs text-gray-500 mt-0.5 truncate">{{ curso.docente }}</p>
                </div>
                <span
                  v-if="curso.promedio !== null"
                  class="flex-shrink-0 px-2.5 py-1 text-xs rounded-md font-bold"
                  :class="curso.promedio >= NOTA_APROBATORIA ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                >
                  Prom: {{ curso.promedio }}
                </span>
                <span v-else class="flex-shrink-0 text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded">
                  Sin notas
                </span>
              </div>

              <div class="grid grid-cols-2 gap-2 pt-2 border-t border-gray-200 text-xs">
                <div>
                  <span class="text-gray-500">Asistencia:</span>
                  <span
                    class="ml-1 font-semibold"
                    :class="curso.asistencia_pct >= 70 ? 'text-green-700' : 'text-red-600'"
                  >
                    {{ curso.asistencia_pct }}%
                  </span>
                </div>
                <div>
                  <span class="text-gray-500">Inasistencias:</span>
                  <span class="ml-1 font-semibold" :class="curso.faltas > 0 ? 'text-red-600' : 'text-gray-900'">
                    {{ curso.faltas }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-6 text-gray-400 italic text-sm">
            No tiene cursos matriculados actualmente en el período activo.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
