import { decodeJwt } from 'jose'

export interface TokenClaims {
  sub?: string
  iss?: string
  aud?: string | string[]
  exp?: number
  nbf?: number
  iat?: number
  roles?: string[]
  institucion_id?: number
  [key: string]: unknown
}

/**
 * Verifica si un token tiene el formato estándar de JWT (3 segmentos separados por punto).
 */
export function isJwtFormat(token: string): boolean {
  if (!token || typeof token !== 'string') return false
  const parts = token.split('.')
  return parts.length === 3 && parts.every(p => p.length > 0)
}

/**
 * Decodifica de forma segura los claims del token usando JOSE.
 * Si es un token opaco (ej. Sanctum ID|hash), retorna null sin arrojar error.
 */
export function decodeTokenClaims(token: string): TokenClaims | null {
  if (!isJwtFormat(token)) {
    return null
  }
  try {
    return decodeJwt(token) as TokenClaims
  } catch (err) {
    console.warn('[Security] Token no pudo ser decodificado como JWT:', err)
    return null
  }
}

/**
 * Verifica si el token ha expirado.
 * Retorna true si el token está expirado, false si sigue vigente.
 * Para tokens opacos de Sanctum sin exp embebido, retorna false (el backend valida sesión).
 */
export function isTokenExpired(token: string, bufferSeconds = 10): boolean {
  if (!token) return true
  const claims = decodeTokenClaims(token)
  if (!claims || !claims.exp) return false

  const expirationTimeMs = claims.exp * 1000
  const currentTimeMs = Date.now() + (bufferSeconds * 1000)
  return currentTimeMs >= expirationTimeMs
}

/**
 * Sanitizador estricto de entradas de usuario para prevenir XSS y CRLF Injection.
 */
export function sanitizeInput(
  raw: unknown,
  options: {
    trim?: boolean
    removeNewlines?: boolean
    maxLength?: number
  } = {}
): string {
  if (raw === null || raw === undefined) return ''
  let str = String(raw)

  // 1. Eliminar caracteres de control nulos
  str = str.replace(/\0/g, '')

  // 2. CRLF Injection prevention para inputs de una sola línea (emails, DNIs, nombres)
  if (options.removeNewlines ?? true) {
    str = str.replace(/[\r\n]+/g, '')
  }

  // 3. Trim por defecto
  if (options.trim ?? true) {
    str = str.trim()
  }

  // 4. Limitar longitud máxima para prevenir desbordamientos o payloads extensos
  if (options.maxLength && str.length > options.maxLength) {
    str = str.slice(0, options.maxLength)
  }

  return str
}

/**
 * Sincronización de sesión entre pestañas: si se cierra sesión en una pestaña,
 * se desloguea automáticamente en todas las demás de forma inmediata.
 */
export function initCrossTabAuthSync(onLogout: () => void): () => void {
  const handler = (event: StorageEvent) => {
    if (event.key === 'modux_token' && !event.newValue) {
      onLogout()
    }
  }

  window.addEventListener('storage', handler)
  return () => window.removeEventListener('storage', handler)
}
