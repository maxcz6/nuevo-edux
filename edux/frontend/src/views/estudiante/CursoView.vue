<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { unidadesApi, tareasApi, entregasApi, materialesApi, evaluacionesApi, asistenciasApi } from '@/api'
import type { Unidad, Tarea, Evaluacion } from '@/types'
import BaseModal from '@/components/ui/BaseModal.vue'
import FileDropzone from '@/components/ui/FileDropzone.vue'

const route = useRoute()
const grupoId = Number(route.params.id)

const unidades = ref<Unidad[]>([])
const tareas = ref<Tarea[]>([])
const evaluaciones = ref<Evaluacion[]>([])
const misAsistenciasData = ref<{ asistencias: Array<{ id: number; fecha: string; estado_asistencia: string; observacion?: string }>; resumen: Record<string, unknown> } | null>(null)
const loading = ref(true)

const currentTab = ref<'materiales' | 'tareas' | 'evaluaciones' | 'asistencia'>('materiales')

const entregaForm = ref({ archivo: null as File | null, comentarios: '' })
const selectedTarea = ref<Tarea | null>(null)
const openEntregaModal = ref(false)
const submittingEntrega = ref(false)

async function loadData() {
  loading.value = true
  try {
    const [resUnidades, resTareas, resEval, resAsist] = await Promise.all([
      unidadesApi.list(grupoId),
      tareasApi.list(grupoId),
      evaluacionesApi.list(grupoId),
      asistenciasApi.misAsistencias(grupoId).catch(() => ({ data: { data: null } }))
    ])
    unidades.value = resUnidades.data.data
    tareas.value = resTareas.data.data
    evaluaciones.value = resEval.data.data
    misAsistenciasData.value = resAsist.data.data as typeof misAsistenciasData.value
  } catch (error) {
    console.error('Error loading data', error)
  } finally {
    loading.value = false
  }
}

async function handleDownloadMaterial(materialId: number, fileName: string) {
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

async function handleDownloadAdjuntoDocente(tarea: Tarea) {
  try {
    const res = await tareasApi.descargarAdjunto(tarea.id)
    const url = window.URL.createObjectURL(new Blob([res.data as BlobPart]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', tarea.archivo_adjunto_nombre || `${tarea.titulo}.${(tarea.archivo_tipo || 'pdf').toLowerCase()}`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch {
    alert('Error al descargar el archivo de la actividad')
  }
}

function formatFecha(fecha: string | null | undefined): string {
  if (!fecha) return ''
  try {
    return new Date(fecha).toLocaleString('es-PE', {
      dateStyle: 'medium',
      timeStyle: 'short',
    })
  } catch {
    return fecha
  }
}

function openEntrega(tarea: Tarea) {
  selectedTarea.value = tarea
  entregaForm.value = {
    archivo: null,
    comentarios: tarea.mi_entrega?.comentario_estudiante || ''
  }
  openEntregaModal.value = true
}

async function submitEntrega() {
  if (!selectedTarea.value) return
  const formData = new FormData()
  if (entregaForm.value.archivo) {
    formData.append('archivo', entregaForm.value.archivo)
  }
  formData.append('comentario_estudiante', entregaForm.value.comentarios)

  submittingEntrega.value = true
  try {
    await entregasApi.create(selectedTarea.value.id, formData)
    openEntregaModal.value = false
    // Defer alert to allow modal close transition (150ms) to complete
    setTimeout(() => alert('Entrega enviada exitosamente'), 200)
    await loadData()
  } catch (error: any) {
    console.error('Error al enviar la entrega:', error.response?.data || error)
    setTimeout(() => alert('Error al enviar la entrega'), 200)
  } finally {
    submittingEntrega.value = false
  }
}

// ─── Rendición de Evaluaciones ───
const openExamenModal = ref(false)
const evaluacionEnCurso = ref<Evaluacion | null>(null)
const intentoEnCurso = ref<{ id: number; numero_intento: number } | null>(null)
const preguntasExamen = ref<Array<{ id: number; tipo: string; enunciado: string; puntaje: number; opciones?: Array<{ id: number; texto: string }> }>>([])
const respuestasExamen = ref<Record<number, number>>({})
const submittingExamen = ref(false)

async function handleIniciarEvaluacion(evaluacion: Evaluacion) {
  try {
    const res = await evaluacionesApi.iniciarIntento(evaluacion.id)
    evaluacionEnCurso.value = evaluacion
    intentoEnCurso.value = res.data.intento
    preguntasExamen.value = res.data.preguntas as typeof preguntasExamen.value
    respuestasExamen.value = {}
    openExamenModal.value = true
  } catch (error: unknown) {
    const msg = (error as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Error al iniciar la evaluación'
    alert(msg)
  }
}

async function handleEnviarExamen() {
  if (!evaluacionEnCurso.value || !intentoEnCurso.value) return

  submittingExamen.value = true
  try {
    const respuestasPayload = Object.entries(respuestasExamen.value).map(([pregId, opcionId]) => ({
      pregunta_id: Number(pregId),
      opcion_id: opcionId
    }))

    const res = await evaluacionesApi.enviarRespuestas(evaluacionEnCurso.value.id, intentoEnCurso.value.id, {
      respuestas: respuestasPayload
    })

    alert(`¡Evaluación finalizada con éxito! Calificación obtenida: ${res.data.nota} pts`)
    openExamenModal.value = false
    loadData()
  } catch (error) {
    alert('Error al enviar respuestas del examen')
  } finally {
    submittingExamen.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header del Aula Virtual -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/80">
      <div>
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-primary-50 text-primary-700 border border-primary-200/60 text-xs font-semibold mb-2">
          <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
          Aula Virtual de Aprendizaje
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold font-headline text-slate-900 tracking-tight">Aula Virtual del Curso</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">Accede a las unidades, descarga material didáctico y envía tus asignaciones.</p>
      </div>
      <RouterLink to="/estudiante/dashboard" class="btn-secondary self-start sm:self-auto text-xs py-2 px-3.5 rounded-xl font-semibold inline-flex items-center gap-2">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Volver a Mis Cursos
      </RouterLink>
    </div>

    <!-- Pestañas de Navegación del Alumno tipo píldora -->
    <div class="p-1.5 bg-slate-100/80 dark:bg-slate-800/60 rounded-2xl border border-slate-200/70 inline-flex flex-wrap gap-1">
      <button 
        @click="currentTab = 'materiales'" 
        :class="[currentTab === 'materiales' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60 font-medium', 'whitespace-nowrap py-2 px-3.5 rounded-xl text-xs sm:text-sm flex items-center gap-2 transition-all']"
      >
        <svg class="h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
        Unidades y Materiales
      </button>
      <button 
        @click="currentTab = 'tareas'" 
        :class="[currentTab === 'tareas' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60 font-medium', 'whitespace-nowrap py-2 px-3.5 rounded-xl text-xs sm:text-sm flex items-center gap-2 transition-all']"
      >
        <svg class="h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        Tareas
      </button>
      <button 
        @click="currentTab = 'evaluaciones'" 
        :class="[currentTab === 'evaluaciones' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60 font-medium', 'whitespace-nowrap py-2 px-3.5 rounded-xl text-xs sm:text-sm flex items-center gap-2 transition-all']"
      >
        <svg class="h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Evaluaciones Online
      </button>
      <button 
        @click="currentTab = 'asistencia'" 
        :class="[currentTab === 'asistencia' ? 'bg-white text-slate-900 shadow-xs font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60 font-medium', 'whitespace-nowrap py-2 px-3.5 rounded-xl text-xs sm:text-sm flex items-center gap-2 transition-all']"
      >
        <svg class="h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        Mi Asistencia
      </button>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
    </div>

    <!-- ─── PESTAÑA: MATERIALES ─── -->
    <div v-else-if="currentTab === 'materiales'" class="space-y-6">
      <div v-for="unidad in unidades" :key="unidad.id" class="card p-0 overflow-hidden shadow-sm">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-bold text-gray-900">{{ unidad.titulo }}</h3>
          <p class="text-sm text-gray-600">{{ unidad.descripcion || 'Sin descripción' }}</p>
        </div>
        <div class="p-6">
          <ul v-if="unidad.materiales?.length" class="space-y-3">
            <li v-for="mat in unidad.materiales" :key="mat.id" class="flex items-center justify-between p-3 border border-gray-100 rounded-md hover:bg-gray-50">
              <div>
                <p class="font-medium text-sm text-gray-900">{{ mat.titulo }}</p>
                <p class="text-xs text-gray-500">{{ mat.descripcion }}</p>
              </div>
              <button @click="handleDownloadMaterial(mat.id, mat.archivo_original ?? 'archivo')" class="text-primary-600 hover:text-primary-800 text-sm font-medium">Descargar</button>
            </li>
          </ul>
          <p v-else class="text-sm text-gray-400 italic">No hay materiales en esta unidad.</p>
        </div>
      </div>
      <div v-if="unidades.length === 0" class="text-center py-8 text-gray-500">No hay unidades publicadas.</div>
    </div>

    <!-- ─── PESTAÑA: TAREAS ─── -->
    <div v-else-if="currentTab === 'tareas'" class="space-y-4">
      <div v-for="tarea in tareas" :key="tarea.id" class="card shadow-sm p-5 border border-gray-200 rounded-lg">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
          <div class="flex-1 space-y-2">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="text-lg font-bold text-gray-900">{{ tarea.titulo }}</h3>

              <!-- Badges de Estado de Entrega -->
              <span
                v-if="tarea.mi_entrega?.estado === 'CALIFICADA'"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800"
              >
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Calificada: {{ tarea.mi_entrega.nota }} / {{ tarea.puntaje_maximo }} pts
              </span>
              <span
                v-else-if="tarea.mi_entrega?.estado === 'ENTREGADA'"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"
              >
                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Entregada {{ tarea.mi_entrega.es_tardia ? '(Tardía)' : '' }}
              </span>
              <span
                v-else
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800"
              >
                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Pendiente de entrega
              </span>
            </div>

            <p class="text-sm text-gray-600">{{ tarea.descripcion || 'Sin descripción adicional.' }}</p>

            <!-- Instrucciones o Comentarios del Docente -->
            <div v-if="tarea.comentarios_instrucciones" class="mt-2.5 p-3 rounded-lg bg-blue-50/80 dark:bg-blue-950/40 border border-blue-200/60 dark:border-blue-800/60 text-xs text-blue-900 dark:text-blue-200">
              <span class="font-bold flex items-center gap-1.5 mb-1 text-blue-950 dark:text-blue-100">
                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Indicaciones y Comentarios del Docente:
              </span>
              <p class="whitespace-pre-line leading-relaxed">{{ tarea.comentarios_instrucciones }}</p>
            </div>

            <!-- Archivo Adjunto de la Actividad (PDF / Word) -->
            <div v-if="tarea.tiene_archivo_adjunto || tarea.archivo_adjunto_nombre" class="mt-2.5 flex items-center gap-3">
              <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200/80 dark:border-emerald-800/60 text-xs font-medium text-emerald-900 dark:text-emerald-100">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="font-semibold uppercase tracking-wider text-[10px] bg-emerald-200/70 dark:bg-emerald-800/60 px-1.5 py-0.5 rounded">
                  {{ tarea.archivo_tipo || 'ARCHIVO' }}
                </span>
                <span class="truncate max-w-xs font-semibold">{{ tarea.archivo_adjunto_nombre || 'Material de la actividad' }}</span>
                <button
                  type="button"
                  @click="handleDownloadAdjuntoDocente(tarea)"
                  class="ml-2 font-bold text-emerald-700 dark:text-emerald-300 hover:text-emerald-900 dark:hover:text-emerald-100 underline inline-flex items-center gap-1"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  Descargar Material
                </button>
              </div>

              <!-- Enlace externo si existe -->
              <a
                v-if="tarea.enlace_recurso"
                :href="tarea.enlace_recurso"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:underline"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                Recurso web de apoyo
              </a>
            </div>

            <!-- Metadata de fechas y puntajes -->
            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 pt-1">
              <span class="inline-flex items-center gap-1 bg-gray-100 dark:bg-slate-800 px-2.5 py-1 rounded">
                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Vence: {{ formatFecha(tarea.fecha_limite || tarea.fecha_vencimiento) }}
              </span>
              <span class="inline-flex items-center gap-1 bg-gray-100 dark:bg-slate-800 px-2.5 py-1 rounded">
                Puntaje máx: {{ tarea.puntaje_maximo }} pts
              </span>
              <span v-if="tarea.mi_entrega?.fecha_entrega" class="inline-flex items-center gap-1 bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 px-2.5 py-1 rounded">
                Enviada el: {{ formatFecha(tarea.mi_entrega.fecha_entrega) }}
              </span>
            </div>

            <!-- Feedback docente si fue calificada -->
            <div v-if="tarea.mi_entrega?.comentario_docente" class="mt-2 text-xs bg-emerald-50 border border-emerald-200 text-emerald-900 rounded p-2.5">
              <span class="font-bold">Comentario del docente:</span> {{ tarea.mi_entrega.comentario_docente }}
            </div>
            <!-- Comentario enviado por estudiante si existe -->
            <div v-else-if="tarea.mi_entrega?.comentario_estudiante" class="mt-2 text-xs bg-gray-50 border border-gray-200 text-gray-700 rounded p-2">
              <span class="font-medium text-gray-500">Tu comentario:</span> {{ tarea.mi_entrega.comentario_estudiante }}
            </div>
          </div>

          <!-- Botón de Acción -->
          <div class="flex-shrink-0 flex items-center">
            <button
              v-if="!tarea.mi_entrega"
              @click="openEntrega(tarea)"
              class="btn-primary text-sm whitespace-nowrap"
            >
              Entregar Tarea
            </button>
            <button
              v-else-if="tarea.mi_entrega.estado !== 'CALIFICADA'"
              @click="openEntrega(tarea)"
              class="btn-secondary text-sm whitespace-nowrap"
            >
              Actualizar Entrega
            </button>
            <button
              v-else
              disabled
              class="btn-secondary opacity-60 cursor-not-allowed text-sm whitespace-nowrap"
            >
              Tarea Calificada
            </button>
          </div>
        </div>
      </div>
      <div v-if="tareas.length === 0" class="text-center py-8 text-gray-500">No hay tareas pendientes en este curso.</div>
    </div>

    <!-- ─── PESTAÑA: EVALUACIONES ─── -->
    <div v-else-if="currentTab === 'evaluaciones'" class="space-y-4">
      <div v-for="evaluacion in evaluaciones" :key="evaluacion.id" class="card shadow-sm flex justify-between items-center">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <h3 class="text-lg font-bold text-gray-900">{{ evaluacion.titulo }}</h3>
            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
              {{ evaluacion.intentos_realizados ?? 0 }} / {{ evaluacion.intentos_permitidos }} intentos
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              Cierre: {{ new Date(evaluacion.fecha_fin).toLocaleString() }}
            </span>
            <span v-if="evaluacion.mejor_nota !== null && evaluacion.mejor_nota !== undefined" class="inline-flex items-center gap-1 font-bold text-green-600">
              <svg class="h-3.5 w-3.5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
              </svg>
              Mejor Nota: {{ evaluacion.mejor_nota }}
            </span>
          </div>
        </div>

        <div>
          <button 
            v-if="(evaluacion.intentos_realizados ?? 0) < evaluacion.intentos_permitidos" 
            class="btn-primary text-sm"
            @click="handleIniciarEvaluacion(evaluacion)"
          >
            Rendir Evaluación
          </button>
          <span v-else class="text-xs font-semibold text-gray-500 bg-gray-100 px-3 py-1.5 rounded">
            Intentos Completados
          </span>
        </div>
      </div>
      <div v-if="evaluaciones.length === 0" class="text-center py-8 text-gray-500">No hay evaluaciones programadas en este curso.</div>
    </div>

    <!-- ─── PESTAÑA: MI ASISTENCIA ─── -->
    <div v-else-if="currentTab === 'asistencia'" class="space-y-6">
      <!-- Tarjeta Resumen -->
      <div v-if="misAsistenciasData?.resumen" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm text-center">
          <p class="text-xs text-gray-500 font-medium">Total Clases</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ misAsistenciasData.resumen.total_clases }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm text-center">
          <p class="text-xs text-gray-500 font-medium">Asistencias</p>
          <p class="text-2xl font-bold text-green-600 mt-1">{{ misAsistenciasData.resumen.presentes }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm text-center">
          <p class="text-xs text-gray-500 font-medium">Faltas Injustificadas</p>
          <p class="text-2xl font-bold text-red-600 mt-1">{{ misAsistenciasData.resumen.ausentes }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm text-center">
          <p class="text-xs text-gray-500 font-medium">% Asistencia</p>
          <p class="text-2xl font-bold text-primary-600 mt-1">{{ misAsistenciasData.resumen.porcentaje_asistencia }}%</p>
        </div>
      </div>

      <!-- Tabla de Asistencias -->
      <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            <tr>
              <th class="px-4 py-3 text-left">Fecha de Clase</th>
              <th class="px-4 py-3 text-center">Estado</th>
              <th class="px-4 py-3 text-left">Observación</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-sm">
            <tr v-for="asist in misAsistenciasData?.asistencias" :key="asist.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-900">{{ asist.fecha }}</td>
              <td class="px-4 py-3 text-center">
                <span 
                  :class="[
                    asist.estado_asistencia === 'PRESENTE' ? 'bg-green-100 text-green-800' :
                    asist.estado_asistencia === 'TARDANZA' ? 'bg-amber-100 text-amber-800' :
                    asist.estado_asistencia === 'AUSENTE' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800',
                    'px-2.5 py-0.5 text-xs font-bold rounded-full'
                  ]"
                >
                  {{ asist.estado_asistencia }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ asist.observacion || '-' }}</td>
            </tr>
            <tr v-if="!misAsistenciasData?.asistencias?.length">
              <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                Aún no hay registros de asistencia para este curso.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Entrega -->
    <BaseModal :open="openEntregaModal" @close="openEntregaModal = false" title="Subir Entrega">
      <form @submit.prevent="submitEntrega" class="space-y-4">
        <div>
          <h4 class="font-bold text-sm mb-1">{{ selectedTarea?.titulo }}</h4>
          <p class="text-xs text-gray-500 mb-4">{{ selectedTarea?.descripcion }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Comentarios (Opcional)</label>
          <textarea v-model="entregaForm.comentarios" class="input-field mt-1" rows="2"></textarea>
        </div>
        <FileDropzone
          v-model="entregaForm.archivo"
          label="Adjuntar trabajo"
          accept=".pdf,.doc,.docx,.zip,.jpg,.jpeg,.png"
          help="PDF, Word, ZIP o imagen."
          :max-size-mb="20"
        />
        <div class="flex justify-end space-x-3 pt-4">
          <button type="button" @click="openEntregaModal = false" class="btn-secondary" :disabled="submittingEntrega">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="submittingEntrega">
            {{ submittingEntrega ? 'Enviando…' : 'Enviar entrega' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Modal Rendición de Evaluación -->
    <BaseModal :open="openExamenModal" @close="openExamenModal = false" :title="evaluacionEnCurso?.titulo || 'Evaluación Online'">
      <div class="space-y-6 max-h-[75vh] overflow-y-auto px-1">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-800">
          <p class="font-bold">Intento #{{ intentoEnCurso?.numero_intento }} en curso</p>
          <p>Lea con atención cada pregunta y seleccione la opción correcta. Al presionar "Finalizar y Enviar", sus respuestas serán registradas y autocalificadas.</p>
        </div>

        <div v-for="(pregunta, index) in preguntasExamen" :key="pregunta.id" class="border border-gray-200 rounded-lg p-4 bg-white space-y-3">
          <div class="flex justify-between items-start">
            <h4 class="text-sm font-bold text-gray-900">Pregunta {{ index + 1 }}</h4>
            <span class="text-xs font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ pregunta.puntaje }} pts</span>
          </div>
          <p class="text-sm text-gray-700 font-medium">{{ pregunta.enunciado }}</p>

          <!-- Opciones de Alternativa -->
          <div v-if="pregunta.opciones?.length" class="space-y-2 pt-1">
            <label 
              v-for="opc in pregunta.opciones" 
              :key="opc.id" 
              class="flex items-center gap-3 p-2.5 rounded border border-gray-100 hover:bg-gray-50 cursor-pointer text-sm"
              :class="respuestasExamen[pregunta.id] === opc.id ? 'bg-primary-50 border-primary-300 font-medium text-primary-900' : ''"
            >
              <input 
                type="radio" 
                :name="'preg_' + pregunta.id" 
                :value="opc.id" 
                v-model="respuestasExamen[pregunta.id]" 
                class="text-primary-600 focus:ring-primary-500"
              />
              <span>{{ opc.texto }}</span>
            </label>
          </div>
        </div>

        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
          <button type="button" @click="openExamenModal = false" class="btn-secondary">Cerrar</button>
          <button 
            type="button" 
            @click="handleEnviarExamen" 
            :disabled="submittingExamen" 
            class="btn-primary"
          >
            {{ submittingExamen ? 'Calificando...' : 'Finalizar y Enviar Examen' }}
          </button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
