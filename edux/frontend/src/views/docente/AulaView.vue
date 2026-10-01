<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { unidadesApi, materialesApi, asistenciasApi, evaluacionesApi } from '@/api'
import type { Unidad, AsistenciaItem, AsistenciaResumenItem, Evaluacion } from '@/types'
import BaseModal from '@/components/ui/BaseModal.vue'
import FileDropzone from '@/components/ui/FileDropzone.vue'

const route = useRoute()
const grupoId = Number(route.params.id)

const activeTab = ref<'contenido' | 'asistencia' | 'evaluaciones'>('contenido')

// ─── Contenido (Unidades y Materiales) ───
const unidades = ref<Unidad[]>([])
const loading = ref(true)

const openUnidadModal = ref(false)
const unidadForm = ref({ titulo: '', descripcion: '', orden: 1 })

const openMaterialModal = ref(false)
const selectedUnidadId = ref<number | null>(null)
const materialForm = ref({ titulo: '', descripcion: '', archivo: null as File | null })
const uploadingMaterial = ref(false)

async function loadUnidades() {
  loading.value = true
  try {
    const res = await unidadesApi.list(grupoId)
    unidades.value = res.data.data
  } catch (error) {
    console.error('Error loading unidades', error)
  } finally {
    loading.value = false
  }
}

async function handleCreateUnidad() {
  try {
    await unidadesApi.create(grupoId, unidadForm.value)
    openUnidadModal.value = false
    unidadForm.value = { titulo: '', descripcion: '', orden: 1 }
    loadUnidades()
  } catch (error) {
    alert('Error al crear unidad')
  }
}

function showMaterialModal(unidadId: number) {
  selectedUnidadId.value = unidadId
  materialForm.value = { titulo: '', descripcion: '', archivo: null }
  openMaterialModal.value = true
}

async function handleCreateMaterial() {
  if (!selectedUnidadId.value || !materialForm.value.archivo) return
  const formData = new FormData()
  formData.append('titulo', materialForm.value.titulo)
  formData.append('descripcion', materialForm.value.descripcion)
  formData.append('archivo', materialForm.value.archivo)
  
  uploadingMaterial.value = true
  try {
    await materialesApi.create(selectedUnidadId.value, formData)
    openMaterialModal.value = false
    loadUnidades()
  } catch (error) {
    alert('Error al subir material')
  } finally {
    uploadingMaterial.value = false
  }
}

async function handleDownload(materialId: number, fileName: string) {
  try {
    const res = await materialesApi.descargar(materialId)
    const url = window.URL.createObjectURL(new Blob([res.data as BlobPart]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', fileName)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (error) {
    alert('Error al descargar')
  }
}

// ─── Asistencia ───
const fechaAsistencia = ref(new Date().toISOString().split('T')[0])
const asistencias = ref<AsistenciaItem[]>([])
const loadingAsistencia = ref(false)
const savingAsistencia = ref(false)
const verResumenAsistencia = ref(false)
const resumenAsistencias = ref<AsistenciaResumenItem[]>([])
const totalFechas = ref(0)

async function loadAsistencias() {
  loadingAsistencia.value = true
  try {
    const res = await asistenciasApi.list(grupoId, fechaAsistencia.value)
    asistencias.value = res.data.data
  } catch (error) {
    console.error('Error cargando asistencias', error)
  } finally {
    loadingAsistencia.value = false
  }
}

async function loadResumenAsistencias() {
  try {
    const res = await asistenciasApi.resumen(grupoId)
    resumenAsistencias.value = res.data.data
    totalFechas.value = res.data.total_fechas
  } catch (error) {
    console.error('Error cargando resumen de asistencias', error)
  }
}

async function handleGuardarAsistencia() {
  savingAsistencia.value = true
  try {
    const payload = {
      fecha: fechaAsistencia.value,
      asistencias: asistencias.value.map(a => ({
        estudiante_id: a.estudiante_id,
        estado_asistencia: a.estado_asistencia,
        observacion: a.observacion || ''
      }))
    }
    await asistenciasApi.store(grupoId, payload)
    alert('Asistencia guardada exitosamente')
    loadResumenAsistencias()
  } catch (error) {
    alert('Error al guardar asistencia')
  } finally {
    savingAsistencia.value = false
  }
}

// ─── Evaluaciones ───
const evaluaciones = ref<Evaluacion[]>([])
const loadingEvaluaciones = ref(false)
const openEvaluacionModal = ref(false)
const evaluacionForm = ref({
  titulo: '',
  instrucciones: '',
  puntaje_maximo: 20,
  duracion_minutos: 60,
  intentos_permitidos: 1,
  fecha_inicio: '',
  fecha_fin: '',
  estado: 'PUBLICADA' as const
})

async function loadEvaluaciones() {
  loadingEvaluaciones.value = true
  try {
    const res = await evaluacionesApi.list(grupoId)
    evaluaciones.value = res.data.data
  } catch (error) {
    console.error('Error cargando evaluaciones', error)
  } finally {
    loadingEvaluaciones.value = false
  }
}

async function handleCreateEvaluacion() {
  try {
    await evaluacionesApi.create(grupoId, evaluacionForm.value)
    openEvaluacionModal.value = false
    evaluacionForm.value = {
      titulo: '',
      instrucciones: '',
      puntaje_maximo: 20,
      duracion_minutos: 60,
      intentos_permitidos: 1,
      fecha_inicio: '',
      fecha_fin: '',
      estado: 'PUBLICADA'
    }
    loadEvaluaciones()
  } catch (error) {
    alert('Error al crear evaluación')
  }
}

function switchTab(tab: 'contenido' | 'asistencia' | 'evaluaciones') {
  activeTab.value = tab
  if (tab === 'asistencia') {
    loadAsistencias()
    loadResumenAsistencias()
  } else if (tab === 'evaluaciones') {
    loadEvaluaciones()
  }
}

onMounted(() => {
  loadUnidades()
})
</script>

<template>
  <div>
    <!-- Encabezado y Selector de Pestañas -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Aula Virtual — Gestión Docente</h1>
        <p class="text-sm text-gray-500">Gestione contenidos, registro de asistencias y evaluaciones online del curso.</p>
      </div>

      <div class="flex items-center gap-2">
        <button 
          v-if="activeTab === 'contenido'" 
          @click="openUnidadModal = true" 
          class="btn-primary"
        >
          Nueva Unidad
        </button>
        <button 
          v-if="activeTab === 'asistencia' && !verResumenAsistencia" 
          @click="handleGuardarAsistencia" 
          :disabled="savingAsistencia || asistencias.length === 0" 
          class="btn-primary"
        >
          {{ savingAsistencia ? 'Guardando...' : 'Guardar Asistencia' }}
        </button>
        <button 
          v-if="activeTab === 'evaluaciones'" 
          @click="openEvaluacionModal = true" 
          class="btn-primary"
        >
          Nueva Evaluación
        </button>
      </div>
    </div>

    <!-- Pestañas de Navegación -->
    <div class="border-b border-gray-200 mb-6">
      <nav class="-mb-px flex space-x-8">
        <button
          @click="switchTab('contenido')"
          :class="[
            activeTab === 'contenido'
              ? 'border-primary-600 text-primary-600 font-semibold'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap pb-4 px-1 border-b-2 text-sm transition-colors flex items-center gap-2'
          ]"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          Contenidos y Materiales
        </button>
        <button
          @click="switchTab('asistencia')"
          :class="[
            activeTab === 'asistencia'
              ? 'border-primary-600 text-primary-600 font-semibold'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap pb-4 px-1 border-b-2 text-sm transition-colors flex items-center gap-2'
          ]"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          Control de Asistencia
        </button>
        <button
          @click="switchTab('evaluaciones')"
          :class="[
            activeTab === 'evaluaciones'
              ? 'border-primary-600 text-primary-600 font-semibold'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap pb-4 px-1 border-b-2 text-sm transition-colors flex items-center gap-2'
          ]"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Evaluaciones y Quizzes
        </button>
      </nav>
    </div>

    <!-- ─── PESTAÑA 1: CONTENIDO ─── -->
    <div v-if="activeTab === 'contenido'">
      <div v-if="loading" class="flex justify-center py-12">
        <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
      </div>

      <div v-else class="space-y-4">
        <div v-for="unidad in unidades" :key="unidad.id" class="border border-gray-200 rounded-lg bg-white overflow-hidden shadow-sm">
          <div class="bg-gray-50 px-4 py-3 flex justify-between items-center border-b border-gray-200">
            <h3 class="font-bold text-gray-900">{{ unidad.titulo }}</h3>
            <button @click="showMaterialModal(unidad.id)" class="text-sm font-medium text-primary-600 hover:underline">
              + Añadir Material
            </button>
          </div>
          <div class="p-4">
            <p class="text-sm text-gray-600 mb-4">{{ unidad.descripcion || 'Sin descripción' }}</p>
            <div v-if="unidad.materiales?.length" class="space-y-2">
              <div v-for="mat in unidad.materiales" :key="mat.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-md border border-gray-100">
                <div>
                  <p class="font-medium text-sm text-gray-900">{{ mat.titulo }}</p>
                  <p class="text-xs text-gray-500">{{ mat.descripcion }}</p>
                </div>
                <button @click="handleDownload(mat.id, mat.archivo_original ?? 'archivo')" class="text-primary-600 text-sm font-medium hover:underline">
                  Descargar
                </button>
              </div>
            </div>
            <div v-else class="text-sm text-gray-400 italic">No hay materiales en esta unidad.</div>
          </div>
        </div>
        <div v-if="unidades.length === 0" class="text-center py-12 text-gray-500 bg-white rounded-lg border border-dashed border-gray-300">
          No hay unidades creadas en este grupo. Haz clic en "Nueva Unidad" para comenzar.
        </div>
      </div>
    </div>

    <!-- ─── PESTAÑA 2: ASISTENCIA ─── -->
    <div v-else-if="activeTab === 'asistencia'" class="space-y-4">
      <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center gap-3">
          <label class="text-sm font-medium text-gray-700">Fecha de Clase:</label>
          <input 
            v-model="fechaAsistencia" 
            type="date" 
            @change="loadAsistencias" 
            class="input-field max-w-[180px] text-sm" 
          />
        </div>

        <div class="flex items-center gap-2">
          <button 
            @click="verResumenAsistencia = !verResumenAsistencia" 
            class="btn-secondary text-xs flex items-center gap-1.5"
          >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            {{ verResumenAsistencia ? 'Ver Registro Diario' : 'Ver Resumen Acumulado' }}
          </button>
        </div>
      </div>

      <!-- Vista Diaria de Asistencia -->
      <div v-if="!verResumenAsistencia">
        <div v-if="loadingAsistencia" class="flex justify-center py-12">
          <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
        </div>

        <div v-else class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
              <tr>
                <th class="px-4 py-3 text-left">Código</th>
                <th class="px-4 py-3 text-left">Estudiante</th>
                <th class="px-4 py-3 text-center">Estado de Asistencia</th>
                <th class="px-4 py-3 text-left">Observación</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-sm">
              <tr v-for="item in asistencias" :key="item.estudiante_id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ item.codigo }}</td>
                <td class="px-4 py-3 font-medium text-gray-900">{{ item.nombre_completo }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="inline-flex rounded-md shadow-sm" role="group">
                    <button
                      type="button"
                      @click="item.estado_asistencia = 'PRESENTE'"
                      :class="[
                        item.estado_asistencia === 'PRESENTE' ? 'bg-green-600 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-50',
                        'px-3 py-1 text-xs border border-gray-300 rounded-l-md transition-colors'
                      ]"
                    >
                      P
                    </button>
                    <button
                      type="button"
                      @click="item.estado_asistencia = 'TARDANZA'"
                      :class="[
                        item.estado_asistencia === 'TARDANZA' ? 'bg-amber-500 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-50',
                        'px-3 py-1 text-xs border-t border-b border-gray-300 transition-colors'
                      ]"
                    >
                      T
                    </button>
                    <button
                      type="button"
                      @click="item.estado_asistencia = 'AUSENTE'"
                      :class="[
                        item.estado_asistencia === 'AUSENTE' ? 'bg-red-600 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-50',
                        'px-3 py-1 text-xs border-t border-b border-r border-gray-300 transition-colors'
                      ]"
                    >
                      A
                    </button>
                    <button
                      type="button"
                      @click="item.estado_asistencia = 'JUSTIFICADO'"
                      :class="[
                        item.estado_asistencia === 'JUSTIFICADO' ? 'bg-blue-600 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-50',
                        'px-3 py-1 text-xs border border-gray-300 rounded-r-md transition-colors'
                      ]"
                    >
                      J
                    </button>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <input 
                    v-model="item.observacion" 
                    type="text" 
                    placeholder="Opcional..." 
                    class="input-field text-xs py-1"
                  />
                </td>
              </tr>
              <tr v-if="asistencias.length === 0">
                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                  No hay estudiantes matriculados en este grupo para registrar asistencia.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Resumen Acumulado -->
      <div v-else class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
          <h3 class="font-bold text-gray-900 text-sm">Resumen de Asistencia Acumulada (Total de sesiones registradas: {{ totalFechas }})</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            <tr>
              <th class="px-4 py-3 text-left">Estudiante</th>
              <th class="px-4 py-3 text-center">Presentes</th>
              <th class="px-4 py-3 text-center">Tardanzas</th>
              <th class="px-4 py-3 text-center">Faltas</th>
              <th class="px-4 py-3 text-center">Justificadas</th>
              <th class="px-4 py-3 text-center">% Asistencia</th>
              <th class="px-4 py-3 text-center">Condición</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-for="res in resumenAsistencias" :key="res.estudiante_id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-900">{{ res.nombre_completo }}</td>
              <td class="px-4 py-3 text-center text-green-700 font-semibold">{{ res.presentes }}</td>
              <td class="px-4 py-3 text-center text-amber-600 font-semibold">{{ res.tardanzas }}</td>
              <td class="px-4 py-3 text-center text-red-600 font-semibold">{{ res.ausentes }}</td>
              <td class="px-4 py-3 text-center text-blue-600 font-semibold">{{ res.justificados }}</td>
              <td class="px-4 py-3 text-center font-bold">
                {{ res.porcentaje_asistencia }}%
              </td>
              <td class="px-4 py-3 text-center">
                <span 
                  v-if="res.en_riesgo" 
                  class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-700"
                >
                  <svg class="h-3 w-3 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  En Riesgo (&lt;70%)
                </span>
                <span 
                  v-else 
                  class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700"
                >
                  Regular
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ─── PESTAÑA 3: EVALUACIONES ─── -->
    <div v-else-if="activeTab === 'evaluaciones'" class="space-y-4">
      <div v-if="loadingEvaluaciones" class="flex justify-center py-12">
        <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
      </div>

      <div v-else class="space-y-4">
        <div 
          v-for="evaluacion in evaluaciones" 
          :key="evaluacion.id" 
          class="border border-gray-200 rounded-lg bg-white p-5 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
        >
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <h3 class="font-bold text-gray-900 text-base">{{ evaluacion.titulo }}</h3>
              <span 
                :class="[
                  evaluacion.estado === 'PUBLICADA' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600',
                  'px-2 py-0.5 text-xs font-semibold rounded-full'
                ]"
              >
                {{ evaluacion.estado }}
              </span>
            </div>
            <p class="text-sm text-gray-600">{{ evaluacion.instrucciones || 'Sin instrucciones adicionales' }}</p>
            <div class="text-xs text-gray-500 flex flex-wrap gap-4 pt-1">
              <span class="inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Duración: {{ evaluacion.duracion_minutos || 'Ilimitada' }} min
              </span>
              <span class="inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Puntaje Máx: {{ evaluacion.puntaje_maximo }} pts
              </span>
              <span class="inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Intentos: {{ evaluacion.intentos_permitidos }}
              </span>
              <span class="inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Cierre: {{ new Date(evaluacion.fecha_fin).toLocaleString() }}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-gray-500 bg-gray-50 border border-gray-200 rounded px-2.5 py-1">
              {{ evaluacion.preguntas_count || 0 }} Preguntas
            </span>
          </div>
        </div>

        <div v-if="evaluaciones.length === 0" class="text-center py-12 text-gray-500 bg-white rounded-lg border border-dashed border-gray-300">
          No hay evaluaciones registradas en este grupo. Haz clic en "Nueva Evaluación" para programar una.
        </div>
      </div>
    </div>

    <!-- Modal Unidad -->
    <BaseModal :open="openUnidadModal" @close="openUnidadModal = false" title="Nueva Unidad">
      <form @submit.prevent="handleCreateUnidad" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Título</label>
          <input v-model="unidadForm.titulo" type="text" class="input-field mt-1" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Descripción</label>
          <textarea v-model="unidadForm.descripcion" class="input-field mt-1" rows="3"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Orden</label>
          <input v-model="unidadForm.orden" type="number" class="input-field mt-1" required />
        </div>
        <div class="flex justify-end space-x-3 pt-4">
          <button type="button" @click="openUnidadModal = false" class="btn-secondary">Cancelar</button>
          <button type="submit" class="btn-primary">Guardar</button>
        </div>
      </form>
    </BaseModal>

    <!-- Modal Material -->
    <BaseModal :open="openMaterialModal" @close="openMaterialModal = false" title="Subir Material">
      <form @submit.prevent="handleCreateMaterial" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Título</label>
          <input v-model="materialForm.titulo" type="text" class="input-field mt-1" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Descripción</label>
          <textarea v-model="materialForm.descripcion" class="input-field mt-1" rows="2"></textarea>
        </div>
        <FileDropzone
          v-model="materialForm.archivo"
          label="Archivo del material"
          accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.mp4,.zip"
          help="PDF, Office, imágenes, video MP4 o ZIP."
          :max-size-mb="20"
          required
        />
        <div class="flex justify-end space-x-3 pt-4">
          <button type="button" @click="openMaterialModal = false" class="btn-secondary" :disabled="uploadingMaterial">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="!materialForm.archivo || uploadingMaterial">
            {{ uploadingMaterial ? 'Subiendo…' : 'Subir material' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Modal Nueva Evaluación -->
    <BaseModal :open="openEvaluacionModal" @close="openEvaluacionModal = false" title="Programar Nueva Evaluación">
      <form @submit.prevent="handleCreateEvaluacion" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Título de la Evaluación</label>
          <input v-model="evaluacionForm.titulo" type="text" placeholder="Ej. Examen Parcial / Cuestionario 1" class="input-field mt-1" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Instrucciones</label>
          <textarea v-model="evaluacionForm.instrucciones" class="input-field mt-1" rows="2" placeholder="Indicaciones para los alumnos..."></textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Puntaje Máximo</label>
            <input v-model.number="evaluacionForm.puntaje_maximo" type="number" step="0.5" class="input-field mt-1" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Duración (minutos)</label>
            <input v-model.number="evaluacionForm.duracion_minutos" type="number" class="input-field mt-1" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Fecha y Hora de Inicio</label>
            <input v-model="evaluacionForm.fecha_inicio" type="datetime-local" class="input-field mt-1" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Fecha y Hora de Fin</label>
            <input v-model="evaluacionForm.fecha_fin" type="datetime-local" class="input-field mt-1" required />
          </div>
        </div>
        <div class="flex justify-end space-x-3 pt-4">
          <button type="button" @click="openEvaluacionModal = false" class="btn-secondary">Cancelar</button>
          <button type="submit" class="btn-primary">Crear Evaluación</button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
