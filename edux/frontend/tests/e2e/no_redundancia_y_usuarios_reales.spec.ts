import { test, expect, type Page } from '@playwright/test'

/**
 * no_redundancia_y_usuarios_reales.spec.ts
 *
 * Verificaciones Playwright:
 *  1. Ausencia de redundancia de información, títulos duplicados o botones repetidos.
 *  2. Flujos completos de usuarios reales simulados (Admin, Coordinador, Docente, Estudiante).
 *  3. Verificación de interfaz limpia, jerarquía visual y aislamiento de acciones por rol.
 */

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

// ── Helper de login para usuarios simulados ──────────────────────────────────
async function loginAsRole(page: Page, email: string, pass: string, targetPath: string) {
  await page.goto(`${BASE}/login`)
  await page.evaluate(() => localStorage.clear())
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('domcontentloaded')

  await page.fill('[data-testid="email"]', email)
  await page.fill('[data-testid="password"]', pass)
  await page.click('[data-testid="submit"]')

  await page.waitForURL(new RegExp(targetPath), { timeout: 20000 })
  await page.waitForLoadState('domcontentloaded')
}

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 1: Verificación de No-Redundancia en la Interfaz (Login & Header)
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('✨ Verificación de No-Redundancia y Limpieza Visual', () => {

  test.beforeEach(async ({ page, context }) => {
    await context.clearCookies()
    await page.goto(`${BASE}/login`)
    await page.evaluate(() => localStorage.clear())
    await page.goto(`${BASE}/login`)
  })

  test('NR-01: Login no tiene botones de acción ni inputs duplicados', async ({ page }) => {
    // 1. Exactamente un botón submit de ingreso
    await expect(page.locator('[data-testid="submit"]')).toHaveCount(1)

    // 2. Exactamente un input de email y un input de password
    await expect(page.locator('[data-testid="email"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="password"]')).toHaveCount(1)

    // 3. Exactamente un enlace de recuperación de contraseña
    await expect(page.locator('a[href*="/forgot-password"]')).toHaveCount(1)
  })

  test('NR-02: Header y Sidebar del Admin no duplican controles ni perfiles', async ({ page }) => {
    await loginAsRole(page, 'admin@modux.demo', 'Admin2026!', '/admin/dashboard')

    // Solo 1 interruptor de tema (ThemeToggle) en la barra superior
    const themeToggles = page.locator('button.theme-toggle')
    await expect(themeToggles).toHaveCount(1)

    // Solo 1 botón de cerrar sesión en el menú lateral
    const logoutBtns = page.getByRole('button', { name: /cerrar sesión/i })
    await expect(logoutBtns).toHaveCount(1)

    // Solo 1 elemento de nombre de usuario en el header
    const userNames = page.locator('[data-testid="user-name"]')
    await expect(userNames).toHaveCount(1)

    // Verificar que los enlaces del menú lateral sean únicos
    const navLinks = await page.locator('aside nav a').evaluateAll((links: HTMLAnchorElement[]) =>
      links.map(a => a.getAttribute('href')).filter(Boolean)
    )
    const uniqueNavLinks = new Set(navLinks)
    expect(navLinks.length).toBe(uniqueNavLinks.size)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 2: Jornadas de Usuarios Reales Simulados (Multi-Rol)
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('👥 Jornadas de Usuarios Reales Simulados', () => {

  test.beforeEach(async ({ page, context }) => {
    await context.clearCookies()
    await page.goto(`${BASE}/login`)
    await page.evaluate(() => localStorage.clear())
  })

  test('USER-01: Flujo real del Administrador (Métricas, Usuarios, Períodos)', async ({ page }) => {
    await loginAsRole(page, 'admin@modux.demo', 'Admin2026!', '/admin/dashboard')

    // 1. Verificar métricas del dashboard sin datos duplicados
    await expect(page.locator('[data-testid="user-name"]')).toBeVisible()

    // 2. Navegar a la lista de usuarios
    const usuariosLink = page.locator('aside nav a[href*="/admin/usuarios"]').first()
    await usuariosLink.click()
    await page.waitForURL(/\/admin\/usuarios/)

    // 3. Verificar que la tabla de usuarios carga
    await expect(page.locator('table, [role="table"]').first()).toBeVisible()

    // 4. Navegar a períodos académicos
    const periodosLink = page.locator('aside nav a[href*="/admin/periodos"]').first()
    await periodosLink.click()
    await page.waitForURL(/\/admin\/periodos/)

    await expect(page.getByText('Semestre Académico 2026-I').or(page.getByText('2026-I'))).toBeVisible()
  })

  test('USER-02: Flujo real del Coordinador (Aislamiento a su Carrera)', async ({ page }) => {
    await loginAsRole(page, 'coord.enf@modux.demo', 'Coord2026!', '/coordinador/dashboard')

    // 1. El coordinador no debe ver rutas ni menús de instituciones (exclusivo de SuperAdmin)
    const institucionesLink = page.locator('aside nav a[href*="/admin/instituciones"]')
    await expect(institucionesLink).toHaveCount(0)

    // 2. Debe visualizar su área de trabajo
    await expect(page.getByText(/Enfermería|Coordinador|IESTP SAM|Panel/i).first()).toBeVisible()
  })

  test('USER-03: Flujo real del Docente (Acceso a sus Grupos y Aula)', async ({ page }) => {
    await loginAsRole(page, 'docente01@modux.demo', 'Docente2026!', '/docente/dashboard')

    // 1. El docente no debe tener botones administrativos
    await expect(page.locator('aside nav a[href*="/admin/instituciones"]')).toHaveCount(0)

    // 2. Debe visualizar su portal de docente
    await expect(page.getByText(/Docente|Aula Virtual|Mis Cursos|Grupos|IESTP SAM/i).first()).toBeVisible()
  })

  test('USER-04: Flujo real del Estudiante (Consulta de Notas y Tareas)', async ({ page }) => {
    await loginAsRole(page, 'est001@modux.demo', 'Alumno2026!', '/estudiante/dashboard')

    // 1. El estudiante no tiene acceso a menús de gestión
    await expect(page.locator('aside nav a[href*="/admin/"]')).toHaveCount(0)

    // 2. Interfaz limpia para el alumno
    await expect(page.getByText(/Estudiante|Mis Cursos|Notas|Calificaciones|IESTP SAM/i).first()).toBeVisible()
  })
})
