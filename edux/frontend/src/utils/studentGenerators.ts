/**
 * Utilidades para la generación automática de códigos y credenciales de estudiantes
 * según la normativa institucional y los requerimientos de Mesa de Partes / Secretaría.
 */

/**
 * Genera el nombre de usuario (login) institucional según el formato:
 * #(numero de dni)(primera letra del nombre en minúscula)(primer apellido en mayúscula, solo la primera letra)
 * 
 * Ejemplo:
 * Para "Max Paucar Ramos" con DNI "60981968" => "#60981968mP"
 */
export function generateEstudianteUsername(dni: string, nombres: string, apellidoPaterno: string): string {
  const cleanDni = dni ? dni.replace(/\D/g, '').trim() : ''
  const firstNombre = nombres ? nombres.trim().charAt(0).toLowerCase() : ''
  const firstApellido = apellidoPaterno ? apellidoPaterno.trim().charAt(0).toUpperCase() : ''

  if (!cleanDni && !firstNombre && !firstApellido) {
    return ''
  }

  return `#${cleanDni}${firstNombre}${firstApellido}`
}

/**
 * Genera el código de matrícula oficial según orden de llegada y turno:
 * Formato: [correlativo de llegada 3 dígitos][Turno D o N][Año actual 4 dígitos]X
 * 
 * Ejemplo:
 * Para orden 26 en turno de Día (D) en 2026 => "026D2026X"
 * Para orden 26 en turno de Noche (N) en 2026 => "026N2026X"
 */
export function generateCodigoMatricula(
  ordenLlegada: number = 26,
  turno: 'D' | 'N' = 'D',
  anio: number = new Date().getFullYear()
): string {
  const safeOrder = Math.max(1, ordenLlegada || 1)
  const seq = String(safeOrder).padStart(3, '0')
  const validTurno = turno === 'N' ? 'N' : 'D'
  const validAnio = anio || 2026

  return `${seq}${validTurno}${validAnio}X`
}

/**
 * Sugerencia de correo institucional o personal para nuevo estudiante.
 */
export function generateEstudianteEmail(nombres: string, apellidoPaterno: string, dni: string): string {
  const cleanDni = dni ? dni.replace(/\D/g, '').trim() : ''
  const n = nombres ? nombres.trim().split(' ')[0].toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') : ''
  const a = apellidoPaterno ? apellidoPaterno.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') : ''

  if (n && a) {
    return cleanDni ? `${n}.${a}.${cleanDni}@modux.edu.pe` : `${n}.${a}@modux.edu.pe`
  }
  if (cleanDni) {
    return `est${cleanDni}@modux.edu.pe`
  }
  return ''
}
