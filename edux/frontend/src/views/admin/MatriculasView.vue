<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { matriculasApi, gruposApi, estudiantesApi } from '@/api'
import type { Matricula, GrupoCurso, Estudiante } from '@/types'

const matriculas = ref<Matricula[]>([])
const grupos = ref<GrupoCurso[]>([])
const estudiantes = ref<Estudiante[]>([])

const loading = ref(true)
const error = ref<string | null>(null)
const modalOpen = ref(false)

const form = ref({
  grupo_curso_id: 0,
  estudiante_id: 0,
})

async function loadData() {
  loading.value = true
  error.value = null
  try {
    const [mRes, gRes, eRes] = await Promise.all([
      matriculasApi.list(),
      gruposApi.list(),
      estudiantesApi.list(),
    ])
    matriculas.value = mRes.data.data
    grupos.value = gRes.data.data
    estudiantes.value = eRes.data.data

    if (grupos.value.length > 0) form.value.grupo_curso_id = grupos.value[0].id
    if (estudiantes.value.length > 0) form.value.estudiante_id = estudiantes.value[0].id
  } catch {
    error.value = 'Error al cargar las matrículas.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  form.value = {
    grupo_curso_id: grupos.value[0]?.id || 0,
    estudiante_id: estudiantes.value[0]?.id || 0,
  }
  modalOpen.value = true
}

async function handleSubmit() {
  try {
    await matriculasApi.create(form.value)
    modalOpen.value = false
    await loadData()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al matricular estudiante.')
  }
}

async function handleCancel(id: number) {
  if (!confirm('¿Estás seguro de anular esta matrícula?')) return
  try {
    await matriculasApi.remove(id)
    await loadData()
  } catch {
    alert('Error al anular matrícula.')
  }
}

onMounted(loadData)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Matrículas</h1>
        <p class="text-sm text-gray-500">Inscripción directa de estudiantes a grupos de curso.</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        + Matricular Estudiante
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
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estudiante</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Curso & Grupo</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Origen</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fecha</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
            <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-if="matriculas.length === 0">
            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No hay matrículas registradas.</td>
          </tr>
          <tr v-for="mat in matriculas" :key="mat.id" class="hover:bg-gray-50">
            <td class="whitespace-nowrap px-6 py-4">
              <div class="text-sm font-semibold text-gray-900">
                {{ mat.estudiante?.usuario?.nombres }} {{ mat.estudiante?.usuario?.apellido_paterno }}
              </div>
              <div class="text-xs text-gray-500">{{ mat.estudiante?.usuario?.email }}</div>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
              <div class="font-medium text-gray-900">{{ mat.grupo_curso?.curso?.nombre }}</div>
              <div class="text-xs text-gray-500">Grupo {{ mat.grupo_curso?.codigo_grupo || 'A' }}</div>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ mat.origen }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ mat.fecha_matricula }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm">
              <span :class="[
                'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                mat.estado === 'MATRICULADO' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
              ]">
                {{ mat.estado }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
              <button v-if="mat.estado === 'MATRICULADO'" @click="handleCancel(mat.id)" class="text-red-600 hover:text-red-900">
                Anular
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Form -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-lg font-bold text-gray-900">Nueva Matrícula</h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Estudiante</label>
            <select v-model="form.estudiante_id" required class="input-field mt-1">
              <option v-for="e in estudiantes" :key="e.id" :value="e.id">
                {{ e.usuario?.nombres }} {{ e.usuario?.apellido_paterno }} ({{ e.codigo_estudiante || e.usuario?.username }})
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Grupo de Curso</label>
            <select v-model="form.grupo_curso_id" required class="input-field mt-1">
              <option v-for="g in grupos" :key="g.id" :value="g.id">
                {{ g.curso?.nombre }} - Grupo {{ g.codigo_grupo || 'A' }} ({{ g.periodo?.nombre }})
              </option>
            </select>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="modalOpen = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary">Matricular</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
