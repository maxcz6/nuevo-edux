import { describe, it, expect } from 'vitest'
import { isJwtFormat, decodeTokenClaims, isTokenExpired, sanitizeInput } from './tokenSecurity'

describe('Token Security & JOSE JWT Utilities', () => {
  // Simular un JWT estándar codificado en base64url: { "sub": "123", "exp": 2500000000, "roles": ["ADMINISTRADOR"] }
  const payloadFuture = btoa(JSON.stringify({ sub: 'user-01', exp: 2500000000, roles: ['ADMIN'] })).replace(/=/g, '')
  const payloadPast = btoa(JSON.stringify({ sub: 'user-02', exp: 1000000000, roles: ['DOCENTE'] })).replace(/=/g, '')
  const validJwtFuture = `eyJhbGciOiJIUzI1NiJ9.${payloadFuture}.fake_signature`
  const expiredJwtPast = `eyJhbGciOiJIUzI1NiJ9.${payloadPast}.fake_signature`
  const opaqueSanctumToken = '1|XfK0pQ7ZmL123456789abcdef'

  it('detecta correctamente formato JWT vs token opaco Sanctum', () => {
    expect(isJwtFormat(validJwtFuture)).toBe(true)
    expect(isJwtFormat(opaqueSanctumToken)).toBe(false)
    expect(isJwtFormat('')).toBe(false)
  })

  it('decodifica claims de JWT usando JOSE', () => {
    const claims = decodeTokenClaims(validJwtFuture)
    expect(claims).not.toBeNull()
    expect(claims?.sub).toBe('user-01')
    expect(claims?.exp).toBe(2500000000)
    expect(claims?.roles).toContain('ADMIN')
  })

  it('retorna null para tokens opacos sin lanzar excepción', () => {
    expect(decodeTokenClaims(opaqueSanctumToken)).toBeNull()
  })

  it('valida si un token ha expirado', () => {
    // Token con exp en el pasado debe estar expirado
    expect(isTokenExpired(expiredJwtPast)).toBe(true)

    // Token con exp en el futuro no debe estar expirado
    expect(isTokenExpired(validJwtFuture)).toBe(false)

    // Token opaco de Sanctum no contiene exp client-side, se delega al servidor
    expect(isTokenExpired(opaqueSanctumToken)).toBe(false)

    // Token nulo o vacío se considera expirado
    expect(isTokenExpired('')).toBe(true)
  })

  describe('Sanitización de entradas (Input Protection & CRLF Prevention)', () => {
    it('elimina caracteres de salto de línea (\r, \n) para prevenir CRLF Injection', () => {
      const maliciousEmail = "admin@sam.edu.pe\r\nBcc: hacker@external.com"
      const cleaned = sanitizeInput(maliciousEmail)
      expect(cleaned).toBe("admin@sam.edu.peBcc: hacker@external.com")
      expect(cleaned).not.toContain("\r")
      expect(cleaned).not.toContain("\n")
    })

    it('elimina caracteres nulos y recorta espacios', () => {
      const dirty = "  user\0name  "
      expect(sanitizeInput(dirty)).toBe("username")
    })

    it('respeta la longitud máxima indicada', () => {
      const longInput = "123456789012345"
      expect(sanitizeInput(longInput, { maxLength: 8 })).toBe("12345678")
    })
  })
})
