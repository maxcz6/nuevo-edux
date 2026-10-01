<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { estudiantesApi } from '@/api'
import { useProgramaStore } from '@/stores/programa'
import { useAuthStore } from '@/stores/auth'
import type { Estudiante } from '@/types'
import {
  generateEstudianteUsername,
  generateCodigoMatricula,
  generateEstudianteEmail
} from '@/utils/studentGenerators'

const route = useRoute()
const programaStore = useProgramaStore()
const authStore = useAuthStore()
const isSuperAdmin = computed(() => authStore.hasRole('SUPERADMIN'))

const estudiantes = ref<Estudiante[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const searchQuery = ref('')
const selectedFilterProg = ref<string>('')
const selectedFilterEstado = ref<string>('')

const form = ref({
  dni: '',
  nombres: '',
  apellido_paterno: '',
  apellido_materno: '',
  email: '',
  username: '',
  password: '',
  codigo_estudiante: '',
  turno: 'D' as 'D' | 'N',
  fecha_nacimiento: '',
  sexo: 'M',
  unidad_academica_id: null as number | null,
})

const ordenLlegadaCalculado = computed(() => {
  return (estudiantes.value.length || 0) + 1
})

// Auto-completado en tiempo real al crear un nuevo estudiante
watch(
  () => [form.value.dni, form.value.nombres, form.value.apellido_paterno],
  ([newDni, newNom, newPat]) => {
    if (!editingId.value) {
      form.value.username = generateEstudianteUsername(newDni, newNom, newPat)
      if (!form.value.email || form.value.email.includes('@modux.edu.pe')) {
        const suggested = generateEstudianteEmail(newNom, newPat, newDni)
        if (suggested) form.value.email = suggested
      }
    }
  }
)

watch(
  () => form.value.turno,
  (newTurno) => {
    if (!editingId.value) {
      form.value.codigo_estudiante = generateCodigoMatricula(ordenLlegadaCalculado.value, newTurno)
    }
  }
)

async function loadEstudiantes() {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, unknown> = {}
    const progId = selectedFilterProg.value || route.query.programa_id || programaStore.selectedProgramaId
    if (progId) {
      params.programa_id = progId
    }
    if (selectedFilterEstado.value) {
      params.estado = selectedFilterEstado.value
    }
    if (searchQuery.value) {
      params.search = searchQuery.value
    }
    const res = await estudiantesApi.list(params)
    estudiantes.value = res.data.data
  } catch {
    error.value = 'Error al cargar los estudiantes.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingId.value = null
  const defaultTurno = 'D'
  form.value = {
    dni: '',
    nombres: '',
    apellido_paterno: '',
    apellido_materno: '',
    email: '',
    username: '',
    password: 'Password2026*',
    codigo_estudiante: generateCodigoMatricula(ordenLlegadaCalculado.value, defaultTurno),
    turno: defaultTurno,
    fecha_nacimiento: '',
    sexo: 'M',
    unidad_academica_id: programaStore.selectedProgramaId ?? (programaStore.programas[0]?.id || null),
  }
  modalOpen.value = true
}

function openEditModal(estudiante: Estudiante) {
  editingId.value = estudiante.id
  form.value = {
    dni: (estudiante.usuario as any)?.documento_numero || '',
    nombres: estudiante.usuario?.nombres || '',
    apellido_paterno: estudiante.usuario?.apellido_paterno || '',
    apellido_materno: estudiante.usuario?.apellido_materno || '',
    email: estudiante.usuario?.email || '',
    username: estudiante.usuario?.username || '',
    password: '',
    codigo_estudiante: estudiante.codigo_estudiante || '',
    turno: (estudiante.codigo_estudiante && estudiante.codigo_estudiante.includes('N')) ? 'N' : 'D',
    fecha_nacimiento: (estudiante as any).fecha_nacimiento || '',
    sexo: (estudiante as any).sexo || 'M',
    unidad_academica_id: estudiante.unidad_academica_id ?? null,
  }
  modalOpen.value = true
}

async function handleSubmit() {
  try {
    if (!form.value.username) {
      form.value.username = generateEstudianteUsername(form.value.dni, form.value.nombres, form.value.apellido_paterno)
    }
    if (!form.value.codigo_estudiante) {
      form.value.codigo_estudiante = generateCodigoMatricula(ordenLlegadaCalculado.value, form.value.turno)
    }

    const payload: Record<string, unknown> = {
      nombres: form.value.nombres.trim(),
      apellido_paterno: form.value.apellido_paterno.trim(),
      apellido_materno: form.value.apellido_materno.trim() || null,
      documento_numero: form.value.dni.trim() || null,
      email: form.value.email.trim(),
      username: form.value.username.trim(),
      codigo_estudiante: form.value.codigo_estudiante.trim() || null,
      sexo: form.value.sexo,
      fecha_nacimiento: form.value.fecha_nacimiento || null,
      unidad_academica_id: form.value.unidad_academica_id,
    }

    if (!editingId.value) {
      payload.password = form.value.password || 'Password2026*'
      await estudiantesApi.create(payload)
    } else {
      if (form.value.password) {
        payload.password = form.value.password
      }
      await estudiantesApi.update(editingId.value, payload)
    }
    modalOpen.value = false
    await loadEstudiantes()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar estudiante.')
  }
}

async function handleDeactivate(id: number) {
  if (!confirm('¿Estás seguro de desactivar este estudiante?')) return
  try {
    await estudiantesApi.remove(id, { action: 'desactivar' })
    await loadEstudiantes()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al desactivar estudiante.')
  }
}

async function handleActivate(id: number) {
  if (!confirm('¿Deseas activar nuevamente a este estudiante?')) return
  try {
    await estudiantesApi.update(id, { estado: 'ACTIVO' })
    await loadEstudiantes()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al activar estudiante.')
  }
}

async function handleDelete(id: number) {
  if (!confirm('¿Estás seguro de eliminar PERMANENTEMENTE este estudiante? Esta acción no se puede deshacer.')) return
  try {
    await estudiantesApi.remove(id, { force: true })
    await loadEstudiantes()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al eliminar estudiante.')
  }
}

watch(() => programaStore.selectedProgramaId, (newVal) => {
  selectedFilterProg.value = newVal ? String(newVal) : ''
  loadEstudiantes()
})

watch(() => route.query.programa_id, (newVal) => {
  if (newVal) {
    selectedFilterProg.value = String(newVal)
    loadEstudiantes()
  }
})

onMounted(async () => {
  await programaStore.fetchProgramas()
  if (route.query.programa_id) {
    selectedFilterProg.value = String(route.query.programa_id)
  } else if (programaStore.selectedProgramaId) {
    selectedFilterProg.value = String(programaStore.selectedProgramaId)
  }
  await loadEstudiantes()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Alumnado</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Gestión de estudiantes matriculados por carrera técnica profesional.</p>
      </div>
      <button @click="openCreateModal" class="btn-primary inline-flex items-center gap-2 shrink-0">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Estudiante
      </button>
    </div>

    <!-- Filter Bar -->
    <div class="card p-4 flex flex-col sm:flex-row items-center gap-4">
      <div class="relative flex-1 w-full">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </span>
        <input
          v-model="searchQuery"
          @keyup.enter="loadEstudiantes"
          type="text"
          placeholder="Buscar estudiante por nombre o código (ej. Lucia, EST-ENF)..."
          class="input-field pl-10 w-full"
        />
      </div>
      <div class="w-full sm:w-64">
        <select
          v-model="selectedFilterProg"
          @change="loadEstudiantes"
          class="input-field w-full cursor-pointer text-xs"
        >
          <option value="">Todos los Programas</option>
          <option v-for="prog in programaStore.programas" :key="prog.id" :value="String(prog.id)">
            {{ prog.codigo }} - {{ prog.nombre }}
          </option>
        </select>
      </div>
      <div class="w-full sm:w-44">
        <select
          v-model="selectedFilterEstado"
          @change="loadEstudiantes"
          class="input-field w-full cursor-pointer text-xs"
        >
          <option value="">Todos los Estados</option>
          <option value="ACTIVO">Activo</option>
          <option value="INACTIVO">Inactivo</option>
        </select>
      </div>
      <button
        type="button"
        @click="loadEstudiantes"
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
    <div v-else-if="error" class="rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 p-4 text-sm text-rose-700 dark:text-rose-300">
      {{ error }}
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs transition-colors">
      <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
        <thead class="bg-slate-50/80 dark:bg-slate-800/60">
          <tr>
            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Estudiante</th>
            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Código</th>
            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Programa de Estudios</th>
            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Email</th>
            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300">Estado</th>
            <th class="relative px-6 py-3.5"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 text-sm">
          <tr v-if="estudiantes.length === 0">
            <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">No hay estudiantes registrados para el filtro seleccionado.</td>
          </tr>
          <tr v-for="estudiante in estudiantes" :key="estudiante.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
            <td class="whitespace-nowrap px-6 py-4">
              <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                {{ estudiante.usuario?.nombres }} {{ estudiante.usuario?.apellido_paterno }} {{ estudiante.usuario?.apellido_materno || '' }}
              </div>
              <div class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                {{ estudiante.usuario?.username }}
              </div>
            </td>
            <td class="whitespace-nowrap px-6 py-4 font-mono text-sm font-bold text-slate-800 dark:text-slate-200">
              {{ estudiante.codigo_estudiante || '—' }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm">
              <span v-if="estudiante.programa" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 border border-primary-200/60 dark:border-primary-800/60">
                {{ estudiante.programa.nombre }}
              </span>
              <span v-else class="text-slate-400 dark:text-slate-500 text-xs italic">Sin asignar</span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-xs font-mono text-slate-500 dark:text-slate-400">
              {{ estudiante.usuario?.email }}
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-sm">
              <span :class="[
                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5',
                estudiante.estado === 'ACTIVO'
                  ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700'
              ]">
                {{ estudiante.estado }}
              </span>
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
              <button @click="openEditModal(estudiante)" class="mr-3 text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-200 font-semibold">Editar</button>
              <button
                v-if="estudiante.estado === 'INACTIVO'"
                @click="handleActivate(estudiante.id)"
                data-testid="activar-estudiante-btn"
                class="mr-3 text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 font-semibold"
              >
                Activar
              </button>
              <button
                v-else
                @click="handleDeactivate(estudiante.id)"
                data-testid="desactivar-estudiante-btn"
                class="mr-3 text-amber-600 dark:text-amber-400 hover:text-amber-800 font-semibold"
              >
                Desactivar
              </button>
              <button
                v-if="isSuperAdmin"
                @click="handleDelete(estudiante.id)"
                data-testid="eliminar-estudiante-btn"
                class="text-rose-600 dark:text-rose-400 hover:text-rose-800 font-semibold"
              >
                Eliminar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Form (Crear / Editar Estudiante) -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-xs p-4" role="dialog" aria-modal="true">
      <div class="w-full max-w-xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-6 shadow-2xl space-y-4 max-h-[92vh] overflow-y-auto text-slate-900 dark:text-slate-100 custom-scrollbar transition-all">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">
              {{ editingId ? 'Editar Estudiante' : 'Nuevo Estudiante' }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              {{ editingId ? 'Modifique los datos requeridos' : 'Registro institucional con credenciales y matrícula automatizada' }}
            </p>
          </div>
          <button @click="modalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- DNI y Nombres -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                N° DNI *
              </label>
              <input
                v-model="form.dni"
                required
                maxlength="12"
                placeholder="Ej: 60981968"
                class="input-field w-full"
              />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                Nombres *
              </label>
              <input
                v-model="form.nombres"
                required
                placeholder="Ej: Max"
                class="input-field w-full"
              />
            </div>
          </div>

          <!-- Apellidos -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                Apellido Paterno *
              </label>
              <input
                v-model="form.apellido_paterno"
                required
                placeholder="Ej: Paucar"
                class="input-field w-full"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                Apellido Materno
              </label>
              <input
                v-model="form.apellido_materno"
                placeholder="Ej: Ramos"
                class="input-field w-full"
              />
            </div>
          </div>

          <!-- Turno y Carrera -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                Turno *
              </label>
              <select
                v-model="form.turno"
                class="input-field w-full"
              >
                <option value="D">Día (Diurno - D)</option>
                <option value="N">Noche (Nocturno - N)</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                Programa de Estudios (Carrera)
              </label>
              <select
                v-model="form.unidad_academica_id"
                class="input-field w-full cursor-pointer"
              >
                <option :value="null">-- Seleccionar Programa --</option>
                <option v-for="prog in programaStore.programas" :key="prog.id" :value="prog.id">
                  {{ prog.codigo }} - {{ prog.nombre }}
                </option>
              </select>
            </div>
          </div>

          <!-- Bloque de campos automatizados: Usuario (Login) y Código de Estudiante -->
          <div class="p-3.5 bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
            <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              ⚡ Campos con Autocompletado Inteligente
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Usuario: #(DNI)(nom)(Ape) -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                    Usuario (Login) *
                  </label>
                  <span class="text-[10px] text-primary-600 dark:text-primary-400 font-mono">
                    #DNI+nom+Ape
                  </span>
                </div>
                <input
                  v-model="form.username"
                  required
                  placeholder="#60981968mP"
                  class="input-field font-mono font-bold text-primary-700 dark:text-primary-300 w-full"
                />
                <p class="text-[10px] text-slate-400 mt-0.5">Ej: para Max Paucar con DNI 60981968 → #60981968mP</p>
              </div>

              <!-- Código Matrícula: 026D2026X -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                    Código de Matrícula *
                  </label>
                  <span class="text-[10px] text-primary-600 dark:text-primary-400 font-mono">
                    026D2026X
                  </span>
                </div>
                <input
                  v-model="form.codigo_estudiante"
                  required
                  placeholder="026D2026X"
                  class="input-field font-mono font-bold text-primary-700 dark:text-primary-300 w-full"
                />
                <p class="text-[10px] text-slate-400 mt-0.5">Formato institucional según llegada y turno</p>
              </div>
            </div>
          </div>

          <!-- Email, Sexo y Contraseña -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                Email *
              </label>
              <input
                v-model="form.email"
                type="email"
                required
                placeholder="estudiante@modux.edu.pe"
                class="input-field w-full"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                Sexo
              </label>
              <select v-model="form.sexo" class="input-field w-full">
                <option value="M">Masculino</option>
                <option value="F">Femenino</option>
              </select>
            </div>
          </div>

          <div v-if="!editingId">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
              Contraseña inicial *
            </label>
            <input
              v-model="form.password"
              type="password"
              required
              class="input-field w-full"
            />
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
            <button type="button" @click="modalOpen = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
