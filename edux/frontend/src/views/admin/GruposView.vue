<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { gruposApi, cursosApi, periodosApi, docentesApi } from '@/api'
import type { GrupoCurso, Curso, PeriodoAcademico, Docente } from '@/types'

const grupos = ref<GrupoCurso[]>([])
const cursos = ref<Curso[]>([])
const periodos = ref<PeriodoAcademico[]>([])
const docentes = ref<Docente[]>([])

const loading = ref(true)
const error = ref<string | null>(null)
const modalOpen = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  curso_id: 0,
  periodo_id: 0,
  docente_id: 0,
  codigo_grupo: 'A',
  turno: 'MANANA',
  aula: 'Aula 101',
  cupo: 30,
  estado: 'ACTIVO',
})

async function loadData() {
  loading.value = true
  error.value = null
  try {
    const [gRes, cRes, pRes, dRes] = await Promise.all([
      gruposApi.list(),
      cursosApi.list(),
      periodosApi.list(),
      docentesApi.list(),
    ])
    grupos.value = gRes.data.data
    cursos.value = cRes.data.data
    periodos.value = pRes.data.data
    docentes.value = dRes.data.data

    if (cursos.value.length > 0) form.value.curso_id = cursos.value[0].id
    if (periodos.value.length > 0) form.value.periodo_id = periodos.value[0].id
    if (docentes.value.length > 0) form.value.docente_id = docentes.value[0].id
  } catch {
    error.value = 'Error al cargar los grupos de curso.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingId.value = null
  form.value = {
    curso_id: cursos.value[0]?.id || 0,
    periodo_id: periodos.value[0]?.id || 0,
    docente_id: docentes.value[0]?.id || 0,
    codigo_grupo: 'A',
    turno: 'MANANA',
    aula: 'Aula 101',
    cupo: 30,
    estado: 'ACTIVO',
  }
  modalOpen.value = true
}

function openEditModal(grupo: GrupoCurso) {
  editingId.value = grupo.id
  form.value = {
    curso_id: grupo.curso_id,
    periodo_id: grupo.periodo_id,
    docente_id: grupo.docente_id,
    codigo_grupo: grupo.codigo_grupo || 'A',
    turno: grupo.turno || 'MANANA',
    aula: grupo.aula || '',
    cupo: grupo.cupo || 30,
    estado: grupo.estado,
  }
  modalOpen.value = true
}

async function handleSubmit() {
  try {
    if (editingId.value) {
      await gruposApi.update(editingId.value, form.value)
    } else {
      await gruposApi.create(form.value)
    }
    modalOpen.value = false
    await loadData()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar el grupo.')
  }
}

async function handleDelete(id: number) {
  if (!confirm('¿Estás seguro de desactivar este grupo de curso?')) return
  try {
    await gruposApi.remove(id)
    await loadData()
  } catch {
    alert('Error al desactivar grupo.')
  }
}

onMounted(loadData)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Grupos de Curso</h1>
        <p class="text-sm text-gray-500">Instancias de cursos dictadas por docentes en cada período académico.</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        + Nuevo Grupo
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
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Curso</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Grupo / Turno</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Período</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Docente a Cargo</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Cupo</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
            <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-if="grupos.length === 0">
            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No hay grupos de curso registrados.</td>
          </tr>
          <tr v-for="grupo in grupos" :key="grupo.id" class="hover:bg-gray-50">
            <td class="whitespace-nowrap px-6 py-4">
              <div class="font-semibold text-gray-900">{{ grupo.curso?.nombre }}</div>
              <div class="font-mono text-xs text-gray-500">{{ grupo.curso?.codigo }}</div>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
              Grupo {{ grupo.codigo_grupo || 'A' }} ({{ grupo.turno }})
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ grupo.periodo?.nombre }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
              {{ grupo.docente?.usuario?.nombres }} {{ grupo.docente?.usuario?.apellido_paterno }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
              {{ grupo.cupo || '30' }} vacantes
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm">
              <span :class="[
                'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                grupo.estado === 'ACTIVO' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
              ]">
                {{ grupo.estado }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
              <button @click="openEditModal(grupo)" class="mr-3 text-primary-600 hover:text-primary-900">Editar</button>
              <button @click="handleDelete(grupo.id)" class="text-red-600 hover:text-red-900">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Form -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-lg font-bold text-gray-900">{{ editingId ? 'Editar Grupo' : 'Nuevo Grupo de Curso' }}</h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Curso</label>
            <select v-model="form.curso_id" required class="input-field mt-1">
              <option v-for="c in cursos" :key="c.id" :value="c.id">{{ c.codigo }} - {{ c.nombre }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Período</label>
              <select v-model="form.periodo_id" required class="input-field mt-1">
                <option v-for="p in periodos" :key="p.id" :value="p.id">{{ p.codigo }} - {{ p.nombre }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Docente Asignado</label>
              <select v-model="form.docente_id" required class="input-field mt-1">
                <option v-for="d in docentes" :key="d.id" :value="d.id">
                  {{ d.usuario?.nombres }} {{ d.usuario?.apellido_paterno }}
                </option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Código Grupo</label>
              <input v-model="form.codigo_grupo" required class="input-field mt-1" placeholder="A" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Turno</label>
              <select v-model="form.turno" class="input-field mt-1">
                <option value="MANANA">MANANA</option>
                <option value="TARDE">TARDE</option>
                <option value="NOCHE">NOCHE</option>
                <option value="VIRTUAL">VIRTUAL</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Cupo Máximo</label>
              <input v-model.number="form.cupo" type="number" min="1" max="200" required class="input-field mt-1" />
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="modalOpen = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
