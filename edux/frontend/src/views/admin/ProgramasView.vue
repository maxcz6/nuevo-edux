<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { programasApi } from '@/api'
import { useProgramaStore } from '@/stores/programa'
import { useAuthStore } from '@/stores/auth'
import type { UnidadAcademica } from '@/types'
import BaseModal from '@/components/ui/BaseModal.vue'

const router       = useRouter()
const programaStore = useProgramaStore()
const authStore    = useAuthStore()

// Solo SUPERADMIN y ADMINISTRADOR pueden crear/editar programas
const canEdit = computed(() =>
  authStore.hasRole('SUPERADMIN') || authStore.hasRole('ADMINISTRADOR')
)

const programas    = ref<UnidadAcademica[]>([])
const loading      = ref(true)
const error        = ref<string | null>(null)
const searchQuery  = ref('')
const modalOpen    = ref(false)
const saving       = ref(false)
const modalMode    = ref<'create' | 'edit'>('create')
const editingId    = ref<number | null>(null)
const formErrors   = ref<Record<string, string[]>>({})

const form = ref({
  codigo: '',
  nombre: '',
  tipo:   'PROGRAMA_ESTUDIOS' as const,
  estado: 'ACTIVO',
})

const filteredProgramas = computed(() => {
  if (!searchQuery.value) return programas.value
  const q = searchQuery.value.toLowerCase()
  return programas.value.filter(p =>
    p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q)
  )
})

async function loadProgramas() {
  loading.value = true
  error.value   = null
  try {
    const res = await programasApi.list({ tipo: 'PROGRAMA_ESTUDIOS', per_page: 50 })
    programas.value = res.data.data
  } catch {
    error.value = 'Error al cargar los programas de estudio.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  modalMode.value = 'create'
  editingId.value = null
  form.value = { codigo: '', nombre: '', tipo: 'PROGRAMA_ESTUDIOS', estado: 'ACTIVO' }
  formErrors.value = {}
  modalOpen.value = true
}

function openEditModal(programa: UnidadAcademica) {
  modalMode.value = 'edit'
  editingId.value = programa.id
  form.value = {
    codigo: programa.codigo,
    nombre: programa.nombre,
    tipo: 'PROGRAMA_ESTUDIOS',
    estado: programa.estado,
  }
  formErrors.value = {}
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  formErrors.value = {}
  try {
    if (modalMode.value === 'edit' && editingId.value) {
      await programasApi.update(editingId.value, form.value)
    } else {
      await programasApi.create(form.value)
    }
    modalOpen.value = false
    await loadProgramas()
    await programaStore.fetchProgramas(true)
  } catch (error: unknown) {
    const response = (error as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } }).response
    if (response?.status === 422) {
      formErrors.value = response.data?.errors ?? {}
    } else {
      alert(response?.data?.message ?? 'Error al guardar el programa de estudio.')
    }
  } finally {
    saving.value = false
  }
}

function viewDocentes(programaId: number) {
  programaStore.setPrograma(programaId)
  router.push({ path: '/admin/docentes', query: { programa_id: String(programaId) } })
}

function viewEstudiantes(programaId: number) {
  programaStore.setPrograma(programaId)
  router.push({ path: '/admin/estudiantes', query: { programa_id: String(programaId) } })
}

onMounted(() => loadProgramas())
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-800">
            IESTP Santiago Antúnez de Mayolo
          </span>
          <span class="text-xs text-gray-500">Palián, Huancayo</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">Programas de Estudio</h1>
        <p class="text-sm text-gray-500">Carreras profesionales técnicas y unidades de formación tecnológica.</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          v-if="canEdit"
          type="button"
          @click="openCreateModal"
          class="btn-primary flex items-center gap-2"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nuevo Programa
        </button>
      </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card p-4 flex flex-col sm:flex-row items-center gap-4">
      <div class="relative flex-1 w-full">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </span>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar programa por nombre o código (ej. Enfermería, PROG-DPW)..."
          class="input-field pl-10 w-full"
        />
      </div>
      <div class="text-xs font-medium text-gray-500 whitespace-nowrap">
        Mostrando {{ filteredProgramas.length }} de {{ programas.length }} programas
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-500">Cargando programas de estudio...</p>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="p-4 rounded-lg bg-red-50 text-red-700 text-sm border border-red-200">
      {{ error }}
    </div>

    <!-- Grid of Study Programs -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="prog in filteredProgramas"
        :key="prog.id"
        class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs hover:shadow-md transition-all flex flex-col justify-between"
      >
        <div>
          <!-- Top Tag & Code -->
          <div class="flex items-center justify-between gap-2 mb-3">
            <span class="px-2.5 py-1 text-xs font-mono font-bold rounded-md bg-blue-50 text-blue-700 border border-blue-200">
              {{ prog.codigo }}
            </span>
            <span
              class="px-2 py-0.5 text-xs font-semibold rounded-full"
              :class="prog.estado === 'ACTIVO' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'"
            >
              {{ prog.estado }}
            </span>
          </div>

          <!-- Title -->
          <h2 class="text-lg font-bold text-gray-900 leading-snug">
            {{ prog.nombre }}
          </h2>
          <p class="text-xs text-gray-500 mt-1">
            Educación Superior Tecnológica • Plan Modular 2026
          </p>

          <!-- Metrics badges -->
          <div class="grid grid-cols-3 gap-2 mt-4 p-3 bg-gray-50 rounded-lg border border-gray-100 text-center">
            <div>
              <div class="text-base font-bold text-gray-900">{{ prog.docentes_count ?? 0 }}</div>
              <div class="text-xs text-gray-500">Docentes</div>
            </div>
            <div class="border-x border-gray-200">
              <div class="text-base font-bold text-gray-900">{{ prog.estudiantes_count ?? 0 }}</div>
              <div class="text-xs text-gray-500">Estudiantes</div>
            </div>
            <div>
              <div class="text-base font-bold text-gray-900">{{ prog.cursos_count ?? 0 }}</div>
              <div class="text-xs text-gray-500">Cursos</div>
            </div>
          </div>
        </div>

        <!-- Quick actions -->
        <div class="mt-5 pt-4 border-t border-gray-100 flex items-center gap-2">
          <button
            v-if="canEdit"
            type="button"
            :data-testid="`editar-programa-${prog.id}`"
            @click="openEditModal(prog)"
            class="flex-1 py-1.5 px-2 text-xs font-semibold text-primary-700 bg-primary-50 hover:bg-primary-100 border border-primary-200 rounded-lg text-center transition focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            Editar
          </button>
          <button
            type="button"
            @click="viewDocentes(prog.id)"
            class="flex-1 py-1.5 px-2 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg text-center transition focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            Docentes
          </button>
          <button
            type="button"
            @click="viewEstudiantes(prog.id)"
            class="flex-1 py-1.5 px-2 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg text-center transition focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            Estudiantes
          </button>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <BaseModal
      :open="modalOpen"
      :title="modalMode === 'edit' ? 'Editar Programa de Estudio' : 'Nuevo Programa de Estudio'"
      @close="modalOpen = false"
    >
        <form @submit.prevent="handleSave" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Código del Programa *</label>
            <input
              v-model="form.codigo"
              data-testid="programa-codigo-input"
              type="text"
              required
              maxlength="30"
              placeholder="ej. PROG-COMP"
              class="input-field w-full"
            />
            <p v-if="formErrors.codigo" class="mt-1 text-xs text-red-600">{{ formErrors.codigo[0] }}</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Nombre de la Carrera / Programa *</label>
            <input
              v-model="form.nombre"
              data-testid="programa-nombre-input"
              type="text"
              required
              maxlength="200"
              placeholder="ej. Computación e Informática"
              class="input-field w-full"
            />
            <p v-if="formErrors.nombre" class="mt-1 text-xs text-red-600">{{ formErrors.nombre[0] }}</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Estado *</label>
            <select v-model="form.estado" data-testid="programa-estado-select" class="input-field w-full" required>
              <option value="ACTIVO">Activo</option>
              <option value="INACTIVO">Inactivo</option>
            </select>
            <p v-if="formErrors.estado" class="mt-1 text-xs text-red-600">{{ formErrors.estado[0] }}</p>
          </div>
          <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
            <button
              type="button"
              @click="modalOpen = false"
              class="btn-secondary"
              :disabled="saving"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="saving"
              data-testid="guardar-programa-btn"
              class="btn-primary"
            >
              {{ saving ? 'Guardando…' : modalMode === 'edit' ? 'Guardar cambios' : 'Crear Programa' }}
            </button>
          </div>
        </form>
    </BaseModal>
  </div>
</template>
