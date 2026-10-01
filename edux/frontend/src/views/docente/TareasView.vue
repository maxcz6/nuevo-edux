<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { tareasApi, entregasApi } from '@/api'
import type { Tarea, EntregaTarea } from '@/types'
import BaseModal from '@/components/ui/BaseModal.vue'

const route = useRoute()
const grupoId = Number(route.params.id)

const tareas = ref<Tarea[]>([])
const loading = ref(true)
const saving = ref(false)

const openTareaModal = ref(false)
const editingTareaId = ref<number | null>(null)
const selectedFile = ref<File | null>(null)

const tareaForm = ref({
  titulo: '',
  descripcion: '',
  comentarios_instrucciones: '',
  enlace_recurso: '',
  fecha_limite: '',
  puntaje_maximo: 20,
  permite_entrega_tardia: false,
})

const selectedTarea = ref<Tarea | null>(null)
const entregas = ref<EntregaTarea[]>([])
const openEntregasModal = ref(false)
const calificarForm = ref({ calificacion: 0, comentarios: '' })
const selectedEntrega = ref<EntregaTarea | null>(null)

async function loadTareas() {
  loading.value = true
  try {
    const res = await tareasApi.list(grupoId)
    tareas.value = res.data.data
  } catch (error) {
    console.error('Error loading tareas', error)
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingTareaId.value = null
  selectedFile.value = null
  const defaultDate = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16)
  tareaForm.value = {
    titulo: '',
    descripcion: '',
    comentarios_instrucciones: '',
    enlace_recurso: '',
    fecha_limite: defaultDate,
    puntaje_maximo: 20,
    permite_entrega_tardia: true,
  }
  openTareaModal.value = true
}

function openEditModal(tarea: Tarea) {
  editingTareaId.value = tarea.id
  selectedFile.value = null
  const formattedDate = tarea.fecha_limite ? new Date(tarea.fecha_limite).toISOString().slice(0, 16) : ''
  tareaForm.value = {
    titulo: tarea.titulo,
    descripcion: tarea.descripcion || '',
    comentarios_instrucciones: tarea.comentarios_instrucciones || '',
    enlace_recurso: tarea.enlace_recurso || '',
    fecha_limite: formattedDate,
    puntaje_maximo: tarea.puntaje_maximo || 20,
    permite_entrega_tardia: tarea.permite_entrega_tardia ?? true,
  }
  openTareaModal.value = true
}

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    selectedFile.value = target.files[0]
  }
}

function removeFile() {
  selectedFile.value = null
}

async function handleSaveTarea() {
  if (!tareaForm.value.titulo.trim() || !tareaForm.value.fecha_limite) {
    alert('Por favor ingrese el título y la fecha límite de la actividad.')
    return
  }

  saving.value = true
  try {
    const formData = new FormData()
    formData.append('titulo', tareaForm.value.titulo.trim())
    formData.append('descripcion', tareaForm.value.descripcion.trim())
    formData.append('comentarios_instrucciones', tareaForm.value.comentarios_instrucciones.trim())
    if (tareaForm.value.enlace_recurso.trim()) {
      formData.append('enlace_recurso', tareaForm.value.enlace_recurso.trim())
    }
    formData.append('fecha_limite', tareaForm.value.fecha_limite)
    formData.append('puntaje_maximo', String(tareaForm.value.puntaje_maximo))
    formData.append('permite_entrega_tardia', tareaForm.value.permite_entrega_tardia ? '1' : '0')
    formData.append('estado', 'PUBLICADA')

    if (selectedFile.value) {
      formData.append('archivo', selectedFile.value)
    }

    if (editingTareaId.value) {
      await tareasApi.update(grupoId, editingTareaId.value, formData)
    } else {
      await tareasApi.create(grupoId, formData)
    }

    openTareaModal.value = false
    await loadTareas()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Error al guardar la actividad.')
  } finally {
    saving.value = false
  }
}

async function handleDownloadAdjunto(tarea: Tarea) {
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
    alert('Error al descargar el archivo adjunto de la actividad.')
  }
}

async function viewEntregas(tarea: Tarea) {
  selectedTarea.value = tarea
  try {
    const res = await entregasApi.list(tarea.id)
    entregas.value = res.data.data
    openEntregasModal.value = true
  } catch (error) {
    alert('Error cargando entregas')
  }
}

async function downloadEntrega(entrega: EntregaTarea) {
  try {
    const res = await entregasApi.descargar(entrega.id)
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', entrega.archivo_original ?? 'entrega')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (error) {
    alert('Error descargando entrega')
  }
}

function openCalificar(entrega: EntregaTarea) {
  selectedEntrega.value = entrega
  calificarForm.value = { calificacion: entrega.nota ?? entrega.calificacion ?? 0, comentarios: entrega.comentario_docente ?? entrega.comentarios ?? '' }
}

async function submitCalificacion() {
  if (!selectedEntrega.value || !selectedTarea.value) return
  try {
    await entregasApi.calificar(selectedTarea.value.id, selectedEntrega.value.id, {
      nota: calificarForm.value.calificacion,
      comentario_docente: calificarForm.value.comentarios
    })
    const res = await entregasApi.list(selectedTarea.value.id)
    entregas.value = res.data.data
    selectedEntrega.value = null
  } catch (error) {
    alert('Error al calificar entrega')
  }
}

function formatFecha(fecha: string | null | undefined): string {
  if (!fecha) return 'Sin fecha límite'
  try {
    return new Date(fecha).toLocaleString('es-PE', {
      dateStyle: 'medium',
      timeStyle: 'short',
    })
  } catch {
    return fecha
  }
}

onMounted(() => {
  loadTareas()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xs border border-slate-200/80 dark:border-slate-800 p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 transition-colors">
      <div>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
            Portal Docente
          </span>
          <span class="text-xs text-slate-500 dark:text-slate-400">Actividades y Evaluaciones</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight mt-1">
          Actividades del Curso
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Cree tareas, adjunte guías (PDF, Word), proporcione pautas metodológicas y califique entregas.
        </p>
      </div>

      <button @click="openCreateModal" class="btn-primary inline-flex items-center gap-2 shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nueva Actividad
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
    </div>

    <!-- Lista de Actividades -->
    <div v-else class="grid gap-4">
      <div
        v-for="tarea in tareas"
        :key="tarea.id"
        class="card bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-xl p-5 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-all space-y-4"
      >
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
          <div class="space-y-1.5 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="font-bold text-lg text-slate-900 dark:text-slate-100">{{ tarea.titulo }}</h3>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
                Máx: {{ tarea.puntaje_maximo }} pts
              </span>
            </div>

            <p class="text-sm text-slate-600 dark:text-slate-300">{{ tarea.descripcion || 'Sin descripción.' }}</p>

            <!-- Instrucciones o Comentarios del Docente -->
            <div v-if="tarea.comentarios_instrucciones" class="mt-2.5 p-3 rounded-lg bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-300">
              <span class="font-bold text-slate-900 dark:text-slate-200 flex items-center gap-1.5 mb-1">
                <svg class="w-3.5 h-3.5 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Indicaciones y Comentarios del Docente:
              </span>
              <p class="whitespace-pre-line leading-relaxed">{{ tarea.comentarios_instrucciones }}</p>
            </div>

            <!-- Archivo Adjunto de la Actividad (PDF, Word, etc.) -->
            <div v-if="tarea.tiene_archivo_adjunto || tarea.archivo_adjunto_nombre" class="mt-3 flex items-center gap-3">
              <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50 dark:bg-primary-950/60 border border-primary-200/80 dark:border-primary-800/60 text-xs font-medium text-primary-800 dark:text-primary-200">
                <svg class="w-4 h-4 text-primary-600 dark:text-primary-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="font-semibold uppercase tracking-wider text-[10px] bg-primary-200/60 dark:bg-primary-800/60 px-1.5 py-0.5 rounded">
                  {{ tarea.archivo_tipo || 'ARCHIVO' }}
                </span>
                <span class="truncate max-w-xs">{{ tarea.archivo_adjunto_nombre || 'Guía de actividad' }}</span>
                <button
                  type="button"
                  @click="handleDownloadAdjunto(tarea)"
                  class="ml-2 font-bold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-200 underline"
                >
                  Descargar
                </button>
              </div>

              <!-- Enlace de recurso externo si existe -->
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
                Recurso web
              </a>
            </div>

            <!-- Metadata de fechas -->
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400 flex flex-wrap gap-4 pt-1">
              <span class="inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Fecha Límite: {{ formatFecha(tarea.fecha_limite || tarea.fecha_vencimiento) }}
              </span>
              <span v-if="tarea.permite_entrega_tardia" class="text-amber-600 dark:text-amber-400">
                • Admite entrega tardía
              </span>
            </div>
          </div>

          <!-- Botones de Acción -->
          <div class="flex items-center gap-2 self-start sm:self-auto shrink-0">
            <button @click="openEditModal(tarea)" class="btn-secondary text-xs px-3 py-1.5">
              Editar
            </button>
            <button @click="viewEntregas(tarea)" class="btn-primary text-xs px-3 py-1.5">
              Ver Entregas
            </button>
          </div>
        </div>
      </div>

      <div v-if="tareas.length === 0" class="text-center py-12 text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 p-8">
        No hay actividades creadas en este curso. Haga clic en "Nueva Actividad" para comenzar.
      </div>
    </div>

    <!-- Modal Crear / Editar Actividad -->
    <BaseModal
      :open="openTareaModal"
      @close="openTareaModal = false"
      :title="editingTareaId ? 'Editar Actividad / Tarea' : 'Nueva Actividad / Tarea'"
      size="lg"
    >
      <form @submit.prevent="handleSaveTarea" class="space-y-4">
        <!-- Título -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
            Título de la Actividad *
          </label>
          <input
            v-model="tareaForm.titulo"
            type="text"
            required
            placeholder="Ej: Informe de Práctica N° 01 — Base de Datos"
            class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary-500"
          />
        </div>

        <!-- Descripción / Resumen -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
            Descripción / Resumen de la Actividad
          </label>
          <textarea
            v-model="tareaForm.descripcion"
            rows="2"
            placeholder="Breve resumen del objetivo de la actividad..."
            class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary-500"
          ></textarea>
        </div>

        <!-- Comentarios, Instrucciones y Pautas para el Estudiante -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
            Instrucciones Detalladas y Comentarios del Docente
          </label>
          <textarea
            v-model="tareaForm.comentarios_instrucciones"
            rows="3"
            placeholder="Escriba aquí los criterios de evaluación, pautas metodológicas, estructura requerida o comentarios para los estudiantes..."
            class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary-500"
          ></textarea>
        </div>

        <!-- Archivo Adjunto (PDF, Word, etc.) -->
        <div class="p-3.5 bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 rounded-xl space-y-2">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide">
            📎 Archivo Adjunto de la Actividad (PDF, Word, PPTX, etc.)
          </label>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            Adjunte la guía de práctica, rúbrica, formato Word o material necesario para que el alumno resuelva la actividad.
          </p>

          <div class="mt-2 flex items-center gap-3">
            <label class="btn-secondary cursor-pointer text-xs py-2 px-3 inline-flex items-center gap-1.5">
              <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              <span>{{ selectedFile ? 'Cambiar archivo' : 'Seleccionar archivo (PDF / Word)' }}</span>
              <input
                type="file"
                class="hidden"
                accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.txt"
                @change="handleFileChange"
              />
            </label>

            <span v-if="selectedFile" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 truncate max-w-xs">
              <span>📄 {{ selectedFile.name }} ({{ Math.round(selectedFile.size / 1024) }} KB)</span>
              <button type="button" @click="removeFile" class="text-rose-500 hover:text-rose-700 ml-1 font-bold">✕</button>
            </span>
            <span v-else-if="editingTareaId" class="text-xs text-slate-400 italic">
              Deje vacío si desea conservar el archivo existente.
            </span>
          </div>
        </div>

        <!-- Enlace Web / Recurso Externo -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
            Enlace Web / Recurso de Apoyo (Opcional)
          </label>
          <input
            v-model="tareaForm.enlace_recurso"
            type="url"
            placeholder="https://ejemplo.com/recurso-complementario"
            class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary-500"
          />
        </div>

        <!-- Fechas y Puntaje -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
              Fecha y Hora Límite *
            </label>
            <input
              v-model="tareaForm.fecha_limite"
              type="datetime-local"
              required
              class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary-500 [color-scheme:light] dark:[color-scheme:dark]"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">
              Puntaje Máximo (1 - 20) *
            </label>
            <input
              v-model="tareaForm.puntaje_maximo"
              type="number"
              min="1"
              max="20"
              required
              class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>

        <!-- Permitir entrega tardía -->
        <div class="flex items-center gap-2 pt-1">
          <input
            id="permite_tardia"
            v-model="tareaForm.permite_entrega_tardia"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-900"
          />
          <label for="permite_tardia" class="text-xs text-slate-700 dark:text-slate-300">
            Permitir que los estudiantes entreguen después de la fecha límite (marcado como tardío)
          </label>
        </div>

        <!-- Botones de Acción -->
        <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100 dark:border-slate-800">
          <button type="button" @click="openTareaModal = false" class="btn-secondary">
            Cancelar
          </button>
          <button type="submit" :disabled="saving" class="btn-primary">
            {{ saving ? 'Guardando...' : (editingTareaId ? 'Actualizar Actividad' : 'Publicar Actividad') }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Modal Entregas y Calificaciones -->
    <BaseModal
      :open="openEntregasModal"
      @close="openEntregasModal = false; selectedEntrega = null"
      size="lg"
      title="Entregas de los Estudiantes"
    >
      <div v-if="entregas.length === 0" class="text-center py-6 text-slate-500 dark:text-slate-400">
        No hay entregas registradas para esta actividad.
      </div>
      <div v-else class="space-y-4">
        <div
          v-for="entrega in entregas"
          :key="entrega.id"
          class="p-4 border border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50/70 dark:bg-slate-950/60 space-y-2"
        >
          <div class="flex justify-between items-start gap-2">
            <div>
              <p class="font-bold text-sm text-slate-900 dark:text-slate-100">
                {{ entrega.estudiante?.usuario?.apellido_paterno || entrega.estudiante?.apellido_paterno }} {{ entrega.estudiante?.usuario?.nombres || entrega.estudiante?.nombres }}
              </p>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                Entregado: {{ new Date(entrega.fecha_entrega!).toLocaleString() }}
                <span v-if="entrega.es_tardia" class="text-rose-600 dark:text-rose-400 font-semibold">• Tardía</span>
              </p>
            </div>
            <span
              :class="(entrega.nota !== null && entrega.nota !== undefined) || (entrega.calificacion !== null && entrega.calificacion !== undefined) ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800'"
              class="px-2.5 py-0.5 text-xs font-semibold rounded-full"
            >
              {{ (entrega.nota ?? entrega.calificacion) !== null && (entrega.nota ?? entrega.calificacion) !== undefined ? `Nota: ${entrega.nota ?? entrega.calificacion}` : 'Sin calificar' }}
            </span>
          </div>

          <p v-if="entrega.comentario_estudiante || entrega.comentarios_estudiante" class="text-xs text-slate-700 dark:text-slate-300 italic">
            "{{ entrega.comentario_estudiante || entrega.comentarios_estudiante }}"
          </p>

          <div class="flex items-center gap-3 pt-1">
            <button
              v-if="entrega.tiene_archivo || entrega.archivo_ruta || entrega.archivo_url"
              @click="downloadEntrega(entrega)"
              class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              Descargar Archivo Entregado
            </button>
            <button @click="openCalificar(entrega)" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
              Calificar / Retroalimentar
            </button>
          </div>
          
          <!-- Calificar inline form -->
          <div v-if="selectedEntrega?.id === entrega.id" class="mt-3 p-3.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nota (0 - 20)</label>
                <input v-model="calificarForm.calificacion" type="number" step="0.5" min="0" max="20" class="input-field text-sm py-1.5" />
              </div>
              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Comentario / Feedback Docente</label>
                <input v-model="calificarForm.comentarios" type="text" placeholder="Retroalimentación al estudiante..." class="input-field text-sm py-1.5" />
              </div>
            </div>
            <div class="flex justify-end gap-2">
              <button @click="selectedEntrega = null" class="btn-secondary py-1 px-3 text-xs">Cancelar</button>
              <button @click="submitCalificacion" class="btn-primary py-1 px-3 text-xs">Guardar Calificación</button>
            </div>
          </div>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
