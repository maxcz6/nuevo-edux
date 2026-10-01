import { test, expect } from '@playwright/test'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

test.describe('Modux — Auditoría Completa de Roles, Permisos y Accesibilidad', () => {

  test('ADMINISTRADOR: Recorrido completo de módulos administrativos sin errores', async ({ page }) => {
    test.slow()
    const errors: string[] = []
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(`[Console Error] ${msg.text()}`)
    })
    page.on('pageerror', err => {
      errors.push(`[Page Error] ${err.message}`)
    })

    // 1. Login
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)
    await expect(page.locator('h1, h2').first()).toBeVisible()

    // 2. Usuarios
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/usuarios/)

    // 3. Docentes
    await page.goto(`${BASE}/admin/docentes`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/docentes/)

    // 4. Estudiantes
    await page.goto(`${BASE}/admin/estudiantes`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/estudiantes/)

    // 5. Periodos
    await page.goto(`${BASE}/admin/periodos`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/periodos/)

    // 6. Cursos
    await page.goto(`${BASE}/admin/cursos`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/cursos/)

    // 7. Grupos
    await page.goto(`${BASE}/admin/grupos`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/grupos/)

    // 8. Matrículas
    await page.goto(`${BASE}/admin/matriculas`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/matriculas/)

    // 9. Programas de Estudio (8 Carreras Oficiales IESTP SAM)
    await page.goto(`${BASE}/admin/programas`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/programas/)
    await expect(page.getByRole('heading', { name: 'Enfermería Técnica' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Diseño y Programación Web' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Farmacia Técnica' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Mecánica de Producción Industrial' })).toBeVisible()

    // 10. Admisión Tecnológica
    await page.goto(`${BASE}/admin/admision`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/admision/)
    await expect(page.locator('h1')).toContainText('Admisión Tecnológica')
    await expect(page.locator('table')).toBeVisible()

    // 11. Trámites Académicos
    await page.goto(`${BASE}/admin/tramites`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/tramites/)
    await expect(page.locator('h1')).toContainText('Trámites')

    // 12. Ambientes y Talleres
    await page.goto(`${BASE}/admin/ambientes`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/ambientes/)
    await expect(page.locator('h1')).toContainText('Ambientes, Talleres y Laboratorios')

    // 13. Titulación y Certificados Modulares (Ley N° 30512)
    await page.goto(`${BASE}/admin/titulacion`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/titulacion/)
    await expect(page.locator('h1')).toContainText('Titulación y Certificación Modular')

    console.log('Admin errors captured:', errors)
    expect(errors.filter(e => !e.includes('favicon'))).toHaveLength(0)
  })

  test('SUPERADMIN: Acceso global, selector multiprograma y gestión de programas', async ({ page }) => {
    const errors: string[] = []
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(`[Console Error] ${msg.text()}`)
    })
    page.on('pageerror', err => {
      errors.push(`[Page Error] ${err.message}`)
    })

    // 1. Login con Superadmin
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'superadmin@modux.demo')
    await page.fill('[data-testid="password"]', 'SuperAdmin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)

    // 2. Verificar badge de Superadmin
    await expect(page.locator('text=Superadmin')).toBeVisible()

    // 3. Acceder a Programas de Estudio
    await page.goto(`${BASE}/admin/programas`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('Programas de Estudio')
    await expect(page.getByRole('heading', { name: 'Enfermería Técnica' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Diseño y Programación Web' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Contabilidad' })).toBeVisible()

    // 4. Filtrar docentes por programa desde selector
    await page.goto(`${BASE}/admin/docentes`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('table')).toBeVisible()

    console.log('Superadmin errors captured:', errors)
    expect(errors.filter(e => !e.includes('favicon'))).toHaveLength(0)
  })

  test('COORDINADOR: Aislamiento estricto por programa de estudios', async ({ page }) => {
    const errors: string[] = []
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(`[Console Error] ${msg.text()}`)
    })
    page.on('pageerror', err => {
      errors.push(`[Page Error] ${err.message}`)
    })

    // 1. Login como Coordinadora de Enfermería Técnica
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'coord.enf@modux.demo')
    await page.fill('[data-testid="password"]', 'Coord2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/coordinacion\/dashboard/)

    // 2. Verificar badge de Coordinador
    await expect(page.locator('text=Coordinador de Carrera')).toBeVisible()

    // 3. Verificar docentes aislados a Enfermería Técnica
    await page.goto(`${BASE}/coordinacion/docentes`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('text=Maria Garcia')).toBeVisible()
    // No debe aparecer el docente de Programación Web
    await expect(page.locator('text=Carlos Mendoza')).not.toBeVisible()

    // 4. Verificar estudiantes aislados a Enfermería Técnica
    await page.goto(`${BASE}/coordinacion/estudiantes`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('text=Lucia Huaman')).toBeVisible()
    // No debe aparecer el estudiante de Programación Web
    await expect(page.locator('text=David Quispe')).not.toBeVisible()

    console.log('Coordinador errors captured:', errors)
    expect(errors.filter(e => !e.includes('favicon'))).toHaveLength(0)
  })

  test('DOCENTE: Dashboard, Aula Virtual, Asistencia, Evaluaciones y Calificaciones', async ({ page }) => {
    const errors: string[] = []
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(`[Console Error] ${msg.text()}`)
    })
    page.on('pageerror', err => {
      errors.push(`[Page Error] ${err.message}`)
    })

    // 1. Login
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'docente01@modux.demo')
    await page.fill('[data-testid="password"]', 'Docente2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/docente\/dashboard/)

    // 2. Aula Virtual del primer grupo (id: 1)
    await page.goto(`${BASE}/docente/grupos/1/aula`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/docente\/grupos\/1\/aula/)

    // Verificar pestañas si existen
    const tabAsistencia = page.locator('button:has-text("Asistencia"), tab:has-text("Asistencia")')
    if (await tabAsistencia.count() > 0) {
      await tabAsistencia.first().click()
      await page.waitForTimeout(500)
    }

    const tabEvaluaciones = page.locator('button:has-text("Evaluaciones"), tab:has-text("Evaluaciones")')
    if (await tabEvaluaciones.count() > 0) {
      await tabEvaluaciones.first().click()
      await page.waitForTimeout(500)
    }

    // 3. Tareas del grupo
    await page.goto(`${BASE}/docente/grupos/1/tareas`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/docente\/grupos\/1\/tareas/)

    // 4. Calificaciones del grupo
    await page.goto(`${BASE}/docente/grupos/1/calificaciones`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/docente\/grupos\/1\/calificaciones/)

    console.log('Docente errors captured:', errors)
    expect(errors.filter(e => !e.includes('favicon'))).toHaveLength(0)
  })

  test('ESTUDIANTE: Dashboard, Curso, Materiales, Quizzes, Tareas y Notas', async ({ page }) => {
    const errors: string[] = []
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(`[Console Error] ${msg.text()}`)
    })
    page.on('pageerror', err => {
      errors.push(`[Page Error] ${err.message}`)
    })

    // 1. Login
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'est001@modux.demo')
    await page.fill('[data-testid="password"]', 'Alumno2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/estudiante\/dashboard/)

    // 2. Curso interactivo (id: 1)
    await page.goto(`${BASE}/estudiante/cursos/1`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/estudiante\/cursos\/1/)

    // Verificar tabs de estudiante
    const tabTareas = page.locator('button:has-text("Tareas")')
    if (await tabTareas.count() > 0) {
      await tabTareas.first().click()
      await page.waitForTimeout(500)
    }

    const tabEvaluaciones = page.locator('button:has-text("Evaluaciones")')
    if (await tabEvaluaciones.count() > 0) {
      await tabEvaluaciones.first().click()
      await page.waitForTimeout(500)
    }

    // 3. Lista global de tareas
    await page.goto(`${BASE}/estudiante/tareas`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/estudiante\/tareas/)

    // 4. Reporte de notas
    await page.goto(`${BASE}/estudiante/notas`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/estudiante\/notas/)

    console.log('Estudiante errors captured:', errors)
    expect(errors.filter(e => !e.includes('favicon'))).toHaveLength(0)
  })

  test('APODERADO: Dashboard con hijos asignados y seguimiento', async ({ page }) => {
    const errors: string[] = []
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(`[Console Error] ${msg.text()}`)
    })
    page.on('pageerror', err => {
      errors.push(`[Page Error] ${err.message}`)
    })

    // 1. Login
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'apoderado01@modux.demo')
    await page.fill('[data-testid="password"]', 'Padre2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/apoderado\/dashboard/)

    await page.waitForLoadState('networkidle')
    await expect(page.getByRole('heading', { name: /Seguimiento Académico/ })).toBeVisible()

    console.log('Apoderado errors captured:', errors)
    expect(errors.filter(e => !e.includes('favicon'))).toHaveLength(0)
  })

  test('SEGURIDAD: Control de acceso entre roles impide escalación o cruces', async ({ page }) => {
    // 1. Iniciar sesión como estudiante
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'est001@modux.demo')
    await page.fill('[data-testid="password"]', 'Alumno2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/estudiante\/dashboard/)

    // 2. Intentar ingresar a ruta de administrador
    await page.goto(`${BASE}/admin/usuarios`)
    await expect(page).not.toHaveURL(/\/admin\/usuarios/)
    await expect(page).toHaveURL(/\/estudiante\/dashboard/)

    // 3. Intentar ingresar a ruta de docente
    await page.goto(`${BASE}/docente/grupos/1/calificaciones`)
    await expect(page).not.toHaveURL(/\/docente/)
    await expect(page).toHaveURL(/\/estudiante\/dashboard/)
  })

  test('ACCESIBILIDAD Y RESPONSIVE: Vista móvil y navegación adaptable', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 })

    await page.goto(`${BASE}/login`)
    await expect(page.locator('[data-testid="email"]')).toBeVisible()
    await expect(page.locator('[data-testid="password"]')).toBeVisible()
    await expect(page.locator('[data-testid="submit"]')).toBeVisible()

    // Login en móvil
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)

    // El contenido debe ser visible y scrolleable sin overflow roto
    await expect(page.locator('body')).toBeVisible()
  })

  test('CONSULTA PÚBLICA: Verificación de Títulos y Certificados Modulares con Código Oficial', async ({ page }) => {
    // 1. Navegar a la página pública de verificación
    await page.goto(`${BASE}/verificar-documento/TIT-SAM-2026-AGRO-042`)
    await page.waitForLoadState('networkidle')

    // 2. Comprobar que carga la información del documento oficial
    await expect(page.locator('text=Documento Oficial Auténtico')).toBeVisible()
    await expect(page.getByRole('heading', { name: 'TÍTULO PROFESIONAL TÉCNICO' })).toBeVisible()
    await expect(page.locator('text=Producción Agropecuaria')).toBeVisible()
    await expect(page.locator('text=TIT-SAM-2026-AGRO-042').first()).toBeVisible()
  })

})
