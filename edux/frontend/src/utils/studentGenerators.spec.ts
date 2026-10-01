import { describe, it, expect } from 'vitest'
import {
  generateEstudianteUsername,
  generateCodigoMatricula,
  generateEstudianteEmail
} from '@/utils/studentGenerators'

describe('studentGenerators', () => {
  it('genera el username con formato #(DNI)(primer letra nom minúscula)(primer apellido Mayúscula)', () => {
    // Ejemplo exacto requerido por el usuario:
    // Para Max Paucar Ramos con DNI 60981968 => #60981968mP
    const username = generateEstudianteUsername('60981968', 'Max', 'Paucar')
    expect(username).toBe('#60981968mP')
  })

  it('genera el username correctamente con espacios adicionales o minúsculas', () => {
    const username = generateEstudianteUsername(' 72123456 ', ' Carlos Alberto ', ' gomez ')
    expect(username).toBe('#72123456cG')
  })

  it('genera el código de matrícula oficial con correlativo de 3 dígitos, turno D/N, año 2026 y X', () => {
    // Para llegada 26 en turno de Día (D) => 026D2026X
    const codigoDia = generateCodigoMatricula(26, 'D', 2026)
    expect(codigoDia).toBe('026D2026X')

    // Para llegada 26 en turno de Noche (N) => 026N2026X
    const codigoNoche = generateCodigoMatricula(26, 'N', 2026)
    expect(codigoNoche).toBe('026N2026X')
  })

  it('genera el correo institucional sugerido', () => {
    const email = generateEstudianteEmail('Max', 'Paucar', '60981968')
    expect(email).toBe('max.paucar.60981968@modux.edu.pe')
  })
})
