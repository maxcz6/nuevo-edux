<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { docentesApi } from '@/api'
import { useProgramaStore } from '@/stores/programa'
import { useAuthStore } from '@/stores/auth'
import type { Docente } from '@/types'

const route = useRoute()
const programaStore = useProgramaStore()
const authStore = useAuthStore()
const isSuperAdmin = computed(() => authStore.hasRole('SUPERADMIN'))

const docentes = ref<Docente[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const searchQuery = ref('')
const selectedFilterProg = ref<string>('')

const form = ref({
  nombres: '',
  apellido_paterno: '',
  apellido_materno: '',
  email: '',
  username: '',
  password: '',
  codigo_docente: '',
  especialidad: '',
  grado_academico: 'LICENCIADO',
  tipo_contrato: 'TIEMPO_COMPLETO',
  unidad_academica_id: null as number | null,
})

async function loadDocentes() {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, unknown> = {}
    const progId = selectedFilterProg.value || route.query.programa_id || programaStore.selectedProgramaId
    if (progId) {
      params.programa_id = progId
    }
    if (searchQuery.value) {
      params.search = searchQuery.value
    }
    const res = await docentesApi.list(params)
    docentes.value = res.data.data
  } catch {
    error.value = 'Error al cargar los docentes.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingId.value = null
  form.value = {
    nombres: '',
    apellido_paterno: '',
    apellido_materno: '',
    email: '',
    username: '',
    password: '',
    codigo_docente: '',
    especialidad: '',
    grado_academico: 'LICENCIADO',
    tipo_contrato: 'TIEMPO_COMPLETO',
    unidad_academica_id: programaStore.selectedProgramaId ?? null,
  }
  modalOpen.value = true
}

function openEditModal(docente: Docente) {
  editingId.value = docente.id
  form.value = {
    nombres: docente.usuario?.nombres || '',
    apellido_paterno: docente.usuario?.apellido_paterno || '',
    apellido_materno: docente.usuario?.apellido_materno || '',
    email: docente.usuario?.email || '',
    username: docente.usuario?.username || '',
    password: '',
    codigo_docente: docente.codigo_docente || '',
    especialidad: docente.especialidad || '',
    grado_academico: docente.grado_academico || '',
    tipo_contrato: docente.tipo_contrato || 'TIEMPO_COMPLETO',
    unidad_academica_id: docente.unidad_academica_id ?? null,
  }
  modalOpen.value = true
}

async function handleSubmit() {
  try {
    if (editingId.value) {
      await docentesApi.update(editingId.value, form.value)
    } else {
      await docentesApi.create(form.value)
    }
    modalOpen.value = false
    await loadDocentes()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar docente.')
  }
}

async function handleDeactivate(id: number) {
  if (!confirm('¿Estás seguro de desactivar este docente?')) return
  try {
    await docentesApi.remove(id, { action: 'desactivar' })
    await loadDocentes()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al desactivar docente.')
  }
}

async function handleDelete(id: number) {
  if (!confirm('¿Estás seguro de eliminar PERMANENTEMENTE este docente? Esta acción no se puede deshacer.')) return
  try {
    await docentesApi.remove(id, { force: true })
    await loadDocentes()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al eliminar docente.')
  }
}

watch(() => programaStore.selectedProgramaId, (newVal) => {
  selectedFilterProg.value = newVal ? String(newVal) : ''
  loadDocentes()
})

watch(() => route.query.programa_id, (newVal) => {
  if (newVal) {
    selectedFilterProg.value = String(newVal)
    loadDocentes()
  }
})

onMounted(async () => {
  await programaStore.fetchProgramas()
  if (route.query.programa_id) {
    selectedFilterProg.value = String(route.query.programa_id)
  } else if (programaStore.selectedProgramaId) {
    selectedFilterProg.value = String(programaStore.selectedProgramaId)
  }
  await loadDocentes()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Planta Docente</h1>
        <p class="text-sm text-gray-500">Gestión de profesores e instructores asignados a los programas de estudio.</p>
      </div>
      <button @click="openCreateModal" class="btn-primary flex items-center gap-2">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Docente
      </button>
    </div>

    <!-- Filters Bar -->
    <div class="card p-4 flex flex-col sm:flex-row items-center gap-4">
      <div class="relative flex-1 w-full">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </span>
        <input
          v-model="searchQuery"
          @keyup.enter="loadDocentes"
          type="text"
          placeholder="Buscar docente por nombre o especialidad..."
          class="input-field pl-10 w-full"
        />
      </div>
      <div class="w-full sm:w-64">
        <select
          v-model="selectedFilterProg"
          @change="loadDocentes"
          class="input-field w-full cursor-pointer text-xs"
        >
          <option value="">Todos los Programas</option>
          <option v-for="prog in programaStore.programas" :key="prog.id" :value="String(prog.id)">
            {{ prog.codigo }} - {{ prog.nombre }}
          </option>
        </select>
      </div>
      <button
        type="button"
        @click="loadDocentes"
        class="btn-secondary whitespace-nowrap"
      >
        Buscar
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="rounded-md bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Docente</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Programa de Estudios</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Especialidad</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
            <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-if="docentes.length === 0">
            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No hay docentes registrados para el filtro seleccionado.</td>
          </tr>
          <tr v-for="docente in docentes" :key="docente.id" class="hover:bg-gray-50">
            <td class="whitespace-nowrap px-6 py-4">
              <div class="text-sm font-semibold text-gray-900">
                {{ docente.usuario?.nombres }} {{ docente.usuario?.apellido_paterno }}
              </div>
              <div class="text-xs text-gray-500">{{ docente.usuario?.username }}</div>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm">
              <span v-if="docente.programa" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                {{ docente.programa.nombre }}
              </span>
              <span v-else class="text-gray-400 text-xs italic">Sin asignar</span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
              {{ docente.especialidad || 'General' }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 font-mono text-xs">
              {{ docente.usuario?.email }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm">
              <span :class="[
                'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                docente.estado === 'ACTIVO' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
              ]">
                {{ docente.estado }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
              <button @click="openEditModal(docente)" class="mr-3 text-primary-600 hover:text-primary-900">Editar</button>
              <template v-if="isSuperAdmin">
                <button @click="handleDeactivate(docente.id)" data-testid="desactivar-docente-btn" class="mr-3 text-amber-600 hover:text-amber-900">Desactivar</button>
                <button @click="handleDelete(docente.id)" data-testid="eliminar-docente-btn" class="text-red-600 hover:text-red-900">Eliminar</button>
              </template>
              <template v-else>
                <button @click="handleDeactivate(docente.id)" data-testid="desactivar-docente-btn" class="text-amber-600 hover:text-amber-900">Desactivar</button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Form -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-gray-900">{{ editingId ? 'Editar Docente' : 'Nuevo Docente' }}</h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-700">Nombres *</label>
              <input v-model="form.nombres" required class="input-field mt-1 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700">Apellido Paterno *</label>
              <input v-model="form.apellido_paterno" required class="input-field mt-1 w-full" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-700">Email *</label>
              <input v-model="form.email" type="email" required class="input-field mt-1 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700">Usuario (Login) *</label>
              <input v-model="form.username" required class="input-field mt-1 w-full" />
            </div>
          </div>
          <div v-if="!editingId">
            <label class="block text-xs font-medium text-gray-700">Contraseña inicial *</label>
            <input v-model="form.password" type="password" required class="input-field mt-1 w-full" />
          </div>
          <div v-if="!editingId">
            <label class="block text-xs font-medium text-gray-700">Programa de Estudios (Carrera)</label>
            <select v-model="form.unidad_academica_id" class="input-field mt-1 w-full cursor-pointer">
              <option :value="null">-- Seleccionar Programa --</option>
              <option v-for="prog in programaStore.programas" :key="prog.id" :value="prog.id">
                {{ prog.codigo }} - {{ prog.nombre }}
              </option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-700">Especialidad</label>
              <input v-model="form.especialidad" class="input-field mt-1 w-full" placeholder="Ej: Enfermería, Redes..." />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700">Grado Académico</label>
              <input v-model="form.grado_academico" class="input-field mt-1 w-full" placeholder="Ej: Licenciado, Ingeniero" />
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
            <button type="button" @click="modalOpen = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
