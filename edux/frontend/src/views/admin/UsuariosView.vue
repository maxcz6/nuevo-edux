<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usuariosApi, rolesApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import type { Usuario, Rol } from '@/types'
import BaseTable from '@/components/ui/BaseTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import UsuarioPasswordModal from '@/components/admin/UsuarioPasswordModal.vue'

const authStore = useAuthStore()

const users = ref<Usuario[]>([])
const loading = ref(true)
const meta = ref<any>(null)
const currentPage = ref(1)

const roles = ref<Rol[]>([])
const loadingRoles = ref(false)

// ── Búsqueda y Filtros ────────────────────────────────────────────────────────
const searchQuery = ref('')
const selectedRole = ref('')
const selectedEstado = ref('')
const activeQuickTab = ref('ALL') // 'ALL' | 'ESTUDIANTE' | 'DOCENTE' | 'COORDINADOR' | 'STAFF' | 'ADMIN'

const isModalOpen = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const formData = ref<Partial<Usuario> & { password?: string; rol_id?: number }>({})
const formErrors = ref<Record<string, string[]>>({})
const submitting = ref(false)

// Modal de cambio de contraseña
const isPasswordModalOpen = ref(false)
const selectedUserForPassword = ref<Usuario | null>(null)

const columns = [
  { key: 'nombre', label: 'Nombre Completo' },
  { key: 'email', label: 'Email' },
  { key: 'username', label: 'Usuario / Doc' },
  { key: 'estado', label: 'Estado' },
  { key: 'roles', label: 'Roles' },
]

// Filtrar roles disponibles: ADMINISTRADOR no puede asignar SUPERADMIN
const availableRoles = computed(() => {
  if (authStore.hasRole('SUPERADMIN')) {
    return roles.value
  }
  return roles.value.filter(r => r.nombre !== 'SUPERADMIN')
})

const hasActiveFilters = computed(() => {
  return !!searchQuery.value.trim() || !!selectedRole.value || !!selectedEstado.value
})

async function loadRoles() {
  loadingRoles.value = true
  try {
    const res = await rolesApi.list({ per_page: 50 })
    roles.value = (res.data as any).data ?? []
  } catch (error) {
    console.error('Failed to load roles', error)
  } finally {
    loadingRoles.value = false
  }
}

async function loadUsers(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const params: Record<string, unknown> = { page }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }
    if (selectedRole.value) {
      params.rol = selectedRole.value
    }
    if (selectedEstado.value) {
      params.estado = selectedEstado.value
    }
    const res = await usuariosApi.list(params)
    users.value = res.data.data
    meta.value = res.data.meta
  } catch (error) {
    console.error('Failed to load users', error)
  } finally {
    loading.value = false
  }
}

// Búsqueda con debounce al tipear
let searchDebounceTimer: any = null
function handleSearchInput() {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    loadUsers(1)
  }, 350)
}

function handleFilterChange() {
  // Sincronizar quick tab si coincide
  if (selectedRole.value === 'ESTUDIANTE') {
    activeQuickTab.value = 'ESTUDIANTE'
  } else if (selectedRole.value === 'DOCENTE') {
    activeQuickTab.value = 'DOCENTE'
  } else if (selectedRole.value === 'COORDINADOR') {
    activeQuickTab.value = 'COORDINADOR'
  } else if (selectedRole.value === '') {
    activeQuickTab.value = 'ALL'
  } else {
    activeQuickTab.value = 'CUSTOM'
  }
  loadUsers(1)
}

function setQuickFilter(tab: string) {
  activeQuickTab.value = tab
  if (tab === 'ALL') {
    selectedRole.value = ''
  } else if (tab === 'ESTUDIANTE') {
    selectedRole.value = 'ESTUDIANTE'
  } else if (tab === 'DOCENTE') {
    selectedRole.value = 'DOCENTE'
  } else if (tab === 'COORDINADOR') {
    selectedRole.value = 'COORDINADOR'
  } else if (tab === 'ADMINISTRADOR') {
    selectedRole.value = 'ADMINISTRADOR'
  }
  loadUsers(1)
}

function clearFilters() {
  searchQuery.value = ''
  selectedRole.value = ''
  selectedEstado.value = ''
  activeQuickTab.value = 'ALL'
  loadUsers(1)
}

function openCreateModal() {
  modalMode.value = 'create'
  formData.value = {
    nombres: '',
    apellido_paterno: '',
    apellido_materno: '',
    email: '',
    username: '',
    password: '',
    estado: 'ACTIVO',
    rol_id: undefined
  }
  formErrors.value = {}
  isModalOpen.value = true
}

function openEditModal(user: Usuario) {
  modalMode.value = 'edit'
  formData.value = {
    ...user,
    rol_id: user.roles?.[0]?.id,
    password: ''
  }
  formErrors.value = {}
  isModalOpen.value = true
}

function openPasswordModal(user: Usuario) {
  selectedUserForPassword.value = user
  isPasswordModalOpen.value = true
}

async function handleSubmit() {
  formErrors.value = {}

  if (!formData.value.rol_id) {
    formErrors.value = { rol_id: ['Debe seleccionar un rol para el usuario.'] }
    return
  }

  submitting.value = true
  try {
    if (modalMode.value === 'create') {
      await usuariosApi.create({
        nombres: formData.value.nombres,
        apellido_paterno: formData.value.apellido_paterno,
        apellido_materno: formData.value.apellido_materno || null,
        email: formData.value.email,
        username: formData.value.username,
        password: formData.value.password,
        rol_id: formData.value.rol_id,
      })
    } else {
      await usuariosApi.update(formData.value.id!, {
        nombres: formData.value.nombres,
        apellido_paterno: formData.value.apellido_paterno,
        apellido_materno: formData.value.apellido_materno || null,
        email: formData.value.email,
        username: formData.value.username,
        estado: formData.value.estado,
        rol_id: formData.value.rol_id,
      })
    }
    isModalOpen.value = false
    loadUsers(currentPage.value)
  } catch (err: any) {
    if (err.response?.status === 422) {
      formErrors.value = err.response.data.errors || {}
    } else {
      alert(err.response?.data?.message || 'Error guardando usuario')
    }
  } finally {
    submitting.value = false
  }
}

async function handleDeactivate(user: Usuario) {
  const rolName = user.roles?.[0]?.nombre ? `(${user.roles[0].nombre})` : ''
  if (!window.confirm(`¿Estás seguro de desactivar al usuario ${user.nombres} ${user.apellido_paterno} ${rolName}?`)) return
  try {
    await usuariosApi.remove(user.id, { action: 'desactivar' })
    await loadUsers(currentPage.value)
  } catch (err: any) {
    alert(err.response?.data?.message || 'Error desactivando usuario')
  }
}

async function handleActivate(user: Usuario) {
  const rolName = user.roles?.[0]?.nombre ? `(${user.roles[0].nombre})` : ''
  if (!window.confirm(`¿Deseas activar nuevamente al usuario ${user.nombres} ${user.apellido_paterno} ${rolName}?`)) return
  try {
    await usuariosApi.update(user.id, { estado: 'ACTIVO' })
    await loadUsers(currentPage.value)
  } catch (err: any) {
    alert(err.response?.data?.message || 'Error activando usuario')
  }
}

async function handleDelete(user: Usuario) {
  if (!window.confirm(`¿Estás seguro de eliminar PERMANENTEMENTE a ${user.nombres}? Esta acción no se puede deshacer.`)) return
  try {
    await usuariosApi.remove(user.id, { force: true })
    await loadUsers(currentPage.value)
  } catch (err: any) {
    alert(err.response?.data?.message || 'Error eliminando usuario')
  }
}

function getRoleBadgeClass(roleName: string) {
  switch (roleName) {
    case 'SUPERADMIN':
      return 'bg-purple-100 text-purple-800 ring-purple-200'
    case 'ADMINISTRADOR':
      return 'bg-blue-100 text-blue-800 ring-blue-200'
    case 'COORDINADOR':
      return 'bg-teal-100 text-teal-800 ring-teal-200'
    case 'DOCENTE':
      return 'bg-emerald-100 text-emerald-800 ring-emerald-200'
    case 'ESTUDIANTE':
      return 'bg-sky-100 text-sky-800 ring-sky-300 font-semibold'
    case 'SECRETARIA':
    case 'CAJA':
    case 'MESA_DE_PARTES':
      return 'bg-amber-100 text-amber-800 ring-amber-200'
    default:
      return 'bg-gray-100 text-gray-700 ring-gray-200'
  }
}

onMounted(() => {
  loadRoles()
  loadUsers()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Usuarios del Sistema</h1>
        <p class="text-sm text-gray-500 mt-1">
          Gestión centralizada de cuentas de usuario, asignación de roles y estados de acceso.
        </p>
      </div>
      <button @click="openCreateModal" data-testid="nuevo-usuario-btn" class="btn-primary flex items-center gap-2 self-start sm:self-auto">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Usuario
      </button>
    </div>

    <!-- Quick Filter Tabs (Píldoras de Acceso Rápido por Rol) -->
    <div class="flex flex-wrap items-center gap-2 pb-1 border-b border-gray-200" aria-label="Filtrar por rol rápido">
      <button
        type="button"
        @click="setQuickFilter('ALL')"
        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-primary-500"
        :class="activeQuickTab === 'ALL' ? 'bg-gray-900 text-white shadow-xs' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
      >
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span>Todos</span>
      </button>
      <button
        type="button"
        @click="setQuickFilter('ESTUDIANTE')"
        data-testid="filtro-estudiantes-btn"
        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-sky-500"
        :class="activeQuickTab === 'ESTUDIANTE' ? 'bg-sky-700 text-white shadow-xs' : 'bg-sky-50 text-sky-800 border border-sky-200 hover:bg-sky-100'"
      >
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14v6.5" />
        </svg>
        <span>Estudiantes</span>
      </button>
      <button
        type="button"
        @click="setQuickFilter('DOCENTE')"
        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        :class="activeQuickTab === 'DOCENTE' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100'"
      >
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        <span>Docentes</span>
      </button>
      <button
        type="button"
        @click="setQuickFilter('COORDINADOR')"
        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-teal-500"
        :class="activeQuickTab === 'COORDINADOR' ? 'bg-teal-700 text-white shadow-xs' : 'bg-teal-50 text-teal-800 border border-teal-200 hover:bg-teal-100'"
      >
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
        </svg>
        <span>Coordinadores</span>
      </button>
      <button
        type="button"
        @click="setQuickFilter('ADMINISTRADOR')"
        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
        :class="activeQuickTab === 'ADMINISTRADOR' ? 'bg-blue-700 text-white shadow-xs' : 'bg-blue-50 text-blue-800 border border-blue-200 hover:bg-blue-100'"
      >
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <span>Administradores</span>
      </button>
    </div>

    <!-- Search & Filter Controls Card -->
    <div class="card p-4 bg-white shadow-xs border border-gray-200 rounded-xl space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
        <!-- Search Input -->
        <div class="sm:col-span-6 relative">
          <label for="usuario-search" class="sr-only">Buscar usuarios</label>
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </span>
          <input
            id="usuario-search"
            v-model="searchQuery"
            @input="handleSearchInput"
            @keyup.enter="loadUsers(1)"
            data-testid="usuario-search-input"
            type="search"
            placeholder="Buscar por nombre, usuario, email o DNI..."
            class="input-field pl-9 pr-8 text-sm w-full"
          />
          <button
            v-if="searchQuery"
            type="button"
            @click="searchQuery = ''; loadUsers(1)"
            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600"
            aria-label="Borrar búsqueda"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Role Select -->
        <div class="sm:col-span-3">
          <label for="filtro-rol" class="sr-only">Filtrar por Rol</label>
          <select
            id="filtro-rol"
            v-model="selectedRole"
            @change="handleFilterChange"
            data-testid="filtro-rol-select"
            class="input-field text-sm w-full cursor-pointer"
          >
            <option value="">Todos los roles</option>
            <option value="ESTUDIANTE">Estudiante</option>
            <option value="DOCENTE">Docente</option>
            <option value="COORDINADOR">Coordinador</option>
            <option value="SECRETARIA">Secretaría</option>
            <option value="CAJA">Caja</option>
            <option value="MESA_DE_PARTES">Mesa de Partes</option>
            <option value="ADMINISTRADOR">Administrador</option>
            <option v-if="authStore.hasRole('SUPERADMIN')" value="SUPERADMIN">Superadmin</option>
            <option value="APODERADO">Apoderado</option>
          </select>
        </div>

        <!-- Estado Select -->
        <div class="sm:col-span-3 flex items-center gap-2">
          <label for="filtro-estado" class="sr-only">Filtrar por Estado</label>
          <select
            id="filtro-estado"
            v-model="selectedEstado"
            @change="loadUsers(1)"
            data-testid="filtro-estado-select"
            class="input-field text-sm w-full cursor-pointer"
          >
            <option value="">Todos los estados</option>
            <option value="ACTIVO">Activos</option>
            <option value="INACTIVO">Inactivos</option>
          </select>

          <!-- Botón Limpiar filtros -->
          <button
            v-if="hasActiveFilters"
            type="button"
            @click="clearFilters"
            class="px-2.5 py-2 text-xs font-semibold text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition whitespace-nowrap"
            title="Limpiar todos los filtros"
          >
            Limpiar
          </button>
        </div>
      </div>

      <!-- Live Info Bar: concisa y sin redundancia de datos -->
      <div v-if="meta" class="flex items-center justify-between text-xs text-gray-500 pt-2 border-t border-gray-100">
        <span>Mostrando <strong>{{ meta.total }}</strong> {{ meta.total === 1 ? 'usuario' : 'usuarios' }}</span>
        <span v-if="meta.last_page > 1">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      </div>
    </div>

    <!-- Table -->
    <BaseTable
      :columns="columns"
      :rows="users"
      :loading="loading"
      :meta="meta"
      emptyText="No se encontraron usuarios con los filtros o búsqueda especificada."
      @page="loadUsers"
      @edit="openEditModal"
      @deactivate="handleDeactivate"
      @activate="handleActivate"
      @delete="handleDelete"
    >
      <template #default="{ row }">
        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
          <div class="font-medium text-gray-900">
            {{ row.nombres }} {{ row.apellido_paterno }} {{ row.apellido_materno }}
          </div>
          <div v-if="row.documento_numero" class="text-xs text-gray-400 font-mono">
            Doc: {{ row.documento_numero }}
          </div>
        </td>
        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-600">
          {{ row.email }}
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
          <code class="font-mono text-xs bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200">
            {{ row.username }}
          </code>
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-sm">
          <span
            :class="row.estado === 'ACTIVO' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
          >
            <span
              class="h-1.5 w-1.5 rounded-full"
              :class="row.estado === 'ACTIVO' ? 'bg-emerald-500' : 'bg-rose-500'"
            />
            {{ row.estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}
          </span>
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
          <div class="flex flex-wrap gap-1">
            <span
              v-for="rol in row.roles"
              :key="rol.id"
              class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
              :class="getRoleBadgeClass(rol.nombre)"
            >
              {{ rol.nombre }}
            </span>
            <span v-if="!row.roles?.length" class="text-xs text-gray-400 italic">Sin rol</span>
          </div>
        </td>
      </template>

      <template #extra-actions="{ row }">
        <button
          type="button"
          @click="openPasswordModal(row)"
          data-testid="cambiar-password-btn"
          class="mr-2 text-amber-600 hover:text-amber-800 hover:underline text-sm font-medium"
          title="Cambiar contraseña de usuario"
        >
          Contraseña
        </button>
      </template>
    </BaseTable>

    <!-- Modal Crear/Editar Usuario -->
    <BaseModal :open="isModalOpen" @close="isModalOpen = false" :title="modalMode === 'create' ? 'Nuevo Usuario' : 'Editar Usuario'">
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nombres *</label>
            <input v-model="formData.nombres" data-testid="nombres-input" type="text" class="input-field mt-1" required />
            <p v-if="formErrors.nombres" class="mt-1 text-xs text-red-600">{{ formErrors.nombres[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Apellido Paterno *</label>
            <input v-model="formData.apellido_paterno" data-testid="apellido-paterno-input" type="text" class="input-field mt-1" required />
            <p v-if="formErrors.apellido_paterno" class="mt-1 text-xs text-red-600">{{ formErrors.apellido_paterno[0] }}</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Apellido Materno</label>
          <input v-model="formData.apellido_materno" type="text" class="input-field mt-1" />
          <p v-if="formErrors.apellido_materno" class="mt-1 text-xs text-red-600">{{ formErrors.apellido_materno[0] }}</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Email *</label>
          <input v-model="formData.email" data-testid="email-input" type="email" class="input-field mt-1" required />
          <p v-if="formErrors.email" class="mt-1 text-xs text-red-600">{{ formErrors.email[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Username *</label>
          <input v-model="formData.username" data-testid="username-input" type="text" class="input-field mt-1" required />
          <p v-if="formErrors.username" class="mt-1 text-xs text-red-600">{{ formErrors.username[0] }}</p>
        </div>

        <!-- Selector de Rol -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Rol del Usuario *</label>
          <select
            v-model="formData.rol_id"
            data-testid="rol-select"
            class="input-field mt-1 cursor-pointer"
            required
          >
            <option :value="undefined" disabled>Seleccione un rol</option>
            <option v-for="rol in availableRoles" :key="rol.id" :value="rol.id">
              {{ rol.nombre }} {{ rol.descripcion ? `— ${rol.descripcion}` : '' }}
            </option>
          </select>
          <p v-if="formErrors.rol_id" class="mt-1 text-xs text-red-600">{{ formErrors.rol_id[0] }}</p>
        </div>

        <div v-if="modalMode === 'create'">
          <label class="block text-sm font-medium text-gray-700">Password *</label>
          <input v-model="formData.password" data-testid="password-input" type="password" class="input-field mt-1" required />
          <p v-if="formErrors.password" class="mt-1 text-xs text-red-600">{{ formErrors.password[0] }}</p>
          <p class="mt-1 text-xs text-gray-500">Mínimo 8 caracteres.</p>
        </div>

        <div v-if="modalMode === 'edit'">
          <label class="block text-sm font-medium text-gray-700">Estado *</label>
          <select v-model="formData.estado" data-testid="estado-select" class="input-field mt-1 cursor-pointer">
            <option value="ACTIVO">Activo</option>
            <option value="INACTIVO">Inactivo</option>
          </select>
        </div>

        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
          <button type="button" @click="isModalOpen = false" class="btn-secondary">Cancelar</button>
          <button type="submit" :disabled="submitting" data-testid="submit-user-btn" class="btn-primary">
            {{ submitting ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Modal de Cambio de Contraseña -->
    <UsuarioPasswordModal
      v-if="selectedUserForPassword"
      :open="isPasswordModalOpen"
      :usuario="selectedUserForPassword"
      @close="isPasswordModalOpen = false"
      @done="isPasswordModalOpen = false; loadUsers(currentPage)"
    />
  </div>
</template>
