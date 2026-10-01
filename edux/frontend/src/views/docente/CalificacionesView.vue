<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { gruposApi, calificacionesApi, evaluacionesApi, tareasApi } from '@/api'
import type { GrupoCurso, Evaluacion, Tarea, ConsolidadoEstudiante } from '@/types'
import SkeletonTable from '@/components/ui/SkeletonTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import PageHeader from '@/components/ui/PageHeader.vue'

const route = useRoute()
const router = useRouter()

// Grupo ID actual desde la URL
const grupoId = ref<number>(Number(route.params.id))

// Grupos asignados al docente (para aislamiento estricto y selector de cátedra)
const misGrupos = ref<GrupoCurso[]>([])
const grupoActual = computed(() => misGrupos.value.find(g => g.id === grupoId.value) || null)

// Estado de la vista
const activeTab = ref<'consolidado' | 'por_actividad'>('consolidado')
const loading = ref(true)
const savingBatch = ref(false)
const exporting = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Datos del consolidado
const consolidado = ref<ConsolidadoEstudiante[]>([])
const rubros = ref<string[]>([])
const searchQuery = ref('')

// Actividades del curso (para calificar por actividad)
const evaluaciones = ref<Evaluacion[]>([])
const tareas = ref<Tarea[]>([])

// Selección para calificar en lote
interface ActividadOpcion {
  id: string
  tipo: 'EVALUACION' | 'TAREA' | 'MANUAL'
  origen_id?: number | null
  nombre: string
  label: string
  pesoDefecto: number
}

const actividadSeleccionadaKey = ref<string>('')
const rubroPersonalizadoNombre = ref('')
const pesoActividad = ref<number>(20)
const notasBatch = ref<Record<number, number | ''>>({})

// Modal para editar/registrar nota individual
const modalIndividualOpen = ref(false)
const savingIndividual = ref(false)
const individualForm = ref({
  id: null as number | null,
  estudiante_id: 0,
  estudiante_nombre: '',
  nombre_rubro: '',
  tipo_origen: 'MANUAL' as 'MANUAL' | 'EVALUACION' | 'TAREA' | 'PARTICIPACION',
  origen_id: null as number | null,
  nota: 14,
  peso_porcentual: 20,
})

// Opciones de actividades combinadas
const listaActividades = computed<ActividadOpcion[]>(() => {
  const opciones: ActividadOpcion[] = []

  // Evaluaciones y Quizzes
  evaluaciones.value.forEach(ev => {
    opciones.push({
      id: `EV_${ev.id}`,
      tipo: 'EVALUACION',
      origen_id: ev.id,
      nombre: ev.titulo,
      label: `[EVALUACIÓN] ${ev.titulo} (${ev.modalidad || 'ONLINE'})`,
      pesoDefecto: 25,
    })
  })

  // Tareas
  tareas.value.forEach(t => {
    opciones.push({
      id: `TA_${t.id}`,
      tipo: 'TAREA',
      origen_id: t.id,
      nombre: t.titulo,
      label: `[TAREA] ${t.titulo}`,
      pesoDefecto: 20,
    })
  })

  // Opción manual
  opciones.push({
    id: 'MANUAL_CUSTOM',
    tipo: 'MANUAL',
    origen_id: null,
    nombre: 'Rubro Personalizado',
    label: '+ Registrar Rúbrica o Evaluación Manual (Participación, Parcial, Examen...)',
    pesoDefecto: 20,
  })

  return opciones
})

// Filtrado de alumnos en el consolidado
const consolidadoFiltrado = computed(() => {
  if (!searchQuery.value.trim()) return consolidado.value
  const q = searchQuery.value.toLowerCase().trim()
  return consolidado.value.filter(item =>
    item.nombre_completo.toLowerCase().includes(q) ||
    item.codigo.toLowerCase().includes(q) ||
    (item.numero_documento && item.numero_documento.toLowerCase().includes(q))
  )
})

// Estadísticas del acta
const kpis = computed(() => {
  const total = consolidado.value.length
  if (total === 0) return { total: 0, aprobados: 0, desaprobados: 0, promedioGeneral: 0 }

  const aprobados = consolidado.value.filter(c => c.promedio_final >= 11).length
  const desaprobados = total - aprobados
  const suma = consolidado.value.reduce((acc, c) => acc + (Number(c.promedio_final) || 0), 0)
  const promedioGeneral = round(suma / total, 2)

  return { total, aprobados, desaprobados, promedioGeneral }
})

function round(num: number, decimals: number): number {
  const factor = Math.pow(10, decimals)
  return Math.round(num * factor) / factor
}

// Cargar todos los datos del grupo
async function loadDatos(clearSuccess = true) {
  loading.value = true
  error.value = null
  if (clearSuccess) {
    successMessage.value = null
  }

  try {
    // 1. Cargar grupos del docente si aún no se han cargado
    if (misGrupos.value.length === 0) {
      const resGrupos = await gruposApi.misGrupos()
      misGrupos.value = resGrupos.data.data as GrupoCurso[]
    }

    // 2. Cargar consolidado de calificaciones del grupo
    const resConsolidado = await calificacionesApi.consolidado(grupoId.value)
    consolidado.value = (resConsolidado.data.data || []) as ConsolidadoEstudiante[]
    rubros.value = resConsolidado.data.rubros || []

    // 3. Cargar actividades del aula (evaluaciones y tareas)
    const [resEv, resTar] = await Promise.allSettled([
      evaluacionesApi.list(grupoId.value),
      tareasApi.list(grupoId.value)
    ])

    if (resEv.status === 'fulfilled') {
      evaluaciones.value = resEv.value.data.data || []
    }
    if (resTar.status === 'fulfilled') {
      tareas.value = resTar.value.data.data || []
    }

    // Inicializar selección de actividad por defecto si hay opciones
    if (!actividadSeleccionadaKey.value && listaActividades.value.length > 0) {
      seleccionarActividad(listaActividades.value[0].id)
    } else if (actividadSeleccionadaKey.value) {
      actualizarNotasBatchParaActividad()
    }
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { message?: string } } }
    if (e.response?.status === 403) {
      error.value = 'No tienes permiso para ver o gestionar las calificaciones de este grupo.'
    } else {
      error.value = e.response?.data?.message || 'Error al cargar las calificaciones del grupo.'
    }
  } finally {
    loading.value = false
  }
}

// Cambiar de curso mediante selector
function cambiarGrupo(nuevoGrupoId: number) {
  if (nuevoGrupoId === grupoId.value) return
  grupoId.value = nuevoGrupoId
  router.push(`/docente/grupos/${nuevoGrupoId}/calificaciones`)
}

// Al seleccionar actividad en Pestaña 2
function seleccionarActividad(key: string) {
  actividadSeleccionadaKey.value = key
  const opt = listaActividades.value.find(o => o.id === key)
  if (opt) {
    pesoActividad.value = opt.pesoDefecto
    if (opt.tipo === 'MANUAL') {
      rubroPersonalizadoNombre.value = ''
    }
  }
  actualizarNotasBatchParaActividad()
}

// Prefill notas para los alumnos de la actividad seleccionada
function actualizarNotasBatchParaActividad() {
  const opt = listaActividades.value.find(o => o.id === actividadSeleccionadaKey.value)
  if (!opt) return

  const nombreRubro = opt.tipo === 'MANUAL' ? rubroPersonalizadoNombre.value.trim() : opt.nombre

  const nuevasNotas: Record<number, number | ''> = {}
  consolidado.value.forEach(est => {
    // Si ya existe nota registrada para este rubro en este alumno, prellenarla
    const detalle = est.detalle_rubros?.[nombreRubro]
    if (detalle && detalle.nota !== null && detalle.nota !== undefined) {
      nuevasNotas[est.estudiante_id] = detalle.nota
    } else if (est.notas_por_rubro && est.notas_por_rubro[nombreRubro] !== undefined) {
      nuevasNotas[est.estudiante_id] = est.notas_por_rubro[nombreRubro]
    } else {
      nuevasNotas[est.estudiante_id] = ''
    }
  })
  notasBatch.value = nuevasNotas
}

// Rellenar notas vacías con un valor
function autoRellenar(valor: number) {
  consolidado.value.forEach(est => {
    if (notasBatch.value[est.estudiante_id] === '' || notasBatch.value[est.estudiante_id] === undefined) {
      notasBatch.value[est.estudiante_id] = valor
    }
  })
}

// Limpiar todas las notas de la actividad
function limpiarNotas() {
  consolidado.value.forEach(est => {
    notasBatch.value[est.estudiante_id] = ''
  })
}

// Guardar calificaciones en lote para la actividad seleccionada
async function guardarCalificacionesLote() {
  const opt = listaActividades.value.find(o => o.id === actividadSeleccionadaKey.value)
  if (!opt) {
    alert('Por favor selecciona una actividad válida.')
    return
  }

  const nombreRubro = opt.tipo === 'MANUAL'
    ? rubroPersonalizadoNombre.value.trim()
    : opt.nombre.trim()

  if (!nombreRubro) {
    alert('Debes ingresar un nombre para el rubro o evaluación.')
    return
  }

  // Filtrar solo notas válidas (números entre 0 y 20)
  const notasAEnviar: Array<{ estudiante_id: number; nota: number }> = []
  for (const est of consolidado.value) {
    const val = notasBatch.value[est.estudiante_id]
    if (val !== '' && val !== null && val !== undefined && !isNaN(Number(val))) {
      const num = Number(val)
      if (num >= 0 && num <= 20) {
        notasAEnviar.push({ estudiante_id: est.estudiante_id, nota: num })
      }
    }
  }

  if (notasAEnviar.length === 0) {
    alert('No has ingresado ninguna nota válida para guardar (las notas deben estar entre 0 y 20).')
    return
  }

  savingBatch.value = true
  successMessage.value = null
  try {
    await calificacionesApi.guardarLote(grupoId.value, {
      nombre_rubro: nombreRubro,
      tipo_origen: opt.tipo,
      origen_id: opt.origen_id ?? null,
      peso_porcentual: Number(pesoActividad.value) || 20,
      notas: notasAEnviar
    })

    await loadDatos(false)
    successMessage.value = `¡Se guardaron exitosamente ${notasAEnviar.length} calificaciones para "${nombreRubro}"!`
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al registrar calificaciones en lote.')
  } finally {
    savingBatch.value = false
  }
}

// Exportar a Excel (CSV con BOM)
async function exportarExcel() {
  exporting.value = true
  try {
    const cursoNombre = grupoActual.value?.curso?.nombre ? grupoActual.value.curso.nombre.replace(/[^a-zA-Z0-9]/g, '_') : 'curso'
    const filename = `acta_calificaciones_${cursoNombre}_${grupoId.value}.csv`
    await calificacionesApi.descargarExcel(grupoId.value, filename)
    successMessage.value = 'El archivo de calificaciones en Excel (.CSV) se ha descargado correctamente.'
  } catch {
    alert('Error al descargar el archivo de calificaciones en Excel.')
  } finally {
    exporting.value = false
  }
}

// Imprimir Acta Oficial
function imprimirActa() {
  window.print()
}

// Abrir modal de nota individual
function abrirModalIndividual(est: ConsolidadoEstudiante, rubroNombre?: string) {
  const nombreR = rubroNombre || (rubros.value[0] || 'Práctica 01')
  const detalle = est.detalle_rubros?.[nombreR]
  const notaActual = detalle?.nota ?? est.notas_por_rubro?.[nombreR] ?? 14

  individualForm.value = {
    id: detalle?.id || null,
    estudiante_id: est.estudiante_id,
    estudiante_nombre: est.nombre_completo,
    nombre_rubro: nombreR,
    tipo_origen: (detalle?.tipo_origen as any) || 'MANUAL',
    origen_id: detalle?.origen_id || null,
    nota: Number(notaActual),
    peso_porcentual: Number(detalle?.peso_porcentual || 20),
  }
  modalIndividualOpen.value = true
}

// Guardar nota individual
async function guardarNotaIndividual() {
  if (individualForm.value.nota < 0 || individualForm.value.nota > 20) {
    alert('La nota debe estar entre 0 y 20.')
    return
  }

  savingIndividual.value = true
  try {
    if (individualForm.value.id) {
      await calificacionesApi.update(grupoId.value, individualForm.value.id, {
        nombre_rubro: individualForm.value.nombre_rubro,
        nota: individualForm.value.nota,
        peso_porcentual: individualForm.value.peso_porcentual,
      })
    } else {
      await calificacionesApi.create(grupoId.value, {
        estudiante_id: individualForm.value.estudiante_id,
        nombre_rubro: individualForm.value.nombre_rubro,
        tipo_origen: individualForm.value.tipo_origen,
        origen_id: individualForm.value.origen_id,
        nota: individualForm.value.nota,
        peso_porcentual: individualForm.value.peso_porcentual,
      })
    }

    modalIndividualOpen.value = false
    await loadDatos(false)
    successMessage.value = `Nota actualizada para ${individualForm.value.estudiante_nombre}.`
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar la calificación.')
  } finally {
    savingIndividual.value = false
  }
}

watch(() => route.params.id, (newId) => {
  if (newId) {
    grupoId.value = Number(newId)
    loadDatos()
  }
})

onMounted(loadDatos)
</script>

<template>
  <div class="space-y-6 print:space-y-3">
    <!-- Header institucional y selector de cátedra con PageHeader -->
    <PageHeader 
      eyebrow="Cátedra Activa" 
      :title="`Acta y Calificaciones${grupoActual?.curso?.nombre ? ' — ' + grupoActual.curso.nombre : ''}`" 
      description="Registro consolidado, evaluación por actividades y exportación oficial de notas por cátedra."
      class="print:hidden"
    >
      <template #actions>
        <!-- Selector de Grupos Asignados del Docente (Aislamiento por curso) -->
        <div v-if="misGrupos.length > 1" class="flex items-center gap-2">
          <label for="grupoSelect" class="text-xs font-semibold text-slate-600 dark:text-slate-300 shrink-0">Cambiar Cátedra:</label>
          <select
            id="grupoSelect"
            :value="grupoId"
            @change="cambiarGrupo(Number(($event.target as HTMLSelectElement).value))"
            class="input-field text-xs py-1.5 px-3 font-medium bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-lg border-slate-300 dark:border-slate-700 shadow-xs max-w-xs truncate"
          >
            <option v-for="g in misGrupos" :key="g.id" :value="g.id">
              {{ g.curso?.nombre }} ({{ g.codigo_grupo }})
            </option>
          </select>
        </div>

        <RouterLink
          :to="`/docente/grupos/${grupoId}/aula`"
          class="btn-secondary inline-flex items-center gap-1.5 text-xs py-2 px-3 font-semibold rounded-lg shadow-xs"
          title="Ir al Aula Virtual"
        >
          <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          Aula Virtual
        </RouterLink>

        <RouterLink
          :to="`/docente/grupos/${grupoId}/tareas`"
          class="btn-secondary inline-flex items-center gap-1.5 text-xs py-2 px-3 font-semibold rounded-lg shadow-xs"
          title="Ver Tareas"
        >
          <svg class="h-4 w-4 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
          Tareas
        </RouterLink>
      </template>
    </PageHeader>

    <!-- Encabezado para Impresión Oficial (solo visible en print) -->
    <div class="hidden print:block border-b-2 border-slate-900 pb-3 mb-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-black tracking-wider uppercase text-slate-900">EDUX — SISTEMA DE GESTIÓN ACADÉMICA</h2>
          <h3 class="text-sm font-bold text-slate-700">ACTA CONSOLIDADA DE CALIFICACIONES</h3>
        </div>
        <div class="text-right text-xs text-slate-600">
          <p><strong>Fecha de Emisión:</strong> {{ new Date().toLocaleDateString('es-PE') }}</p>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-2 mt-2 text-xs text-slate-800 bg-slate-100 p-2 rounded">
        <div>
          <p><strong>Curso:</strong> {{ grupoActual?.curso?.nombre || 'N/A' }} ({{ grupoActual?.curso?.codigo || 'N/A' }})</p>
          <p><strong>Grupo / Sección:</strong> {{ grupoActual?.codigo_grupo }} - {{ grupoActual?.seccion || 'Única' }}</p>
        </div>
        <div>
          <p><strong>Periodo Académico:</strong> {{ grupoActual?.periodo?.nombre || '2026-I' }}</p>
          <p><strong>Alumnos Matriculados:</strong> {{ consolidado.length }}</p>
        </div>
      </div>
    </div>

    <!-- Banner de Información del Curso Seleccionado (Pantalla) -->
    <div v-if="grupoActual" class="print:hidden rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-xs flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-bold text-sm shadow-xs shrink-0">
          {{ (grupoActual.curso?.nombre || 'C').slice(0, 2).toUpperCase() }}
        </div>
        <div>
          <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100 font-headline">{{ grupoActual.curso?.nombre }}</h2>
          <div class="flex flex-wrap items-center gap-2 mt-0.5 text-xs text-slate-500 dark:text-slate-400">
            <span class="font-mono bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-1.5 py-0.5 rounded font-semibold">{{ grupoActual.codigo_grupo }}</span>
            <span>• Sección: <strong>{{ grupoActual.seccion || 'A' }}</strong></span>
            <span>• Periodo: <strong>{{ grupoActual.periodo?.nombre || '2026-I' }}</strong></span>
            <span>• Matriculados: <strong>{{ consolidado.length }}</strong></span>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="exportarExcel"
          :disabled="exporting || consolidado.length === 0"
          class="btn-secondary inline-flex items-center gap-2 text-xs py-2 px-3 font-semibold rounded-lg shadow-xs hover:border-emerald-300 dark:hover:border-emerald-700 hover:text-emerald-800 dark:hover:text-emerald-300 transition-colors"
          title="Descargar en formato Excel (.CSV compatible nativo)"
        >
          <svg v-if="!exporting" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span v-else class="h-3 w-3 border-2 border-emerald-600 border-t-transparent rounded-full animate-spin"></span>
          <span>{{ exporting ? 'Generando Excel…' : 'Exportar a Excel (.CSV)' }}</span>
        </button>

        <button
          @click="imprimirActa"
          :disabled="consolidado.length === 0"
          class="btn-secondary inline-flex items-center gap-2 text-xs py-2 px-3 font-semibold rounded-lg shadow-xs hover:border-slate-400 dark:hover:border-slate-600"
          title="Imprimir acta oficial"
        >
          <svg class="h-4 w-4 text-slate-600 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          <span>Imprimir Acta</span>
        </button>
      </div>
    </div>

    <!-- Alertas y Mensajes -->
    <div v-if="successMessage" class="rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/80 dark:border-emerald-800/60 p-3.5 text-xs text-emerald-800 dark:text-emerald-200 flex items-center justify-between gap-3 shadow-xs">
      <div class="flex items-center gap-2">
        <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span>{{ successMessage }}</span>
      </div>
      <button @click="successMessage = null" class="text-emerald-700 dark:text-emerald-400 hover:text-emerald-900 dark:hover:text-emerald-200 font-bold text-sm">×</button>
    </div>

    <div v-if="error" class="rounded-xl bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-800/60 p-4 text-xs text-red-800 dark:text-red-200 flex items-center gap-3">
      <svg class="h-5 w-5 text-red-600 dark:text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <span>{{ error }}</span>
    </div>

    <!-- Navegación de Pestañas (Pantalla) -->
    <div class="border-b border-slate-200 dark:border-slate-800 print:hidden flex gap-4">
      <button
        @click="activeTab = 'consolidado'"
        class="pb-3 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2"
        :class="activeTab === 'consolidado' ? 'border-emerald-600 dark:border-emerald-400 text-emerald-700 dark:text-emerald-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span>Acta Consolidada (Matriz de Notas)</span>
        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60">
          {{ consolidado.length }}
        </span>
      </button>

      <button
        @click="activeTab = 'por_actividad'"
        class="pb-3 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2"
        :class="activeTab === 'por_actividad' ? 'border-emerald-600 dark:border-emerald-400 text-emerald-700 dark:text-emerald-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        <span>Calificar por Actividad Publicada</span>
        <span v-if="listaActividades.length > 0" class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/70 text-emerald-800 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">
          {{ listaActividades.length - 1 }} Actividades
        </span>
      </button>
    </div>

    <!-- Indicador de Carga -->
    <SkeletonTable v-if="loading" :columns="6" :rows="8" />

    <!-- ═════════════════════════════════════════════════════════════════════════════ -->
    <!-- PESTAÑA 1: ACTA CONSOLIDADA (MATRIZ COMPLETA DE CALIFICACIONES)              -->
    <!-- ═════════════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="activeTab === 'consolidado'" class="space-y-5">
      <!-- Tarjetas KPIs del Acta (Pantalla) -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 print:hidden">
        <div class="rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-3.5 shadow-xs">
          <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Matriculados</p>
          <p class="text-xl font-bold font-headline text-slate-900 dark:text-slate-100 mt-0.5">{{ kpis.total }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/40 dark:bg-emerald-950/30 p-3.5 shadow-xs">
          <p class="text-[11px] font-semibold text-emerald-800 dark:text-emerald-300 uppercase tracking-wider">Aprobados (≥ 11)</p>
          <div class="flex items-baseline gap-2 mt-0.5">
            <span class="text-xl font-bold font-headline text-emerald-700 dark:text-emerald-300">{{ kpis.aprobados }}</span>
            <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">({{ kpis.total ? Math.round((kpis.aprobados / kpis.total) * 100) : 0 }}%)</span>
          </div>
        </div>
        <div class="rounded-xl border border-red-200/80 dark:border-red-800/60 bg-red-50/40 dark:bg-red-950/30 p-3.5 shadow-xs">
          <p class="text-[11px] font-semibold text-red-800 dark:text-red-300 uppercase tracking-wider">Desaprobados (&lt; 11)</p>
          <div class="flex items-baseline gap-2 mt-0.5">
            <span class="text-xl font-bold font-headline text-red-700 dark:text-red-300">{{ kpis.desaprobados }}</span>
            <span class="text-xs text-red-600 dark:text-red-400 font-medium">({{ kpis.total ? Math.round((kpis.desaprobados / kpis.total) * 100) : 0 }}%)</span>
          </div>
        </div>
        <div class="rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-3.5 shadow-xs">
          <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Promedio General</p>
          <p class="text-xl font-bold font-headline mt-0.5" :class="kpis.promedioGeneral >= 11 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
            {{ kpis.promedioGeneral.toFixed(2) }} <span class="text-xs font-normal text-slate-500 dark:text-slate-400">/ 20</span>
          </p>
        </div>
      </div>

      <!-- Barra de herramientas: Búsqueda y acciones -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 print:hidden">
        <div class="relative flex-1 max-w-sm">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Buscar por código o nombre de alumno…"
            class="input-field pl-9 text-xs py-2 w-full rounded-lg"
          />
          <svg class="h-4 w-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="activeTab = 'por_actividad'"
            class="btn-primary inline-flex items-center gap-2 text-xs py-2 px-3 font-semibold rounded-lg shadow-xs"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Ingresar Calificaciones</span>
          </button>
        </div>
      </div>

      <!-- Tabla Matriz Consolidada -->
      <div class="overflow-x-auto rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs text-left">
          <thead class="bg-slate-50/90 dark:bg-slate-850 text-slate-600 dark:text-slate-300 font-semibold uppercase tracking-wider text-[11px]">
            <tr>
              <th scope="col" class="py-3 px-3 w-10 text-center">N°</th>
              <th scope="col" class="py-3 px-3 w-28 font-mono">Código</th>
              <th scope="col" class="py-3 px-4 min-w-[200px]">Apellidos y Nombres</th>
              
              <!-- Columnas dinámicas de Rubros / Actividades evaluadas -->
              <th
                v-for="rubro in rubros"
                :key="rubro"
                scope="col"
                class="py-3 px-3 text-center min-w-[100px] border-l border-slate-200/60 dark:border-slate-800"
                :title="`Rubro: ${rubro}`"
              >
                <div class="truncate max-w-[130px] font-bold text-slate-700 dark:text-slate-200" :title="rubro">
                  {{ rubro }}
                </div>
              </th>

              <!-- Promedio y Estado -->
              <th scope="col" class="py-3 px-3 text-center w-28 bg-emerald-50/50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200 border-l border-slate-200 dark:border-slate-800 font-bold">
                Promedio
              </th>
              <th scope="col" class="py-3 px-3 text-center w-28 border-l border-slate-200 dark:border-slate-800">
                Condición
              </th>
              <th scope="col" class="py-3 px-3 text-center w-20 print:hidden">
                Acción
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
            <tr v-if="consolidadoFiltrado.length === 0">
              <td :colspan="6 + rubros.length" class="py-12 text-center text-slate-400">
                <svg class="h-8 w-8 mx-auto text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p class="font-medium text-slate-600">No se encontraron estudiantes en este grupo.</p>
                <p class="text-xs text-slate-400 mt-0.5">Los alumnos matriculados aparecerán automáticamente aquí.</p>
              </td>
            </tr>

            <tr
              v-for="(est, idx) in consolidadoFiltrado"
              :key="est.estudiante_id"
              class="hover:bg-slate-50/70 dark:hover:bg-slate-850/50 transition-colors"
            >
              <td class="py-2.5 px-3 text-center text-slate-400 font-mono">
                {{ idx + 1 }}
              </td>
              <td class="py-2.5 px-3 font-mono font-medium text-slate-600 dark:text-slate-400 whitespace-nowrap">
                {{ est.codigo }}
              </td>
              <td class="py-2.5 px-4 font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">
                {{ est.nombre_completo }}
              </td>

              <!-- Celdas de Notas por Rubro -->
              <td
                v-for="rubro in rubros"
                :key="rubro"
                class="py-2.5 px-3 text-center border-l border-slate-100 dark:border-slate-800 font-mono font-medium cursor-pointer hover:bg-emerald-50/30 dark:hover:bg-emerald-950/30 transition-colors"
                @click="abrirModalIndividual(est, rubro)"
                :title="`Hacer clic para editar la nota de ${rubro}`"
              >
                <span
                  v-if="est.notas_por_rubro && est.notas_por_rubro[rubro] !== undefined"
                  :class="est.notas_por_rubro[rubro] >= 11 ? 'text-emerald-700 dark:text-emerald-400 font-bold' : 'text-red-600 dark:text-red-400 font-bold'"
                >
                  {{ Number(est.notas_por_rubro[rubro]).toFixed(1) }}
                </span>
                <span v-else class="text-slate-300 dark:text-slate-600">-</span>
              </td>

              <!-- Promedio Final -->
              <td class="py-2.5 px-3 text-center border-l border-slate-200 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-850/30 font-mono font-black text-sm"
                  :class="est.promedio_final >= 11 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                {{ Number(est.promedio_final).toFixed(2) }}
              </td>

              <!-- Condición (Aprobado / Desaprobado) -->
              <td class="py-2.5 px-3 text-center border-l border-slate-100 dark:border-slate-800 whitespace-nowrap">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide"
                  :class="est.estado === 'APROBADO' ? 'bg-emerald-100 dark:bg-emerald-950/70 text-emerald-800 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60' : 'bg-red-100 dark:bg-red-950/70 text-red-800 dark:text-red-300 border border-red-200/60 dark:border-red-800/60'"
                >
                  {{ est.estado }}
                </span>
              </td>

              <!-- Acciones -->
              <td class="py-2.5 px-3 text-center border-l border-slate-100 dark:border-slate-800 print:hidden whitespace-nowrap">
                <button
                  @click="abrirModalIndividual(est)"
                  class="text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-emerald-700 dark:hover:text-emerald-300 p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                  title="Modificar o registrar nota individual"
                >
                  Editar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pie de página oficial para versión impresa (Firmas) -->
      <div class="hidden print:grid grid-cols-2 gap-12 pt-16 text-center text-xs text-slate-700">
        <div class="border-t border-slate-400 pt-2">
          <p class="font-bold text-slate-900">{{ grupoActual?.docente?.usuario ? `${grupoActual.docente.usuario.nombres} ${grupoActual.docente.usuario.apellido_paterno}` : 'Firma del Docente Titular' }}</p>
          <p class="text-slate-500">Docente Titular de Cátedra</p>
        </div>
        <div class="border-t border-slate-400 pt-2">
          <p class="font-bold text-slate-900">Coordinación Académica</p>
          <p class="text-slate-500">V° B° Registro y Actas</p>
        </div>
      </div>
    </div>

    <!-- ═════════════════════════════════════════════════════════════════════════════ -->
    <!-- PESTAÑA 2: CALIFICAR POR ACTIVIDAD PUBLICADA (EVALUACIONES / TAREAS / RÚBRICA) -->
    <!-- ═════════════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="activeTab === 'por_actividad'" class="space-y-5">
      <!-- Selector de la Actividad a calificar -->
      <div class="rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-xs space-y-4">
        <div>
          <h2 class="text-sm font-bold font-headline text-slate-900 dark:text-slate-100">1. Selecciona la Actividad o Evaluación a Calificar</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Puedes seleccionar un Quizz/Evaluación programada en el aula, una Tarea con entregas, o ingresar una rúbrica manual.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-1">
          <!-- Dropdown de Actividades -->
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Actividad / Evaluación del Aula:</label>
            <select
              :value="actividadSeleccionadaKey"
              @change="seleccionarActividad(($event.target as HTMLSelectElement).value)"
              class="input-field text-xs py-2 w-full font-medium"
            >
              <option v-for="opt in listaActividades" :key="opt.id" :value="opt.id">
                {{ opt.label }}
              </option>
            </select>
          </div>

          <!-- Peso porcentual -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Peso en el Promedio (%):</label>
            <div class="flex items-center gap-1">
              <input
                v-model.number="pesoActividad"
                type="number"
                min="1"
                max="100"
                class="input-field text-xs py-2 w-full font-semibold"
                placeholder="20"
              />
              <span class="text-xs text-slate-500 dark:text-slate-400 font-bold px-1">%</span>
            </div>
          </div>

          <!-- Nombre del rubro si es manual -->
          <div v-if="listaActividades.find(o => o.id === actividadSeleccionadaKey)?.tipo === 'MANUAL'" class="md:col-span-3">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nombre de la Rúbrica / Examen Manual:</label>
            <input
              v-model="rubroPersonalizadoNombre"
              @input="actualizarNotasBatchParaActividad"
              type="text"
              class="input-field text-xs py-2 w-full font-medium"
              placeholder="Ej: Examen Parcial 01, Participación en Clase, Práctica Calificada 2…"
            />
          </div>
        </div>

        <!-- Herramientas de llenado rápido -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
          <div class="flex items-center gap-2">
            <span class="text-slate-500 dark:text-slate-400 font-medium">Llenado rápido:</span>
            <button
              @click="autoRellenar(20)"
              type="button"
              class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium transition-colors"
            >
              Llenar con 20
            </button>
            <button
              @click="autoRellenar(15)"
              type="button"
              class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium transition-colors"
            >
              Llenar con 15
            </button>
            <button
              @click="autoRellenar(0)"
              type="button"
              class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium transition-colors"
            >
              Llenar vacíos con 00
            </button>
            <button
              @click="limpiarNotas"
              type="button"
              class="px-2.5 py-1 rounded text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 font-medium transition-colors"
            >
              Limpiar notas
            </button>
          </div>

          <div class="text-slate-500 dark:text-slate-400 text-[11px]">
            * Sistema vigesimal estándar (00.00 a 20.00). La nota aprobatoria mínima es 11.
          </div>
        </div>
      </div>

      <!-- Lista de Nómina de Alumnos con Inputs de Calificación -->
      <div class="rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs overflow-hidden">
        <div class="px-5 py-3.5 bg-slate-50/80 dark:bg-slate-850 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
          <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
            2. Nómina de Estudiantes ({{ consolidado.length }})
          </h3>
          <span class="text-xs text-slate-500 dark:text-slate-400">
            Ingresa las notas y presiona <strong>"Guardar Calificaciones"</strong> abajo.
          </span>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-slate-800">
          <div
            v-for="(est, idx) in consolidado"
            :key="est.estudiante_id"
            class="px-5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors"
          >
            <div class="flex items-center gap-3">
              <span class="text-slate-400 font-mono text-xs w-6 text-center">{{ idx + 1 }}</span>
              <div class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300 text-xs font-bold font-mono">
                {{ est.codigo.slice(-2) }}
              </div>
              <div>
                <p class="text-xs font-bold text-slate-900 dark:text-slate-100 leading-tight">{{ est.nombre_completo }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                  Código: {{ est.codigo }} • {{ est.tipo_documento || 'DNI' }}: {{ est.numero_documento || 'N/A' }}
                </p>
              </div>
            </div>

            <!-- Input de nota para este estudiante -->
            <div class="flex items-center gap-3 self-end sm:self-center">
              <label :for="`nota-${est.estudiante_id}`" class="text-xs font-semibold text-slate-600 dark:text-slate-300">Nota:</label>
              <div class="relative w-28">
                <input
                  :id="`nota-${est.estudiante_id}`"
                  v-model.number="notasBatch[est.estudiante_id]"
                  type="number"
                  min="0"
                  max="20"
                  step="0.5"
                  placeholder="0.0"
                  class="input-field text-center font-bold text-sm py-1.5 px-2 rounded-lg"
                  :class="notasBatch[est.estudiante_id] !== '' && Number(notasBatch[est.estudiante_id]) < 11 ? 'border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 focus:border-red-500 focus:ring-red-200' : 'border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 focus:border-emerald-500 focus:ring-emerald-200'"
                />
                <span class="absolute right-2 top-2 text-[10px] font-bold text-slate-400">/20</span>
              </div>
              
              <!-- Estado visual de nota -->
              <span
                v-if="notasBatch[est.estudiante_id] !== '' && notasBatch[est.estudiante_id] !== undefined"
                class="w-20 text-center text-[10px] font-bold px-2 py-1 rounded-full"
                :class="Number(notasBatch[est.estudiante_id]) >= 11 ? 'bg-emerald-100 dark:bg-emerald-950/70 text-emerald-800 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60' : 'bg-red-100 dark:bg-red-950/70 text-red-800 dark:text-red-300 border border-red-200/60 dark:border-red-800/60'"
              >
                {{ Number(notasBatch[est.estudiante_id]) >= 11 ? 'Aprobado' : 'Desaprobado' }}
              </span>
              <span v-else class="w-20 text-center text-[10px] text-slate-400 italic">
                Sin calificar
              </span>
            </div>
          </div>
        </div>

        <!-- Botón de Guardar en Lote -->
        <div class="p-5 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
          <p class="text-xs text-slate-500 dark:text-slate-400">
            Al guardar, las notas ingresadas se registrarán de inmediato en el acta oficial del curso y serán visibles para los estudiantes correspondientes.
          </p>
          <button
            @click="guardarCalificacionesLote"
            :disabled="savingBatch"
            class="btn-primary inline-flex items-center gap-2 text-sm py-2.5 px-6 font-bold rounded-xl shadow-md w-full sm:w-auto justify-center"
          >
            <svg v-if="!savingBatch" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            <span v-else class="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>{{ savingBatch ? 'Guardando Calificaciones…' : 'Guardar Calificaciones del Curso' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ═════════════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL DE CALIFICACIÓN INDIVIDUAL                                             -->
    <!-- ═════════════════════════════════════════════════════════════════════════════ -->
    <BaseModal
      :open="modalIndividualOpen"
      :title="individualForm.id ? 'Modificar Calificación' : 'Registrar Calificación'"
      @close="modalIndividualOpen = false"
    >
      <form @submit.prevent="guardarNotaIndividual" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Estudiante:</label>
          <input
            :value="individualForm.estudiante_nombre"
            disabled
            class="input-field mt-1 text-xs py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Rubro / Nombre de la Evaluación:</label>
          <input
            v-model="individualForm.nombre_rubro"
            required
            class="input-field mt-1 text-xs py-2"
            placeholder="Ej: Evaluación Continua 1, Parcial, Quizz..."
          />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Nota (0 a 20):</label>
            <input
              v-model.number="individualForm.nota"
              type="number"
              min="0"
              max="20"
              step="0.5"
              required
              class="input-field mt-1 text-xs py-2 font-bold text-center text-sm"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Peso (%):</label>
            <input
              v-model.number="individualForm.peso_porcentual"
              type="number"
              min="1"
              max="100"
              required
              class="input-field mt-1 text-xs py-2 font-semibold text-center"
            />
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
          <button
            type="button"
            @click="modalIndividualOpen = false"
            class="btn-secondary text-xs py-2 px-3"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="savingIndividual"
            class="btn-primary text-xs py-2 px-4 font-semibold inline-flex items-center gap-2"
          >
            <span v-if="savingIndividual" class="h-3 w-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>{{ savingIndividual ? 'Guardando…' : 'Guardar Calificación' }}</span>
          </button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>

<style scoped>
@media print {
  body {
    background: white !important;
    font-size: 10pt;
  }
}
</style>
