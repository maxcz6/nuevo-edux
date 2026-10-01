<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import http from '@/api/http'
import { useProgramaStore } from '@/stores/programa'

interface Usuario {
  id: number
  nombres: string
  apellidos?: string
  apellido_paterno?: string
  apellido_materno?: string
  numero_documento?: string
}

interface Programa {
  id: number
  codigo: string
  nombre: string
}

interface Estudiante {
  id: number
  codigo_estudiante?: string
  usuario?: Usuario
}

interface Titulacion {
  id: number
  estudiante_id: number
  programa_id: number
  modalidad_titulacion: string
  numero_titulo: string
  numero_registro: string
  numero_folio: string
  numero_libro: string
  resolucion_directoral: string
  codigo_minedu: string | null
  fecha_sustentacion: string | null
  fecha_emision: string
  codigo_verificacion: string
  estado: string
  estudiante?: Estudiante
  programa?: Programa
}

interface CertificadoModular {
  id: number
  estudiante_id: number
  programa_id: number
  numero_modulo: number
  nombre_modulo: string
  resolucion_directoral: string | null
  horas_teoria: number
  horas_practica: number
  creditos: number
  horas_efsrt: number
  codigo_verificacion: string
  fecha_emision: string
  estado: string
  estudiante?: Estudiante
  programa?: Programa
}

const programaStore = useProgramaStore()

const activeTab = ref<'titulos' | 'certificados'>('titulos')
const loading = ref(false)
const error = ref<string | null>(null)
const successMsg = ref<string | null>(null)

// Datos
const titulaciones = ref<Titulacion[]>([])
const certificados = ref<CertificadoModular[]>([])
const estudiantesList = ref<Estudiante[]>([])

// Filtros
const search = ref('')
const selectedPrograma = ref<number | ''>('')
const selectedModulo = ref<number | ''>('')

// Modal Titulación
const isModalTituloOpen = ref(false)
const formTitulo = ref({
  estudiante_id: '',
  programa_id: '',
  modalidad_titulacion: 'PROYECTO_APLICACION_PROFESIONAL',
  numero_titulo: '',
  numero_registro: '',
  numero_folio: '',
  numero_libro: 'LIB-IV',
  resolucion_directoral: '',
  codigo_minedu: '',
  fecha_sustentacion: '',
  fecha_emision: new Date().toISOString().split('T')[0],
  estado: 'REGISTRADO'
})

// Modal Certificado Modular
const isModalCertOpen = ref(false)
const formCert = ref({
  estudiante_id: '',
  programa_id: '',
  numero_modulo: 1,
  nombre_modulo: '',
  resolucion_directoral: '',
  horas_teoria: 144,
  horas_practica: 288,
  creditos: 22.0,
  horas_efsrt: 120,
  fecha_emision: new Date().toISOString().split('T')[0],
  estado: 'EMITIDO'
})

onMounted(async () => {
  await programaStore.fetchProgramas()
  await Promise.all([loadTitulaciones(), loadCertificados(), loadEstudiantes()])
})

watch(() => programaStore.selectedProgramaId, (val) => {
  selectedPrograma.value = val ?? ''
  if (activeTab.value === 'titulos') loadTitulaciones()
  else loadCertificados()
})

async function loadTitulaciones() {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, any> = {}
    if (selectedPrograma.value) params.programa_id = selectedPrograma.value
    if (search.value) params.search = search.value

    const res = await http.get('/admin/titulacion', { params })
    titulaciones.value = res.data.data?.data || res.data.data || []
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cargar títulos.'
  } finally {
    loading.value = false
  }
}

async function loadCertificados() {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, any> = {}
    if (selectedPrograma.value) params.programa_id = selectedPrograma.value
    if (selectedModulo.value) params.numero_modulo = selectedModulo.value
    if (search.value) params.search = search.value

    const res = await http.get('/admin/certificados-modulares', { params })
    certificados.value = res.data.data?.data || res.data.data || []
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cargar certificados modulares.'
  } finally {
    loading.value = false
  }
}

async function loadEstudiantes() {
  try {
    const res = await http.get('/admin/estudiantes')
    estudiantesList.value = res.data.data?.data || res.data.data || []
  } catch (err) {
    console.error('Error cargando estudiantes:', err)
  }
}

async function submitTitulo() {
  loading.value = true
  error.value = null
  try {
    await http.post('/admin/titulacion', {
      ...formTitulo.value,
      estudiante_id: Number(formTitulo.value.estudiante_id),
      programa_id: Number(formTitulo.value.programa_id)
    })
    successMsg.value = 'Título registrado con éxito en el Libro de Títulos institucional.'
    isModalTituloOpen.value = false
    await loadTitulaciones()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al registrar título.'
  } finally {
    loading.value = false
  }
}

async function submitCertificado() {
  loading.value = true
  error.value = null
  try {
    await http.post('/admin/certificados-modulares', {
      ...formCert.value,
      estudiante_id: Number(formCert.value.estudiante_id),
      programa_id: Number(formCert.value.programa_id),
      numero_modulo: Number(formCert.value.numero_modulo),
      horas_teoria: Number(formCert.value.horas_teoria),
      horas_practica: Number(formCert.value.horas_practica),
      creditos: Number(formCert.value.creditos),
      horas_efsrt: Number(formCert.value.horas_efsrt)
    })
    successMsg.value = 'Certificado modular emitido y registrado exitosamente.'
    isModalCertOpen.value = false
    await loadCertificados()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al emitir certificado modular.'
  } finally {
    loading.value = false
  }
}

async function exportLibroTitulos() {
  try {
    const res = await http.get('/admin/titulacion/exportar/libro-titulos', { responseType: 'blob' })
    const blobUrl = window.URL.createObjectURL(new Blob([res.data]))
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = `libro_titulos_sam_${new Date().toISOString().slice(0, 19).replace(/[-T:]/g, '')}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(blobUrl)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al exportar el libro de títulos.'
  }
}

async function exportCertificados() {
  try {
    const res = await http.get('/admin/certificados-modulares/exportar/padron', { responseType: 'blob' })
    const blobUrl = window.URL.createObjectURL(new Blob([res.data]))
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = `padron_certificados_modulares_sam_${new Date().toISOString().slice(0, 19).replace(/[-T:]/g, '')}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(blobUrl)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al exportar el padrón de certificados modulares.'
  }
}

function getNombreCompleto(u?: Usuario) {
  if (!u) return 'Desconocido'
  const ap = u.apellidos || `${u.apellido_paterno || ''} ${u.apellido_materno || ''}`.trim()
  return `${ap}, ${u.nombres}`.trim()
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Titulación y Certificación Modular</h1>
        <p class="text-sm text-gray-500 mt-1">
          Libro de Títulos Oficial (Ley N° 30512) y Registro de Certificados Modulares (RVM N° 277-2019-MINEDU) - IESTP SAM
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          v-if="activeTab === 'titulos'"
          @click="exportLibroTitulos"
          class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 shadow-xs focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Exportar Libro CSV
        </button>

        <button
          v-if="activeTab === 'certificados'"
          @click="exportCertificados"
          class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 shadow-xs focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Exportar Padrón CSV
        </button>

        <button
          v-if="activeTab === 'titulos'"
          @click="isModalTituloOpen = true"
          class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg text-white bg-primary-600 hover:bg-primary-700 shadow-xs focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Registrar Título
        </button>

        <button
          v-if="activeTab === 'certificados'"
          @click="isModalCertOpen = true"
          class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg text-white bg-primary-600 hover:bg-primary-700 shadow-xs focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Emitir Certificado
        </button>
      </div>
    </div>

    <!-- Feedback Alerts -->
    <div v-if="successMsg" class="p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 flex justify-between items-center">
      <span>{{ successMsg }}</span>
      <button @click="successMsg = null" class="text-emerald-600 hover:text-emerald-800 font-bold">&times;</button>
    </div>
    <div v-if="error" class="p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 flex justify-between items-center">
      <span>{{ error }}</span>
      <button @click="error = null" class="text-rose-600 hover:text-rose-800 font-bold">&times;</button>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
      <nav class="-mb-px flex space-x-8" aria-label="Pestañas de titulación">
        <button
          @click="activeTab = 'titulos'; loadTitulaciones()"
          :class="[
            activeTab === 'titulos'
              ? 'border-primary-600 text-primary-600 font-semibold'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap py-3 px-1 border-b-2 text-sm flex items-center gap-2'
          ]"
        >
          <span>Libro de Títulos Institucional</span>
          <span class="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs font-medium">{{ titulaciones.length }}</span>
        </button>

        <button
          @click="activeTab = 'certificados'; loadCertificados()"
          :class="[
            activeTab === 'certificados'
              ? 'border-primary-600 text-primary-600 font-semibold'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap py-3 px-1 border-b-2 text-sm flex items-center gap-2'
          ]"
        >
          <span>Certificados Modulares (EFSRT)</span>
          <span class="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs font-medium">{{ certificados.length }}</span>
        </button>
      </nav>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-xs flex flex-wrap gap-4 items-center justify-between">
      <div class="flex flex-wrap gap-3 items-center flex-1">
        <div class="w-64">
          <input
            v-model="search"
            @keyup.enter="activeTab === 'titulos' ? loadTitulaciones() : loadCertificados()"
            type="text"
            placeholder="Buscar por estudiante, DNI, código..."
            class="w-full text-sm rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:outline-none"
          />
        </div>

        <div class="w-56">
          <select
            v-model="selectedPrograma"
            @change="activeTab === 'titulos' ? loadTitulaciones() : loadCertificados()"
            class="w-full text-sm rounded-lg border border-gray-300 px-3 py-2 bg-white focus:ring-2 focus:ring-primary-500 focus:outline-none"
          >
            <option value="">Todos los programas (8 SAM)</option>
            <option v-for="prog in programaStore.programas" :key="prog.id" :value="prog.id">
              {{ prog.nombre }}
            </option>
          </select>
        </div>

        <div v-if="activeTab === 'certificados'" class="w-40">
          <select
            v-model="selectedModulo"
            @change="loadCertificados()"
            class="w-full text-sm rounded-lg border border-gray-300 px-3 py-2 bg-white focus:ring-2 focus:ring-primary-500 focus:outline-none"
          >
            <option value="">Todos los módulos</option>
            <option :value="1">Módulo I</option>
            <option :value="2">Módulo II</option>
            <option :value="3">Módulo III</option>
          </select>
        </div>
      </div>

      <button
        @click="activeTab === 'titulos' ? loadTitulaciones() : loadCertificados()"
        class="px-3 py-2 text-sm font-medium rounded-lg text-primary-700 bg-primary-50 hover:bg-primary-100"
      >
        Actualizar
      </button>
    </div>

    <!-- TAB 1: LIBRO DE TÍTULOS -->
    <div v-if="activeTab === 'titulos'" class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
          <thead class="bg-gray-50 text-gray-700 text-xs uppercase tracking-wider border-b border-gray-200 font-semibold">
            <tr>
              <th scope="col" class="py-3 px-4">Libro / Folio / Reg.</th>
              <th scope="col" class="py-3 px-4">N° Título</th>
              <th scope="col" class="py-3 px-4">Egresado Titulado</th>
              <th scope="col" class="py-3 px-4">Programa de Estudios</th>
              <th scope="col" class="py-3 px-4">Modalidad</th>
              <th scope="col" class="py-3 px-4">Resolución Directoral</th>
              <th scope="col" class="py-3 px-4">Código Verificación / QR</th>
              <th scope="col" class="py-3 px-4">Estado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-if="loading" class="text-center py-6">
              <td colspan="8" class="py-8 text-gray-500">Cargando Libro de Títulos...</td>
            </tr>
            <tr v-else-if="titulaciones.length === 0" class="text-center py-6">
              <td colspan="8" class="py-8 text-gray-500">No hay títulos registrados con los filtros seleccionados.</td>
            </tr>
            <tr v-for="t in titulaciones" :key="t.id" class="hover:bg-gray-50 transition-colors">
              <td class="py-3 px-4 font-mono text-xs font-semibold text-gray-900">
                {{ t.numero_libro }} / F.{{ t.numero_folio }} / R.{{ t.numero_registro }}
              </td>
              <td class="py-3 px-4 font-bold text-gray-900">{{ t.numero_titulo }}</td>
              <td class="py-3 px-4">
                <div class="font-medium text-gray-900">{{ getNombreCompleto(t.estudiante?.usuario) }}</div>
                <div class="text-xs text-gray-500">DNI: {{ t.estudiante?.usuario?.numero_documento || 'S/N' }}</div>
              </td>
              <td class="py-3 px-4 font-medium text-gray-800">{{ t.programa?.nombre }}</td>
              <td class="py-3 px-4 text-xs">{{ t.modalidad_titulacion.replace(/_/g, ' ') }}</td>
              <td class="py-3 px-4 text-xs font-mono">{{ t.resolucion_directoral }}</td>
              <td class="py-3 px-4">
                <a
                  :href="'/verificar-documento/' + t.codigo_verificacion"
                  target="_blank"
                  class="inline-flex items-center gap-1 font-mono text-xs text-primary-600 hover:text-primary-800 font-semibold underline"
                >
                  <span>{{ t.codigo_verificacion }}</span>
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>
              </td>
              <td class="py-3 px-4">
                <span
                  :class="[
                    t.estado === 'REPORTADO_MINEDU' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                    t.estado === 'EMITIDO' ? 'bg-blue-50 text-blue-700 border-blue-200' :
                    t.estado === 'ANULADO' ? 'bg-rose-50 text-rose-700 border-rose-200' :
                    'bg-gray-50 text-gray-700 border-gray-200',
                    'px-2.5 py-1 text-xs font-semibold rounded-full border'
                  ]"
                >
                  {{ t.estado }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 2: CERTIFICACIÓN MODULAR -->
    <div v-if="activeTab === 'certificados'" class="bg-white rounded-xl border border-gray-200 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
          <thead class="bg-gray-50 text-gray-700 text-xs uppercase tracking-wider border-b border-gray-200 font-semibold">
            <tr>
              <th scope="col" class="py-3 px-4">Módulo</th>
              <th scope="col" class="py-3 px-4">Nombre del Módulo Formativo</th>
              <th scope="col" class="py-3 px-4">Estudiante</th>
              <th scope="col" class="py-3 px-4">Programa</th>
              <th scope="col" class="py-3 px-4">Horas (T / P / EFSRT)</th>
              <th scope="col" class="py-3 px-4">Créditos</th>
              <th scope="col" class="py-3 px-4">Resolución Directoral</th>
              <th scope="col" class="py-3 px-4">Verificación QR</th>
              <th scope="col" class="py-3 px-4">Estado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-if="loading" class="text-center py-6">
              <td colspan="9" class="py-8 text-gray-500">Cargando certificados modulares...</td>
            </tr>
            <tr v-else-if="certificados.length === 0" class="text-center py-6">
              <td colspan="9" class="py-8 text-gray-500">No hay certificados registrados con los filtros seleccionados.</td>
            </tr>
            <tr v-for="c in certificados" :key="c.id" class="hover:bg-gray-50 transition-colors">
              <td class="py-3 px-4 font-bold text-primary-700">Módulo {{ c.numero_modulo }}</td>
              <td class="py-3 px-4 font-medium text-gray-900">{{ c.nombre_modulo }}</td>
              <td class="py-3 px-4">
                <div class="font-medium text-gray-900">{{ getNombreCompleto(c.estudiante?.usuario) }}</div>
                <div class="text-xs text-gray-500">DNI: {{ c.estudiante?.usuario?.numero_documento || 'S/N' }}</div>
              </td>
              <td class="py-3 px-4">{{ c.programa?.nombre }}</td>
              <td class="py-3 px-4 font-mono text-xs">
                {{ c.horas_teoria }}T / {{ c.horas_practica }}P / <strong class="text-emerald-700">{{ c.horas_efsrt }} EFSRT</strong>
              </td>
              <td class="py-3 px-4 font-semibold text-gray-800">{{ Number(c.creditos).toFixed(1) }} cr.</td>
              <td class="py-3 px-4 text-xs font-mono">{{ c.resolucion_directoral || '—' }}</td>
              <td class="py-3 px-4">
                <a
                  :href="'/verificar-documento/' + c.codigo_verificacion"
                  target="_blank"
                  class="inline-flex items-center gap-1 font-mono text-xs text-primary-600 hover:text-primary-800 font-semibold underline"
                >
                  <span>{{ c.codigo_verificacion }}</span>
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>
              </td>
              <td class="py-3 px-4">
                <span
                  :class="[
                    c.estado === 'EMITIDO' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                    'bg-rose-50 text-rose-700 border-rose-200',
                    'px-2.5 py-1 text-xs font-semibold rounded-full border'
                  ]"
                >
                  {{ c.estado }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL REGISTRO DE TÍTULO -->
    <div v-if="isModalTituloOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-xs">
      <div class="bg-white rounded-2xl shadow-xl max-w-xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b pb-3">
          <h3 class="text-lg font-bold text-gray-900">Registrar en Libro de Títulos (Ley N° 30512)</h3>
          <button @click="isModalTituloOpen = false" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>

        <form @submit.prevent="submitTitulo" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Egresado / Estudiante</label>
              <select v-model="formTitulo.estudiante_id" required class="w-full text-sm rounded-lg border border-gray-300 p-2">
                <option value="">Seleccione estudiante</option>
                <option v-for="est in estudiantesList" :key="est.id" :value="est.id">
                  {{ getNombreCompleto(est.usuario) }} ({{ est.codigo_estudiante || 'ID ' + est.id }})
                </option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Programa de Estudios (SAM)</label>
              <select v-model="formTitulo.programa_id" required class="w-full text-sm rounded-lg border border-gray-300 p-2">
                <option value="">Seleccione programa</option>
                <option v-for="prog in programaStore.programas" :key="prog.id" :value="prog.id">
                  {{ prog.nombre }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">N° Libro</label>
              <input v-model="formTitulo.numero_libro" required type="text" placeholder="LIB-IV" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">N° Folio</label>
              <input v-model="formTitulo.numero_folio" required type="text" placeholder="045" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">N° Registro</label>
              <input v-model="formTitulo.numero_registro" required type="text" placeholder="REG-2026-088" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">N° Título</label>
              <input v-model="formTitulo.numero_titulo" required type="text" placeholder="T-2026-001" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Modalidad de Titulación</label>
            <select v-model="formTitulo.modalidad_titulacion" required class="w-full text-sm rounded-lg border border-gray-300 p-2">
              <option value="PROYECTO_APLICACION_PROFESIONAL">Proyecto de Aplicación Profesional</option>
              <option value="EXAMEN_SUFICIENCIA_PROFESIONAL">Examen de Suficiencia Profesional</option>
              <option value="TRABAJO_INVESTIGACION">Trabajo de Investigación Aplicada e Innovación</option>
            </select>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Resolución Directoral Institucional</label>
              <input v-model="formTitulo.resolucion_directoral" required type="text" placeholder="RD N° 0120-2026-DG-IESTP-SAM" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Código REGISTRA / MINEDU (Opcional)</label>
              <input v-model="formTitulo.codigo_minedu" type="text" placeholder="TIT-MINEDU-2026-..." class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Fecha de Sustentación</label>
              <input v-model="formTitulo.fecha_sustentacion" type="date" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Fecha de Emisión</label>
              <input v-model="formTitulo.fecha_emision" required type="date" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <button @click="isModalTituloOpen = false" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancelar</button>
            <button :disabled="loading" type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50">
              {{ loading ? 'Registrando...' : 'Registrar en Libro' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL CERTIFICADO MODULAR -->
    <div v-if="isModalCertOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-xs">
      <div class="bg-white rounded-2xl shadow-xl max-w-xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b pb-3">
          <h3 class="text-lg font-bold text-gray-900">Emitir Certificado Modular (RVM N° 277-2019-MINEDU)</h3>
          <button @click="isModalCertOpen = false" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>

        <form @submit.prevent="submitCertificado" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Estudiante</label>
              <select v-model="formCert.estudiante_id" required class="w-full text-sm rounded-lg border border-gray-300 p-2">
                <option value="">Seleccione estudiante</option>
                <option v-for="est in estudiantesList" :key="est.id" :value="est.id">
                  {{ getNombreCompleto(est.usuario) }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Programa de Estudios</label>
              <select v-model="formCert.programa_id" required class="w-full text-sm rounded-lg border border-gray-300 p-2">
                <option value="">Seleccione programa</option>
                <option v-for="prog in programaStore.programas" :key="prog.id" :value="prog.id">
                  {{ prog.nombre }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">N° de Módulo</label>
              <select v-model="formCert.numero_modulo" required class="w-full text-sm rounded-lg border border-gray-300 p-2">
                <option :value="1">Módulo I</option>
                <option :value="2">Módulo II</option>
                <option :value="3">Módulo III</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-gray-700 mb-1">Nombre Oficial del Módulo</label>
              <input v-model="formCert.nombre_modulo" required type="text" placeholder="Ej: Módulo I: Gestión y Soporte..." class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Horas Teoría</label>
              <input v-model="formCert.horas_teoria" required type="number" min="0" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Horas Práctica</label>
              <input v-model="formCert.horas_practica" required type="number" min="0" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Créditos</label>
              <input v-model="formCert.creditos" required type="number" step="0.5" min="0" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Horas EFSRT</label>
              <input v-model="formCert.horas_efsrt" required type="number" min="0" placeholder="120" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Resolución Directoral</label>
              <input v-model="formCert.resolucion_directoral" type="text" placeholder="RD N° 088-2025-DG-IESTP-SAM" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">Fecha de Emisión</label>
              <input v-model="formCert.fecha_emision" required type="date" class="w-full text-sm rounded-lg border border-gray-300 p-2" />
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <button @click="isModalCertOpen = false" type="button" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancelar</button>
            <button :disabled="loading" type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50">
              {{ loading ? 'Emitiendo...' : 'Emitir Certificado' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
