<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { periodosApi } from '@/api'
import type { PeriodoAcademico } from '@/types'

const periodos = ref<PeriodoAcademico[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const modalOpen = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  codigo: '',
  nombre: '',
  tipo_periodo: 'SEMESTRAL',
  fecha_inicio: '',
  fecha_fin: '',
  estado: 'ACTIVO',
})

async function loadPeriodos() {
  loading.value = true
  error.value = null
  try {
    const res = await periodosApi.list()
    periodos.value = res.data.data
  } catch {
    error.value = 'Error al cargar los períodos académicos.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingId.value = null
  form.value = {
    codigo: '',
    nombre: '',
    tipo_periodo: 'SEMESTRAL',
    fecha_inicio: '',
    fecha_fin: '',
    estado: 'ACTIVO',
  }
  modalOpen.value = true
}

function openEditModal(periodo: PeriodoAcademico) {
  editingId.value = periodo.id
  form.value = {
    codigo: periodo.codigo,
    nombre: periodo.nombre,
    tipo_periodo: periodo.tipo_periodo,
    fecha_inicio: periodo.fecha_inicio,
    fecha_fin: periodo.fecha_fin,
    estado: periodo.estado,
  }
  modalOpen.value = true
}

async function handleSubmit() {
  try {
    if (editingId.value) {
      await periodosApi.update(editingId.value, form.value)
    } else {
      await periodosApi.create(form.value)
    }
    modalOpen.value = false
    await loadPeriodos()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar período.')
  }
}

async function handleDelete(id: number) {
  if (!confirm('¿Estás seguro de desactivar este período?')) return
  try {
    await periodosApi.remove(id)
    await loadPeriodos()
  } catch {
    alert('Error al eliminar período.')
  }
}

onMounted(loadPeriodos)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Períodos Académicos</h1>
        <p class="text-sm text-gray-500">Gestión de ciclos y años académicos de la institución.</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        + Nuevo Período
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
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tipo</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Vigencia</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
            <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-if="periodos.length === 0">
            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No hay períodos registrados.</td>
          </tr>
          <tr v-for="periodo in periodos" :key="periodo.id" class="hover:bg-gray-50">
            <td class="whitespace-nowrap px-6 py-4 font-mono text-sm font-semibold text-gray-900">{{ periodo.codigo }}</td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ periodo.nombre }}</td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ periodo.tipo_periodo }}</td>
            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ periodo.fecha_inicio }} al {{ periodo.fecha_fin }}</td>
            <td class="whitespace-nowrap px-6 py-4 text-sm">
              <span :class="[
                'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                periodo.estado === 'ACTIVO' ? 'bg-green-100 text-green-800' :
                periodo.estado === 'PLANIFICADO' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'
              ]">
                {{ periodo.estado }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
              <button @click="openEditModal(periodo)" class="mr-3 text-primary-600 hover:text-primary-900">Editar</button>
              <button @click="handleDelete(periodo.id)" class="text-red-600 hover:text-red-900">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Form -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-lg font-bold text-gray-900">{{ editingId ? 'Editar Período' : 'Nuevo Período' }}</h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Código</label>
            <input v-model="form.codigo" required class="input-field mt-1" placeholder="Ej: 2026-I" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nombre</label>
            <input v-model="form.nombre" required class="input-field mt-1" placeholder="Ej: Semestre 2026-I" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Tipo de Período</label>
            <select v-model="form.tipo_periodo" class="input-field mt-1">
              <option value="SEMESTRAL">SEMESTRAL</option>
              <option value="ANUAL">ANUAL</option>
              <option value="TRIMESTRAL">TRIMESTRAL</option>
              <option value="BIMESTRAL">BIMESTRAL</option>
              <option value="CICLO">CICLO</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Fecha Inicio</label>
              <input v-model="form.fecha_inicio" type="date" required class="input-field mt-1" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Fecha Fin</label>
              <input v-model="form.fecha_fin" type="date" required class="input-field mt-1" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Estado</label>
            <select v-model="form.estado" class="input-field mt-1">
              <option value="PLANIFICADO">PLANIFICADO</option>
              <option value="ACTIVO">ACTIVO</option>
              <option value="FINALIZADO">FINALIZADO</option>
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
