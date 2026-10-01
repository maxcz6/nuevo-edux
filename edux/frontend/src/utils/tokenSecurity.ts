import { decodeJwt, decodeProtectedHeader, jwtVerify, type JWTPayload, type KeyLike } from 'jose'

export interface TokenClaims extends JWTPayload {
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
 * Inspecciona los encabezados protegidos del JWT usando JOSE.
 * Detecta y previene ataques de tipo "alg: none" o algoritmos no seguros.
 */
export function inspectJwtHeader(token: string): { alg?: string; typ?: string; [key: string]: unknown } | null {
  if (!isJwtFormat(token)) return null
  try {
    return decodeProtectedHeader(token)
  } catch (err) {
    console.warn('[Security] No se pudo leer el header del JWT:', err)
    return null
  }
}

/**
 * Valida que el algoritmo del JWT sea seguro (rechaza alg: 'none').
 */
export function isSecureAlgorithm(token: string, allowedAlgorithms = ['HS256', 'HS384', 'HS512', 'RS256', 'RS384', 'RS512', 'ES256', 'ES384', 'ES512']): boolean {
  const header = inspectJwtHeader(token)
  if (!header || !header.alg) return false
  if (header.alg.toLowerCase() === 'none') return false
  return allowedAlgorithms.includes(header.alg)
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
 * Verifica criptográficamente la firma del JWT usando la clave secreta o pública con JOSE.
 */
export async function verifyJwtSignature(
  token: string,
  secretOrPublicKey: Uint8Array | KeyLike,
  options?: {
    issuer?: string | string[]
    audience?: string | string[]
    currentDate?: Date
    maxTokenAge?: string
  }
): Promise<{ isValid: boolean; payload: TokenClaims | null; error?: string }> {
  if (!isJwtFormat(token)) {
    return { isValid: false, payload: null, error: 'Token format is not JWT' }
  }

  // Prevenir ataques alg: none
  if (!isSecureAlgorithm(token)) {
    return { isValid: false, payload: null, error: 'Insecure or rejected algorithm' }
  }

  try {
    const result = await jwtVerify(token, secretOrPublicKey, options)
    return { isValid: true, payload: result.payload as TokenClaims }
  } catch (err) {
    return { isValid: false, payload: null, error: (err as Error).message }
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
 * Sanitizador estricto de entradas de usuario para prevenir XSS, SQLi strings y CRLF Injection.
 */
export function sanitizeInput(
  raw: unknown,
  options: {
    trim?: boolean
    removeNewlines?: boolean
    maxLength?: number
    escapeHtml?: boolean
  } = {}
): string {
  if (raw === null || raw === undefined) return ''
  let str = String(raw)

  // 1. Eliminar caracteres de control nulos (Null Byte Injection)
  str = str.replace(/\0/g, '')

  // 2. CRLF Injection prevention para inputs de una sola línea (emails, DNIs, nombres)
  if (options.removeNewlines ?? true) {
    str = str.replace(/[\r\n]+/g, '')
  }

  // 3. Escape de caracteres HTML sensibles si se solicita
  if (options.escapeHtml) {
    str = str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
  }

  // 4. Trim por defecto
  if (options.trim ?? true) {
    str = str.trim()
  }

  // 5. Limitar longitud máxima para prevenir desbordamientos o payloads extensos
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
