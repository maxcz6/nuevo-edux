<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { admisionApi, programasApi, registraApi } from '@/api'
import type { ConvocatoriaAdmision, UnidadAcademica } from '@/types'

const loading = ref(true)
const error = ref<string | null>(null)
const successMsg = ref<string | null>(null)

const convocatorias = ref<ConvocatoriaAdmision[]>([])
const selectedConvId = ref<number | null>(null)
const activeTab = ref<'postulantes' | 'merito' | 'vacantes'>('postulantes')

const programas = ref<UnidadAcademica[]>([])
const postulantes = ref<any[]>([])
const postulantesMeta = ref<any>({ total: 0, current_page: 1, last_page: 1 })

// Filtros
const filterPrograma = ref<number | ''>('')
const filterTurno = ref<string>('')
const filterCondicion = ref<string>('')
const searchQuery = ref('')

// Modal Inscribir Postulante
const modalPostulanteOpen = ref(false)
const savingPostulante = ref(false)
const postForm = ref({
  programa_id: '',
  turno: 'DIURNO',
  modalidad: 'ORDINARIO',
  nombres: '',
  apellido_paterno: '',
  apellido_materno: '',
  documento_tipo: 'DNI',
  documento_numero: '',
  email: '',
  telefono: '',
})

// Calificaciones rápidas
const calificacionesInput = ref<Record<number, number>>({})
const savingCalificaciones = ref(false)

// Proceso de Mérito y Matrícula
const calculandoMerito = ref(false)
const matriculando = ref(false)

const selectedConvocatoria = computed(() => {
  return convocatorias.value.find(c => c.id === selectedConvId.value) || null
})

onMounted(async () => {
  await Promise.all([loadConvocatorias(), loadProgramas()])
})

async function loadProgramas() {
  try {
    const res = await programasApi.list({ tipo: 'PROGRAMA_ESTUDIOS', per_page: 50 })
    programas.value = res.data.data
  } catch (e) {
    console.error(e)
  }
}

async function loadConvocatorias() {
  loading.value = true
  error.value = null
  try {
    const res = await admisionApi.convocatorias()
    convocatorias.value = res.data.data
    if (convocatorias.value.length > 0 && !selectedConvId.value) {
      selectedConvId.value = convocatorias.value[0].id
      await loadPostulantes()
    }
  } catch (err: unknown) {
    if (axios.isAxiosError(err)) {
      if (!err.response) {
        error.value = 'No se pudo conectar con la API de Modux. Verifica que los contenedores backend y nginx estén activos.'
      } else {
        error.value = err.response.data?.message || `No se pudieron cargar las convocatorias (HTTP ${err.response.status}).`
      }
    } else {
      error.value = 'Error al cargar convocatorias de admisión.'
    }
  } finally {
    loading.value = false
  }
}

async function selectConvocatoria(id: number) {
  selectedConvId.value = id
  await loadPostulantes()
}

async function loadPostulantes() {
  if (!selectedConvId.value) return
  loading.value = true
  try {
    const params: Record<string, any> = {
      page: postulantesMeta.value.current_page,
      per_page: 25,
    }
    if (filterPrograma.value) params.programa_id = filterPrograma.value
    if (filterTurno.value) params.turno = filterTurno.value
    if (filterCondicion.value) params.condicion = filterCondicion.value
    if (searchQuery.value) params.search = searchQuery.value

    const res = await admisionApi.postulantes(selectedConvId.value, params)
    postulantes.value = res.data.data
    postulantesMeta.value = res.data.meta

    // Inicializar notas en calificacionesInput
    postulantes.value.forEach(p => {
      if (p.puntaje_examen !== null && p.puntaje_examen !== undefined) {
        calificacionesInput.value[p.id] = Number(p.puntaje_examen)
      }
    })
  } catch {
    error.value = 'Error al cargar postulantes.'
  } finally {
    loading.value = false
  }
}

function openModalPostulante() {
  postForm.value = {
    programa_id: programas.value.length > 0 ? String(programas.value[0].id) : '',
    turno: 'DIURNO',
    modalidad: 'ORDINARIO',
    nombres: '',
    apellido_paterno: '',
    apellido_materno: '',
    documento_tipo: 'DNI',
    documento_numero: '',
    email: '',
    telefono: '',
  }
  modalPostulanteOpen.value = true
}

async function handleSavePostulante() {
  if (!selectedConvId.value) return
  savingPostulante.value = true
  error.value = null
  try {
    await admisionApi.createPostulante(selectedConvId.value, {
      ...postForm.value,
      programa_id: Number(postForm.value.programa_id),
    })
    modalPostulanteOpen.value = false
    successMsg.value = 'Postulante registrado con éxito.'
    setTimeout(() => successMsg.value = null, 4000)
    await loadPostulantes()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al inscribir postulante.'
  } finally {
    savingPostulante.value = false
  }
}

async function handleGuardarCalificaciones() {
  if (!selectedConvId.value) return
  savingCalificaciones.value = true
  error.value = null
  try {
    const list = Object.entries(calificacionesInput.value).map(([id, nota]) => ({
      postulante_id: Number(id),
      puntaje_examen: Number(nota),
    }))
    const res = await admisionApi.registrarCalificaciones(selectedConvId.value, list)
    successMsg.value = res.data.message
    setTimeout(() => successMsg.value = null, 4000)
    await loadPostulantes()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al guardar notas de examen.'
  } finally {
    savingCalificaciones.value = false
  }
}

async function handleCalcularMerito() {
  if (!selectedConvId.value) return
  calculandoMerito.value = true
  error.value = null
  try {
    const res = await admisionApi.calcularMerito(selectedConvId.value)
    successMsg.value = res.data.message
    setTimeout(() => successMsg.value = null, 4000)
    await loadPostulantes()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al calcular mérito.'
  } finally {
    calculandoMerito.value = false
  }
}

async function handleMatricularIngresantes() {
  if (!selectedConvId.value) return
  if (!confirm('¿Deseas matricular formalmente a todos los postulantes que alcanzaron vacante? Esto creará sus cuentas y códigos oficiales de estudiante.')) return
  matriculando.value = true
  error.value = null
  try {
    const res = await admisionApi.matricularIngresantes(selectedConvId.value)
    successMsg.value = res.data.message
    setTimeout(() => successMsg.value = null, 4000)
    await loadPostulantes()
    await loadConvocatorias()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al matricular ingresantes.'
  } finally {
    matriculando.value = false
  }
}

async function exportarPadronMinedu() {
  if (!selectedConvId.value) return
  try {
    await registraApi.descargarIngresantes(selectedConvId.value)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al exportar el padrón de ingresantes.'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
              MINEDU LAG RVM 277-2019
            </span>
            <span class="text-xs text-gray-500 font-mono">IESTP SAM • Palián</span>
          </div>
          <h1 class="text-2xl font-bold text-gray-900 mt-1">Admisión Tecnológica</h1>
          <p class="text-sm text-gray-500 mt-0.5">
            Gestión de convocatorias, vacantes por programa (8 carreras), evaluación, ranking de mérito y pase a REGISTRA.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            @click="exportarPadronMinedu"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition-colors"
            title="Descargar padrón oficial para el sistema REGISTRA de MINEDU"
          >
            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Exportar Padrón REGISTRA
          </button>
          <button
            @click="openModalPostulante"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-lg shadow-sm transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            Inscribir Postulante
          </button>
        </div>
      </div>

      <!-- Selector de Convocatorias -->
      <div v-if="convocatorias.length > 0" class="mt-6 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4">
        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider mr-2">Convocatoria:</span>
        <button
          v-for="c in convocatorias"
          :key="c.id"
          :data-testid="`convocatoria-${c.id}`"
          @click="selectConvocatoria(c.id)"
          :class="[
            selectedConvId === c.id
              ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold'
              : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100',
            'px-3 py-1.5 rounded-lg text-sm border transition-all flex items-center gap-2'
          ]"
        >
          <span>{{ c.nombre }} ({{ c.codigo }})</span>
          <span :class="[
            c.estado === 'FINALIZADA' ? 'bg-gray-200 text-gray-700' : 'bg-emerald-200 text-emerald-800',
            'text-xs px-1.5 py-0.5 rounded'
          ]">{{ c.estado }}</span>
        </button>
      </div>
    </div>

    <!-- Feedback alerts -->
    <div
      v-if="error"
      data-testid="admision-error"
      class="flex flex-col gap-3 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg text-sm text-red-700 sm:flex-row sm:items-center sm:justify-between"
    >
      <span>{{ error }}</span>
      <button
        type="button"
        data-testid="retry-admision-button"
        class="self-start rounded-md border border-red-300 bg-white px-3 py-1.5 font-semibold text-red-700 hover:bg-red-100 sm:self-auto"
        @click="loadConvocatorias"
      >
        Reintentar
      </button>
    </div>
    <div v-if="successMsg" class="p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg text-sm text-emerald-800">
      {{ successMsg }}
    </div>

    <!-- Convocatoria Summary Card -->
    <div v-if="selectedConvocatoria" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-3 bg-gray-50 rounded-lg">
          <span class="text-xs text-gray-500 block">Fecha Examen</span>
          <span class="text-sm font-semibold text-gray-900">{{ selectedConvocatoria.fecha_examen || 'Por definir' }}</span>
        </div>
        <div class="p-3 bg-gray-50 rounded-lg">
          <span class="text-xs text-gray-500 block">Puntaje Mínimo</span>
          <span class="text-sm font-semibold text-gray-900">{{ selectedConvocatoria.puntaje_minimo_aprobatorio }} / 20</span>
        </div>
        <div class="p-3 bg-gray-50 rounded-lg">
          <span class="text-xs text-gray-500 block">Postulantes Registrados</span>
          <span class="text-sm font-semibold text-primary-700">{{ selectedConvocatoria.postulantes_count || postulantesMeta.total }}</span>
        </div>
        <div class="p-3 bg-gray-50 rounded-lg">
          <span class="text-xs text-gray-500 block">Vacantes Totales Ofertadas</span>
          <span class="text-sm font-semibold text-gray-900">320 (40 por programa)</span>
        </div>
      </div>
    </div>

    <!-- Actions toolbar: Ranking and Matricula -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap items-center justify-between gap-4">
      <div class="flex flex-wrap items-center gap-3">
        <select
          v-model="filterPrograma"
          @change="loadPostulantes"
          class="text-sm border-gray-200 rounded-lg focus:ring-primary-500 focus:border-primary-500 py-2 px-3 border"
        >
          <option value="">Todos los 8 Programas</option>
          <option v-for="p in programas" :key="p.id" :value="p.id">{{ p.nombre }}</option>
        </select>

        <select
          v-model="filterCondicion"
          @change="loadPostulantes"
          class="text-sm border-gray-200 rounded-lg focus:ring-primary-500 focus:border-primary-500 py-2 px-3 border"
        >
          <option value="">Todas las Condiciones</option>
          <option value="INSCRITO">Inscrito</option>
          <option value="EVALUADO">Evaluado</option>
          <option value="ALCANZO_VACANTE">Alcanzó Vacante</option>
          <option value="NO_INGRESO">No Ingresó</option>
          <option value="MATRICULADO">Matriculado</option>
        </select>

        <input
          v-model="searchQuery"
          @keyup.enter="loadPostulantes"
          type="text"
          placeholder="Buscar por DNI o nombres..."
          class="text-sm border-gray-200 rounded-lg focus:ring-primary-500 focus:border-primary-500 py-2 px-3 border w-48 sm:w-64"
        />
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          @click="handleGuardarCalificaciones"
          :disabled="savingCalificaciones"
          class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg border border-blue-200 transition-colors disabled:opacity-50"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Guardar Notas
        </button>

        <button
          @click="handleCalcularMerito"
          :disabled="calculandoMerito"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 rounded-lg transition-colors disabled:opacity-50"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          Calcular Orden de Mérito
        </button>

        <button
          @click="handleMatricularIngresantes"
          :disabled="matriculando"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition-colors disabled:opacity-50"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Matricular Ingresantes
        </button>
      </div>
    </div>

    <!-- Table of Postulantes -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="loading" class="p-12 text-center text-gray-500">
        <svg class="w-8 h-8 animate-spin mx-auto text-primary-600 mb-2" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        Cargando padrón de postulantes...
      </div>

      <div v-else-if="postulantes.length === 0" class="p-12 text-center text-gray-500">
        No se encontraron postulantes registrados con los filtros seleccionados.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            <tr>
              <th scope="col" class="px-4 py-3 text-left">Puesto</th>
              <th scope="col" class="px-4 py-3 text-left">Postulante / DNI</th>
              <th scope="col" class="px-4 py-3 text-left">Programa de Estudios</th>
              <th scope="col" class="px-4 py-3 text-left">Modalidad / Turno</th>
              <th scope="col" class="px-4 py-3 text-left">Nota Examen (0-20)</th>
              <th scope="col" class="px-4 py-3 text-left">Condición</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-sm">
            <tr v-for="p in postulantes" :key="p.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-700">
                <span v-if="p.orden_merito" class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 text-gray-800 text-xs">
                  #{{ p.orden_merito }}
                </span>
                <span v-else class="text-gray-400 font-normal">-</span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <div class="font-medium text-gray-900">{{ p.apellido_paterno }} {{ p.apellido_materno }}, {{ p.nombres }}</div>
                <div class="text-xs text-gray-500">{{ p.documento_tipo }}: {{ p.documento_numero }} • {{ p.codigo_postulante }}</div>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-800">
                  {{ p.programa?.nombre || 'Programa SAM' }}
                </span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                <div>{{ p.modalidad }}</div>
                <div class="text-gray-400">{{ p.turno }}</div>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <input
                  v-model.number="calificacionesInput[p.id]"
                  type="number"
                  step="0.1"
                  min="0"
                  max="20"
                  class="w-20 px-2 py-1 text-sm font-semibold border border-gray-300 rounded focus:ring-primary-500 focus:border-primary-500"
                  placeholder="0.00"
                />
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <span
                  :class="[
                    p.condicion === 'MATRICULADO' ? 'bg-purple-100 text-purple-800' :
                    p.condicion === 'ALCANZO_VACANTE' ? 'bg-emerald-100 text-emerald-800' :
                    p.condicion === 'NO_INGRESO' ? 'bg-red-100 text-red-800' :
                    p.condicion === 'EVALUADO' ? 'bg-blue-100 text-blue-800' :
                    'bg-gray-100 text-gray-700',
                    'px-2.5 py-1 rounded-full text-xs font-semibold uppercase'
                  ]"
                >
                  {{ p.condicion.replace('_', ' ') }}
                </span>
                <span v-if="p.estudiante" class="block text-xs text-purple-600 font-mono mt-0.5">
                  Estudiante Oficial
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Inscribir Postulante -->
    <div v-if="modalPostulanteOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" role="dialog" aria-modal="true">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
          <h3 class="text-lg font-bold text-gray-900">Inscribir Nuevo Postulante</h3>
          <button @click="modalPostulanteOpen = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSavePostulante" class="mt-4 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Programa de Estudios (SAM)</label>
            <select v-model="postForm.programa_id" required class="w-full border-gray-300 rounded-lg text-sm p-2 border">
              <option v-for="p in programas" :key="p.id" :value="p.id">{{ p.nombre }}</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Modalidad</label>
              <select v-model="postForm.modalidad" class="w-full border-gray-300 rounded-lg text-sm p-2 border">
                <option value="ORDINARIO">Ordinario</option>
                <option value="EXONERADO">Exonerado</option>
                <option value="PRIMEROS_PUESTOS">Primeros Puestos</option>
                <option value="DEPORTISTA">Deportista Calificado</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Turno</label>
              <select v-model="postForm.turno" class="w-full border-gray-300 rounded-lg text-sm p-2 border">
                <option value="DIURNO">Diurno</option>
                <option value="NOCTURNO">Nocturno</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Tipo Doc.</label>
              <select v-model="postForm.documento_tipo" class="w-full border-gray-300 rounded-lg text-sm p-2 border">
                <option value="DNI">DNI</option>
                <option value="CE">Carnet Extranjería</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Número Documento</label>
              <input v-model="postForm.documento_numero" required type="text" maxlength="20" class="w-full border-gray-300 rounded-lg text-sm p-2 border" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Nombres</label>
            <input v-model="postForm.nombres" required type="text" class="w-full border-gray-300 rounded-lg text-sm p-2 border" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Apellido Paterno</label>
              <input v-model="postForm.apellido_paterno" required type="text" class="w-full border-gray-300 rounded-lg text-sm p-2 border" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Apellido Materno</label>
              <input v-model="postForm.apellido_materno" type="text" class="w-full border-gray-300 rounded-lg text-sm p-2 border" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Email</label>
              <input v-model="postForm.email" type="email" class="w-full border-gray-300 rounded-lg text-sm p-2 border" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Teléfono</label>
              <input v-model="postForm.telefono" type="tel" class="w-full border-gray-300 rounded-lg text-sm p-2 border" />
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
            <button type="button" @click="modalPostulanteOpen = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
              Cancelar
            </button>
            <button type="submit" :disabled="savingPostulante" class="px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50">
              Inscribir Postulante
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
