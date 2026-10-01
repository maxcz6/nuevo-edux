export type RoleName = 'SUPERADMIN' | 'ADMINISTRADOR' | 'COORDINADOR' | 'DOCENTE' | 'ESTUDIANTE' | 'APODERADO'

export interface Institucion {
  id: number
  codigo: string
  nombre: string
  nombre_corto?: string
  tipo_institucion: 'COLEGIO' | 'INSTITUTO' | 'UNIVERSIDAD'
  ruc?: string
  direccion?: string
  telefono?: string
  correo?: string
  logo?: string
  zona_horaria: string
  estado: 'ACTIVO' | 'INACTIVO' | 'SUSPENDIDO'
  created_at?: string
  updated_at?: string
}

export interface Usuario {
  id: number
  institucion_id: number
  unidad_academica_id?: number
  username: string
  email: string
  nombres: string
  apellido_paterno: string
  apellido_materno?: string
  documento_tipo?: string
  documento_numero?: string
  telefono?: string
  foto?: string
  tiene_foto?: boolean
  estado: 'ACTIVO' | 'INACTIVO'
  ultimo_acceso_at?: string
  roles?: Rol[]
  permisos?: string[]
  institucion?: Institucion
  unidad_academica?: UnidadAcademica
  docente?: Docente
  estudiante?: Estudiante
}

export interface Rol {
  id: number
  nombre: string
  descripcion?: string
  es_sistema: boolean
  estado: boolean
  permisos?: (Permiso | string)[]
}

export interface Permiso {
  id: number
  modulo: string
  nombre: string
  codigo?: string
  descripcion?: string
}

export interface UnidadAcademica {
  id: number
  institucion_id: number
  nivel_educativo_id?: number
  padre_id?: number
  codigo: string
  nombre: string
  tipo: 'FACULTAD' | 'PROGRAMA_ESTUDIOS' | 'DEPARTAMENTO_ACADEMICO' | 'NIVEL_ESCOLAR'
  estado: string
  docentes_count?: number
  estudiantes_count?: number
  cursos_count?: number
}

export interface Docente {
  id: number
  institucion_id: number
  usuario_id: number
  unidad_academica_id?: number
  codigo_docente?: string
  especialidad?: string
  grado_academico?: string
  tipo_contrato?: string
  fecha_ingreso?: string
  estado: string
  usuario?: Usuario
  programa?: UnidadAcademica
  nombres?: string
  apellido_paterno?: string
}

export interface Estudiante {
  id: number
  institucion_id: number
  usuario_id: number
  unidad_academica_id?: number
  codigo_estudiante?: string
  fecha_nacimiento?: string
  sexo?: 'M' | 'F'
  estado: string
  usuario?: Usuario
  programa?: UnidadAcademica
  nombres?: string
  apellido_paterno?: string
}

export interface PeriodoAcademico {
  id: number
  institucion_id: number
  codigo: string
  nombre: string
  tipo_periodo: 'ANUAL' | 'SEMESTRAL' | 'TRIMESTRAL' | 'BIMESTRAL' | 'CICLO'
  fecha_inicio: string
  fecha_fin: string
  estado: 'PLANIFICADO' | 'ACTIVO' | 'FINALIZADO'
}

export interface Curso {
  id: number
  institucion_id: number
  codigo: string
  nombre: string
  descripcion?: string
  creditos?: number
  horas_semanales?: number
  estado: string
}

export interface GrupoCurso {
  id: number
  institucion_id: number
  curso_id: number
  periodo_id: number
  docente_id: number
  seccion_id?: number
  codigo_grupo?: string
  codigo?: string
  turno?: string
  aula?: string
  cupo?: number
  cupos?: number
  cupos_disponibles?: number
  matriculas_count?: number
  estado: string
  curso?: Curso
  docente?: Docente
  periodo?: PeriodoAcademico
}

export interface Matricula {
  id: number
  grupo_curso_id: number
  estudiante_id: number
  origen: 'MANUAL' | 'AUTOMATICA_SECCION' | 'INCLUSION_DOCENTE' | string
  fecha_matricula: string
  estado: string
  grupo_curso?: GrupoCurso
  estudiante?: Estudiante
}

export interface SolicitudInclusion {
  id: number
  institucion_id: number
  grupo_curso_id: number
  docente_id: number
  estudiante_id: number
  coordinador_id?: number
  motivo: string
  observaciones_coordinador?: string
  estado: 'PENDIENTE' | 'APROBADO' | 'RECHAZADO' | 'CANCELADO'
  fecha_resolucion?: string
  created_at: string
  updated_at?: string
  grupo_curso?: GrupoCurso
  docente?: Docente
  estudiante?: Estudiante
  coordinador?: Usuario
}

export interface Unidad {
  id: number
  grupo_curso_id: number
  titulo: string
  descripcion?: string
  orden: number
  fecha_inicio?: string
  fecha_fin?: string
  visible_desde?: string
  visible_hasta?: string
  estado: string
  sesiones?: Sesion[]
  materiales?: Material[]
  tareas?: Tarea[]
}

export interface Sesion {
  id: number
  unidad_id: number
  titulo: string
  descripcion?: string
  fecha_sesion?: string
  hora_inicio?: string
  hora_fin?: string
  enlace_virtual?: string
  orden: number
  estado: string
}

export interface Material {
  id: number
  unidad_id?: number
  sesion_id?: number
  docente_id: number
  titulo: string
  descripcion?: string
  tipo: 'PDF' | 'DOCX' | 'PPTX' | 'XLSX' | 'IMAGEN' | 'VIDEO' | 'ENLACE' | 'ZIP' | 'DOCUMENTO'
  archivo_url?: string
  archivo_original?: string
  enlace_externo?: string
  visible_desde?: string
  visible_hasta?: string
  tiene_archivo?: boolean
  estado: string
}

export interface Tarea {
  id: number
  grupo_curso_id: number
  unidad_id?: number
  docente_id: number
  titulo: string
  descripcion?: string
  comentarios_instrucciones?: string | null
  archivo_tipo?: string | null
  archivo_nombre_original?: string | null
  archivo_adjunto_nombre?: string | null
  tiene_archivo_adjunto?: boolean
  enlace_recurso?: string | null
  puntaje_maximo: number
  fecha_inicio?: string
  fecha_limite: string
  fecha_vencimiento?: string
  permite_entrega_tardia: boolean
  estado: 'BORRADOR' | 'PUBLICADA' | 'CERRADA'
  entrega?: EntregaTarea
  mi_entrega?: {
    id: number
    estado: 'ENTREGADA' | 'CALIFICADA' | 'DEVUELTA'
    fecha_entrega: string
    es_tardia: boolean
    tiene_archivo: boolean
    nota?: number | null
    comentario_estudiante?: string | null
    comentario_docente?: string | null
  } | null
}

export interface EntregaTarea {
  id: number
  tarea_id: number
  estudiante_id: number
  archivo_url?: string
  archivo_ruta?: string
  archivo_original?: string
  tiene_archivo?: boolean
  comentario_estudiante?: string
  comentarios_estudiante?: string
  fecha_entrega: string
  es_tardia: boolean
  nota?: number
  calificacion?: number
  comentario_docente?: string
  comentarios?: string
  estado: 'ENTREGADA' | 'CALIFICADA' | 'DEVUELTA'
  estudiante?: Estudiante
}

export interface Calificacion {
  id: number
  grupo_curso_id: number
  estudiante_id: number
  tipo_origen: 'TAREA' | 'EVALUACION' | 'PARTICIPACION' | 'MANUAL'
  origen_id?: number
  nombre_rubro: string
  nota: number
  valor?: number
  tipo_evaluacion?: string
  observaciones?: string
  peso_porcentual?: number
}

export interface ConsolidadoEstudiante {
  estudiante_id: number
  codigo: string
  nombre_completo: string
  tipo_documento?: string
  numero_documento?: string
  notas_por_rubro: Record<string, number>
  detalle_rubros?: Record<string, { id?: number; nota?: number | null; peso_porcentual?: number | null; tipo_origen?: string; origen_id?: number | null }>
  promedio_final: number
  estado: 'APROBADO' | 'DESAPROBADO'
}

export interface Notificacion {
  id: number
  tipo: string
  titulo: string
  mensaje?: string
  referencia_tipo?: string
  referencia_id?: number
  leido: boolean
  created_at: string
}

export interface Aviso {
  id: number
  institucion_id: number
  grupo_curso_id?: number
  autor_usuario_id: number
  titulo: string
  contenido: string
  fecha_publicacion: string
  estado: string
}

export interface AuthUser extends Usuario {
  token: string
}

export interface ApiResponse<T> {
  data: T
  message?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  message?: string
}

export interface AsistenciaItem {
  estudiante_id: number
  codigo: string
  nombre_completo: string
  foto?: string
  fecha: string
  asistencia_id?: number
  estado_asistencia: 'PRESENTE' | 'AUSENTE' | 'TARDANZA' | 'JUSTIFICADO'
  observacion?: string
}

export interface AsistenciaResumenItem {
  estudiante_id: number
  codigo: string
  nombre_completo: string
  presentes: number
  tardanzas: number
  ausentes: number
  justificados: number
  porcentaje_asistencia: number
  en_riesgo: boolean
}

export interface Evaluacion {
  id: number
  grupo_curso_id: number
  unidad_id?: number
  docente_id: number
  titulo: string
  instrucciones?: string
  tipo_evaluacion?: 'QUIZ' | 'ARCHIVO' | 'MIXTO'
  tiene_archivo_adjunto?: boolean
  archivo_nombre_original?: string
  permite_archivo_estudiante?: boolean
  puntaje_maximo: number
  duracion_minutos?: number
  intentos_permitidos: number
  fecha_inicio: string
  fecha_fin: string
  estado: 'BORRADOR' | 'PROGRAMADA' | 'PUBLICADA' | 'CERRADA' | 'OCULTA'
  preguntas_count?: number
  intentos_count?: number
  preguntas?: Pregunta[]
  intentos?: IntentoEvaluacion[]
  intentos_realizados?: number
  mejor_nota?: number
  tiene_intento_activo?: boolean
  intento_activo_id?: number
  esta_expirada?: boolean
  ha_iniciado?: boolean
  revision_disponible?: boolean
  ultimo_intento_estado?: string
  ultimo_intento_archivo?: string
}

export interface Pregunta {
  id: number
  evaluacion_id: number
  tipo: 'ALTERNATIVA' | 'VERDADERO_FALSO' | 'RESPUESTA_CORTA' | 'ABIERTA'
  enunciado: string
  puntaje: number
  orden: number
  opciones?: OpcionPregunta[]
}

export interface OpcionPregunta {
  id: number
  pregunta_id: number
  texto: string
  es_correcta?: boolean
  orden: number
}

export interface IntentoEvaluacion {
  id: number
  evaluacion_id: number
  estudiante_id: number
  numero_intento: number
  fecha_inicio: string
  fecha_fin?: string
  nota?: number
  archivo_estudiante?: string
  archivo_estudiante_nombre?: string
  tiene_archivo_estudiante?: boolean
  comentario_estudiante?: string
  retroalimentacion?: string
  estado: 'EN_CURSO' | 'FINALIZADO' | 'ANULADO'
  estudiante?: Estudiante
}

export interface ConvocatoriaAdmision {
  id: number
  institucion_id: number
  periodo_id?: number
  codigo: string
  nombre: string
  fecha_inicio: string
  fecha_fin: string
  fecha_examen?: string
  puntaje_minimo_aprobatorio: number
  estado: 'BORRADOR' | 'PUBLICADA' | 'EN_EVALUACION' | 'FINALIZADA'
  vacantes_count?: number
  postulantes_count?: number
  periodo?: PeriodoAcademico
  created_at?: string
  updated_at?: string
}

export interface VacanteAdmision {
  id: number
  institucion_id: number
  convocatoria_id: number
  programa_id: number
  turno: 'DIURNO' | 'NOCTURNO'
  modalidad: 'ORDINARIO' | 'EXONERADO' | 'PRIMEROS_PUESTOS' | 'DEPORTISTA'
  vacantes_ofertadas: number
  programa?: UnidadAcademica
}

export interface PostulanteAdmision {
  id: number
  convocatoria_id: number
  programa_id: number
  codigo_postulante: string
  documento_tipo: string
  documento_numero: string
  nombres: string
  apellido_paterno: string
  apellido_materno: string
  email: string
  telefono?: string
  turno: 'DIURNO' | 'NOCTURNO'
  modalidad: string
  puntaje_examen?: number
  merito?: number
  estado: 'INSCRITO' | 'EVALUADO' | 'INGRESANTE' | 'NO_INGRESANTE' | 'MATRICULADO'
  programa?: UnidadAcademica
  estudiante?: Estudiante
}

export interface Tramite {
  id: number
  institucion_id: number
  estudiante_id: number
  tipo_tramite: 'TRASLADO_INTERNO' | 'TRASLADO_EXTERNO' | 'REINCORPORACION' | 'RESERVA_MATRICULA' | 'CONVALIDACION' | string
  programa_origen_id?: number
  programa_destino_id?: number
  resolucion_directoral?: string
  fecha_solicitud: string
  fecha_resolucion?: string
  motivo?: string
  observaciones?: string
  estado: 'SOLICITADO' | 'EN_REVISION' | 'APROBADO' | 'RECHAZADO'
  estudiante?: Estudiante
  programa_origen?: UnidadAcademica
  programa_destino?: UnidadAcademica
  created_at?: string
}

export interface Ambiente {
  id: number
  institucion_id: number
  codigo: string
  nombre: string
  tipo: 'AULA' | 'LABORATORIO' | 'TALLER' | 'AUDITORIO' | string
  pabellon?: string
  capacidad: number
  estado: 'DISPONIBLE' | 'MANTENIMIENTO' | 'INACTIVO' | string
  created_at?: string
}

export interface Titulacion {
  id: number
  institucion_id: number
  estudiante_id: number
  programa_id: number
  modalidad_titulacion: string
  numero_titulo: string
  numero_registro: string
  numero_folio: string
  numero_libro: string
  resolucion_directoral?: string
  codigo_minedu?: string
  fecha_sustentacion?: string
  fecha_emision: string
  codigo_verificacion: string
  estado: string
  estudiante?: Estudiante
  programa?: UnidadAcademica
  created_at?: string
}

export interface CertificadoModular {
  id: number
  institucion_id: number
  estudiante_id: number
  programa_id: number
  numero_modulo: number
  nombre_modulo: string
  resolucion_directoral?: string
  horas_teoria: number
  horas_practica: number
  creditos: number
  horas_efsrt: number
  codigo_verificacion: string
  fecha_emision: string
  estado: string
  estudiante?: Estudiante
  programa?: UnidadAcademica
  created_at?: string
}

export interface Pago {
  id: number
  numero_comprobante: string
  tipo_comprobante: 'RECIBO' | 'BOLETA' | 'FACTURA' | 'TICKET'
  concepto: string
  monto: number
  metodo_pago: 'EFECTIVO' | 'TRANSFERENCIA' | 'YAPE' | 'PLIN' | 'TARJETA_DEBITO' | 'TARJETA_CREDITO' | 'DEPOSITO_BANCARIO'
  numero_operacion?: string | null
  fecha_pago: string
  estado: 'PAGADO' | 'ANULADO'
  motivo_anulacion?: string | null
  observaciones?: string | null
  estudiante?: {
    id: number
    codigo_estudiante: string
    nombre_completo: string
    numero_documento?: string | null
  } | null
  registrado_por?: {
    id: number
    nombres: string
    apellido_paterno: string
  } | null
}

export interface NuevoPagoPayload {
  estudiante_id?: number | null
  matricula_id?: number | null
  tramite_id?: number | null
  tipo_comprobante: string
  concepto: string
  monto: number
  metodo_pago: string
  numero_operacion?: string
  fecha_pago?: string
  observaciones?: string
}

export interface CajaKpis {
  total_hoy: number
  total_general: number
  cantidad_pagos: number
  cantidad_anulados: number
  promedio_ticket: number
}

export interface EstudianteCajaBusqueda {
  id: number
  codigo_estudiante: string
  nombre_completo: string
  numero_documento?: string | null
  programa?: string | null
}

