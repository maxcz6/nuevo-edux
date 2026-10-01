import { describe, it, expect } from 'vitest'
import { SignJWT } from 'jose'
import {
  isJwtFormat,
  decodeTokenClaims,
  isTokenExpired,
  inspectJwtHeader,
  isSecureAlgorithm,
  verifyJwtSignature,
  sanitizeInput,
} from './tokenSecurity'

describe('Token Security & JOSE JWT Full Verification Suite', () => {
  const secretKey = new TextEncoder().encode('super-secret-key-32-chars-long!!')
  const differentKey = new TextEncoder().encode('different-key-32-chars-long-abc!')

  const payloadFuture = btoa(JSON.stringify({ sub: 'user-01', exp: 2500000000, roles: ['ADMIN'] })).replace(/=/g, '')
  const payloadPast = btoa(JSON.stringify({ sub: 'user-02', exp: 1000000000, roles: ['DOCENTE'] })).replace(/=/g, '')
  const validJwtFuture = `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.${payloadFuture}.fake_signature`
  const expiredJwtPast = `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.${payloadPast}.fake_signature`
  const opaqueSanctumToken = '1|XfK0pQ7ZmL123456789abcdef'
  const algNoneToken = `eyJhbGciOiJub25lIiwidHlwIjoiSldUIn0.${payloadFuture}.`

  it('detecta correctamente formato JWT vs token opaco Sanctum', () => {
    expect(isJwtFormat(validJwtFuture)).toBe(true)
    expect(isJwtFormat(opaqueSanctumToken)).toBe(false)
    expect(isJwtFormat('')).toBe(false)
    expect(isJwtFormat('two.parts')).toBe(false)
  })

  it('inspecciona y valida encabezados protegidos del JWT', () => {
    const header = inspectJwtHeader(validJwtFuture)
    expect(header?.alg).toBe('HS256')
    expect(header?.typ).toBe('JWT')
    expect(inspectJwtHeader(opaqueSanctumToken)).toBeNull()
  })

  it('rechaza tokens inseguros con alg: none', () => {
    expect(isSecureAlgorithm(validJwtFuture)).toBe(true)
    expect(isSecureAlgorithm(algNoneToken)).toBe(false)
  })

  it('decodifica claims de JWT usando JOSE decodeJwt', () => {
    const claims = decodeTokenClaims(validJwtFuture)
    expect(claims).not.toBeNull()
    expect(claims?.sub).toBe('user-01')
    expect(claims?.exp).toBe(2500000000)
    expect(claims?.roles).toContain('ADMIN')
  })

  it('retorna null para tokens opacos sin lanzar excepción', () => {
    expect(decodeTokenClaims(opaqueSanctumToken)).toBeNull()
  })

  it('valida si un token ha expirado con buffer de tiempo', () => {
    expect(isTokenExpired(expiredJwtPast)).toBe(true)
    expect(isTokenExpired(validJwtFuture)).toBe(false)
    expect(isTokenExpired(opaqueSanctumToken)).toBe(false)
    expect(isTokenExpired('')).toBe(true)
  })

  describe('Verificación Criptográfica de Firmas con JOSE (jwtVerify)', () => {
    it('verifica exitosamente un JWT firmado con HS256', async () => {
      const jwt = await new SignJWT({ sub: 'student-99', roles: ['ESTUDIANTE'] })
        .setProtectedHeader({ alg: 'HS256' })
        .setIssuedAt()
        .setIssuer('https://edux.institucion.edu.pe')
        .setAudience('edux-app')
        .setExpirationTime('2h')
        .sign(secretKey)

      const result = await verifyJwtSignature(jwt, secretKey, {
        issuer: 'https://edux.institucion.edu.pe',
        audience: 'edux-app',
      })

      expect(result.isValid).toBe(true)
      expect(result.payload?.sub).toBe('student-99')
      expect(result.payload?.roles).toContain('ESTUDIANTE')
    })

    it('rechaza tokens manipulados o alterados (Tampered Tokens)', async () => {
      const jwt = await new SignJWT({ sub: 'student-99', roles: ['ESTUDIANTE'] })
        .setProtectedHeader({ alg: 'HS256' })
        .setExpirationTime('2h')
        .sign(secretKey)

      // Alterar el payload (cambiar a rol SUPERADMIN fraudulentamente)
      const parts = jwt.split('.')
      const tamperedPayload = btoa(JSON.stringify({ sub: 'student-99', roles: ['SUPERADMIN'] })).replace(/=/g, '')
      const tamperedJwt = `${parts[0]}.${tamperedPayload}.${parts[2]}`

      const result = await verifyJwtSignature(tamperedJwt, secretKey)
      expect(result.isValid).toBe(false)
      expect(result.payload).toBeNull()
    })

    it('rechaza tokens firmados con clave secreta incorrecta', async () => {
      const jwt = await new SignJWT({ sub: 'admin-01' })
        .setProtectedHeader({ alg: 'HS256' })
        .setExpirationTime('1h')
        .sign(differentKey)

      const result = await verifyJwtSignature(jwt, secretKey)
      expect(result.isValid).toBe(false)
    })

    it('rechaza tokens expirados durante la verificación criptográfica', async () => {
      const jwt = await new SignJWT({ sub: 'user-expired' })
        .setProtectedHeader({ alg: 'HS256' })
        .setExpirationTime('-10s') // Expirado en el pasado
        .sign(secretKey)

      const result = await verifyJwtSignature(jwt, secretKey)
      expect(result.isValid).toBe(false)
    })
  })

  describe('Sanitización y Mitigación de Inyecciones (Input Protection & CRLF/XSS)', () => {
    it('elimina caracteres de salto de línea (\r, \n) para prevenir CRLF Injection', () => {
      const maliciousEmail = "admin@sam.edu.pe\r\nBcc: hacker@external.com"
      const cleaned = sanitizeInput(maliciousEmail)
      expect(cleaned).toBe("admin@sam.edu.peBcc: hacker@external.com")
      expect(cleaned).not.toContain("\r")
      expect(cleaned).not.toContain("\n")
    })

    it('elimina caracteres nulos (Null Byte Injection) y recorta espacios', () => {
      const dirty = "  user\0name\0  "
      expect(sanitizeInput(dirty)).toBe("username")
    })

    it('escapa caracteres especiales para prevenir Cross-Site Scripting (XSS)', () => {
      const xssPayload = '<script>alert("xss")</script>'
      const sanitized = sanitizeInput(xssPayload, { escapeHtml: true })
      expect(sanitized).toBe('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;')
      expect(sanitized).not.toContain('<')
      expect(sanitized).not.toContain('>')
    })

    it('respeta la longitud máxima indicada truncando payloads excesivos', () => {
      const longInput = "123456789012345"
      expect(sanitizeInput(longInput, { maxLength: 8 })).toBe("12345678")
    })

    it('maneja valores null y undefined de forma segura sin excepciones', () => {
      expect(sanitizeInput(null)).toBe('')
      expect(sanitizeInput(undefined)).toBe('')
    })
  })
})
