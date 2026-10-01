<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { tramitesApi, estudiantesApi, programasApi } from '@/api'
import type { UnidadAcademica } from '@/types'
import {
  generateEstudianteUsername,
  generateCodigoMatricula,
  generateEstudianteEmail
} from '@/utils/studentGenerators'

const loading = ref(true)
const error = ref<string | null>(null)
const successMsg = ref<string | null>(null)

const tramites = ref<any[]>([])
const tramitesMeta = ref<any>({ total: 0, current_page: 1, last_page: 1 })
const estudiantes = ref<any[]>([])
const programas = ref<UnidadAcademica[]>([])

// Filtros
const filterTipo = ref('')
const filterEstado = ref('')

// Modal Nueva Solicitud
const modalCreateOpen = ref(false)
const savingTramite = ref(false)
const modalStep = ref<'tramite' | 'nuevo_estudiante'>('tramite')
const nuevoEstudianteAsignado = ref<string | null>(null)

const form = ref({
  estudiante_id: '',
  tipo_tramite: 'RESERVA_MATRICULA',
  programa_origen_id: '',
  programa_destino_id: '',
  fecha_solicitud: new Date().toISOString().split('T')[0],
  motivo: '',
  observaciones: '',
})

// Sub-formulario para Registrar Nuevo Estudiante inline
const savingEstudiante = ref(false)
const estudianteError = ref<string | null>(null)
const studentForm = ref({
  dni: '',
  nombres: '',
  apellido_paterno: '',
  apellido_materno: '',
  email: '',
  username: '',
  password: 'Password2026*',
  codigo_estudiante: '',
  turno: 'D' as 'D' | 'N',
  sexo: 'M',
  fecha_nacimiento: '',
  unidad_academica_id: '' as string | number,
})

// Auto-cálculo de orden de llegada según la cantidad de estudiantes existentes
const ordenLlegadaCalculado = computed(() => {
  return (estudiantes.value.length || 0) + 1
})

// Watchers para auto-rellenar username y código de estudiante en tiempo real
watch(
  () => [studentForm.value.dni, studentForm.value.nombres, studentForm.value.apellido_paterno],
  ([newDni, newNom, newPat]) => {
    studentForm.value.username = generateEstudianteUsername(newDni, newNom, newPat)
    if (!studentForm.value.email || studentForm.value.email.includes('@modux.edu.pe')) {
      const suggested = generateEstudianteEmail(newNom, newPat, newDni)
      if (suggested) studentForm.value.email = suggested
    }
  }
)

watch(
  () => studentForm.value.turno,
  (newTurno) => {
    studentForm.value.codigo_estudiante = generateCodigoMatricula(ordenLlegadaCalculado.value, newTurno)
  }
)

// Modal Procesar / Aprobar
const modalProcessOpen = ref(false)
const processingTramite = ref(false)
const currentTramite = ref<any>(null)
const processForm = ref({
  resolucion_directoral: '',
  fecha_resolucion: new Date().toISOString().split('T')[0],
  observaciones: '',
  estado: 'APROBADO',
})

onMounted(async () => {
  await Promise.all([loadTramites(), loadEstudiantes(), loadProgramas()])
})

async function loadTramites() {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, any> = {
      page: tramitesMeta.value.current_page,
      per_page: 20,
    }
    if (filterTipo.value) params.tipo_tramite = filterTipo.value
    if (filterEstado.value) params.estado = filterEstado.value

    const res = await tramitesApi.list(params)
    tramites.value = res.data.data
    tramitesMeta.value = res.data.meta
  } catch {
    error.value = 'Error al cargar los trámites académicos.'
  } finally {
    loading.value = false
  }
}

async function loadEstudiantes() {
  try {
    const res = await estudiantesApi.list({ per_page: 100 })
    estudiantes.value = res.data.data
  } catch (e) {
    console.error(e)
  }
}

async function loadProgramas() {
  try {
    const res = await programasApi.list({ tipo: 'PROGRAMA_ESTUDIOS', per_page: 50 })
    programas.value = res.data.data
  } catch (e) {
    console.error(e)
  }
}

function openCreateModal() {
  modalStep.value = 'tramite'
  nuevoEstudianteAsignado.value = null
  form.value = {
    estudiante_id: estudiantes.value.length > 0 ? String(estudiantes.value[0].id) : '',
    tipo_tramite: 'RESERVA_MATRICULA',
    programa_origen_id: '',
    programa_destino_id: '',
    fecha_solicitud: new Date().toISOString().split('T')[0],
    motivo: '',
    observaciones: '',
  }
  modalCreateOpen.value = true
}

function switchToNewStudent() {
  estudianteError.value = null
  studentForm.value = {
    dni: '',
    nombres: '',
    apellido_paterno: '',
    apellido_materno: '',
    email: '',
    username: '',
    password: 'Password2026*',
    codigo_estudiante: generateCodigoMatricula(ordenLlegadaCalculado.value, 'D'),
    turno: 'D',
    sexo: 'M',
    fecha_nacimiento: '',
    unidad_academica_id: programas.value.length > 0 ? String(programas.value[0].id) : '',
  }
  modalStep.value = 'nuevo_estudiante'
}

function handleEstudianteSelectChange() {
  if (form.value.estudiante_id === '__NEW_STUDENT__') {
    switchToNewStudent()
  }
}

async function handleSaveEstudianteInline() {
  if (!studentForm.value.nombres || !studentForm.value.apellido_paterno) {
    estudianteError.value = 'Ingrese los nombres y apellido paterno del estudiante.'
    return
  }
  if (!studentForm.value.dni) {
    estudianteError.value = 'Ingrese el N° de DNI para generar el usuario.'
    return
  }
  if (!studentForm.value.username) {
    studentForm.value.username = generateEstudianteUsername(
      studentForm.value.dni,
      studentForm.value.nombres,
      studentForm.value.apellido_paterno
    )
  }
  if (!studentForm.value.codigo_estudiante) {
    studentForm.value.codigo_estudiante = generateCodigoMatricula(ordenLlegadaCalculado.value, studentForm.value.turno)
  }
  if (!studentForm.value.email) {
    studentForm.value.email = generateEstudianteEmail(
      studentForm.value.nombres,
      studentForm.value.apellido_paterno,
      studentForm.value.dni
    )
  }

  savingEstudiante.value = true
  estudianteError.value = null

  try {
    const payload: Record<string, unknown> = {
      nombres: studentForm.value.nombres.trim(),
      apellido_paterno: studentForm.value.apellido_paterno.trim(),
      apellido_materno: studentForm.value.apellido_materno.trim() || null,
      documento_numero: studentForm.value.dni.trim(),
      email: studentForm.value.email.trim(),
      username: studentForm.value.username.trim(),
      password: studentForm.value.password || 'Password2026*',
      codigo_estudiante: studentForm.value.codigo_estudiante.trim(),
      sexo: studentForm.value.sexo,
      fecha_nacimiento: studentForm.value.fecha_nacimiento || null,
      unidad_academica_id: studentForm.value.unidad_academica_id ? Number(studentForm.value.unidad_academica_id) : null,
    }

    const res = await estudiantesApi.create(payload)
    const nuevoEst = res.data.data

    // Insertar el nuevo estudiante a la lista local y seleccionarlo
    estudiantes.value.unshift(nuevoEst)
    form.value.estudiante_id = String(nuevoEst.id)

    nuevoEstudianteAsignado.value = `${nuevoEst.usuario?.nombres} ${nuevoEst.usuario?.apellido_paterno} (${nuevoEst.codigo_estudiante || nuevoEst.usuario?.username})`

    // Regresar al formulario de solicitud de trámite
    modalStep.value = 'tramite'
  } catch (err: any) {
    estudianteError.value = err.response?.data?.message || 'Error al registrar el nuevo estudiante.'
  } finally {
    savingEstudiante.value = false
  }
}

async function handleSaveTramite() {
  if (!form.value.estudiante_id || form.value.estudiante_id === '__NEW_STUDENT__') {
    error.value = 'Debe seleccionar o registrar un estudiante para la solicitud.'
    return
  }

  savingTramite.value = true
  error.value = null
  try {
    await tramitesApi.create({
      ...form.value,
      estudiante_id: Number(form.value.estudiante_id),
      programa_origen_id: form.value.programa_origen_id ? Number(form.value.programa_origen_id) : undefined,
      programa_destino_id: form.value.programa_destino_id ? Number(form.value.programa_destino_id) : undefined,
    })
    modalCreateOpen.value = false
    successMsg.value = 'Solicitud de trámite registrada exitosamente.'
    setTimeout(() => (successMsg.value = null), 4000)
    await loadTramites()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al registrar trámite.'
  } finally {
    savingTramite.value = false
  }
}

function openProcessModal(t: any) {
  currentTramite.value = t
  processForm.value = {
    resolucion_directoral: t.resolucion_directoral || `RD N° ${Math.floor(100 + Math.random() * 900)}-2026-IESTP-SAM`,
    fecha_resolucion: new Date().toISOString().split('T')[0],
    observaciones: t.observaciones || '',
    estado: 'APROBADO',
  }
  modalProcessOpen.value = true
}

async function handleProcessTramite() {
  if (!currentTramite.value) return
  processingTramite.value = true
  error.value = null
  try {
    await tramitesApi.update(currentTramite.value.id, processForm.value)
    modalProcessOpen.value = false
    successMsg.value = 'Trámite procesado y resuelto exitosamente.'
    setTimeout(() => (successMsg.value = null), 4000)
    await loadTramites()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al procesar trámite.'
  } finally {
    processingTramite.value = false
  }
}

function formatTipo(tipo: string): string {
  switch (tipo) {
    case 'RESERVA_MATRICULA':
      return 'Reserva de Matrícula'
    case 'REINCORPORACION':
      return 'Reincorporación'
    case 'TRASLADO_INTERNO':
      return 'Traslado Interno'
    case 'TRASLADO_EXTERNO':
      return 'Traslado Externo'
    case 'CONVALIDACION':
      return 'Convalidación de UD'
    case 'RECTIFICACION':
      return 'Rectificación de Matrícula'
    default:
      return tipo
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xs border border-slate-200/80 dark:border-slate-800 p-6 transition-colors">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/60">
              Ciclo de Vida Estudiantil
            </span>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">IESTP SAM</span>
          </div>
          <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight mt-1.5">
            Trámites y Trayectoria Académica
          </h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
            Gestión oficial de reservas de matrícula, reincorporaciones, traslados y convalidaciones con resolución directoral.
          </p>
        </div>

        <button
          @click="openCreateModal"
          class="btn-primary inline-flex items-center gap-2 shrink-0"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nueva Solicitud
        </button>
      </div>
    </div>

    <!-- Alerts -->
    <div v-if="error" class="p-4 bg-rose-50 dark:bg-rose-950/40 border-l-4 border-rose-500 rounded-r-lg text-sm text-rose-700 dark:text-rose-300 flex items-center justify-between">
      <span>{{ error }}</span>
      <button @click="error = null" class="text-rose-500 hover:text-rose-700 text-xs font-bold">✕</button>
    </div>
    <div v-if="successMsg" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border-l-4 border-emerald-500 rounded-r-lg text-sm text-emerald-800 dark:text-emerald-300 flex items-center justify-between">
      <span>{{ successMsg }}</span>
      <button @click="successMsg = null" class="text-emerald-500 hover:text-emerald-700 text-xs font-bold">✕</button>
    </div>

    <!-- Filter toolbar -->
    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xs border border-slate-200/80 dark:border-slate-800 p-4 flex flex-wrap items-center gap-4 transition-colors">
      <div class="flex-1 min-w-[220px]">
        <label class="sr-only">Tipo de trámite</label>
        <select
          v-model="filterTipo"
          @change="loadTramites"
          class="w-full text-sm bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg py-2 px-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        >
          <option value="">Todos los Tipos de Trámite</option>
          <option value="RESERVA_MATRICULA">Reserva de Matrícula</option>
          <option value="REINCORPORACION">Reincorporación</option>
          <option value="TRASLADO_INTERNO">Traslado Interno</option>
          <option value="TRASLADO_EXTERNO">Traslado Externo</option>
          <option value="CONVALIDACION">Convalidación de UD</option>
          <option value="RECTIFICACION">Rectificación de Matrícula</option>
        </select>
      </div>

      <div class="w-full sm:w-48">
        <label class="sr-only">Estado del trámite</label>
        <select
          v-model="filterEstado"
          @change="loadTramites"
          class="w-full text-sm bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg py-2 px-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        >
          <option value="">Todos los Estados</option>
          <option value="PENDIENTE">Pendientes</option>
          <option value="APROBADO">Aprobados</option>
          <option value="RECHAZADO">Rechazados</option>
        </select>
      </div>
    </div>

    <!-- Tramites Table -->
    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xs border border-slate-200/80 dark:border-slate-800 overflow-hidden transition-colors">
      <div v-if="loading" class="p-12 text-center text-slate-500 dark:text-slate-400">
        <svg class="w-8 h-8 animate-spin mx-auto text-primary-600 mb-2" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        Cargando trámites...
      </div>

      <div v-else-if="tramites.length === 0" class="p-12 text-center text-slate-500 dark:text-slate-400">
        No hay trámites registrados para los criterios seleccionados.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
          <thead class="bg-slate-50/80 dark:bg-slate-800/60 text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
            <tr>
              <th scope="col" class="px-5 py-3.5 text-left">Tipo de Trámite</th>
              <th scope="col" class="px-5 py-3.5 text-left">Estudiante</th>
              <th scope="col" class="px-5 py-3.5 text-left">Fecha Solicitud</th>
              <th scope="col" class="px-5 py-3.5 text-left">Resolución Directoral</th>
              <th scope="col" class="px-5 py-3.5 text-left">Estado</th>
              <th scope="col" class="px-5 py-3.5 text-right">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 text-sm">
            <tr v-for="t in tramites" :key="t.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
              <td class="px-5 py-4 whitespace-nowrap">
                <div class="font-semibold text-slate-900 dark:text-slate-100">{{ formatTipo(t.tipo_tramite) }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 max-w-xs truncate mt-0.5">{{ t.motivo }}</div>
              </td>
              <td class="px-5 py-4 whitespace-nowrap">
                <div class="font-medium text-slate-900 dark:text-slate-100">
                  {{ t.estudiante?.usuario?.apellido_paterno }} {{ t.estudiante?.usuario?.apellido_materno }}, {{ t.estudiante?.usuario?.nombres }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mt-0.5">
                  <span class="font-mono text-primary-600 dark:text-primary-400 font-semibold">{{ t.estudiante?.codigo_estudiante || t.estudiante?.usuario?.username }}</span>
                  <span>•</span>
                  <span>{{ t.estudiante?.programa?.nombre || 'General' }}</span>
                </div>
              </td>
              <td class="px-5 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300 font-mono">
                {{ t.fecha_solicitud }}
              </td>
              <td class="px-5 py-4 whitespace-nowrap">
                <span v-if="t.resolucion_directoral" class="font-mono text-xs text-slate-800 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-2 py-1 rounded">
                  {{ t.resolucion_directoral }}
                </span>
                <span v-else class="text-xs text-slate-400 dark:text-slate-500 italic">Sin resolución</span>
              </td>
              <td class="px-5 py-4 whitespace-nowrap">
                <span
                  :class="[
                    t.estado === 'APROBADO' ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60' :
                    t.estado === 'RECHAZADO' ? 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200/60 dark:border-rose-800/60' :
                    'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/60',
                    'px-2.5 py-1 rounded-full text-xs font-semibold uppercase'
                  ]"
                >
                  {{ t.estado }}
                </span>
              </td>
              <td class="px-5 py-4 whitespace-nowrap text-right text-sm">
                <button
                  v-if="t.estado === 'PENDIENTE'"
                  @click="openProcessModal(t)"
                  class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-950/60 hover:bg-primary-100 dark:hover:bg-primary-900/60 rounded-lg transition-colors border border-primary-200/60 dark:border-primary-800/60"
                >
                  Resolver Trámite
                </button>
                <span v-else class="text-xs text-slate-400 dark:text-slate-500">Procesado</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Registrar Solicitud de Trámite / Nuevo Estudiante -->
    <div v-if="modalCreateOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" role="dialog" aria-modal="true">
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-2xl w-full max-w-xl p-6 overflow-y-auto max-h-[92vh] text-slate-900 dark:text-slate-100 custom-scrollbar transition-all">
        
        <!-- Pestaña 1: Formulario de Trámite Principal -->
        <div v-if="modalStep === 'tramite'">
          <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
            <div>
              <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Registrar Solicitud de Trámite</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Mesa de Partes • Atención al Estudiante</p>
            </div>
            <button @click="modalCreateOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Banner si se acaba de crear un estudiante nuevo -->
          <div v-if="nuevoEstudianteAsignado" class="mt-4 p-3 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 rounded-xl text-xs text-emerald-800 dark:text-emerald-200 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span><strong>Estudiante registrado:</strong> {{ nuevoEstudianteAsignado }}. Listo para completar la solicitud.</span>
          </div>

          <form @submit.prevent="handleSaveTramite" class="mt-4 space-y-4">
            <!-- Estudiante con opción rápida para crear uno nuevo -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide">
                  Estudiante Solicitante *
                </label>
                <button
                  type="button"
                  @click="switchToNewStudent"
                  class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 hover:underline"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  <span>+ Registrar Nuevo Estudiante</span>
                </button>
              </div>

              <select
                v-model="form.estudiante_id"
                @change="handleEstudianteSelectChange"
                required
                class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option value="" disabled>-- Seleccione un estudiante --</option>
                <option value="__NEW_STUDENT__" class="font-bold text-primary-600 dark:text-primary-400">
                  ✨ + [ Registrar Nuevo Estudiante no listado... ]
                </option>
                <option v-for="e in estudiantes" :key="e.id" :value="String(e.id)">
                  {{ e.codigo_estudiante || e.usuario?.username }} — {{ e.usuario?.apellido_paterno }} {{ e.usuario?.nombres }} ({{ e.programa?.nombre || 'General' }})
                </option>
              </select>
            </div>

            <!-- Tipo de Trámite -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
                Tipo de Trámite *
              </label>
              <select
                v-model="form.tipo_tramite"
                required
                class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option value="RESERVA_MATRICULA">Reserva de Matrícula</option>
                <option value="REINCORPORACION">Reincorporación</option>
                <option value="TRASLADO_INTERNO">Traslado Interno de Programa</option>
                <option value="TRASLADO_EXTERNO">Traslado Externo</option>
                <option value="CONVALIDACION">Convalidación de Unidad Didáctica</option>
                <option value="RECTIFICACION">Rectificación de Matrícula</option>
              </select>
            </div>

            <!-- Destino en Traslado Interno -->
            <div v-if="form.tipo_tramite === 'TRASLADO_INTERNO'">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
                Programa de Destino *
              </label>
              <select
                v-model="form.programa_destino_id"
                required
                class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option value="" disabled>-- Seleccione programa de destino --</option>
                <option v-for="p in programas" :key="p.id" :value="String(p.id)">{{ p.nombre }}</option>
              </select>
            </div>

            <!-- Fecha Solicitud con estilo oscuro consistente -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
                Fecha Solicitud *
              </label>
              <input
                v-model="form.fecha_solicitud"
                required
                type="date"
                class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 [color-scheme:light] dark:[color-scheme:dark]"
              />
            </div>

            <!-- Motivo / Justificación -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
                Motivo / Justificación *
              </label>
              <textarea
                v-model="form.motivo"
                required
                rows="3"
                class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Detalle el motivo o fundamentación legal de la solicitud..."
              ></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
              <button
                type="button"
                @click="modalCreateOpen = false"
                class="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
              >
                Cancelar
              </button>
              <button
                type="submit"
                :disabled="savingTramite"
                class="btn-primary disabled:opacity-50"
              >
                {{ savingTramite ? 'Registrando...' : 'Registrar Trámite' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Pestaña 2: Formulario Inline para Crear Nuevo Estudiante -->
        <div v-else-if="modalStep === 'nuevo_estudiante'">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
                  Mesa de Partes
                </span>
                <span class="text-xs text-slate-400">Nuevo Registro</span>
              </div>
              <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100 mt-1">
                Registrar Nuevo Estudiante
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Al guardar, volverá al formulario de trámite con este estudiante seleccionado automáticamente.
              </p>
            </div>
            <button
              @click="modalStep = 'tramite'"
              class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
              title="Volver a la solicitud"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div v-if="estudianteError" class="mt-3 p-3 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 rounded-lg text-xs text-rose-700 dark:text-rose-300">
            {{ estudianteError }}
          </div>

          <form @submit.prevent="handleSaveEstudianteInline" class="mt-4 space-y-4">
            <!-- DNI y Nombres -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                  N° DNI *
                </label>
                <input
                  v-model="studentForm.dni"
                  required
                  maxlength="12"
                  placeholder="Ej: 60981968"
                  class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2 text-sm focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                  Nombres *
                </label>
                <input
                  v-model="studentForm.nombres"
                  required
                  placeholder="Ej: Max"
                  class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2 text-sm focus:ring-2 focus:ring-primary-500"
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
                  v-model="studentForm.apellido_paterno"
                  required
                  placeholder="Ej: Paucar"
                  class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2 text-sm focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                  Apellido Materno
                </label>
                <input
                  v-model="studentForm.apellido_materno"
                  placeholder="Ej: Ramos"
                  class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2 text-sm focus:ring-2 focus:ring-primary-500"
                />
              </div>
            </div>

            <!-- Turno y Programa -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                  Turno *
                </label>
                <select
                  v-model="studentForm.turno"
                  class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2 text-sm focus:ring-2 focus:ring-primary-500"
                >
                  <option value="D">Día (Diurno - D)</option>
                  <option value="N">Noche (Nocturno - N)</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                  Programa de Estudios (Carrera) *
                </label>
                <select
                  v-model="studentForm.unidad_academica_id"
                  required
                  class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2 text-sm focus:ring-2 focus:ring-primary-500"
                >
                  <option value="" disabled>-- Seleccione Carrera --</option>
                  <option v-for="prog in programas" :key="prog.id" :value="String(prog.id)">
                    {{ prog.codigo }} - {{ prog.nombre }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Campos Automatizados con destaque visual -->
            <div class="p-3.5 bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
              <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                ⚡ Generación Automática de Credenciales y Matrícula
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Usuario (Login): #(dni)(nom)(Ape) -->
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
                    v-model="studentForm.username"
                    required
                    placeholder="#60981968mP"
                    class="w-full bg-white dark:bg-slate-900 border border-primary-300 dark:border-primary-800 text-primary-700 dark:text-primary-300 font-mono font-bold rounded-lg p-2 text-sm focus:ring-2 focus:ring-primary-500"
                  />
                  <p class="text-[10px] text-slate-400 mt-0.5">Ej: para Max Paucar con DNI 60981968 → #60981968mP</p>
                </div>

                <!-- Código de Matrícula: 026D2026X -->
                <div>
                  <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                      Código de Matrícula *
                    </label>
                    <span class="text-[10px] text-primary-600 dark:text-primary-400 font-mono">
                      Llegada+Turno+Año+X
                    </span>
                  </div>
                  <input
                    v-model="studentForm.codigo_estudiante"
                    required
                    placeholder="026D2026X"
                    class="w-full bg-white dark:bg-slate-900 border border-primary-300 dark:border-primary-800 text-primary-700 dark:text-primary-300 font-mono font-bold rounded-lg p-2 text-sm focus:ring-2 focus:ring-primary-500"
                  />
                  <p class="text-[10px] text-slate-400 mt-0.5">Formato correlativo: 026D2026X según llegada y turno</p>
                </div>
              </div>
            </div>

            <!-- Email, Sexo y Contraseña -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                  Email de Contacto *
                </label>
                <input
                  v-model="studentForm.email"
                  type="email"
                  required
                  placeholder="estudiante@correo.com"
                  class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2 text-sm focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
                  Sexo
                </label>
                <select
                  v-model="studentForm.sexo"
                  class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2 text-sm focus:ring-2 focus:ring-primary-500"
                >
                  <option value="M">Masculino</option>
                  <option value="F">Femenino</option>
                </select>
              </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
              <button
                type="button"
                @click="modalStep = 'tramite'"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Volver a Solicitud
              </button>

              <button
                type="submit"
                :disabled="savingEstudiante"
                class="btn-primary inline-flex items-center gap-2"
              >
                <svg v-if="savingEstudiante" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                {{ savingEstudiante ? 'Guardando Estudiante...' : 'Guardar y Usar en Solicitud' }}
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>

    <!-- Modal Resolver / Aprobar Trámite con Dictamen Oficial -->
    <div v-if="modalProcessOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" role="dialog" aria-modal="true">
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-2xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh] text-slate-900 dark:text-slate-100 custom-scrollbar transition-all">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Emitir Resolución de Trámite</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Dictamen de Mesa de Partes / Secretaría</p>
          </div>
          <button @click="modalProcessOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleProcessTramite" class="mt-4 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
              Decisión Oficial *
            </label>
            <select
              v-model="processForm.estado"
              required
              class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-primary-500"
            >
              <option value="APROBADO">Aprobar Solicitud</option>
              <option value="RECHAZADO">Rechazar Solicitud</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
              N° Resolución Directoral (RD) *
            </label>
            <input
              v-model="processForm.resolucion_directoral"
              required
              type="text"
              class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 font-mono text-sm p-2.5 rounded-lg focus:ring-2 focus:ring-primary-500"
              placeholder="RD N° 0150-2026-IESTP-SAM"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
              Fecha Resolución *
            </label>
            <input
              v-model="processForm.fecha_resolucion"
              required
              type="date"
              class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-primary-500 [color-scheme:light] dark:[color-scheme:dark]"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
              Observaciones / Dictamen
            </label>
            <textarea
              v-model="processForm.observaciones"
              rows="3"
              class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-primary-500"
              placeholder="Fundamento legal según Lineamientos Académicos Generales..."
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
            <button
              type="button"
              @click="modalProcessOpen = false"
              class="px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="processingTramite"
              class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg disabled:opacity-50 transition shadow-xs"
            >
              Confirmar Dictamen
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
