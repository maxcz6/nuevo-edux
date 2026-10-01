<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { cursosApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import type { Curso } from '@/types'

const authStore = useAuthStore()
const isSuperAdmin = computed(() => authStore.hasRole('SUPERADMIN'))

const cursos = ref<Curso[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const modalOpen = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  codigo: '',
  nombre: '',
  descripcion: '',
  creditos: 3,
  horas_semanales: 4,
  estado: 'ACTIVO',
})

async function loadCursos() {
  loading.value = true
  error.value = null
  try {
    const res = await cursosApi.list()
    cursos.value = res.data.data
  } catch {
    error.value = 'Error al cargar los cursos.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingId.value = null
  form.value = {
    codigo: '',
    nombre: '',
    descripcion: '',
    creditos: 3,
    horas_semanales: 4,
    estado: 'ACTIVO',
  }
  modalOpen.value = true
}

function openEditModal(curso: Curso) {
  editingId.value = curso.id
  form.value = {
    codigo: curso.codigo,
    nombre: curso.nombre,
    descripcion: curso.descripcion || '',
    creditos: curso.creditos ?? 0,
    horas_semanales: curso.horas_semanales ?? 0,
    estado: curso.estado,
  }
  modalOpen.value = true
}

async function handleSubmit() {
  try {
    if (editingId.value) {
      await cursosApi.update(editingId.value, form.value)
    } else {
      await cursosApi.create(form.value)
    }
    modalOpen.value = false
    await loadCursos()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar curso.')
  }
}

async function handleDeactivate(id: number) {
  if (!confirm('¿Estás seguro de desactivar este curso?')) return
  try {
    await cursosApi.remove(id, { action: 'desactivar' })
    await loadCursos()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al desactivar curso.')
  }
}

async function handleDelete(id: number) {
  if (!confirm('¿Estás seguro de eliminar PERMANENTEMENTE este curso? Esta acción no se puede deshacer.')) return
  try {
    await cursosApi.remove(id, { force: true })
    await loadCursos()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al eliminar curso.')
  }
}

onMounted(loadCursos)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Catálogo de Cursos</h1>
        <p class="text-sm text-gray-500">Malla curricular y asignaturas disponibles.</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        + Nuevo Curso
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
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Código</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Créditos</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Horas/Sem</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
            <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-if="cursos.length === 0">
            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No hay cursos registrados.</td>
          </tr>
          <tr v-for="curso in cursos" :key="curso.id" class="hover:bg-gray-50">
            <td class="whitespace-nowrap px-6 py-4 font-mono text-sm font-semibold text-gray-900">{{ curso.codigo }}</td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ curso.nombre }}</td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ curso.creditos }} cr.</td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ curso.horas_semanales }} hrs</td>
            <td class="whitespace-nowrap px-6 py-4 text-sm">
              <span :class="[
                'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                curso.estado === 'ACTIVO' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
              ]">
                {{ curso.estado }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
              <button @click="openEditModal(curso)" class="mr-3 text-primary-600 hover:text-primary-900">Editar</button>
              <template v-if="isSuperAdmin">
                <button @click="handleDeactivate(curso.id)" data-testid="desactivar-curso-btn" class="mr-3 text-amber-600 hover:text-amber-900">Desactivar</button>
                <button @click="handleDelete(curso.id)" data-testid="eliminar-curso-btn" class="text-red-600 hover:text-red-900">Eliminar</button>
              </template>
              <template v-else>
                <button @click="handleDeactivate(curso.id)" data-testid="desactivar-curso-btn" class="text-amber-600 hover:text-amber-900">Desactivar</button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Form -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-lg font-bold text-gray-900">{{ editingId ? 'Editar Curso' : 'Nuevo Curso' }}</h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Código</label>
            <input v-model="form.codigo" required class="input-field mt-1" placeholder="Ej: CUR-001" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nombre de la Asignatura</label>
            <input v-model="form.nombre" required class="input-field mt-1" placeholder="Ej: Programación Web I" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Descripción</label>
            <textarea v-model="form.descripcion" rows="2" class="input-field mt-1"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Créditos</label>
              <input v-model.number="form.creditos" type="number" min="1" max="20" required class="input-field mt-1" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Horas Semanales</label>
              <input v-model.number="form.horas_semanales" type="number" min="1" max="40" required class="input-field mt-1" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Estado</label>
            <select v-model="form.estado" class="input-field mt-1">
              <option value="ACTIVO">ACTIVO</option>
              <option value="INACTIVO">INACTIVO</option>
            </select>
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
