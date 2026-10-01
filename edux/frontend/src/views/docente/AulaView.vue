<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { unidadesApi, materialesApi, asistenciasApi, evaluacionesApi, matriculasApi, solicitudesInclusionApi } from '@/api'
import type { Unidad, Material, AsistenciaItem, AsistenciaResumenItem, Evaluacion, Pregunta, IntentoEvaluacion, Matricula, SolicitudInclusion } from '@/types'
import BaseModal from '@/components/ui/BaseModal.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import FileDropzone from '@/components/ui/FileDropzone.vue'
import SkeletonAula from '@/components/ui/SkeletonAula.vue'
import SkeletonTable from '@/components/ui/SkeletonTable.vue'
import SkeletonList from '@/components/ui/SkeletonList.vue'

const route = useRoute()
const grupoId = Number(route.params.id)

const activeTab = ref<'contenido' | 'asistencia' | 'evaluaciones' | 'alumnos'>('contenido')

// ─── Contenido (Unidades y Materiales) ───
const unidades = ref<Unidad[]>([])
const loading = ref(true)

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

// Helpers de fecha
function toDateInput(iso: string | null | undefined): string {
  if (!iso) return ''
  return iso.split('T')[0]
}

function toDatetimeLocalInput(iso: string | null | undefined): string {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    if (isNaN(d.getTime())) return ''
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const hours = String(d.getHours()).padStart(2, '0')
    const mins = String(d.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day}T${hours}:${mins}`
  } catch {
    return ''
  }
}

function formatFechaCorta(fecha: string | null | undefined): string {
  if (!fecha) return ''
  try {
    const d = new Date(fecha)
    return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' })
  } catch {
    return fecha
  }
}

function formatFechaHora(fecha: string | null | undefined): string {
  if (!fecha) return ''
  try {
    const d = new Date(fecha)
    return d.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return fecha
  }
}

function isFutureDate(fecha: string | null | undefined): boolean {
  if (!fecha) return false
  return new Date(fecha).getTime() > Date.now()
}

function isPastDate(fecha: string | null | undefined): boolean {
  if (!fecha) return false
  return new Date(fecha).getTime() < Date.now()
}

// ─── Unidades ───
const openUnidadModal = ref(false)
const editingUnidadId = ref<number | null>(null)
const submittingUnidad = ref(false)
const unidadForm = ref({
  titulo: '',
  descripcion: '',
  orden: 1,
  fecha_inicio: '',
  fecha_fin: '',
  visible_desde: '',
  visible_hasta: '',
  estado: 'ACTIVO' as 'ACTIVO' | 'INACTIVO'
})

function openCreateUnidadModal() {
  editingUnidadId.value = null
  unidadForm.value = {
    titulo: '',
    descripcion: '',
    orden: unidades.value.length + 1,
    fecha_inicio: '',
    fecha_fin: '',
    visible_desde: '',
    visible_hasta: '',
    estado: 'ACTIVO'
  }
  openUnidadModal.value = true
}

function openEditUnidadModal(u: Unidad) {
  editingUnidadId.value = u.id
  unidadForm.value = {
    titulo: u.titulo,
    descripcion: u.descripcion || '',
    orden: u.orden,
    fecha_inicio: toDateInput(u.fecha_inicio),
    fecha_fin: toDateInput(u.fecha_fin),
    visible_desde: toDatetimeLocalInput(u.visible_desde),
    visible_hasta: toDatetimeLocalInput(u.visible_hasta),
    estado: u.estado === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO'
  }
  openUnidadModal.value = true
}

async function handleSaveUnidad() {
  if (!unidadForm.value.titulo.trim()) {
    alert('El título de la unidad es obligatorio.')
    return
  }
  submittingUnidad.value = true
  try {
    const payload: Record<string, unknown> = {
      titulo: unidadForm.value.titulo.trim(),
      descripcion: unidadForm.value.descripcion || null,
      orden: Number(unidadForm.value.orden) || 1,
      fecha_inicio: unidadForm.value.fecha_inicio || null,
      fecha_fin: unidadForm.value.fecha_fin || null,
      visible_desde: unidadForm.value.visible_desde || null,
      visible_hasta: unidadForm.value.visible_hasta || null,
      estado: unidadForm.value.estado
    }
    if (editingUnidadId.value) {
      await unidadesApi.update(grupoId, editingUnidadId.value, payload)
      alert('Unidad actualizada exitosamente.')
    } else {
      await unidadesApi.create(grupoId, payload)
      alert('Unidad creada exitosamente.')
    }
    openUnidadModal.value = false
    await loadUnidades()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar la unidad.')
  } finally {
    submittingUnidad.value = false
  }
}

async function handleToggleUnidadVisibility(u: Unidad) {
  const nuevoEstado = u.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'
  const actionLabel = nuevoEstado === 'ACTIVO' ? 'mostrar a los alumnos' : 'ocultar a los alumnos'
  if (!confirm(`¿Deseas ${actionLabel} la unidad "${u.titulo}"?`)) return
  try {
    await unidadesApi.update(grupoId, u.id, { estado: nuevoEstado })
    await loadUnidades()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al cambiar visibilidad de la unidad.')
  }
}

async function handleDeleteUnidad(u: Unidad) {
  if (!confirm(`¿Estás seguro de eliminar la unidad "${u.titulo}"? Esta acción solo es posible si no contiene sesiones ni materiales asociados.`)) return
  try {
    await unidadesApi.remove(grupoId, u.id)
    await loadUnidades()
    alert('Unidad eliminada exitosamente.')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'No se puede eliminar una unidad con sesiones o materiales asociados.')
  }
}

// ─── Materiales ───
const openMaterialModal = ref(false)
const editingMaterialId = ref<number | null>(null)
const selectedUnidadId = ref<number | null>(null)
const uploadingMaterial = ref(false)
const materialForm = ref({
  titulo: '',
  descripcion: '',
  tipo: 'DOCUMENTO' as 'DOCUMENTO' | 'ENLACE',
  enlace_externo: '',
  archivo: null as File | null,
  visible_desde: '',
  visible_hasta: '',
  estado: 'ACTIVO' as 'ACTIVO' | 'INACTIVO'
})

function showMaterialModal(unidadId: number) {
  editingMaterialId.value = null
  selectedUnidadId.value = unidadId
  materialForm.value = {
    titulo: '',
    descripcion: '',
    tipo: 'DOCUMENTO',
    enlace_externo: '',
    archivo: null,
    visible_desde: '',
    visible_hasta: '',
    estado: 'ACTIVO'
  }
  openMaterialModal.value = true
}

function openEditMaterialModal(unidadId: number, mat: Material) {
  editingMaterialId.value = mat.id
  selectedUnidadId.value = unidadId
  materialForm.value = {
    titulo: mat.titulo,
    descripcion: mat.descripcion || '',
    tipo: mat.tipo === 'ENLACE' ? 'ENLACE' : 'DOCUMENTO',
    enlace_externo: mat.enlace_externo || '',
    archivo: null,
    visible_desde: toDatetimeLocalInput(mat.visible_desde),
    visible_hasta: toDatetimeLocalInput(mat.visible_hasta),
    estado: mat.estado === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO'
  }
  openMaterialModal.value = true
}

async function handleSaveMaterial() {
  if (!selectedUnidadId.value) return
  if (!materialForm.value.titulo.trim()) {
    alert('Por favor ingresa el título del material.')
    return
  }

  if (!editingMaterialId.value && materialForm.value.tipo !== 'ENLACE' && !materialForm.value.archivo) {
    alert('Por favor selecciona un archivo para subir.')
    return
  }

  if (materialForm.value.tipo === 'ENLACE' && !materialForm.value.enlace_externo) {
    alert('Por favor ingresa la URL o enlace externo válido.')
    return
  }

  uploadingMaterial.value = true
  try {
    const formData = new FormData()
    formData.append('titulo', materialForm.value.titulo.trim())
    formData.append('descripcion', materialForm.value.descripcion || '')
    formData.append('tipo', materialForm.value.tipo === 'ENLACE' ? 'ENLACE' : 'DOCUMENTO')
    if (materialForm.value.tipo === 'ENLACE') {
      formData.append('enlace_externo', materialForm.value.enlace_externo)
    }
    if (materialForm.value.archivo) {
      formData.append('archivo', materialForm.value.archivo)
    }
    if (materialForm.value.visible_desde) {
      formData.append('visible_desde', materialForm.value.visible_desde)
    }
    if (materialForm.value.visible_hasta) {
      formData.append('visible_hasta', materialForm.value.visible_hasta)
    }
    formData.append('estado', materialForm.value.estado)

    if (editingMaterialId.value) {
      await materialesApi.update(selectedUnidadId.value, editingMaterialId.value, formData)
      alert('Material actualizado exitosamente.')
    } else {
      await materialesApi.create(selectedUnidadId.value, formData)
      alert('Material subido exitosamente.')
    }
    openMaterialModal.value = false
    await loadUnidades()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar el material.')
  } finally {
    uploadingMaterial.value = false
  }
}

async function handleToggleMaterialVisibility(unidadId: number, mat: Material) {
  const nuevoEstado = mat.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'
  try {
    await materialesApi.update(unidadId, mat.id, { estado: nuevoEstado })
    await loadUnidades()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al cambiar visibilidad del material.')
  }
}

async function handleDeleteMaterial(unidadId: number, materialId: number) {
  if (!confirm('¿Estás seguro de eliminar este material didáctico?')) return
  try {
    await materialesApi.remove(unidadId, materialId)
    await loadUnidades()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al eliminar material.')
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
    alert('Error al descargar archivo.')
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
const submittingEvaluacion = ref(false)
const isEditingEvaluacion = ref(false)
const editingEvaluacionId = ref<number | null>(null)
const evaluacionForm = ref({
  titulo: '',
  instrucciones: '',
  tipo_evaluacion: 'QUIZ' as 'QUIZ' | 'ARCHIVO' | 'MIXTO',
  permite_archivo_estudiante: false,
  archivo_adjunto: null as File | null,
  puntaje_maximo: 20,
  duracion_minutos: 60,
  intentos_permitidos: 1,
  fecha_inicio: '',
  fecha_fin: '',
  estado: 'PUBLICADA' as 'PUBLICADA' | 'OCULTA' | 'BORRADOR'
})

function openCreateEvaluacionModal() {
  isEditingEvaluacion.value = false
  editingEvaluacionId.value = null
  evaluacionForm.value = {
    titulo: '',
    instrucciones: '',
    tipo_evaluacion: 'QUIZ',
    permite_archivo_estudiante: false,
    archivo_adjunto: null,
    puntaje_maximo: 20,
    duracion_minutos: 60,
    intentos_permitidos: 1,
    fecha_inicio: '',
    fecha_fin: '',
    estado: 'PUBLICADA'
  }
  openEvaluacionModal.value = true
}

function openEditEvaluacionModal(ev: Evaluacion) {
  isEditingEvaluacion.value = true
  editingEvaluacionId.value = ev.id
  evaluacionForm.value = {
    titulo: ev.titulo,
    instrucciones: ev.instrucciones || '',
    tipo_evaluacion: (ev.tipo_evaluacion || 'QUIZ') as 'QUIZ' | 'ARCHIVO' | 'MIXTO',
    permite_archivo_estudiante: !!ev.permite_archivo_estudiante,
    archivo_adjunto: null,
    puntaje_maximo: Number(ev.puntaje_maximo) || 20,
    duracion_minutos: ev.duracion_minutos || 60,
    intentos_permitidos: ev.intentos_permitidos || 1,
    fecha_inicio: ev.fecha_inicio ? ev.fecha_inicio.slice(0, 16) : '',
    fecha_fin: ev.fecha_fin ? ev.fecha_fin.slice(0, 16) : '',
    estado: (ev.estado === 'OCULTA' || ev.estado === 'BORRADOR') ? 'OCULTA' : 'PUBLICADA'
  }
  openEvaluacionModal.value = true
}

async function handleToggleEvaluacionVisibility(ev: Evaluacion) {
  const nuevoEstado = ev.estado === 'PUBLICADA' ? 'OCULTA' : 'PUBLICADA'
  const actionLabel = nuevoEstado === 'PUBLICADA' ? 'publicar y hacer visible a los alumnos' : 'ocultar a los alumnos'
  if (!confirm(`¿Deseas ${actionLabel} la evaluación "${ev.titulo}"?`)) return
  try {
    await evaluacionesApi.update(grupoId, ev.id, { estado: nuevoEstado })
    await loadEvaluaciones()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al cambiar la visibilidad de la evaluación.')
  }
}

// Modal de Preguntas / Quiz
const openPreguntasModal = ref(false)
const selectedEvaluacion = ref<Evaluacion | null>(null)
const preguntasList = ref<Pregunta[]>([])
const loadingPreguntas = ref(false)
const guardandoPregunta = ref(false)
const nuevaPreguntaForm = ref({
  enunciado: '',
  tipo: 'ALTERNATIVA' as 'ALTERNATIVA' | 'VERDADERO_FALSO',
  puntaje: 5,
  opciones: [
    { texto: '', es_correcta: true },
    { texto: '', es_correcta: false },
    { texto: '', es_correcta: false },
    { texto: '', es_correcta: false },
  ]
})

// Modal de Entregas / Intentos de Alumnos
const openIntentosModal = ref(false)
const intentosList = ref<IntentoEvaluacion[]>([])
const loadingIntentos = ref(false)

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

async function handleSaveEvaluacion() {
  if (!evaluacionForm.value.titulo || !evaluacionForm.value.fecha_inicio || !evaluacionForm.value.fecha_fin) {
    alert('Por favor complete los campos obligatorios: título, fecha de inicio y fecha de fin.')
    return
  }

  submittingEvaluacion.value = true
  try {
    const formData = new FormData()
    formData.append('titulo', evaluacionForm.value.titulo)
    formData.append('instrucciones', evaluacionForm.value.instrucciones || '')
    formData.append('tipo_evaluacion', evaluacionForm.value.tipo_evaluacion)
    formData.append('permite_archivo_estudiante', evaluacionForm.value.permite_archivo_estudiante ? '1' : '0')
    if (evaluacionForm.value.archivo_adjunto) {
      formData.append('archivo_adjunto', evaluacionForm.value.archivo_adjunto)
    }
    formData.append('puntaje_maximo', String(evaluacionForm.value.puntaje_maximo))
    if (evaluacionForm.value.duracion_minutos) {
      formData.append('duracion_minutos', String(evaluacionForm.value.duracion_minutos))
    }
    formData.append('intentos_permitidos', String(evaluacionForm.value.intentos_permitidos))
    formData.append('fecha_inicio', evaluacionForm.value.fecha_inicio)
    formData.append('fecha_fin', evaluacionForm.value.fecha_fin)
    formData.append('estado', evaluacionForm.value.estado)

    if (isEditingEvaluacion.value && editingEvaluacionId.value) {
      await evaluacionesApi.update(grupoId, editingEvaluacionId.value, formData)
      alert('Evaluación actualizada exitosamente.')
    } else {
      await evaluacionesApi.create(grupoId, formData)
      alert('Evaluación programada exitosamente.')
    }

    openEvaluacionModal.value = false
    evaluacionForm.value = {
      titulo: '',
      instrucciones: '',
      tipo_evaluacion: 'QUIZ',
      permite_archivo_estudiante: false,
      archivo_adjunto: null,
      puntaje_maximo: 20,
      duracion_minutos: 60,
      intentos_permitidos: 1,
      fecha_inicio: '',
      fecha_fin: '',
      estado: 'PUBLICADA'
    }
    await loadEvaluaciones()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar la evaluación.')
  } finally {
    submittingEvaluacion.value = false
  }
}

async function handleDeleteEvaluacion(id: number) {
  if (!confirm('¿Estás seguro de eliminar esta evaluación? Se borrarán sus preguntas asociadas.')) return
  try {
    await evaluacionesApi.remove(grupoId, id)
    await loadEvaluaciones()
  } catch {
    alert('Error al eliminar la evaluación.')
  }
}

async function handleDescargarAdjuntoDocente(evaluacion: Evaluacion) {
  try {
    const res = await evaluacionesApi.descargarAdjunto(evaluacion.id)
    const url = window.URL.createObjectURL(new Blob([res.data as BlobPart]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', evaluacion.archivo_nombre_original || `evaluacion_${evaluacion.id}.pdf`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch {
    alert('Error al descargar el archivo adjunto de la evaluación.')
  }
}

async function openGestionarPreguntas(evaluacion: Evaluacion) {
  selectedEvaluacion.value = evaluacion
  openPreguntasModal.value = true
  loadingPreguntas.value = true
  resetPreguntaForm()
  try {
    const res = await evaluacionesApi.get(grupoId, evaluacion.id)
    preguntasList.value = res.data.data.preguntas || []
  } catch {
    alert('Error al cargar preguntas de la evaluación.')
  } finally {
    loadingPreguntas.value = false
  }
}

function resetPreguntaForm() {
  nuevaPreguntaForm.value = {
    enunciado: '',
    tipo: 'ALTERNATIVA',
    puntaje: 5,
    opciones: [
      { texto: '', es_correcta: true },
      { texto: '', es_correcta: false },
      { texto: '', es_correcta: false },
      { texto: '', es_correcta: false },
    ]
  }
}

function agregarOpcionForm() {
  nuevaPreguntaForm.value.opciones.push({ texto: '', es_correcta: false })
}

function removerOpcionForm(idx: number) {
  if (nuevaPreguntaForm.value.opciones.length <= 2) {
    alert('Una pregunta debe tener al menos 2 opciones.')
    return
  }
  const wasCorrect = nuevaPreguntaForm.value.opciones[idx].es_correcta
  nuevaPreguntaForm.value.opciones.splice(idx, 1)
  if (wasCorrect && nuevaPreguntaForm.value.opciones.length > 0) {
    nuevaPreguntaForm.value.opciones[0].es_correcta = true
  }
}

function marcarOpcionCorrecta(idx: number) {
  nuevaPreguntaForm.value.opciones.forEach((opc, i) => {
    opc.es_correcta = (i === idx)
  })
}

function cambiarTipoPregunta() {
  if (nuevaPreguntaForm.value.tipo === 'VERDADERO_FALSO') {
    nuevaPreguntaForm.value.opciones = [
      { texto: 'Verdadero', es_correcta: true },
      { texto: 'Falso', es_correcta: false },
    ]
  } else {
    nuevaPreguntaForm.value.opciones = [
      { texto: '', es_correcta: true },
      { texto: '', es_correcta: false },
      { texto: '', es_correcta: false },
      { texto: '', es_correcta: false },
    ]
  }
}

async function handleGuardarPregunta() {
  if (!selectedEvaluacion.value) return
  if (!nuevaPreguntaForm.value.enunciado.trim()) {
    alert('Por favor ingrese el enunciado de la pregunta.')
    return
  }

  const opcionesVacias = nuevaPreguntaForm.value.opciones.some(o => !o.texto.trim())
  if (opcionesVacias) {
    alert('Por favor complete el texto de todas las opciones.')
    return
  }

  const tieneCorrecta = nuevaPreguntaForm.value.opciones.some(o => o.es_correcta)
  if (!tieneCorrecta) {
    alert('Debe seleccionar cuál de las opciones es la respuesta correcta.')
    return
  }

  guardandoPregunta.value = true
  try {
    await evaluacionesApi.agregarPregunta(selectedEvaluacion.value.id, nuevaPreguntaForm.value)
    // Refrescar preguntas
    const res = await evaluacionesApi.get(grupoId, selectedEvaluacion.value.id)
    preguntasList.value = res.data.data.preguntas || []
    resetPreguntaForm()
    await loadEvaluaciones()
    alert('Pregunta añadida al cuestionario correctamente.')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar la pregunta.')
  } finally {
    guardandoPregunta.value = false
  }
}

async function handleEliminarPregunta(preguntaId: number) {
  if (!selectedEvaluacion.value) return
  if (!confirm('¿Eliminar esta pregunta del cuestionario?')) return
  try {
    await evaluacionesApi.eliminarPregunta(selectedEvaluacion.value.id, preguntaId)
    const res = await evaluacionesApi.get(grupoId, selectedEvaluacion.value.id)
    preguntasList.value = res.data.data.preguntas || []
    await loadEvaluaciones()
  } catch {
    alert('Error al eliminar la pregunta.')
  }
}

const calificarIntentoForm = ref<Record<number, { nota: number; retroalimentacion: string; guardando: boolean }>>({})
const openRevisionDocenteModal = ref(false)
const selectedIntentoRevision = ref<IntentoEvaluacion | null>(null)
const revisionDocenteData = ref<Array<{
  id: number
  enunciado: string
  tipo: string
  puntaje: number
  puntaje_obtenido: number
  opcion_elegida?: number
  respuesta_texto?: string
  opciones: Array<{ id: number; texto: string; es_correcta: boolean }>
}>>([])
const loadingRevisionDocente = ref(false)

async function handleVerIntentos(evaluacion: Evaluacion) {
  selectedEvaluacion.value = evaluacion
  openIntentosModal.value = true
  loadingIntentos.value = true
  try {
    const res = await evaluacionesApi.get(grupoId, evaluacion.id)
    intentosList.value = res.data.data.intentos || []
    calificarIntentoForm.value = {}
    for (const it of intentosList.value) {
      calificarIntentoForm.value[it.id] = {
        nota: it.nota !== undefined && it.nota !== null ? Number(it.nota) : 0,
        retroalimentacion: it.retroalimentacion || '',
        guardando: false,
      }
    }
  } catch {
    alert('Error al cargar las entregas e intentos de los alumnos.')
  } finally {
    loadingIntentos.value = false
  }
}

async function handleGuardarNotaIntento(intentoId: number) {
  if (!selectedEvaluacion.value) return
  const form = calificarIntentoForm.value[intentoId]
  if (!form) return

  const maxPts = selectedEvaluacion.value.puntaje_maximo || 20
  if (form.nota < 0 || form.nota > maxPts) {
    alert(`La nota debe estar comprendida entre 0 y ${maxPts} puntos.`)
    return
  }

  form.guardando = true
  try {
    const res = await evaluacionesApi.calificarIntento(selectedEvaluacion.value.id, intentoId, {
      nota: form.nota,
      retroalimentacion: form.retroalimentacion || undefined,
    })
    const it = intentosList.value.find(i => i.id === intentoId)
    if (it) {
      it.nota = res.data.data.nota
      it.retroalimentacion = res.data.data.retroalimentacion
      it.estado = 'FINALIZADO'
    }
    alert('Calificación guardada y sincronizada con el acta oficial del curso.')
    await loadEvaluaciones()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al guardar la calificación.')
  } finally {
    form.guardando = false
  }
}

async function handleVerExamenAlumno(it: IntentoEvaluacion) {
  if (!selectedEvaluacion.value) return
  selectedIntentoRevision.value = it
  openRevisionDocenteModal.value = true
  loadingRevisionDocente.value = true
  try {
    const res = await evaluacionesApi.revision(selectedEvaluacion.value.id, it.id)
    revisionDocenteData.value = res.data.revision || []
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al cargar las respuestas del estudiante.')
  } finally {
    loadingRevisionDocente.value = false
  }
}

async function handleDescargarArchivoEstudiante(evalId: number, intentoId: number, nombreOriginal?: string) {
  try {
    const res = await evaluacionesApi.descargarArchivoEstudiante(evalId, intentoId)
    const url = window.URL.createObjectURL(new Blob([res.data as BlobPart]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', nombreOriginal || `entrega_alumno_${intentoId}.pdf`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch {
    alert('Error al descargar el archivo entregado por el estudiante.')
  }
}

// ─── Alumnos e Inclusiones ───
const matriculas = ref<Matricula[]>([])
const solicitudesInclusion = ref<SolicitudInclusion[]>([])
const loadingAlumnos = ref(false)

const openInclusionModal = ref(false)
const searchEstudiante = ref('')
const searchingEstudiantes = ref(false)
const estudiantesDisponibles = ref<Array<{ id: number; codigo_estudiante?: string; documento_numero?: string; nombre_completo: string; email?: string }>>([])
const selectedEstudianteId = ref<number | null>(null)
const inclusionMotivo = ref('')
const submittingInclusion = ref(false)

const solicitudesPendientesCount = computed(() => {
  return solicitudesInclusion.value.filter(s => s.estado === 'PENDIENTE').length
})

async function loadAlumnosEInclusiones() {
  loadingAlumnos.value = true
  try {
    const [mRes, sRes] = await Promise.allSettled([
      matriculasApi.list({ grupo_curso_id: grupoId, per_page: 100 }),
      solicitudesInclusionApi.list({ grupo_curso_id: grupoId }),
    ])
    if (mRes.status === 'fulfilled') {
      matriculas.value = mRes.value.data.data
    } else {
      console.error('Error al cargar matrículas', mRes.reason)
    }
    if (sRes.status === 'fulfilled') {
      solicitudesInclusion.value = sRes.value.data.data
    } else {
      console.error('Error al cargar solicitudes de inclusión', sRes.reason)
    }
  } catch (err) {
    console.error('Error al cargar alumnos e inclusiones', err)
  } finally {
    loadingAlumnos.value = false
  }
}

async function handleOpenInclusionModal() {
  openInclusionModal.value = true
  searchEstudiante.value = ''
  selectedEstudianteId.value = null
  inclusionMotivo.value = ''
  await handleBuscarEstudiantes()
}

async function handleBuscarEstudiantes() {
  searchingEstudiantes.value = true
  try {
    const res = await solicitudesInclusionApi.estudiantesDisponibles(grupoId, {
      q: searchEstudiante.value.trim() || undefined,
    })
    estudiantesDisponibles.value = res.data.data
    if (estudiantesDisponibles.value.length > 0 && !selectedEstudianteId.value) {
      selectedEstudianteId.value = estudiantesDisponibles.value[0].id
    }
  } catch (err) {
    console.error('Error buscando estudiantes disponibles', err)
  } finally {
    searchingEstudiantes.value = false
  }
}

async function handleEnviarSolicitudInclusion() {
  if (!selectedEstudianteId.value) {
    alert('Por favor selecciona un estudiante de la lista.')
    return
  }
  if (!inclusionMotivo.value.trim() || inclusionMotivo.value.trim().length < 5) {
    alert('Por favor redacta una justificación válida para el coordinador (mínimo 5 caracteres).')
    return
  }

  submittingInclusion.value = true
  try {
    await solicitudesInclusionApi.create({
      grupo_curso_id: grupoId,
      estudiante_id: selectedEstudianteId.value,
      motivo: inclusionMotivo.value.trim(),
    })
    openInclusionModal.value = false
    await loadAlumnosEInclusiones()
    alert('Solicitud enviada a Coordinación. El coordinador recibirá una notificación para evaluar la solicitud.')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al enviar la solicitud de inclusión.')
  } finally {
    submittingInclusion.value = false
  }
}

async function handleCancelarSolicitud(id: number) {
  if (!confirm('¿Deseas cancelar esta solicitud de inclusión?')) return
  try {
    await solicitudesInclusionApi.cancelar(id)
    await loadAlumnosEInclusiones()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    alert(e.response?.data?.message || 'Error al cancelar la solicitud.')
  }
}

function switchTab(tab: 'contenido' | 'asistencia' | 'evaluaciones' | 'alumnos') {
  activeTab.value = tab
  if (tab === 'asistencia') {
    loadAsistencias()
    loadResumenAsistencias()
  } else if (tab === 'evaluaciones') {
    loadEvaluaciones()
  } else if (tab === 'alumnos') {
    loadAlumnosEInclusiones()
  }
}

onMounted(() => {
  loadUnidades()
  solicitudesInclusionApi.list({ grupo_curso_id: grupoId }).then(res => {
    solicitudesInclusion.value = res.data.data
  }).catch(() => {})
})
</script>

<template>
  <div>
    <!-- Encabezado y Selector de Pestañas con PageHeader -->
    <PageHeader 
      eyebrow="Cátedra Activa" 
      title="Aula Virtual — Gestión Docente" 
      description="Gestione contenidos formativos, control de asistencias, evaluaciones online y nómina de estudiantes."
    >
      <template #actions>
        <RouterLink 
          :to="`/docente/grupos/${grupoId}/calificaciones`" 
          class="btn-secondary flex items-center gap-1.5 text-xs font-semibold py-2 px-3"
          title="Ver y registrar calificaciones del curso"
        >
          <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Calificaciones
        </RouterLink>
        <button 
          v-if="activeTab === 'contenido'" 
          @click="openCreateUnidadModal" 
          class="btn-primary flex items-center gap-1.5"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
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
          @click="openCreateEvaluacionModal" 
          class="btn-primary flex items-center gap-1.5"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Programar Evaluación
        </button>
        <button 
          v-if="activeTab === 'alumnos'" 
          @click="handleOpenInclusionModal" 
          class="btn-primary flex items-center gap-1.5"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          Solicitar Inclusión de Alumno
        </button>
      </template>
    </PageHeader>

    <!-- Pestañas de Navegación -->
    <div class="border-b border-slate-200 dark:border-slate-800 mb-6">
      <nav class="-mb-px flex space-x-8 overflow-x-auto custom-scrollbar">
        <button
          @click="switchTab('contenido')"
          :class="[
            activeTab === 'contenido'
              ? 'border-primary-600 dark:border-primary-400 text-primary-700 dark:text-primary-300 font-semibold'
              : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300 dark:hover:border-slate-700',
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
              ? 'border-primary-600 dark:border-primary-400 text-primary-700 dark:text-primary-300 font-semibold'
              : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300 dark:hover:border-slate-700',
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
              ? 'border-primary-600 dark:border-primary-400 text-primary-700 dark:text-primary-300 font-semibold'
              : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300 dark:hover:border-slate-700',
            'whitespace-nowrap pb-4 px-1 border-b-2 text-sm transition-colors flex items-center gap-2'
          ]"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Evaluaciones y Quizzes
        </button>
        <button
          @click="switchTab('alumnos')"
          :class="[
            activeTab === 'alumnos'
              ? 'border-primary-600 dark:border-primary-400 text-primary-700 dark:text-primary-300 font-semibold'
              : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300 dark:hover:border-slate-700',
            'whitespace-nowrap pb-4 px-1 border-b-2 text-sm transition-colors flex items-center gap-2'
          ]"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          Alumnos e Inclusiones
          <span v-if="solicitudesPendientesCount > 0" class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-950/70 text-amber-800 dark:text-amber-300 border border-amber-300/60 dark:border-amber-700/60">
            {{ solicitudesPendientesCount }}
          </span>
        </button>
      </nav>
    </div>

    <!-- ─── PESTAÑA 1: CONTENIDO Y MATERIALES ─── -->
    <div v-if="activeTab === 'contenido'">
      <SkeletonAula v-if="loading" />

      <div v-else class="space-y-5">
        <div 
          v-for="unidad in unidades" 
          :key="unidad.id" 
          :class="[
            'border rounded-xl bg-white dark:bg-slate-900 overflow-hidden shadow-xs transition-all',
            unidad.estado === 'INACTIVO' ? 'border-rose-200 dark:border-rose-900/50 bg-rose-50/10 dark:bg-rose-950/10' : 'border-slate-200 dark:border-slate-800'
          ]"
        >
          <!-- Cabecera de la Unidad -->
          <div class="bg-slate-50/90 dark:bg-slate-800/50 px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
            <div class="space-y-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-primary-700 dark:text-primary-300 bg-primary-100/70 dark:bg-primary-900/40 px-2 py-0.5 rounded border border-primary-200/50 dark:border-primary-800/50">
                  Unidad {{ unidad.orden }}
                </span>
                <span class="font-bold text-slate-900 dark:text-slate-100 text-base">{{ unidad.titulo }}</span>

                <!-- Fechas Pedagógicas de la Unidad -->
                <span 
                  v-if="unidad.fecha_inicio || unidad.fecha_fin" 
                  class="text-[11px] font-medium bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700/80 inline-flex items-center gap-1"
                  title="Periodo de dictado de la unidad"
                >
                  <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  {{ formatFechaCorta(unidad.fecha_inicio) || 'Inicio' }} – {{ formatFechaCorta(unidad.fecha_fin) || 'Fin' }}
                </span>

                <!-- Badge de Visibilidad para Estudiantes -->
                <span 
                  v-if="unidad.estado === 'INACTIVO'" 
                  class="text-[11px] font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 px-2 py-0.5 rounded-full inline-flex items-center gap-1 border border-rose-200/60 dark:border-rose-800/60"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-rose-600 dark:bg-rose-500"></span>
                  Oculta para alumnos
                </span>
                <span 
                  v-else-if="unidad.visible_desde && isFutureDate(unidad.visible_desde)" 
                  class="text-[11px] font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 px-2 py-0.5 rounded-full inline-flex items-center gap-1 border border-amber-200/60 dark:border-amber-800/60"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-600 dark:bg-amber-500"></span>
                  Visible desde {{ formatFechaHora(unidad.visible_desde) }}
                </span>
                <span 
                  v-else-if="unidad.visible_hasta && isPastDate(unidad.visible_hasta)" 
                  class="text-[11px] font-bold bg-slate-200 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 px-2 py-0.5 rounded-full inline-flex items-center gap-1 border border-slate-300/60 dark:border-slate-700/60"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-slate-500 dark:bg-slate-400"></span>
                  Cerrada (Hasta {{ formatFechaHora(unidad.visible_hasta) }})
                </span>
                <span 
                  v-else 
                  class="text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 px-2 py-0.5 rounded-full inline-flex items-center gap-1 border border-emerald-200/60 dark:border-emerald-800/60"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
                  Visible para alumnos
                </span>
              </div>

              <p class="text-xs text-slate-500 dark:text-slate-400">{{ unidad.descripcion || 'Sin descripción pedagógica registrada.' }}</p>
            </div>

            <!-- Acciones de Unidad -->
            <div class="flex items-center gap-1.5 shrink-0 self-end md:self-auto">
              <!-- Botón Toggle Visibilidad -->
              <button 
                @click="handleToggleUnidadVisibility(unidad)" 
                class="text-xs font-semibold px-2.5 py-1 rounded border transition-colors flex items-center gap-1"
                :class="unidad.estado === 'ACTIVO' ? 'border-gray-300 text-gray-700 hover:bg-gray-100' : 'border-emerald-300 text-emerald-700 bg-emerald-50 hover:bg-emerald-100'"
                :title="unidad.estado === 'ACTIVO' ? 'Ocultar esta unidad a los estudiantes' : 'Hacer visible esta unidad a los estudiantes'"
              >
                <svg v-if="unidad.estado === 'ACTIVO'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                </svg>
                <svg v-else class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                {{ unidad.estado === 'ACTIVO' ? 'Ocultar' : 'Mostrar' }}
              </button>

              <!-- Botón Editar Unidad -->
              <button 
                @click="openEditUnidadModal(unidad)" 
                class="btn-secondary text-xs py-1 px-2.5 flex items-center gap-1"
                title="Editar título, descripción y fechas de visibilidad"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Editar
              </button>

              <!-- Botón Añadir Material -->
              <button 
                @click="showMaterialModal(unidad.id)" 
                class="btn-primary text-xs py-1 px-2.5 flex items-center gap-1 shadow-2xs"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Material
              </button>

              <!-- Botón Eliminar Unidad -->
              <button 
                @click="handleDeleteUnidad(unidad)" 
                class="text-red-500 hover:text-red-700 text-xs p-1.5 rounded hover:bg-red-50"
                title="Eliminar unidad vacía"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Cuerpo: Lista de Materiales Didácticos -->
          <div class="p-4">
            <div v-if="unidad.materiales && unidad.materiales.length > 0" class="space-y-2.5">
              <div 
                v-for="mat in unidad.materiales" 
                :key="mat.id" 
                :class="[
                  'flex flex-col sm:flex-row items-start sm:items-center justify-between p-3 rounded-lg border transition-colors gap-3',
                  mat.estado === 'INACTIVO' ? 'bg-red-50/30 border-red-200' : 'bg-gray-50/70 border-gray-200 hover:bg-gray-50'
                ]"
              >
                <div class="flex items-start gap-3">
                  <div class="p-2 rounded-lg bg-white border border-gray-200 text-gray-600 shadow-2xs shrink-0 mt-0.5">
                    <svg v-if="mat.tipo === 'ENLACE'" class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                    <svg v-else class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>

                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="font-bold text-sm text-gray-900">{{ mat.titulo }}</p>
                      
                      <!-- Tipo de material -->
                      <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-blue-100 text-blue-800">
                        {{ mat.tipo || 'DOCUMENTO' }}
                      </span>

                      <!-- Estado / Visibilidad del material -->
                      <span 
                        v-if="mat.estado === 'INACTIVO'" 
                        class="text-[10px] font-bold bg-rose-100 text-rose-800 px-1.5 py-0.5 rounded"
                      >
                        Oculto para alumnos
                      </span>
                      <span 
                        v-else-if="mat.visible_desde && isFutureDate(mat.visible_desde)" 
                        class="text-[10px] font-bold bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded"
                        :title="`Se publicará el ${formatFechaHora(mat.visible_desde)}`"
                      >
                        Visible desde {{ formatFechaHora(mat.visible_desde) }}
                      </span>
                      <span 
                        v-else-if="mat.visible_hasta && isPastDate(mat.visible_hasta)" 
                        class="text-[10px] font-bold bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded"
                      >
                        Expirado ({{ formatFechaHora(mat.visible_hasta) }})
                      </span>
                      <span 
                        v-else 
                        class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded"
                      >
                        Visible para alumnos
                      </span>

                      <span 
                        v-if="mat.visible_hasta && !isPastDate(mat.visible_hasta)" 
                        class="text-[10px] bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded border border-slate-200"
                      >
                        Hasta {{ formatFechaHora(mat.visible_hasta) }}
                      </span>
                    </div>

                    <p class="text-xs text-gray-500 mt-1">{{ mat.descripcion || 'Material didáctico para clase' }}</p>
                  </div>
                </div>

                <!-- Botones del Material -->
                <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-auto">
                  <!-- Toggle Visibilidad Rápido -->
                  <button 
                    @click="handleToggleMaterialVisibility(unidad.id, mat)" 
                    class="text-xs p-1.5 rounded border transition-colors"
                    :class="mat.estado === 'ACTIVO' ? 'border-gray-200 text-gray-600 hover:bg-gray-100' : 'border-emerald-200 text-emerald-700 bg-emerald-50 hover:bg-emerald-100'"
                    :title="mat.estado === 'ACTIVO' ? 'Ocultar a estudiantes' : 'Mostrar a estudiantes'"
                  >
                    <svg v-if="mat.estado === 'ACTIVO'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                    <svg v-else class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>

                  <!-- Editar Material -->
                  <button 
                    @click="openEditMaterialModal(unidad.id, mat)" 
                    class="text-xs p-1.5 rounded border border-gray-200 text-gray-600 hover:text-gray-900 hover:bg-gray-100"
                    title="Editar material, reemplazar archivo o cambiar fechas"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>

                  <!-- Descargar o Abrir Enlace -->
                  <a 
                    v-if="mat.tipo === 'ENLACE' && mat.enlace_externo" 
                    :href="mat.enlace_externo" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    class="btn-secondary text-xs py-1 px-2.5 inline-flex items-center gap-1 font-semibold text-primary-700"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    Abrir Enlace
                  </a>
                  <button 
                    v-else 
                    @click="handleDownload(mat.id, mat.titulo)" 
                    class="btn-secondary text-xs py-1 px-2.5 inline-flex items-center gap-1 font-semibold text-primary-700"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar
                  </button>

                  <!-- Eliminar Material -->
                  <button 
                    @click="handleDeleteMaterial(unidad.id, mat.id)" 
                    class="text-red-500 hover:text-red-700 text-xs p-1.5 rounded hover:bg-red-50"
                    title="Eliminar material didáctico"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <div v-else class="text-xs text-gray-400 italic py-2 text-center bg-gray-50/50 rounded-lg border border-dashed border-gray-200">
              No hay materiales didácticos publicados en esta unidad. Haz clic en "Material" para adjuntar guías, lecturas o presentaciones.
            </div>
          </div>
        </div>

        <div v-if="unidades.length === 0" class="text-center py-12 text-gray-500 bg-white rounded-xl border border-dashed border-gray-300">
          <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
          <p class="font-medium text-gray-700">No hay unidades didácticas creadas en este curso</p>
          <p class="text-xs text-gray-400 mt-1">Organiza el sílabo en unidades pedagógicas con sus rangos de fechas y materiales.</p>
          <button @click="openCreateUnidadModal" class="btn-primary text-xs mt-4 inline-flex items-center gap-1.5">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Crear Primera Unidad
          </button>
        </div>
      </div>
    </div>

    <!-- ─── PESTAÑA 2: ASISTENCIA ─── -->
    <div v-else-if="activeTab === 'asistencia'" class="space-y-4">
      <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center gap-3">
          <label class="text-sm font-medium text-gray-700">Fecha de Asistencia:</label>
          <input 
            type="date" 
            v-model="fechaAsistencia" 
            @change="loadAsistencias" 
            class="input-field text-sm py-1.5"
          />
        </div>
        <div class="flex items-center gap-2">
          <button 
            @click="verResumenAsistencia = !verResumenAsistencia" 
            class="btn-secondary text-sm"
          >
            {{ verResumenAsistencia ? 'Ver Registro Diario' : 'Ver Resumen Acumulado' }}
          </button>
        </div>
      </div>

      <!-- Registro Diario -->
      <SkeletonTable v-if="loadingAsistencia" :columns="4" :rows="6" />
      <div v-else-if="!verResumenAsistencia" class="bg-white rounded-lg border border-gray-200 overflow-hidden shadow-sm dark:bg-slate-900 dark:border-slate-800">
        <div class="overflow-x-auto">
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
              <tr v-for="alumno in asistencias" :key="alumno.estudiante_id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ alumno.codigo || '—' }}</td>
                <td class="px-4 py-3 font-medium text-gray-900">{{ alumno.nombre_completo }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="inline-flex rounded-md shadow-xs" role="group">
                    <button 
                      type="button" 
                      @click="alumno.estado_asistencia = 'PRESENTE'"
                      :class="[
                        alumno.estado_asistencia === 'PRESENTE' ? 'bg-green-600 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-50',
                        'px-2.5 py-1 text-xs border border-gray-300 rounded-l-md transition-colors'
                      ]"
                    >
                      P
                    </button>
                    <button 
                      type="button" 
                      @click="alumno.estado_asistencia = 'TARDANZA'"
                      :class="[
                        alumno.estado_asistencia === 'TARDANZA' ? 'bg-amber-500 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-50',
                        'px-2.5 py-1 text-xs border-t border-b border-r border-gray-300 transition-colors'
                      ]"
                    >
                      T
                    </button>
                    <button 
                      type="button" 
                      @click="alumno.estado_asistencia = 'AUSENTE'"
                      :class="[
                        alumno.estado_asistencia === 'AUSENTE' ? 'bg-red-600 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-50',
                        'px-2.5 py-1 text-xs border-t border-b border-gray-300 transition-colors'
                      ]"
                    >
                      F
                    </button>
                    <button 
                      type="button" 
                      @click="alumno.estado_asistencia = 'JUSTIFICADO'"
                      :class="[
                        alumno.estado_asistencia === 'JUSTIFICADO' ? 'bg-blue-600 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-50',
                        'px-2.5 py-1 text-xs border border-gray-300 rounded-r-md transition-colors'
                      ]"
                    >
                      J
                    </button>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <input 
                    v-model="alumno.observacion" 
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

    <!-- ─── PESTAÑA 3: EVALUACIONES Y QUIZZES ─── -->
    <div v-else-if="activeTab === 'evaluaciones'" class="space-y-4">
      <SkeletonList v-if="loadingEvaluaciones" :items="3" :has-avatar="false" />

      <div v-else class="space-y-4">
        <div 
          v-for="evaluacion in evaluaciones" 
          :key="evaluacion.id" 
          class="border border-slate-200 dark:border-slate-800 rounded-xl bg-white dark:bg-slate-900 p-5 shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:border-slate-300 dark:hover:border-slate-700 transition-all"
        >
          <div class="space-y-2 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="font-bold text-slate-900 dark:text-slate-100 text-base">{{ evaluacion.titulo }}</h3>
              <span 
                v-if="evaluacion.estado === 'PUBLICADA'"
                class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 flex items-center gap-1 border border-emerald-200/60 dark:border-emerald-800/60"
              >
                <svg class="w-3 h-3 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                PUBLICADA
              </span>
              <span 
                v-else
                class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 flex items-center gap-1"
              >
                <svg class="w-3 h-3 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                </svg>
                Oculta para alumnos
              </span>
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-primary-100 dark:bg-primary-950/60 text-primary-800 dark:text-primary-300 border border-primary-200/60 dark:border-primary-800/60">
                Modalidad: {{ evaluacion.tipo_evaluacion || 'QUIZ' }}
              </span>
              <span v-if="evaluacion.tiene_archivo_adjunto" class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/60 flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
                Archivo Adjunto
              </span>
              <span v-if="evaluacion.permite_archivo_estudiante" class="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60">
                Entrega de Archivo Permitida
              </span>
            </div>

            <p class="text-xs text-slate-600 dark:text-slate-400">{{ evaluacion.instrucciones || 'Sin instrucciones adicionales' }}</p>

            <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap gap-4 pt-1">
              <span class="inline-flex items-center gap-1 font-medium">
                <svg class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Duración: {{ evaluacion.duracion_minutos || 'Ilimitada' }} min
              </span>
              <span class="inline-flex items-center gap-1 font-medium">
                <svg class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Puntaje Máx: {{ evaluacion.puntaje_maximo }} pts
              </span>
              <span class="inline-flex items-center gap-1 font-medium">
                <svg class="h-3.5 w-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Intentos: {{ evaluacion.intentos_permitidos }}
              </span>
              <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 font-semibold">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Cierre: {{ new Date(evaluacion.fecha_fin).toLocaleString() }}
              </span>
            </div>
          </div>

          <!-- Acciones de la Evaluación -->
          <div class="flex flex-wrap items-center justify-end gap-2 xl:shrink-0">
            <!-- Botón Ocultar / Publicar -->
            <button
              @click="handleToggleEvaluacionVisibility(evaluacion)"
              class="text-xs font-semibold px-2.5 py-1.5 rounded border transition-colors flex items-center gap-1 shadow-2xs"
              :class="evaluacion.estado === 'PUBLICADA' 
                ? 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700' 
                : 'border-emerald-300 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/40'"
              :title="evaluacion.estado === 'PUBLICADA' 
                ? 'Ocultar esta evaluación a los estudiantes' 
                : 'Publicar y hacer visible esta evaluación a los estudiantes'"
            >
              <svg v-if="evaluacion.estado === 'PUBLICADA'" class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
              </svg>
              <svg v-else class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              {{ evaluacion.estado === 'PUBLICADA' ? 'Ocultar' : 'Publicar' }}
            </button>

            <!-- Botón Editar Evaluación -->
            <button
              @click="openEditEvaluacionModal(evaluacion)"
              class="btn-secondary text-xs py-1.5 px-2.5 flex items-center gap-1"
              title="Editar parámetros y visibilidad de la evaluación"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Editar
            </button>

            <!-- Descargar archivo docente si existe -->
            <button
              v-if="evaluacion.tiene_archivo_adjunto"
              @click="handleDescargarAdjuntoDocente(evaluacion)"
              class="btn-secondary text-xs py-1.5 px-2.5 flex items-center gap-1 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/60 hover:bg-blue-100 dark:hover:bg-blue-900/40 border-blue-200 dark:border-blue-800"
              title="Descargar consignas o examen adjunto"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              Descargar Archivo
            </button>

            <!-- Gestionar Preguntas / Quiz -->
            <button
              @click="openGestionarPreguntas(evaluacion)"
              class="btn-primary text-xs py-1.5 px-3 flex items-center gap-1.5"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
              <span>Preguntas ({{ evaluacion.preguntas_count || 0 }})</span>
            </button>

            <!-- Ver Entregas / Intentos -->
            <button
              @click="handleVerIntentos(evaluacion)"
              class="btn-secondary text-xs py-1.5 px-2.5 flex items-center gap-1"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              Entregas
            </button>

            <!-- Eliminar Evaluación -->
            <button
              @click="handleDeleteEvaluacion(evaluacion.id)"
              class="text-red-500 hover:text-red-700 p-1.5 rounded hover:bg-red-50"
              title="Eliminar evaluación"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>

        <div v-if="evaluaciones.length === 0" class="text-center py-12 text-gray-500 bg-white rounded-xl border border-dashed border-gray-300">
          <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p class="font-medium text-gray-700">No hay evaluaciones registradas en este grupo</p>
          <p class="text-xs text-gray-400 mt-1">Crea cuestionarios interactivos o evaluaciones con entrega de archivos.</p>
          <button @click="openCreateEvaluacionModal" class="btn-primary text-xs mt-4 inline-flex items-center gap-1.5">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Programar Evaluación
          </button>
        </div>
      </div>
    </div>

    <!-- ─── PESTAÑA 4: ALUMNOS E INCLUSIONES ─── -->
    <div v-if="activeTab === 'alumnos'" class="space-y-8">
      <!-- Sección 1: Nómina Oficial de Estudiantes Matriculados -->
      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between sm:items-center gap-3 bg-slate-50/50">
          <div>
            <h3 class="font-bold text-base text-slate-900 font-headline flex items-center gap-2">
              <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              Nómina de Alumnos Matriculados ({{ matriculas.length }})
            </h3>
            <p class="text-xs text-slate-500">Listado de estudiantes con acceso vigente a las sesiones y actividades de este grupo.</p>
          </div>
        </div>

        <SkeletonTable v-if="loadingAlumnos" :columns="6" :rows="6" />

        <div v-else-if="matriculas.length === 0" class="p-8 text-center text-slate-500">
          <p class="text-sm">No hay estudiantes matriculados en este grupo aún.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-200">
              <tr>
                <th class="py-3 px-4">#</th>
                <th class="py-3 px-4">Código</th>
                <th class="py-3 px-4">Estudiante</th>
                <th class="py-3 px-4">Documento</th>
                <th class="py-3 px-4">Fecha Matrícula</th>
                <th class="py-3 px-4">Origen</th>
                <th class="py-3 px-4">Estado</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(m, idx) in matriculas" :key="m.id" class="hover:bg-slate-50/60 transition-colors">
                <td class="py-3 px-4 text-slate-400 font-mono">{{ idx + 1 }}</td>
                <td class="py-3 px-4 font-mono font-semibold text-slate-700">{{ m.estudiante?.codigo_estudiante || '—' }}</td>
                <td class="py-3 px-4">
                  <div class="font-bold text-slate-900">
                    {{ m.estudiante?.usuario?.apellido_paterno }} {{ m.estudiante?.usuario?.apellido_materno }}, {{ m.estudiante?.usuario?.nombres }}
                  </div>
                  <div class="text-[11px] text-slate-400">{{ m.estudiante?.usuario?.email }}</div>
                </td>
                <td class="py-3 px-4 text-slate-600">
                  {{ m.estudiante?.usuario?.documento_tipo || 'DNI' }}: {{ m.estudiante?.usuario?.documento_numero || '—' }}
                </td>
                <td class="py-3 px-4 text-slate-600">{{ m.fecha_matricula }}</td>
                <td class="py-3 px-4">
                  <span class="text-[11px] px-2 py-0.5 rounded-md font-medium" :class="m.origen === 'INCLUSION_DOCENTE' ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-700'">
                    {{ m.origen === 'INCLUSION_DOCENTE' ? 'Inclusión Docente' : (m.origen || 'Ordinaria') }}
                  </span>
                </td>
                <td class="py-3 px-4">
                  <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800">
                    {{ m.estado }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Sección 2: Solicitudes de Inclusión Enviadas al Coordinador -->
      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between sm:items-center gap-3 bg-slate-50/50">
          <div>
            <h3 class="font-bold text-base text-slate-900 font-headline flex items-center gap-2">
              <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Solicitudes de Inclusión Enviadas a Coordinación
            </h3>
            <p class="text-xs text-slate-500">Historial y estado de las peticiones para agregar alumnos a este curso.</p>
          </div>
        </div>

        <div v-if="solicitudesInclusion.length === 0" class="p-8 text-center text-slate-500 text-xs">
          No hay solicitudes de inclusión registradas para este grupo.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-200">
              <tr>
                <th class="py-3 px-4">Estudiante Solicitado</th>
                <th class="py-3 px-4">Fecha Solicitud</th>
                <th class="py-3 px-4">Motivo del Docente</th>
                <th class="py-3 px-4">Estado</th>
                <th class="py-3 px-4">Respuesta de Coordinación</th>
                <th class="py-3 px-4 text-right">Acción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="s in solicitudesInclusion" :key="s.id" class="hover:bg-slate-50/60 transition-colors">
                <td class="py-3 px-4">
                  <div class="font-bold text-slate-900">
                    {{ s.estudiante?.usuario?.apellido_paterno }} {{ s.estudiante?.usuario?.apellido_materno }}, {{ s.estudiante?.usuario?.nombres }}
                  </div>
                  <div class="text-[11px] text-slate-400">
                    Cód: {{ s.estudiante?.codigo_estudiante || '—' }} | DNI: {{ s.estudiante?.usuario?.documento_numero || '—' }}
                  </div>
                </td>
                <td class="py-3 px-4 text-slate-600">
                  {{ new Date(s.created_at).toLocaleDateString() }}
                </td>
                <td class="py-3 px-4 text-slate-700 max-w-xs">
                  <p class="truncate" :title="s.motivo">"{{ s.motivo }}"</p>
                </td>
                <td class="py-3 px-4">
                  <span
                    :class="[
                      'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold',
                      s.estado === 'PENDIENTE' ? 'bg-amber-100 text-amber-800' :
                      s.estado === 'APROBADO' ? 'bg-emerald-100 text-emerald-800' :
                      s.estado === 'RECHAZADO' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'
                    ]"
                  >
                    {{ s.estado }}
                  </span>
                </td>
                <td class="py-3 px-4 text-slate-600 max-w-xs">
                  <div v-if="s.estado === 'APROBADO'" class="text-emerald-700 flex items-center gap-1 font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Aprobado por {{ s.coordinador?.nombres || 'Coordinación' }}
                  </div>
                  <div v-else-if="s.estado === 'RECHAZADO'" class="text-rose-600">
                    <p class="font-medium">Rechazado:</p>
                    <p class="text-[11px] text-slate-600 truncate" :title="s.observaciones_coordinador || 'Sin observaciones'">
                      {{ s.observaciones_coordinador || 'Sin observaciones' }}
                    </p>
                  </div>
                  <span v-else class="text-amber-600 text-xs italic">En espera de revisión</span>
                </td>
                <td class="py-3 px-4 text-right">
                  <button
                    v-if="s.estado === 'PENDIENTE'"
                    @click="handleCancelarSolicitud(s.id)"
                    class="text-xs text-rose-600 hover:text-rose-800 font-medium hover:underline"
                  >
                    Cancelar
                  </button>
                  <span v-else class="text-slate-400 text-xs">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Unidad (Crear y Editar) -->
    <BaseModal 
      :open="openUnidadModal" 
      @close="openUnidadModal = false" 
      :title="editingUnidadId ? 'Editar Unidad Didáctica' : 'Nueva Unidad Didáctica'"
    >
      <form @submit.prevent="handleSaveUnidad" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Título de la Unidad *</label>
          <input 
            v-model="unidadForm.titulo" 
            type="text" 
            placeholder="Ej. Unidad I: Fundamentos y Conceptos Básicos" 
            class="input-field mt-1" 
            required 
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Descripción o Logro de Aprendizaje</label>
          <textarea 
            v-model="unidadForm.descripcion" 
            class="input-field mt-1" 
            rows="2" 
            placeholder="Competencias o contenidos que comprende esta unidad..."
          ></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Orden correlativo *</label>
            <input 
              v-model.number="unidadForm.orden" 
              type="number" 
              min="1" 
              class="input-field mt-1" 
              required 
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Fecha Inicio Dictado</label>
            <input 
              v-model="unidadForm.fecha_inicio" 
              type="date" 
              class="input-field mt-1 text-xs" 
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Fecha Fin Dictado</label>
            <input 
              v-model="unidadForm.fecha_fin" 
              type="date" 
              class="input-field mt-1 text-xs" 
            />
          </div>
        </div>

        <!-- Visibilidad para Alumnos -->
        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-bold text-slate-800">Publicación y Visibilidad para los Estudiantes</p>
              <p class="text-[11px] text-slate-500">Configura si los estudiantes pueden ver esta unidad y en qué rango de fechas.</p>
            </div>
            <select 
              v-model="unidadForm.estado" 
              class="input-field text-xs py-1 px-2.5 w-auto font-semibold"
              :class="unidadForm.estado === 'ACTIVO' ? 'text-emerald-700 bg-emerald-50 border-emerald-300' : 'text-rose-700 bg-rose-50 border-rose-300'"
            >
              <option value="ACTIVO">✓ Visible / Publicado</option>
              <option value="INACTIVO">🚫 Oculto para alumnos</option>
            </select>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <div>
              <label class="block text-[11px] font-semibold text-gray-600">Visible a partir de (Fecha y Hora)</label>
              <input 
                v-model="unidadForm.visible_desde" 
                type="datetime-local" 
                class="input-field mt-1 text-xs" 
              />
              <span class="text-[10px] text-gray-400">Dejar en blanco para visibilidad inmediata.</span>
            </div>
            <div>
              <label class="block text-[11px] font-semibold text-gray-600">Visible hasta (Fecha y Hora)</label>
              <input 
                v-model="unidadForm.visible_hasta" 
                type="datetime-local" 
                class="input-field mt-1 text-xs" 
              />
              <span class="text-[10px] text-gray-400">Dejar en blanco para visibilidad permanente.</span>
            </div>
          </div>
        </div>

        <div class="flex justify-end space-x-3 pt-2">
          <button 
            type="button" 
            @click="openUnidadModal = false" 
            class="btn-secondary text-sm" 
            :disabled="submittingUnidad"
          >
            Cancelar
          </button>
          <button 
            type="submit" 
            class="btn-primary text-sm shadow-xs" 
            :disabled="submittingUnidad"
          >
            {{ submittingUnidad ? 'Guardando...' : (editingUnidadId ? 'Guardar Cambios' : 'Crear Unidad') }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Modal Material Didáctico (Subir y Editar) -->
    <BaseModal 
      :open="openMaterialModal" 
      @close="openMaterialModal = false" 
      :title="editingMaterialId ? 'Editar Material Didáctico' : 'Subir Material Didáctico'"
    >
      <form @submit.prevent="handleSaveMaterial" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Título del Material *</label>
          <input 
            v-model="materialForm.titulo" 
            type="text" 
            placeholder="Ej. Diapositivas Sesión 1 / Guía Práctica de Laboratorio" 
            class="input-field mt-1" 
            required 
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Tipo de Recurso *</label>
            <select v-model="materialForm.tipo" class="input-field mt-1 text-xs font-medium">
              <option value="DOCUMENTO">Archivo Local (PDF, Word, Excel, PPT, Zip)</option>
              <option value="ENLACE">Enlace Externo / URL (YouTube, Drive, Web)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Estado de Publicación</label>
            <select 
              v-model="materialForm.estado" 
              class="input-field mt-1 text-xs font-semibold"
              :class="materialForm.estado === 'ACTIVO' ? 'text-emerald-700 bg-emerald-50 border-emerald-300' : 'text-rose-700 bg-rose-50 border-rose-300'"
            >
              <option value="ACTIVO">✓ Visible para alumnos</option>
              <option value="INACTIVO">🚫 Oculto para alumnos</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Descripción o Instrucciones</label>
          <textarea 
            v-model="materialForm.descripcion" 
            class="input-field mt-1" 
            rows="2" 
            placeholder="Indicaciones para los alumnos sobre este recurso didáctico..."
          ></textarea>
        </div>

        <!-- Si es enlace externo -->
        <div v-if="materialForm.tipo === 'ENLACE'">
          <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">URL del Enlace Externo *</label>
          <input 
            v-model="materialForm.enlace_externo" 
            type="url" 
            placeholder="https://drive.google.com/... o https://youtube.com/..." 
            class="input-field mt-1 text-xs" 
            required 
          />
        </div>

        <!-- Si es archivo -->
        <div v-else class="space-y-1">
          <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
            {{ editingMaterialId ? 'Reemplazar Archivo (Opcional)' : 'Archivo del Material *' }}
          </label>
          <FileDropzone
            v-model="materialForm.archivo"
            label="Selecciona o arrastra el archivo"
            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.mp4,.zip"
            :help="editingMaterialId ? 'Si no seleccionas un archivo nuevo, se mantendrá el que ya está subido.' : 'Formatos admitidos: PDF, Office, imágenes, video MP4 o ZIP (máx. 20MB).'"
            :max-size-mb="20"
            :required="!editingMaterialId"
          />
        </div>

        <!-- Rango de Visibilidad para Estudiantes -->
        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-slate-800">
              Fechas de Disponibilidad para el Alumno
            </label>
            <span class="text-[10px] text-slate-500">Programación automática</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-semibold text-gray-600">Visible a partir de</label>
              <input 
                v-model="materialForm.visible_desde" 
                type="datetime-local" 
                class="input-field mt-1 text-xs" 
              />
              <span class="text-[10px] text-gray-400">Dejar vacío para publicar inmediatamente.</span>
            </div>
            <div>
              <label class="block text-[11px] font-semibold text-gray-600">Visible hasta</label>
              <input 
                v-model="materialForm.visible_hasta" 
                type="datetime-local" 
                class="input-field mt-1 text-xs" 
              />
              <span class="text-[10px] text-gray-400">Dejar vacío para visibilidad permanente.</span>
            </div>
          </div>
        </div>

        <div class="flex justify-end space-x-3 pt-3">
          <button 
            type="button" 
            @click="openMaterialModal = false" 
            class="btn-secondary text-sm" 
            :disabled="uploadingMaterial"
          >
            Cancelar
          </button>
          <button 
            type="submit" 
            class="btn-primary text-sm shadow-xs" 
            :disabled="uploadingMaterial || (materialForm.tipo !== 'ENLACE' && !editingMaterialId && !materialForm.archivo)"
          >
            {{ uploadingMaterial ? 'Guardando...' : (editingMaterialId ? 'Guardar Cambios' : 'Subir Material') }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Modal Programar / Editar Evaluación -->
    <BaseModal :open="openEvaluacionModal" @close="openEvaluacionModal = false" :title="isEditingEvaluacion ? 'Editar Evaluación' : 'Programar Nueva Evaluación'">
      <form @submit.prevent="handleSaveEvaluacion" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Título de la Evaluación *</label>
          <input v-model="evaluacionForm.titulo" type="text" placeholder="Ej. Examen Parcial / Cuestionario Teórico" class="input-field mt-1" required />
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Estado y Visibilidad para Alumnos *</label>
          <select v-model="evaluacionForm.estado" class="input-field mt-1 text-sm font-medium">
            <option value="PUBLICADA">Publicada — Visible para los estudiantes dentro del rango de fechas</option>
            <option value="OCULTA">Oculta para alumnos — Modo borrador (invisible para estudiantes)</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Modalidad de Evaluación *</label>
          <select v-model="evaluacionForm.tipo_evaluacion" class="input-field mt-1 text-sm font-medium">
            <option value="QUIZ">Quiz / Cuestionario Online (Preguntas de alternativas y opciones tipo Google Form)</option>
            <option value="ARCHIVO">Evaluación con Archivo (Docente sube examen descargable y Alumno entrega su archivo resuelto)</option>
            <option value="MIXTO">Mixto (Docente adjunta archivo de consignas + Preguntas online + Entrega)</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">Instrucciones o Indicaciones</label>
          <textarea v-model="evaluacionForm.instrucciones" class="input-field mt-1" rows="2" placeholder="Indicaciones para los alumnos sobre el desarrollo de la prueba..."></textarea>
        </div>

        <!-- Archivo Adjunto del Examen (Docente) -->
        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-slate-800">
              Archivo del Examen o Consignas (Opcional si es Quiz, Recomendado para Examen con Archivo)
            </label>
            <span class="text-[11px] text-slate-500">PDF, Word, Excel, ZIP</span>
          </div>
          <FileDropzone
            v-model="evaluacionForm.archivo_adjunto"
            label="Subir examen en archivo"
            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.rar"
            help="Sube las preguntas o consignas en documento formal (máx. 25MB)."
            :max-size-mb="25"
          />
        </div>

        <!-- Checkbox para permitir entrega de archivo por el estudiante -->
        <div class="flex items-center gap-2 p-2 bg-indigo-50 border border-indigo-200 rounded-lg">
          <input
            id="permite_archivo"
            type="checkbox"
            v-model="evaluacionForm.permite_archivo_estudiante"
            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
          />
          <label for="permite_archivo" class="text-xs font-medium text-indigo-900 cursor-pointer">
            Permitir que los estudiantes entreguen un archivo de solución resuelto (sujeto a fecha límite estricta)
          </label>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-700">Puntaje Máximo</label>
            <input v-model.number="evaluacionForm.puntaje_maximo" type="number" step="0.5" class="input-field mt-1" required />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700">Duración (min)</label>
            <input v-model.number="evaluacionForm.duracion_minutos" type="number" class="input-field mt-1" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700">Intentos</label>
            <input v-model.number="evaluacionForm.intentos_permitidos" type="number" min="1" max="10" class="input-field mt-1" required />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-700">Fecha y Hora de Inicio *</label>
            <input v-model="evaluacionForm.fecha_inicio" type="datetime-local" class="input-field mt-1 text-xs" required />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700">Fecha y Hora de Fin (Cierre) *</label>
            <input v-model="evaluacionForm.fecha_fin" type="datetime-local" class="input-field mt-1 text-xs" required />
          </div>
        </div>

        <div class="flex justify-end space-x-3 pt-3">
          <button type="button" @click="openEvaluacionModal = false" class="btn-secondary text-sm" :disabled="submittingEvaluacion">Cancelar</button>
          <button type="submit" class="btn-primary text-sm" :disabled="submittingEvaluacion">
            {{ submittingEvaluacion ? 'Guardando...' : (isEditingEvaluacion ? 'Guardar Cambios' : 'Crear Evaluación') }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- Modal Gestionar Preguntas / Quiz (tipo Google Form) -->
    <BaseModal :open="openPreguntasModal" @close="openPreguntasModal = false" :title="`Cuestionario Quiz: ${selectedEvaluacion?.titulo || ''}`">
      <div class="space-y-6">
        <!-- Información de cabecera -->
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-start gap-2">
          <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <strong>Diseño de Cuestionario (Tipo Google Form):</strong>
            Las preguntas se mostrarán interactivamente al estudiante.
            <em>Por política de seguridad académica, las respuestas correctas permanecerán ocultas para los alumnos hasta que concluya el horario oficial de la evaluación.</em>
          </div>
        </div>

        <!-- Lista de Preguntas Existentes -->
        <div>
          <h4 class="text-sm font-bold text-gray-900 mb-2 flex items-center justify-between">
            <span>Preguntas registradas ({{ preguntasList.length }})</span>
            <span class="text-xs text-gray-500 font-normal">
              Total puntos configurados: {{ preguntasList.reduce((acc, p) => acc + Number(p.puntaje), 0) }} / {{ selectedEvaluacion?.puntaje_maximo }} pts
            </span>
          </h4>

          <div v-if="loadingPreguntas" class="flex justify-center py-6">
            <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
          </div>

          <div v-else-if="preguntasList.length === 0" class="text-xs text-gray-400 p-4 border border-dashed rounded-lg text-center bg-gray-50">
            Aún no has añadido preguntas a este quiz. Usa el formulario de abajo para agregar preguntas de alternativas o Verdadero/Falso.
          </div>

          <div v-else class="space-y-3 max-h-60 overflow-y-auto pr-1">
            <div
              v-for="(p, pIdx) in preguntasList"
              :key="p.id"
              class="p-3 bg-white border border-gray-200 rounded-lg shadow-2xs space-y-2"
            >
              <div class="flex items-start justify-between gap-2">
                <div class="flex items-start gap-2">
                  <span class="font-bold text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded">
                    #{{ pIdx + 1 }}
                  </span>
                  <div>
                    <p class="text-xs font-semibold text-gray-900">{{ p.enunciado }}</p>
                    <span class="text-[10px] text-gray-400 font-medium uppercase">{{ p.tipo }} · {{ p.puntaje }} pts</span>
                  </div>
                </div>
                <button
                  @click="handleEliminarPregunta(p.id)"
                  class="text-red-500 hover:text-red-700 text-xs p-1"
                  title="Eliminar pregunta"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>

              <!-- Opciones de la pregunta -->
              <div class="pl-6 space-y-1">
                <div
                  v-for="(o, oIdx) in p.opciones"
                  :key="o.id || oIdx"
                  class="flex items-center gap-2 text-xs"
                >
                  <span
                    :class="[
                      'w-3.5 h-3.5 rounded-full flex items-center justify-center text-[9px] font-bold',
                      o.es_correcta ? 'bg-emerald-500 text-white' : 'border border-gray-300 bg-gray-100 text-gray-500'
                    ]"
                  >
                    {{ o.es_correcta ? '✓' : '' }}
                  </span>
                  <span :class="o.es_correcta ? 'font-semibold text-emerald-800' : 'text-gray-600'">
                    {{ o.texto }}
                  </span>
                  <span v-if="o.es_correcta" class="text-[10px] text-emerald-600 font-bold bg-emerald-50 px-1 rounded">
                    Correcta
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <hr class="border-gray-200" />

        <!-- Formulario para Agregar Nueva Pregunta -->
        <div class="bg-gray-50/70 p-4 rounded-xl border border-gray-200 space-y-3">
          <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-1.5">
            <svg class="w-4 h-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Agregar Nueva Pregunta al Quiz
          </h4>

          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-2">
              <label class="block text-xs font-semibold text-gray-700">Tipo de Pregunta</label>
              <select v-model="nuevaPreguntaForm.tipo" @change="cambiarTipoPregunta" class="input-field text-xs mt-1">
                <option value="ALTERNATIVA">Opción Múltiple (Alternativas)</option>
                <option value="VERDADERO_FALSO">Verdadero / Falso</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700">Puntaje</label>
              <input v-model.number="nuevaPreguntaForm.puntaje" type="number" step="0.5" min="0.5" class="input-field text-xs mt-1" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700">Enunciado de la Pregunta *</label>
            <textarea
              v-model="nuevaPreguntaForm.enunciado"
              rows="2"
              placeholder="Escribe aquí la pregunta o problema..."
              class="input-field text-xs mt-1"
            ></textarea>
          </div>

          <!-- Opciones con selección de respuesta correcta -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-semibold text-gray-700">
                Opciones de Respuesta (Marca con el círculo la correcta) *
              </label>
              <button
                v-if="nuevaPreguntaForm.tipo === 'ALTERNATIVA'"
                type="button"
                @click="agregarOpcionForm"
                class="text-xs text-primary-600 hover:text-primary-800 font-semibold inline-flex items-center gap-1"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Añadir Opción
              </button>
            </div>

            <div
              v-for="(opc, idx) in nuevaPreguntaForm.opciones"
              :key="idx"
              class="flex items-center gap-2"
            >
              <input
                type="radio"
                name="correcta_radio"
                :checked="opc.es_correcta"
                @change="marcarOpcionCorrecta(idx)"
                class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                title="Marcar como respuesta correcta"
              />
              <input
                v-model="opc.texto"
                type="text"
                :placeholder="`Opción ${idx + 1}...`"
                class="input-field text-xs flex-1"
                :class="opc.es_correcta ? 'border-emerald-400 bg-emerald-50/30' : ''"
              />
              <button
                v-if="nuevaPreguntaForm.tipo === 'ALTERNATIVA' && nuevaPreguntaForm.opciones.length > 2"
                type="button"
                @click="removerOpcionForm(idx)"
                class="text-gray-400 hover:text-red-500 text-sm px-1"
                title="Quitar opción"
              >
                ✕
              </button>
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <button
              type="button"
              @click="handleGuardarPregunta"
              :disabled="guardandoPregunta"
              class="btn-primary text-xs py-2 px-4"
            >
              {{ guardandoPregunta ? 'Guardando pregunta...' : 'Guardar Pregunta en Quiz' }}
            </button>
          </div>
        </div>
      </div>
    </BaseModal>

    <!-- Modal Ver Intentos, Entregas y Calificación de Alumnos -->
    <BaseModal 
      :open="openIntentosModal" 
      @close="openIntentosModal = false" 
      :title="`Entregas y Calificación: ${selectedEvaluacion?.titulo || ''}`"
      size="xl"
    >
      <div class="space-y-4">
        <!-- Resumen de Evaluación -->
        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex flex-wrap items-center justify-between gap-3 text-xs">
          <div>
            <span class="font-bold text-slate-800 text-sm block">{{ selectedEvaluacion?.titulo }}</span>
            <span class="text-slate-500">
              Modalidad: <strong>{{ selectedEvaluacion?.tipo_evaluacion }}</strong> · 
              Puntaje Máximo: <strong>{{ selectedEvaluacion?.puntaje_maximo }} pts</strong>
            </span>
          </div>
          <div class="flex items-center gap-2">
            <span class="px-2 py-1 rounded bg-blue-50 text-blue-800 font-bold border border-blue-200">
              Total Entregas: {{ intentosList.length }}
            </span>
          </div>
        </div>

        <div v-if="loadingIntentos" class="flex justify-center py-8">
          <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
        </div>

        <div v-else-if="intentosList.length === 0" class="text-center py-8 text-gray-500 text-xs bg-gray-50 rounded-xl border border-dashed border-gray-200">
          Aún ningún estudiante ha rendido o entregado esta evaluación.
        </div>

        <div v-else class="space-y-3 max-h-[65vh] overflow-y-auto pr-1">
          <div 
            v-for="it in intentosList" 
            :key="it.id" 
            class="p-4 bg-white border border-gray-200 rounded-xl shadow-2xs space-y-3 hover:border-gray-300 transition-all"
          >
            <!-- Cabecera del Intento -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-2.5">
              <div>
                <div class="font-bold text-sm text-gray-900 flex items-center gap-2">
                  <span>{{ it.estudiante?.usuario?.apellido_paterno }} {{ it.estudiante?.usuario?.apellido_materno }}, {{ it.estudiante?.usuario?.nombres }}</span>
                  <span class="text-[10px] font-normal text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                    DNI: {{ it.estudiante?.usuario?.documento_numero || it.estudiante?.codigo_estudiante || '—' }}
                  </span>
                </div>
                <div class="text-[11px] text-gray-400 mt-0.5">
                  Intento #{{ it.numero_intento }} · Entregado: {{ it.fecha_fin ? new Date(it.fecha_fin).toLocaleString('es-PE') : 'En curso' }}
                </div>
              </div>

              <div class="flex items-center gap-2">
                <span 
                  :class="[
                    'px-2 py-0.5 rounded-full text-[10px] font-bold',
                    it.estado === 'FINALIZADO' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                  ]"
                >
                  {{ it.estado }}
                </span>
                <span 
                  v-if="it.nota !== undefined && it.nota !== null" 
                  class="text-xs font-bold px-2 py-0.5 rounded"
                  :class="Number(it.nota) >= 13 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'"
                >
                  Nota actual: {{ it.nota }} / {{ selectedEvaluacion?.puntaje_maximo }} pts
                </span>
              </div>
            </div>

            <!-- Evidencia Enviada por el Alumno -->
            <div class="bg-gray-50/70 p-3 rounded-lg border border-gray-200 space-y-2">
              <span class="text-[11px] font-bold text-gray-700 uppercase tracking-wider block">
                Lo que envió el Alumno:
              </span>

              <div class="flex flex-wrap items-center gap-3">
                <!-- Archivo entregado -->
                <div v-if="it.tiene_archivo_estudiante" class="flex items-center gap-2">
                  <button
                    @click="handleDescargarArchivoEstudiante(it.evaluacion_id, it.id, it.archivo_estudiante_nombre)"
                    class="btn-secondary text-xs py-1 px-2.5 flex items-center gap-1.5 text-primary-700 font-semibold"
                    title="Descargar archivo de solución del estudiante"
                  >
                    <svg class="w-3.5 h-3.5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar Solución ({{ it.archivo_estudiante_nombre || 'Archivo' }})
                  </button>
                </div>

                <!-- Ver cuestionario / examen resuelto -->
                <button
                  v-if="selectedEvaluacion?.tipo_evaluacion !== 'ARCHIVO'"
                  @click="handleVerExamenAlumno(it)"
                  class="btn-secondary text-xs py-1 px-2.5 flex items-center gap-1.5 text-slate-700 font-semibold hover:text-slate-900"
                >
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                  Ver Respuestas del Quiz
                </button>

                <span v-if="!it.tiene_archivo_estudiante && selectedEvaluacion?.tipo_evaluacion === 'ARCHIVO'" class="text-xs text-gray-400 italic">
                  El alumno no adjuntó archivo.
                </span>
              </div>

              <!-- Comentarios del Estudiante -->
              <div v-if="it.comentario_estudiante" class="text-xs text-slate-600 bg-white p-2 rounded border border-gray-200">
                <span class="font-semibold text-slate-700">Comentario del alumno:</span> "{{ it.comentario_estudiante }}"
              </div>
            </div>

            <!-- Formulario para Colocar Nota y Retroalimentación -->
            <div class="pt-1 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
              <div class="flex items-center gap-1.5">
                <label class="text-xs font-bold text-gray-700 whitespace-nowrap">Nota:</label>
                <input 
                  type="number" 
                  step="0.5" 
                  min="0" 
                  :max="selectedEvaluacion?.puntaje_maximo || 20" 
                  v-model.number="calificarIntentoForm[it.id].nota" 
                  class="input-field text-xs w-20 text-center font-bold" 
                />
                <span class="text-xs text-gray-400 font-semibold">/ {{ selectedEvaluacion?.puntaje_maximo || 20 }}</span>
              </div>

              <div class="flex-1">
                <input 
                  type="text" 
                  v-model="calificarIntentoForm[it.id].retroalimentacion" 
                  placeholder="Retroalimentación / observaciones para el alumno..." 
                  class="input-field text-xs w-full" 
                />
              </div>

              <button 
                @click="handleGuardarNotaIntento(it.id)" 
                :disabled="calificarIntentoForm[it.id]?.guardando" 
                class="btn-primary text-xs py-2 px-3.5 whitespace-nowrap shadow-xs flex items-center justify-center gap-1"
              >
                <svg v-if="!calificarIntentoForm[it.id]?.guardando" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ calificarIntentoForm[it.id]?.guardando ? 'Guardando...' : 'Guardar Nota' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </BaseModal>

    <!-- Modal Detalle / Revisión del Examen del Alumno (Docente) -->
    <BaseModal 
      :open="openRevisionDocenteModal" 
      @close="openRevisionDocenteModal = false" 
      :title="`Examen de: ${selectedIntentoRevision?.estudiante?.usuario?.apellido_paterno || ''} ${selectedIntentoRevision?.estudiante?.usuario?.nombres || ''}`"
      size="lg"
    >
      <div class="space-y-4">
        <div class="p-3 bg-indigo-50 border border-indigo-200 rounded-xl flex items-center justify-between text-xs text-indigo-950">
          <div>
            <p class="font-bold">Respuestas del Estudiante en el Quiz</p>
            <p class="text-indigo-700">Revisa las alternativas seleccionadas y los puntos obtenidos en cada pregunta.</p>
          </div>
          <div class="text-right font-mono font-bold text-sm text-indigo-900">
            Puntaje Quiz: {{ revisionDocenteData.reduce((acc, q) => acc + Number(q.puntaje_obtenido), 0) }} / {{ selectedEvaluacion?.puntaje_maximo }} pts
          </div>
        </div>

        <div v-if="loadingRevisionDocente" class="flex justify-center py-8">
          <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
        </div>

        <div v-else-if="revisionDocenteData.length === 0" class="text-center py-6 text-xs text-gray-500">
          No hay preguntas de quiz registradas para esta evaluación.
        </div>

        <div v-else class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
          <div 
            v-for="(q, qIdx) in revisionDocenteData" 
            :key="q.id" 
            class="p-3.5 bg-white border border-gray-200 rounded-xl space-y-2 text-xs"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-start gap-2">
                <span class="font-bold bg-slate-100 text-slate-700 px-2 py-0.5 rounded">
                  #{{ qIdx + 1 }}
                </span>
                <p class="font-semibold text-gray-900">{{ q.enunciado }}</p>
              </div>
              <span 
                class="font-mono font-bold px-2 py-0.5 rounded shrink-0"
                :class="q.puntaje_obtenido > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
              >
                {{ q.puntaje_obtenido }} / {{ q.puntaje }} pts
              </span>
            </div>

            <!-- Opciones -->
            <div class="pl-6 space-y-1.5 pt-1">
              <div 
                v-for="op in q.opciones" 
                :key="op.id" 
                class="flex items-center gap-2 p-1.5 rounded"
                :class="[
                  q.opcion_elegida === op.id && op.es_correcta ? 'bg-emerald-50 text-emerald-900 border border-emerald-300 font-semibold' : '',
                  q.opcion_elegida === op.id && !op.es_correcta ? 'bg-rose-50 text-rose-900 border border-rose-300 font-semibold' : '',
                  q.opcion_elegida !== op.id && op.es_correcta ? 'bg-slate-50 text-emerald-700 border border-dashed border-emerald-300' : 'text-gray-600'
                ]"
              >
                <!-- Indicador de respuesta -->
                <span 
                  v-if="q.opcion_elegida === op.id" 
                  class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] text-white shrink-0 font-bold"
                  :class="op.es_correcta ? 'bg-emerald-600' : 'bg-rose-600'"
                >
                  {{ op.es_correcta ? '✓' : '✗' }}
                </span>
                <span v-else class="w-4 h-4 rounded-full border border-gray-300 shrink-0"></span>

                <span class="flex-1">{{ op.texto }}</span>

                <span v-if="q.opcion_elegida === op.id" class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-white/80 border">
                  Eligió el alumno
                </span>
                <span v-if="op.es_correcta" class="text-[10px] font-bold text-emerald-700 bg-emerald-100/80 px-1.5 py-0.2 rounded">
                  Correcta
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-2">
          <button type="button" @click="openRevisionDocenteModal = false" class="btn-secondary text-xs py-1.5 px-4">
            Cerrar Revisión
          </button>
        </div>
      </div>
    </BaseModal>

    <!-- Modal Solicitar Inclusión de Alumno -->
    <BaseModal :open="openInclusionModal" @close="openInclusionModal = false" title="Solicitar Inclusión de Alumno">
      <div class="space-y-4">
        <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900 flex gap-2.5">
          <svg class="h-5 w-5 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <p class="font-bold">Permiso de Coordinación Académica</p>
            <p class="mt-0.5">Como docente, puedes proponer la incorporación de un estudiante al aula. Tu solicitud será enviada al Coordinador del programa con una notificación para su evaluación y aprobación oficial.</p>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Buscar Estudiante</label>
          <div class="flex gap-2">
            <input
              v-model="searchEstudiante"
              @keydown.enter.prevent="handleBuscarEstudiantes"
              type="text"
              placeholder="Buscar por DNI, código o apellidos..."
              class="input-field text-xs flex-1"
            />
            <button
              type="button"
              @click="handleBuscarEstudiantes"
              :disabled="searchingEstudiantes"
              class="btn-secondary text-xs px-3"
            >
              {{ searchingEstudiantes ? '...' : 'Buscar' }}
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Estudiante Disponible *</label>
          <select
            v-model="selectedEstudianteId"
            class="input-field text-xs"
            :disabled="estudiantesDisponibles.length === 0"
          >
            <option v-if="estudiantesDisponibles.length === 0" :value="null">
              No se encontraron alumnos disponibles
            </option>
            <option
              v-for="est in estudiantesDisponibles"
              :key="est.id"
              :value="est.id"
            >
              {{ est.nombre_completo }} — (DNI: {{ est.documento_numero || '—' }} | Cód: {{ est.codigo_estudiante || '—' }})
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Motivo o Justificación Pedagógica *</label>
          <textarea
            v-model="inclusionMotivo"
            rows="3"
            placeholder="Ej: El alumno solicitó convalidación previa y asiste a clases / Regularización por cambio de horario..."
            class="input-field text-xs"
          ></textarea>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            @click="openInclusionModal = false"
            :disabled="submittingInclusion"
            class="btn-secondary text-xs"
          >
            Cancelar
          </button>
          <button
            type="button"
            @click="handleEnviarSolicitudInclusion"
            :disabled="submittingInclusion || !selectedEstudianteId"
            class="btn-primary text-xs"
          >
            {{ submittingInclusion ? 'Enviando...' : 'Enviar Solicitud a Coordinación' }}
          </button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
