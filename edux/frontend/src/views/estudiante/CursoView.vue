<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { unidadesApi, tareasApi, entregasApi, materialesApi, evaluacionesApi, asistenciasApi } from '@/api'
import type { Unidad, Tarea, Evaluacion, Pregunta } from '@/types'
import BaseModal from '@/components/ui/BaseModal.vue'
import FileDropzone from '@/components/ui/FileDropzone.vue'
import SkeletonAula from '@/components/ui/SkeletonAula.vue'

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
    alert('Error al descargar el material.')
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

async function handleDownloadExamenDocente(evaluacion: Evaluacion) {
  try {
    const res = await evaluacionesApi.descargarAdjunto(evaluacion.id)
    const url = window.URL.createObjectURL(new Blob([res.data as BlobPart]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', evaluacion.archivo_nombre_original || `examen_${evaluacion.id}.pdf`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch {
    alert('Error al descargar el archivo de la evaluación.')
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

function formatFechaCorta(fecha: string | null | undefined): string {
  if (!fecha) return ''
  try {
    return new Date(fecha).toLocaleDateString('es-PE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
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
  if (entregaForm.value.comentarios) {
    formData.append('comentarios', entregaForm.value.comentarios)
  }

  submittingEntrega.value = true
  try {
    await entregasApi.create(selectedTarea.value.id, formData)
    openEntregaModal.value = false
    setTimeout(() => alert('Entrega enviada exitosamente'), 200)
    await loadData()
  } catch (error: unknown) {
    const e = error as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al enviar la entrega.')
  } finally {
    submittingEntrega.value = false
  }
}

// ─── Rendición de Evaluaciones y Quizzes ───
const openExamenModal = ref(false)
const evaluacionEnCurso = ref<Evaluacion | null>(null)
const intentoEnCurso = ref<{ id: number; numero_intento: number } | null>(null)
const preguntasExamen = ref<Pregunta[]>([])
const respuestasExamen = ref<Record<number, number>>({})
const archivoEstudiante = ref<File | null>(null)
const comentarioEstudiante = ref('')
const submittingExamen = ref(false)

// Modal de Revisión de Respuestas
const openRevisionModal = ref(false)
const revisionData = ref<Array<{
  id: number
  enunciado: string
  tipo: string
  puntaje: number
  puntaje_obtenido: number
  opcion_elegida?: number
  respuesta_texto?: string
  opciones: Array<{ id: number; texto: string; es_correcta: boolean }>
}>>([])
const loadingRevision = ref(false)

const isPastDeadline = computed(() => {
  if (!evaluacionEnCurso.value?.fecha_fin) return false
  return new Date() > new Date(evaluacionEnCurso.value.fecha_fin)
})

async function handleIniciarEvaluacion(evaluacion: Evaluacion) {
  const now = new Date()
  if (now > new Date(evaluacion.fecha_fin)) {
    alert('El plazo de esta evaluación ha expirado. No es posible iniciar un nuevo intento.')
    return
  }

  try {
    const res = await evaluacionesApi.iniciarIntento(evaluacion.id)
    evaluacionEnCurso.value = evaluacion
    intentoEnCurso.value = res.data.intento
    preguntasExamen.value = (res.data.preguntas || []) as Pregunta[]
    respuestasExamen.value = {}
    archivoEstudiante.value = null
    comentarioEstudiante.value = ''
    openExamenModal.value = true
  } catch (error: unknown) {
    const msg = (error as { response?: { data?: { message?: string } } })?.response?.data?.message || 'Error al iniciar la evaluación'
    alert(msg)
  }
}

async function handleFinalizarEvaluacion() {
  if (!evaluacionEnCurso.value || !intentoEnCurso.value) return

  // Comprobar plazo estricto
  if (isPastDeadline.value) {
    alert('El plazo límite de esta evaluación ha expirado. No se pueden registrar respuestas ni enviar archivos fuera de hora.')
    return
  }

  const tienePreguntas = preguntasExamen.value.length > 0
  const permiteArchivo = evaluacionEnCurso.value.permite_archivo_estudiante || evaluacionEnCurso.value.tipo_evaluacion === 'ARCHIVO'

  // Validaciones
  if (permiteArchivo && !tienePreguntas && !archivoEstudiante.value) {
    alert('Por favor adjunta tu archivo de examen resuelto antes de finalizar.')
    return
  }

  if (!confirm('¿Estás seguro de enviar tu evaluación? Esta acción finalizará tu intento.')) {
    return
  }

  submittingExamen.value = true
  try {
    // 1. Si hay preguntas tipo quiz, enviar respuestas
    if (tienePreguntas) {
      const respuestasPayload = Object.entries(respuestasExamen.value).map(([pregId, opcionId]) => ({
        pregunta_id: Number(pregId),
        opcion_id: opcionId
      }))

      const resQuiz = await evaluacionesApi.enviarRespuestas(evaluacionEnCurso.value.id, intentoEnCurso.value.id, {
        respuestas: respuestasPayload
      })

      if (resQuiz.data.revision_disponible) {
        alert(`¡Evaluación finalizada! Calificación obtenida: ${resQuiz.data.nota} pts. Ya puedes revisar tus respuestas.`)
      } else {
        alert(resQuiz.data.mensaje_seguridad || '¡Evaluación enviada con éxito! Por seguridad académica, las respuestas correctas se mostrarán una vez concluido el horario de la evaluación.')
      }
    }

    // 2. Si se adjuntó archivo de solución, subirlo
    if (permiteArchivo && archivoEstudiante.value) {
      const formData = new FormData()
      formData.append('archivo', archivoEstudiante.value)
      if (comentarioEstudiante.value) {
        formData.append('comentario', comentarioEstudiante.value)
      }
      await evaluacionesApi.subirArchivoIntento(evaluacionEnCurso.value.id, intentoEnCurso.value.id, formData)
      if (!tienePreguntas) {
        alert('Archivo de examen entregado exitosamente dentro del plazo establecido.')
      }
    }

    openExamenModal.value = false
    await loadData()
  } catch (error: unknown) {
    const e = error as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al enviar la evaluación.')
  } finally {
    submittingExamen.value = false
  }
}

async function handleVerRevision(evaluacion: Evaluacion) {
  const ultimoIntento = evaluacion.intentos?.slice(-1)[0]
  const intentoId = ultimoIntento?.id || evaluacion.intento_activo_id
  if (!intentoId) {
    alert('No se encontró un intento registrado para revisar.')
    return
  }

  loadingRevision.value = true
  openRevisionModal.value = true
  evaluacionEnCurso.value = evaluacion
  try {
    const res = await evaluacionesApi.revision(evaluacion.id, intentoId)
    revisionData.value = res.data.revision
  } catch (error: unknown) {
    const e = error as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'La revisión de respuestas aún no está disponible.')
    openRevisionModal.value = false
  } finally {
    loadingRevision.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <div class="space-y-6">
    <!-- Header del Aula para el Estudiante -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Aula Virtual del Curso</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">Accede a las unidades, descarga material didáctico, rinde evaluaciones y envía tus asignaciones.</p>
      </div>
      <RouterLink to="/estudiante/dashboard" class="btn-secondary self-start sm:self-auto text-xs py-2 px-3.5 rounded-xl font-semibold inline-flex items-center gap-2">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Volver a Mis Cursos
      </RouterLink>
    </div>

    <!-- Pestañas de Navegación del Alumno tipo píldora -->
    <div class="p-1.5 bg-slate-100/80 rounded-2xl border border-slate-200/70 inline-flex flex-wrap gap-1">
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
        Evaluaciones y Quizzes
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

    <SkeletonAula v-if="loading" />

    <!-- ─── PESTAÑA: MATERIALES ─── -->
    <div v-else-if="currentTab === 'materiales'" class="space-y-6">
      <div v-for="unidad in unidades" :key="unidad.id" class="card p-0 overflow-hidden shadow-xs border border-gray-200 rounded-xl">
        <div class="bg-gray-50/90 px-5 py-3.5 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-primary-700 bg-primary-50 px-2 py-0.5 rounded">Unidad {{ unidad.orden }}</span>
              <h3 class="text-base font-bold text-gray-900 inline">{{ unidad.titulo }}</h3>
              <span 
                v-if="unidad.fecha_inicio || unidad.fecha_fin" 
                class="text-[11px] font-medium bg-slate-100 text-slate-700 px-2 py-0.5 rounded border border-slate-200 inline-flex items-center gap-1"
              >
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {{ formatFechaCorta(unidad.fecha_inicio) || 'Inicio' }} – {{ formatFechaCorta(unidad.fecha_fin) || 'Fin' }}
              </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">{{ unidad.descripcion || 'Sin descripción didáctica' }}</p>
          </div>
        </div>

        <div class="p-5">
          <ul v-if="unidad.materiales && unidad.materiales.length > 0" class="space-y-3">
            <li 
              v-for="mat in unidad.materiales" 
              :key="mat.id" 
              class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-3.5 border border-gray-200 rounded-lg hover:bg-gray-50/70 transition-colors gap-3"
            >
              <div class="flex items-start gap-3">
                <div class="p-2 rounded-lg bg-primary-50 text-primary-700 font-bold text-xs uppercase shrink-0 mt-0.5">
                  {{ mat.tipo || 'DOC' }}
                </div>
                <div>
                  <p class="font-semibold text-sm text-gray-900">{{ mat.titulo }}</p>
                  <p class="text-xs text-gray-500 mt-0.5">{{ mat.descripcion || 'Material pedagógico publicado por el docente' }}</p>
                </div>
              </div>

              <!-- Abrir Enlace o Descargar Archivo -->
              <a 
                v-if="mat.tipo === 'ENLACE' && mat.enlace_externo" 
                :href="mat.enlace_externo" 
                target="_blank" 
                rel="noopener noreferrer"
                class="btn-secondary text-xs py-1.5 px-3 font-semibold text-primary-700 flex items-center gap-1.5 shrink-0 self-end sm:self-auto"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                Abrir Enlace
              </a>
              <button 
                v-else 
                @click="handleDownloadMaterial(mat.id, mat.titulo)" 
                class="btn-secondary text-xs py-1.5 px-3 font-semibold text-primary-700 flex items-center gap-1.5 shrink-0 self-end sm:self-auto"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Descargar
              </button>
            </li>
          </ul>
          <p v-else class="text-xs text-gray-400 italic text-center py-2 bg-gray-50/50 rounded-lg border border-dashed border-gray-200">
            No hay materiales didácticos publicados en esta unidad.
          </p>
        </div>
      </div>
      <div v-if="unidades.length === 0" class="text-center py-8 text-gray-500 text-xs">No hay unidades publicadas para este curso.</div>
    </div>

    <!-- ─── PESTAÑA: TAREAS ─── -->
    <div v-else-if="currentTab === 'tareas'" class="space-y-4">
      <div v-for="tarea in tareas" :key="tarea.id" class="card shadow-sm p-5 border border-gray-200 rounded-lg">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
          <div class="flex-1 space-y-2">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="text-lg font-bold text-gray-900">{{ tarea.titulo }}</h3>
              <span
                v-if="tarea.mi_entrega?.estado === 'CALIFICADA'"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800"
              >
                Calificada: {{ tarea.mi_entrega.nota }} / {{ tarea.puntaje_maximo }} pts
              </span>
              <span
                v-else-if="tarea.mi_entrega?.estado === 'ENTREGADA'"
                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"
              >
                Entregada
              </span>
              <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                Pendiente de Entrega
              </span>
            </div>
            <p class="text-sm text-gray-600">{{ tarea.descripcion }}</p>

            <!-- Indicaciones y Comentarios del Docente -->
            <div v-if="tarea.comentarios_instrucciones" class="mt-2.5 p-3 rounded-lg bg-slate-50 border border-slate-200/80 text-xs text-slate-700">
              <span class="font-bold text-slate-900 flex items-center gap-1.5 mb-1">
                <svg class="w-3.5 h-3.5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Indicaciones y Comentarios del Docente:
              </span>
              <p class="whitespace-pre-line leading-relaxed">{{ tarea.comentarios_instrucciones }}</p>
            </div>
            <div class="text-xs text-gray-500 flex flex-wrap gap-4 pt-1">
              <span>Límite: {{ formatFecha(tarea.fecha_limite) }}</span>
              <span>Puntaje: {{ tarea.puntaje_maximo }} pts</span>
            </div>
            <div v-if="tarea.tiene_archivo_adjunto" class="pt-2">
              <button
                @click="handleDownloadAdjuntoDocente(tarea)"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-700 bg-primary-50 hover:bg-primary-100 px-3 py-1.5 rounded-lg border border-primary-200"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Descargar Guía de Trabajo
              </button>
            </div>
          </div>
          <div>
            <button
              v-if="!tarea.mi_entrega"
              @click="openEntrega(tarea)"
              class="btn-primary text-sm whitespace-nowrap"
            >
              Subir Trabajo
            </button>
            <button
              v-else-if="tarea.mi_entrega.estado !== 'CALIFICADA'"
              @click="openEntrega(tarea)"
              class="btn-secondary text-sm whitespace-nowrap"
            >
              Actualizar Entrega
            </button>
          </div>
        </div>
      </div>
      <div v-if="tareas.length === 0" class="text-center py-8 text-gray-500 text-xs">No hay tareas programadas en este curso.</div>
    </div>

    <!-- ─── PESTAÑA: EVALUACIONES Y QUIZZES ─── -->
    <div v-else-if="currentTab === 'evaluaciones'" class="space-y-4">
      <div v-for="evaluacion in evaluaciones" :key="evaluacion.id" class="card shadow-sm p-5 border border-gray-200 rounded-xl space-y-4">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
          <div class="space-y-2 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="text-lg font-bold text-gray-900">{{ evaluacion.titulo }}</h3>
              <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                Modalidad: {{ evaluacion.tipo_evaluacion || 'QUIZ' }}
              </span>
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                {{ evaluacion.intentos_realizados ?? 0 }} / {{ evaluacion.intentos_permitidos }} intentos
              </span>
              <span v-if="evaluacion.esta_expirada" class="px-2 py-0.5 text-xs font-bold rounded-full bg-rose-100 text-rose-800">
                Plazo Vencido
              </span>
            </div>

            <p class="text-xs text-gray-600">{{ evaluacion.instrucciones || 'Sin instrucciones adicionales' }}</p>

            <div class="text-xs text-gray-500 flex flex-wrap gap-4 pt-1">
              <span>Duración: {{ evaluacion.duracion_minutos || 'Ilimitada' }} min</span>
              <span>Puntaje Máx: {{ evaluacion.puntaje_maximo }} pts</span>
              <span :class="evaluacion.esta_expirada ? 'text-rose-600 font-semibold' : 'text-gray-600'">
                Cierre estricto: {{ formatFecha(evaluacion.fecha_fin) }}
              </span>
            </div>

            <!-- Botón para descargar archivo de examen del docente si existe -->
            <div v-if="evaluacion.tiene_archivo_adjunto" class="pt-2">
              <button
                @click="handleDownloadExamenDocente(evaluacion)"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg border border-blue-200"
              >
                <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Descargar Examen / Consignas ({{ evaluacion.archivo_nombre_original || 'Documento' }})
              </button>
            </div>

            <!-- Mensaje de seguridad académica sobre respuestas -->
            <div v-if="evaluacion.intentos_realizados && evaluacion.intentos_realizados > 0" class="pt-1">
              <div v-if="!evaluacion.revision_disponible" class="p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <span>Por seguridad académica, las respuestas correctas y el desglose de preguntas se mostrarán una vez concluido el horario de la prueba ({{ formatFecha(evaluacion.fecha_fin) }}).</span>
              </div>
              <div v-else class="text-xs text-emerald-700 font-bold flex items-center gap-1">
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Revisión de respuestas disponible. Nota obtenida: {{ evaluacion.mejor_nota }} / {{ evaluacion.puntaje_maximo }} pts.
              </div>
            </div>
          </div>

          <!-- Botones de Acción -->
          <div class="flex flex-col items-end gap-2">
            <!-- Botón Rendir si tiene intentos y no está expirada -->
            <button 
              v-if="!evaluacion.esta_expirada && (evaluacion.intentos_realizados ?? 0) < evaluacion.intentos_permitidos" 
              class="btn-primary text-sm whitespace-nowrap"
              @click="handleIniciarEvaluacion(evaluacion)"
            >
              {{ evaluacion.intentos_realizados ? 'Rendir Siguiente Intento' : 'Rendir Evaluación' }}
            </button>

            <!-- Botón Ver Revisión cuando ya cerró el examen -->
            <button
              v-if="evaluacion.revision_disponible && evaluacion.intentos_realizados"
              @click="handleVerRevision(evaluacion)"
              class="btn-secondary text-xs py-1.5 px-3 text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100 flex items-center gap-1"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              Ver Revisión de Respuestas
            </button>

            <!-- Badge si expiró y no rindió -->
            <span v-else-if="evaluacion.esta_expirada && !evaluacion.intentos_realizados" class="text-xs font-semibold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-lg">
              Evaluación Cerrada
            </span>
          </div>
        </div>
      </div>
      <div v-if="evaluaciones.length === 0" class="text-center py-8 text-gray-500 text-xs">No hay evaluaciones programadas en este curso.</div>
    </div>

    <!-- ─── PESTAÑA: MI ASISTENCIA ─── -->
    <div v-else-if="currentTab === 'asistencia'" class="space-y-6">
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
          <p class="text-xs text-gray-500 font-medium">Faltas</p>
          <p class="text-2xl font-bold text-red-600 mt-1">{{ misAsistenciasData.resumen.ausentes }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm text-center">
          <p class="text-xs text-gray-500 font-medium">% Asistencia</p>
          <p class="text-2xl font-bold text-primary-600 mt-1">{{ misAsistenciasData.resumen.porcentaje_asistencia }}%</p>
        </div>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
          <thead class="bg-gray-50 font-semibold text-gray-500 uppercase tracking-wider">
            <tr>
              <th class="px-4 py-3 text-left">Fecha de Clase</th>
              <th class="px-4 py-3 text-center">Estado</th>
              <th class="px-4 py-3 text-left">Observación</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
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
              <td class="px-4 py-3 text-gray-500">{{ asist.observacion || '-' }}</td>
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

    <!-- Modal Entrega de Tarea -->
    <BaseModal :open="openEntregaModal" @close="openEntregaModal = false" title="Subir Entrega de Tarea">
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
            {{ submittingEntrega ? 'Enviando…' : 'Enviar Entrega' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Modal Rendición de Evaluación (Google Form Style Quiz & Entrega de Archivo) -->
    <BaseModal :open="openExamenModal" @close="openExamenModal = false" :title="evaluacionEnCurso?.titulo || 'Evaluación Online'">
      <div class="space-y-6 max-h-[75vh] overflow-y-auto px-1">
        <!-- Banner de Instrucciones y Tiempo -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-900 space-y-1">
          <div class="flex items-center justify-between">
            <span class="font-bold text-sm">Intento #{{ intentoEnCurso?.numero_intento }} en curso</span>
            <span class="font-semibold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200">
              Cierre: {{ formatFecha(evaluacionEnCurso?.fecha_fin) }}
            </span>
          </div>
          <p class="text-blue-800">
            {{ evaluacionEnCurso?.instrucciones || 'Lea detenidamente cada indicación. Al enviar, sus respuestas y/o archivo serán registrados.' }}
          </p>
          <p class="font-semibold text-[11px] text-amber-800 mt-1">
            🔒 <em>Seguridad académica: Las respuestas correctas del quiz se mostrarán una vez concluido el horario de la prueba. En evaluaciones con archivo, las entregas se bloquean al llegar la hora límite.</em>
          </p>
        </div>

        <!-- Archivo descargable del examen si existe -->
        <div v-if="evaluacionEnCurso?.tiene_archivo_adjunto" class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between gap-3">
          <div>
            <p class="text-xs font-bold text-slate-800">Archivo de Examen Adjunto por el Docente:</p>
            <p class="text-[11px] text-slate-500">{{ evaluacionEnCurso?.archivo_nombre_original || 'Documento con consignas del examen' }}</p>
          </div>
          <button
            type="button"
            @click="handleDownloadExamenDocente(evaluacionEnCurso!)"
            class="btn-secondary text-xs py-1.5 px-3 flex items-center gap-1.5 text-blue-700 bg-blue-50 border-blue-200 hover:bg-blue-100"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Descargar Examen
          </button>
        </div>

        <!-- SECCIÓN 1: Cuestionario Quiz (tipo Google Form) -->
        <div v-if="preguntasExamen.length > 0" class="space-y-4">
          <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">
            Cuestionario de Preguntas ({{ preguntasExamen.length }})
          </h4>

          <div
            v-for="(pregunta, index) in preguntasExamen"
            :key="pregunta.id"
            class="border border-gray-200 rounded-xl p-4 bg-white space-y-3 shadow-2xs hover:border-gray-300 transition-colors"
          >
            <div class="flex justify-between items-start gap-2">
              <h4 class="text-xs font-bold text-gray-900 flex items-center gap-1.5">
                <span class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-[11px] font-bold text-slate-700">
                  {{ index + 1 }}
                </span>
                <span>{{ pregunta.enunciado }}</span>
              </h4>
              <span class="text-[11px] font-bold text-primary-700 bg-primary-50 px-2 py-0.5 rounded shrink-0">
                {{ pregunta.puntaje }} pts
              </span>
            </div>

            <!-- Opciones de Alternativa (estilo Google Form) -->
            <div v-if="pregunta.opciones && pregunta.opciones.length > 0" class="space-y-2 pt-1 pl-6">
              <label 
                v-for="opc in pregunta.opciones" 
                :key="opc.id" 
                class="flex items-center gap-3 p-2.5 rounded-lg border border-gray-200 hover:bg-gray-50/80 cursor-pointer text-xs transition-colors"
                :class="respuestasExamen[pregunta.id] === opc.id ? 'bg-primary-50/50 border-primary-400 font-semibold text-primary-900 ring-1 ring-primary-300' : 'text-gray-700'"
              >
                <input 
                  type="radio" 
                  :name="'preg_' + pregunta.id" 
                  :value="opc.id" 
                  v-model="respuestasExamen[pregunta.id]" 
                  class="h-4 w-4 text-primary-600 focus:ring-primary-500 cursor-pointer"
                />
                <span>{{ opc.texto }}</span>
              </label>
            </div>
          </div>
        </div>

        <!-- SECCIÓN 2: Entrega de Archivo de Solución (si está habilitado) -->
        <div v-if="evaluacionEnCurso?.permite_archivo_estudiante || evaluacionEnCurso?.tipo_evaluacion === 'ARCHIVO'" class="p-4 bg-indigo-50/50 border border-indigo-200 rounded-xl space-y-3">
          <h4 class="text-xs font-bold text-indigo-900 uppercase tracking-wider flex items-center gap-1.5">
            <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Subir Archivo de Solución del Examen
          </h4>
          <p class="text-xs text-indigo-700">
            Adjunta tu archivo de respuestas resuelto (PDF, Word, Excel, ZIP - máx 25MB).
            <strong>Atención: Pasada la fecha y hora límite no se aceptarán envíos.</strong>
          </p>

          <FileDropzone
            v-model="archivoEstudiante"
            label="Archivo de examen resuelto"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar"
            help="Sube tu archivo con la resolución del examen."
            :max-size-mb="25"
          />

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Comentario u Observación (Opcional)</label>
            <input
              v-model="comentarioEstudiante"
              type="text"
              placeholder="Ej. Se adjunta desarrollo en formato PDF según indicaciones."
              class="input-field text-xs"
            />
          </div>
        </div>

        <!-- Advertencia de Bloqueo por Fecha Límite Vencida -->
        <div v-if="isPastDeadline" class="p-3 bg-red-100 border border-red-300 rounded-xl text-xs text-red-900 font-bold flex items-center gap-2">
          <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <span>El plazo de la evaluación ha vencido. No se pueden enviar respuestas ni archivos extemporáneos.</span>
        </div>

        <!-- Footer con Botones -->
        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
          <button type="button" @click="openExamenModal = false" class="btn-secondary text-xs py-2 px-3.5" :disabled="submittingExamen">
            Cerrar
          </button>
          <button 
            type="button" 
            @click="handleFinalizarEvaluacion" 
            :disabled="submittingExamen || isPastDeadline" 
            class="btn-primary text-xs py-2 px-4 flex items-center gap-1.5 disabled:opacity-50"
          >
            <span v-if="submittingExamen">Enviando evaluación...</span>
            <span v-else>Finalizar y Enviar Evaluación</span>
          </button>
        </div>
      </div>
    </BaseModal>

    <!-- Modal Revisión Detallada de Respuestas (Disponible solo tras el cierre del examen) -->
    <BaseModal :open="openRevisionModal" @close="openRevisionModal = false" :title="`Revisión Académica: ${evaluacionEnCurso?.titulo || ''}`">
      <div class="space-y-4 max-h-[75vh] overflow-y-auto px-1">
        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-900">
          <strong>Horario de Evaluación Concluido:</strong>
          A continuación se presenta el desglose de preguntas con las alternativas correctas y el puntaje obtenido por cada ítem.
        </div>

        <div v-if="loadingRevision" class="flex justify-center py-8">
          <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="(p, idx) in revisionData"
            :key="p.id"
            class="border border-gray-200 rounded-xl p-4 bg-white space-y-2 shadow-2xs"
          >
            <div class="flex justify-between items-start gap-2">
              <h4 class="text-xs font-bold text-gray-900">
                #{{ idx + 1 }} {{ p.enunciado }}
              </h4>
              <span
                :class="[
                  'text-xs font-bold px-2 py-0.5 rounded',
                  p.puntaje_obtenido > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
                ]"
              >
                {{ p.puntaje_obtenido }} / {{ p.puntaje }} pts
              </span>
            </div>

            <!-- Opciones -->
            <div class="space-y-1.5 pt-1 pl-4">
              <div
                v-for="opc in p.opciones"
                :key="opc.id"
                class="p-2 rounded-lg text-xs flex items-center justify-between gap-2 border"
                :class="[
                  opc.es_correcta ? 'bg-emerald-50 border-emerald-300 text-emerald-900 font-semibold' :
                  (p.opcion_elegida === opc.id && !opc.es_correcta) ? 'bg-rose-50 border-rose-300 text-rose-800 font-medium' :
                  'bg-gray-50 border-gray-200 text-gray-600'
                ]"
              >
                <div class="flex items-center gap-2">
                  <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold"
                    :class="opc.es_correcta ? 'bg-emerald-600 text-white' : (p.opcion_elegida === opc.id ? 'bg-rose-600 text-white' : 'border border-gray-300 text-gray-400')"
                  >
                    {{ opc.es_correcta ? '✓' : (p.opcion_elegida === opc.id ? '✗' : '') }}
                  </span>
                  <span>{{ opc.texto }}</span>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                  <span v-if="p.opcion_elegida === opc.id" class="text-[10px] uppercase font-bold text-primary-700 bg-primary-100 px-1.5 py-0.5 rounded">
                    Tu Elección
                  </span>
                  <span v-if="opc.es_correcta" class="text-[10px] uppercase font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                    Respuesta Correcta
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-3 border-t border-gray-200">
          <button type="button" @click="openRevisionModal = false" class="btn-primary text-xs py-2 px-4">
            Entendido
          </button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
