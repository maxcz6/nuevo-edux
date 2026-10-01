<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { calificacionesApi } from '@/api'
import type { Calificacion } from '@/types'

const route = useRoute()
const grupoId = Number(route.params.id)

const calificaciones = ref<Calificacion[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const modalOpen = ref(false)

const form = ref({
  estudiante_id: 0,
  nombre_rubro: '',
  tipo_origen: 'MANUAL',
  nota: 15,
  peso_porcentual: 20,
})

async function loadCalificaciones() {
  loading.value = true
  error.value = null
  try {
    const res = await calificacionesApi.list(grupoId)
    calificaciones.value = res.data.data
  } catch {
    error.value = 'Error al cargar las calificaciones.'
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  try {
    await calificacionesApi.create(grupoId, form.value)
    modalOpen.value = false
    await loadCalificaciones()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al registrar calificación.')
  }
}

onMounted(loadCalificaciones)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Registro de Calificaciones</h1>
        <p class="text-sm text-gray-500">Notas de tareas, exámenes y evaluaciones del grupo.</p>
      </div>
      <button @click="modalOpen = true" class="btn-primary">
        + Registrar Nota
      </button>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
    </div>

    <div v-else-if="error" class="rounded-md bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estudiante ID</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rubro / Evaluación</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Origen</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Peso</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nota</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-if="calificaciones.length === 0">
            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No hay calificaciones registradas en este grupo.</td>
          </tr>
          <tr v-for="c in calificaciones" :key="c.id" class="hover:bg-gray-50">
            <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-900">
              Estudiante #{{ c.estudiante_id }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
              {{ c.nombre_rubro }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ c.tipo_origen }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ c.peso_porcentual || 0 }}%
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm font-bold" :class="c.nota >= 11 ? 'text-green-600' : 'text-red-600'">
              {{ c.nota }} / 20
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Form -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-lg font-bold text-gray-900">Registrar Calificación Manual</h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">ID del Estudiante</label>
            <input v-model.number="form.estudiante_id" type="number" required class="input-field mt-1" placeholder="Ej: 1" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nombre del Rubro</label>
            <input v-model="form.nombre_rubro" required class="input-field mt-1" placeholder="Ej: Práctica Calificada 1" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nota (0 - 20)</label>
              <input v-model.number="form.nota" type="number" min="0" max="20" step="0.5" required class="input-field mt-1" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Peso (%)</label>
              <input v-model.number="form.peso_porcentual" type="number" min="1" max="100" required class="input-field mt-1" />
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="modalOpen = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary">Guardar Nota</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
