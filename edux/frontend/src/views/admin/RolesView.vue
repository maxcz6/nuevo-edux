<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import http from '@/api/http'

const authStore    = useAuthStore()
const isSuperAdmin = authStore.hasRole('SUPERADMIN')

interface Rol { id: number; nombre: string; descripcion: string; es_sistema: boolean; estado: boolean; permisos_count?: number }

const roles         = ref<Rol[]>([])
const loading       = ref(true)
const error         = ref<string | null>(null)
const searchQuery   = ref('')
const filterEstado  = ref<'ALL' | 'ACTIVO' | 'INACTIVO'>('ALL')

const filteredRoles = computed(() => {
  return roles.value.filter(rol => {
    // Filtro por estado
    if (filterEstado.value === 'ACTIVO' && !rol.estado) return false
    if (filterEstado.value === 'INACTIVO' && rol.estado) return false
    
    // Filtro por búsqueda
    if (!searchQuery.value.trim()) return true
    const q = searchQuery.value.toLowerCase().trim()
    return (
      rol.nombre.toLowerCase().includes(q) ||
      (rol.descripcion && rol.descripcion.toLowerCase().includes(q))
    )
  })
})

async function loadRoles() {
  loading.value = true
  error.value   = null
  try {
    const res = await http.get('/admin/roles')
    roles.value = res.data.data ?? []
  } catch {
    error.value = 'No se pudieron cargar los roles.'
  } finally {
    loading.value = false
  }
}

onMounted(() => loadRoles())
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Roles y Permisos</h1>
        <p class="text-sm text-gray-500 mt-1">Roles oficiales del sistema académico y matriz de accesos.</p>
      </div>
      <span v-if="isSuperAdmin" class="text-xs font-bold px-2.5 py-1 rounded-full bg-purple-100 text-purple-800 border border-purple-200 self-start sm:self-auto">
        Solo Superadmin puede crear/editar roles
      </span>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card p-4 bg-white border border-gray-200 rounded-xl flex flex-col sm:flex-row items-center gap-3 shadow-xs">
      <div class="relative flex-1 w-full">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </span>
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Buscar rol por nombre o descripción (ej. Estudiante, Docente, Caja)..."
          class="input-field pl-9 pr-8 text-sm w-full"
        />
        <button
          v-if="searchQuery"
          type="button"
          @click="searchQuery = ''"
          class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600"
          aria-label="Borrar búsqueda"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="w-full sm:w-48">
        <select v-model="filterEstado" class="input-field text-sm w-full cursor-pointer">
          <option value="ALL">Todos los Estados</option>
          <option value="ACTIVO">Solo Activos</option>
          <option value="INACTIVO">Solo Inactivos</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <div v-else-if="error" class="p-4 rounded-lg bg-red-50 text-red-700 text-sm border border-red-200">{{ error }}</div>

    <div v-else-if="filteredRoles.length === 0" class="card p-8 text-center bg-white border border-gray-200 rounded-xl">
      <p class="text-sm text-gray-500">No se encontraron roles con el criterio de búsqueda especificado.</p>
      <button @click="searchQuery = ''; filterEstado = 'ALL'" class="mt-3 text-xs text-primary-600 hover:underline font-semibold">
        Restablecer búsqueda
      </button>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="rol in filteredRoles"
        :key="rol.id"
        class="bg-white rounded-xl border border-gray-200 p-5 shadow-xs hover:border-gray-300 transition-colors"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-gray-100 text-gray-800 border border-gray-200">{{ rol.nombre }}</span>
          <span
            class="text-xs font-semibold px-2 py-0.5 rounded-full"
            :class="rol.estado ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
          >{{ rol.estado ? 'Activo' : 'Inactivo' }}</span>
        </div>
        <p class="text-sm text-gray-600 mt-2">{{ rol.descripcion }}</p>
        <div class="mt-4 flex items-center gap-2 pt-3 border-t border-gray-100 text-xs text-gray-500">
          <span v-if="rol.es_sistema" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
            <svg class="h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            Rol del sistema
          </span>
        </div>
      </div>
    </div>

    <div v-if="!isSuperAdmin" class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-sm">
      Solo el Superadministrador puede crear, editar o eliminar roles del sistema.
    </div>
  </div>
</template>
