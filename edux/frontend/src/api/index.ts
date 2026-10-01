import http from './http'
import type { 
  ApiResponse, PaginatedResponse, Usuario, Rol, Docente, Estudiante,
  PeriodoAcademico, Curso, GrupoCurso, Matricula, SolicitudInclusion, Unidad, Tarea, 
  EntregaTarea, Calificacion, Notificacion, Aviso, Material,
  AsistenciaItem, AsistenciaResumenItem, Evaluacion, IntentoEvaluacion, Pregunta,
  UnidadAcademica, ConvocatoriaAdmision, VacanteAdmision, PostulanteAdmision,
  Tramite, Ambiente, Titulacion, CertificadoModular
} from '@/types'

export const dashboardApi = {
  stats: () => http.get<ApiResponse<Record<string, unknown>>>('/dashboard/stats'),
}

export const usuariosApi = {
  list:            (params?: Record<string, unknown>) => http.get<PaginatedResponse<Usuario>>('/admin/usuarios', { params }),
  create:          (data: Record<string, unknown>) => http.post<ApiResponse<Usuario>>('/admin/usuarios', data),
  update:          (id: number, data: Record<string, unknown>) => http.put<ApiResponse<Usuario>>(`/admin/usuarios/${id}`, data),
  remove:          (id: number, params?: Record<string, unknown>) => http.delete(`/admin/usuarios/${id}`, { params }),
  cambiarPassword: (id: number, data: { password: string; password_confirmation: string }) => http.post<ApiResponse<{ message: string }>>(`/admin/usuarios/${id}/cambiar-password`, data),
}

export const rolesApi = {
  list:   (params?: Record<string, unknown>) => http.get<PaginatedResponse<Rol>>('/admin/roles', { params }),
  get:    (id: number) => http.get<ApiResponse<Rol>>(`/admin/roles/${id}`),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<Rol>>('/admin/roles', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<Rol>>(`/admin/roles/${id}`, data),
  remove: (id: number, params?: Record<string, unknown>) => http.delete(`/admin/roles/${id}`, { params }),
}

export const docentesApi = {
  list:   (params?: Record<string, unknown>) => http.get<PaginatedResponse<Docente>>('/admin/docentes', { params }),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<Docente>>('/admin/docentes', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<Docente>>(`/admin/docentes/${id}`, data),
  remove: (id: number, params?: Record<string, unknown>) => http.delete(`/admin/docentes/${id}`, { params }),
}

export const estudiantesApi = {
  list:   (params?: Record<string, unknown>) => http.get<PaginatedResponse<Estudiante>>('/admin/estudiantes', { params }),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<Estudiante>>('/admin/estudiantes', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<Estudiante>>(`/admin/estudiantes/${id}`, data),
  remove: (id: number, params?: Record<string, unknown>) => http.delete(`/admin/estudiantes/${id}`, { params }),
}

export const periodosApi = {
  list:   (params?: Record<string, unknown>) => http.get<PaginatedResponse<PeriodoAcademico>>('/academico/periodos', { params }),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<PeriodoAcademico>>('/academico/periodos', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<PeriodoAcademico>>(`/academico/periodos/${id}`, data),
  remove: (id: number, params?: Record<string, unknown>) => http.delete(`/academico/periodos/${id}`, { params }),
}

export const cursosApi = {
  list:   (params?: Record<string, unknown>) => http.get<PaginatedResponse<Curso>>('/academico/cursos', { params }),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<Curso>>('/academico/cursos', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<Curso>>(`/academico/cursos/${id}`, data),
  remove: (id: number, params?: Record<string, unknown>) => http.delete(`/academico/cursos/${id}`, { params }),
}

export const gruposApi = {
  list:      (params?: Record<string, unknown>) => http.get<PaginatedResponse<GrupoCurso>>('/academico/grupos', { params }),
  misGrupos: () => http.get<ApiResponse<GrupoCurso[]>>('/aula/mis-grupos'),
  create:    (data: Record<string, unknown>) => http.post<ApiResponse<GrupoCurso>>('/academico/grupos', data),
  update:    (id: number, data: Record<string, unknown>) => http.put<ApiResponse<GrupoCurso>>(`/academico/grupos/${id}`, data),
  remove:    (id: number, params?: Record<string, unknown>) => http.delete(`/academico/grupos/${id}`, { params }),
}

export const matriculasApi = {
  list:   (params?: Record<string, unknown>) => http.get<PaginatedResponse<Matricula>>('/academico/matriculas', { params }),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<Matricula>>('/academico/matriculas', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<Matricula>>(`/academico/matriculas/${id}`, data),
  remove: (id: number) => http.delete(`/academico/matriculas/${id}`),
}

export const solicitudesInclusionApi = {
  list:                  (params?: Record<string, unknown>) => http.get<PaginatedResponse<SolicitudInclusion>>('/academico/solicitudes-inclusion', { params }),
  create:                (data: { grupo_curso_id: number; estudiante_id: number; motivo: string }) => http.post<ApiResponse<SolicitudInclusion>>('/academico/solicitudes-inclusion', data),
  aprobar:               (id: number) => http.post<ApiResponse<SolicitudInclusion>>(`/academico/solicitudes-inclusion/${id}/aprobar`),
  rechazar:              (id: number, observaciones_coordinador: string) => http.post<ApiResponse<SolicitudInclusion>>(`/academico/solicitudes-inclusion/${id}/rechazar`, { observaciones_coordinador }),
  cancelar:              (id: number) => http.delete(`/academico/solicitudes-inclusion/${id}`),
  estudiantesDisponibles:(grupoId: number, params?: { q?: string }) => http.get<ApiResponse<Array<{ id: number; codigo_estudiante?: string; documento_numero?: string; nombre_completo: string; email?: string }>>>(`/academico/grupos/${grupoId}/estudiantes-disponibles`, { params }),
}

export const unidadesApi = {
  list:   (grupoId: number, params?: Record<string, unknown>) => http.get<ApiResponse<Unidad[]>>(`/aula/grupos/${grupoId}/unidades`, { params }),
  create: (grupoId: number, data: Record<string, unknown>) => http.post<ApiResponse<Unidad>>(`/aula/grupos/${grupoId}/unidades`, data),
  update: (grupoId: number, id: number, data: Record<string, unknown>) => http.put<ApiResponse<Unidad>>(`/aula/grupos/${grupoId}/unidades/${id}`, data),
  remove: (grupoId: number, id: number) => http.delete(`/aula/grupos/${grupoId}/unidades/${id}`),
}

export const materialesApi = {
  list:      (unidadId: number) => http.get<ApiResponse<Material[]>>(`/aula/unidades/${unidadId}/materiales`),
  create:    (unidadId: number, data: FormData) => http.post<ApiResponse<Material>>(`/aula/unidades/${unidadId}/materiales`, data),
  update:    (unidadId: number, id: number, data: FormData | Record<string, unknown>) => {
    if (data instanceof FormData) {
      data.append('_method', 'PUT')
      return http.post<ApiResponse<Material>>(`/aula/unidades/${unidadId}/materiales/${id}`, data)
    }
    return http.put<ApiResponse<Material>>(`/aula/unidades/${unidadId}/materiales/${id}`, data)
  },
  descargar: (materialId: number) => http.get(`/aula/materiales/${materialId}/descargar`, { responseType: 'blob' }),
  remove:    (unidadId: number, id: number) => http.delete(`/aula/unidades/${unidadId}/materiales/${id}`),
}

export const tareasApi = {
  list:   (grupoId: number) => http.get<ApiResponse<Tarea[]>>(`/aula/grupos/${grupoId}/tareas`),
  create: (grupoId: number, data: FormData | Record<string, unknown>) =>
    http.post<ApiResponse<Tarea>>(`/aula/grupos/${grupoId}/tareas`, data, data instanceof FormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : undefined),
  update: (grupoId: number, id: number, data: FormData | Record<string, unknown>) => {
    if (data instanceof FormData) {
      data.append('_method', 'PUT')
      return http.post<ApiResponse<Tarea>>(`/aula/grupos/${grupoId}/tareas/${id}`, data, { headers: { 'Content-Type': 'multipart/form-data' } })
    }
    return http.put<ApiResponse<Tarea>>(`/aula/grupos/${grupoId}/tareas/${id}`, data)
  },
  remove: (grupoId: number, id: number) => http.delete(`/aula/grupos/${grupoId}/tareas/${id}`),
  descargarAdjunto: (id: number) => http.get(`/aula/tareas/${id}/descargar-adjunto`, { responseType: 'blob' }),
}

export const entregasApi = {
  list:      (tareaId: number) => http.get<ApiResponse<EntregaTarea[]>>(`/aula/tareas/${tareaId}/entregas`),
  create:    (tareaId: number, data: FormData) => http.post<ApiResponse<EntregaTarea>>(`/aula/tareas/${tareaId}/entregas`, data, { headers: { 'Content-Type': 'multipart/form-data' } }),
  calificar: (tareaId: number, entregaId: number, data: Record<string, unknown>) => http.patch<ApiResponse<EntregaTarea>>(`/aula/tareas/${tareaId}/entregas/${entregaId}/calificar`, data),
  descargar: (entregaId: number) => http.get(`/aula/entregas/${entregaId}/descargar`, { responseType: 'blob' }),
}

export const calificacionesApi = {
  list:        (grupoId: number) => http.get<ApiResponse<Calificacion[]>>(`/aula/grupos/${grupoId}/calificaciones`),
  consolidado: (grupoId: number) => http.get<{ data: any[]; rubros: string[]; message: string }>(`/aula/grupos/${grupoId}/calificaciones/consolidado`),
  create:      (grupoId: number, data: Record<string, unknown>) => http.post<ApiResponse<Calificacion>>(`/aula/grupos/${grupoId}/calificaciones`, data),
  update:      (grupoId: number, id: number, data: Record<string, unknown>) => http.put<ApiResponse<Calificacion>>(`/aula/grupos/${grupoId}/calificaciones/${id}`, data),
  remove:      (grupoId: number, id: number) => http.delete(`/aula/grupos/${grupoId}/calificaciones/${id}`),
  guardarLote: (grupoId: number, data: { nombre_rubro: string; tipo_origen: string; origen_id?: number | null; peso_porcentual?: number | null; notas: Array<{ estudiante_id: number; nota: number }> }) =>
    http.post<{ data: Calificacion[]; total: number; message: string }>(`/aula/grupos/${grupoId}/calificaciones/guardar-lote`, data),
  exportarUrl: (grupoId: number) => `/aula/grupos/${grupoId}/calificaciones/exportar`,
  descargarExcel: async (grupoId: number, filename?: string) => {
    const res = await http.get(`/aula/grupos/${grupoId}/calificaciones/exportar`, { responseType: 'blob' })
    const blobUrl = window.URL.createObjectURL(new Blob([res.data], { type: 'text/csv;charset=utf-8;' }))
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = filename || `acta_calificaciones_grupo_${grupoId}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(blobUrl)
  },
}

export const avisosApi = {
  list:   (grupoId: number) => http.get<ApiResponse<Aviso[]>>(`/aula/grupos/${grupoId}/avisos`),
  create: (grupoId: number, data: Record<string, unknown>) => http.post<ApiResponse<Aviso>>(`/aula/grupos/${grupoId}/avisos`, data),
  remove: (grupoId: number, id: number) => http.delete(`/aula/grupos/${grupoId}/avisos/${id}`),
}

export const notificacionesApi = {
  list:        (params?: Record<string, unknown>) => http.get<PaginatedResponse<Notificacion>>('/aula/notificaciones', { params }),
  marcarLeida: (id: number) => http.patch(`/aula/notificaciones/${id}/leer`),
  marcarTodas: () => http.post('/aula/notificaciones/leer-todas'),
}

export const asistenciasApi = {
  list:           (grupoId: number, fecha?: string) => http.get<ApiResponse<AsistenciaItem[]>>(`/aula/grupos/${grupoId}/asistencias`, { params: { fecha } }),
  store:          (grupoId: number, data: { fecha: string; asistencias: Array<{ estudiante_id: number; estado_asistencia: string; observacion?: string }> }) => http.post<ApiResponse<unknown>>(`/aula/grupos/${grupoId}/asistencias`, data),
  resumen:        (grupoId: number) => http.get<{ data: AsistenciaResumenItem[]; total_fechas: number; message: string }>(`/aula/grupos/${grupoId}/asistencias/resumen`),
  misAsistencias: (grupoId: number) => http.get<ApiResponse<{ asistencias: unknown[]; resumen: Record<string, unknown> }>>(`/aula/grupos/${grupoId}/asistencias/mis-asistencias`),
}

export const evaluacionesApi = {
  list:           (grupoId: number, params?: Record<string, unknown>) => http.get<PaginatedResponse<Evaluacion>>(`/aula/grupos/${grupoId}/evaluaciones`, { params }),
  get:            (grupoId: number, id: number) => http.get<ApiResponse<Evaluacion>>(`/aula/grupos/${grupoId}/evaluaciones/${id}`),
  create:         (grupoId: number, data: FormData | Record<string, unknown>) => http.post<ApiResponse<Evaluacion>>(`/aula/grupos/${grupoId}/evaluaciones`, data),
  update:         (grupoId: number, id: number, data: FormData | Record<string, unknown>) => {
    if (data instanceof FormData) {
      data.append('_method', 'PUT')
      return http.post<ApiResponse<Evaluacion>>(`/aula/grupos/${grupoId}/evaluaciones/${id}`, data)
    }
    return http.put<ApiResponse<Evaluacion>>(`/aula/grupos/${grupoId}/evaluaciones/${id}`, data)
  },
  remove:         (grupoId: number, id: number) => http.delete(`/aula/grupos/${grupoId}/evaluaciones/${id}`),
  descargarAdjunto: (id: number) => http.get(`/aula/evaluaciones/${id}/descargar-adjunto`, { responseType: 'blob' }),
  agregarPregunta: (evaluacionId: number, data: { tipo: string; enunciado: string; puntaje: number; opciones?: Array<{ texto: string; es_correcta: boolean }> }) =>
    http.post<ApiResponse<Pregunta>>(`/aula/evaluaciones/${evaluacionId}/preguntas`, data),
  eliminarPregunta: (evaluacionId: number, preguntaId: number) =>
    http.delete(`/aula/evaluaciones/${evaluacionId}/preguntas/${preguntaId}`),
  iniciarIntento: (evaluacionId: number) => http.post<{ intento: IntentoEvaluacion; evaluacion: Evaluacion; preguntas: Pregunta[]; message: string }>(`/aula/evaluaciones/${evaluacionId}/iniciar-intento`),
  enviarRespuestas: (evaluacionId: number, intentoId: number, data: { respuestas: Array<{ pregunta_id: number; opcion_id?: number; respuesta_texto?: string }> }) =>
    http.post<{ message: string; nota?: number | null; intento_id: number; revision_disponible: boolean; mensaje_seguridad: string }>(`/aula/evaluaciones/${evaluacionId}/intentos/${intentoId}/enviar`, data),
  subirArchivoIntento: (evaluacionId: number, intentoId: number, data: FormData) =>
    http.post<ApiResponse<IntentoEvaluacion>>(`/aula/evaluaciones/${evaluacionId}/intentos/${intentoId}/subir-archivo`, data),
  descargarArchivoEstudiante: (evaluacionId: number, intentoId: number) =>
    http.get(`/aula/evaluaciones/${evaluacionId}/intentos/${intentoId}/descargar-archivo`, { responseType: 'blob' }),
  calificarIntento: (evaluacionId: number, intentoId: number, data: { nota: number; retroalimentacion?: string }) =>
    http.post<ApiResponse<IntentoEvaluacion>>(`/aula/evaluaciones/${evaluacionId}/intentos/${intentoId}/calificar`, data),
  revision: (evaluacionId: number, intentoId: number) =>
    http.get<{
      intento: IntentoEvaluacion
      revision: Array<{
        id: number
        enunciado: string
        tipo: string
        puntaje: number
        puntaje_obtenido: number
        opcion_elegida?: number
        respuesta_texto?: string
        opciones: Array<{ id: number; texto: string; es_correcta: boolean }>
      }>
      revision_disponible: boolean
    }>(`/aula/evaluaciones/${evaluacionId}/intentos/${intentoId}/revision`),
}

export const programasApi = {
  list:   (params?: Record<string, unknown>) => http.get<PaginatedResponse<UnidadAcademica>>('/admin/unidades-academicas', { params }),
  get:    (id: number) => http.get<ApiResponse<UnidadAcademica>>(`/admin/unidades-academicas/${id}`),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<UnidadAcademica>>('/admin/unidades-academicas', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<UnidadAcademica>>(`/admin/unidades-academicas/${id}`, data),
  remove: (id: number) => http.delete(`/admin/unidades-academicas/${id}`),
}

export const admisionApi = {
  convocatorias: (params?: Record<string, unknown>) => http.get<ApiResponse<ConvocatoriaAdmision[]>>('/admin/admision/convocatorias', { params }),
  createConvocatoria: (data: Record<string, unknown>) => http.post<ApiResponse<ConvocatoriaAdmision>>('/admin/admision/convocatorias', data),
  getConvocatoria: (id: number) => http.get<ApiResponse<{ convocatoria: ConvocatoriaAdmision; resumen_postulantes: Record<string, number> }>>(`/admin/admision/convocatorias/${id}`),
  guardarVacantes: (id: number, vacantes: VacanteAdmision[] | Array<Record<string, unknown>>) => http.post<ApiResponse<VacanteAdmision[]>>(`/admin/admision/convocatorias/${id}/vacantes`, { vacantes }),
  postulantes: (id: number, params?: Record<string, unknown>) => http.get<PaginatedResponse<PostulanteAdmision>>(`/admin/admision/convocatorias/${id}/postulantes`, { params }),
  createPostulante: (id: number, data: Record<string, unknown>) => http.post<ApiResponse<PostulanteAdmision>>(`/admin/admision/convocatorias/${id}/postulantes`, data),
  registrarCalificaciones: (id: number, calificaciones: Array<{ postulante_id: number; puntaje_examen: number }>) => http.post<{ updated_count: number; message: string }>(`/admin/admision/convocatorias/${id}/registrar-calificaciones`, { calificaciones }),
  calcularMerito: (id: number) => http.post<{ resumen: Array<Record<string, unknown>>; message: string }>(`/admin/admision/convocatorias/${id}/calcular-merito`),
  matricularIngresantes: (id: number) => http.post<{ matriculados: number; message: string }>(`/admin/admision/convocatorias/${id}/matricular-ingresantes`),
}

export const tramitesApi = {
  list: (params?: Record<string, unknown>) => http.get<PaginatedResponse<Tramite>>('/admin/tramites', { params }),
  get: (id: number) => http.get<ApiResponse<Tramite>>(`/admin/tramites/${id}`),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<Tramite>>('/admin/tramites', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<Tramite>>(`/admin/tramites/${id}`, data),
}

export const cajaApi = {
  resumen: (params?: Record<string, unknown>) => http.get<any>('/admin/caja/resumen', { params }),
  operaciones: (params?: Record<string, unknown>) => http.get<PaginatedResponse<any>>('/admin/caja/operaciones', { params }),
  listPagos: (params?: Record<string, unknown>) => http.get<{ data: import('@/types').Pago[]; kpis: import('@/types').CajaKpis; meta: any; message: string }>('/admin/caja/pagos', { params }),
  createPago: (data: import('@/types').NuevoPagoPayload) => http.post<ApiResponse<import('@/types').Pago>>('/admin/caja/pagos', data),
  getPago: (id: number) => http.get<ApiResponse<import('@/types').Pago>>(`/admin/caja/pagos/${id}`),
  anularPago: (id: number, data: { motivo_anulacion: string }) => http.patch<ApiResponse<import('@/types').Pago>>(`/admin/caja/pagos/${id}/anular`, data),
  buscarEstudiantes: (q?: string) => http.get<ApiResponse<import('@/types').EstudianteCajaBusqueda[]>>('/admin/caja/estudiantes-buscar', { params: { q } }),
}

export const ambientesApi = {
  list: (params?: Record<string, unknown>) => http.get<ApiResponse<Ambiente[]>>('/admin/ambientes', { params }),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<Ambiente>>('/admin/ambientes', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<Ambiente>>(`/admin/ambientes/${id}`, data),
  remove: (id: number) => http.delete(`/admin/ambientes/${id}`),
}

export const registraApi = {
  urlIngresantes: (convocatoriaId: number) => `/academico/exportar/registra-ingresantes/${convocatoriaId}`,
  urlNomina: (periodoId: number, programaId?: number) => `/academico/exportar/registra-nomina?periodo_id=${periodoId}${programaId ? `&programa_id=${programaId}` : ''}`,
  urlActaUd: (grupoId: number) => `/academico/exportar/registra-acta-ud/${grupoId}`,
  descargarIngresantes: async (convocatoriaId: number) => {
    const res = await http.get(`/academico/exportar/registra-ingresantes/${convocatoriaId}`, { responseType: 'blob' })
    const blobUrl = window.URL.createObjectURL(new Blob([res.data]))
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = `padron_ingresantes_sam_${convocatoriaId}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(blobUrl)
  },
}

export const titulacionApi = {
  list: (params?: Record<string, unknown>) => http.get<PaginatedResponse<Titulacion>>('/admin/titulacion', { params }),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<Titulacion>>('/admin/titulacion', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<Titulacion>>(`/admin/titulacion/${id}`, data),
  descargarLibroTitulos: async () => {
    const res = await http.get('/admin/titulacion/exportar/libro-titulos', { responseType: 'blob' })
    const blobUrl = window.URL.createObjectURL(new Blob([res.data]))
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = `libro_titulos_sam_${new Date().toISOString().slice(0, 19).replace(/[-T:]/g, '')}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(blobUrl)
  },
}

export const certificadosModularesApi = {
  list: (params?: Record<string, unknown>) => http.get<PaginatedResponse<CertificadoModular>>('/admin/certificados-modulares', { params }),
  create: (data: Record<string, unknown>) => http.post<ApiResponse<CertificadoModular>>('/admin/certificados-modulares', data),
  update: (id: number, data: Record<string, unknown>) => http.put<ApiResponse<CertificadoModular>>(`/admin/certificados-modulares/${id}`, data),
  descargarPadron: async () => {
    const res = await http.get('/admin/certificados-modulares/exportar/padron', { responseType: 'blob' })
    const blobUrl = window.URL.createObjectURL(new Blob([res.data]))
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = `padron_certificados_modulares_sam_${new Date().toISOString().slice(0, 19).replace(/[-T:]/g, '')}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(blobUrl)
  },
}
