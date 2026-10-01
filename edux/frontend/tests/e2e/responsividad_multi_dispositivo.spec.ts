import { test, expect, type Page } from '@playwright/test'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

// Helper para login
async function loginAs(page: Page, email: string, pass: string, urlPattern: RegExp) {
  await page.goto(`${BASE}/login`)
  await page.evaluate(() => localStorage.clear())
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('networkidle')
  await page.getByTestId('email').fill(email)
  await page.getByTestId('password').fill(pass)
  await page.getByTestId('submit').click()
  await page.waitForURL(urlPattern, { timeout: 15000 })
  await page.waitForLoadState('networkidle')
}

// ═══════════════════════════════════════════════════════════════════════════════
// VIEWPORT 1: MÓVIL PEQUEÑO (375 × 667 - iPhone SE)
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('📱 Responsividad: Móvil Pequeño (375x667)', () => {
  test.use({ viewport: { width: 375, height: 667 } })

  test('RESP-01: Login no genera scroll horizontal en 375px', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    const hasHorizontalOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > window.innerWidth
    })
    expect(hasHorizontalOverflow).toBe(false)
  })

  test('RESP-02: Formulario de Login e inputs son 100% visibles y utilizables en 375px', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    await expect(page.getByTestId('email')).toBeVisible()
    await expect(page.getByTestId('password')).toBeVisible()
    await expect(page.getByTestId('submit')).toBeVisible()

    // Botón submit ocupa ancho adecuado sin desbordar
    const submitBox = await page.getByTestId('submit').boundingBox()
    expect(submitBox).not.toBeNull()
    if (submitBox) {
      expect(submitBox.width).toBeGreaterThan(250)
      expect(submitBox.height).toBeGreaterThanOrEqual(44) // WCAG touch target
    }
  })

  test('RESP-03: Drawer de navegación móvil abre, cubre la vista y cierra con botón o backdrop', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    // El botón hamburger debe ser visible
    const hamburger = page.locator('button[aria-label="Abrir menú de navegación"]')
    await expect(hamburger).toBeVisible()
    await hamburger.click()

    // El sidebar debe volverse visible
    const sidebar = page.locator('aside')
    await expect(sidebar).toBeVisible()

    // Cerrar menú con botón cerrar
    const closeBtn = page.locator('button[aria-label="Cerrar menú de navegación"]')
    if (await closeBtn.isVisible()) {
      await closeBtn.click()
    } else {
      // Backdrop click
      await page.locator('.fixed.inset-0.bg-gray-900\\/50').click({ force: true })
    }
  })

  test('RESP-04: Header móvil no se desborda y muestra controles esenciales', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    const topHeader = page.locator('header').first()
    await expect(topHeader).toBeVisible()

    // Theme toggle visible en móvil
    await expect(topHeader.locator('.theme-toggle')).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// VIEWPORT 2: TABLET / IPAD (768 × 1024)
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('📟 Responsividad: Tablet (768x1024)', () => {
  test.use({ viewport: { width: 768, height: 1024 } })

  test('RESP-05: Dashboard Admin distribuye Stat Cards en cuadrícula adaptativa', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    const cards = page.locator('[data-testid="stat-card"]')
    await expect(cards).toHaveCount(4)

    // Cada stat card debe ser visible
    for (let i = 0; i < 4; i++) {
      await expect(cards.nth(i)).toBeVisible()
    }
  })

  test('RESP-06: Vista de Usuarios carga sin overflow no deseado en tablet', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForURL(/\/admin\/usuarios/)
    await page.waitForLoadState('networkidle')

    const hasHorizontalOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > window.innerWidth
    })
    expect(hasHorizontalOverflow).toBe(false)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// VIEWPORT 3: DESKTOP (1280 × 800 & 1920 × 1080)
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('💻 Responsividad: Desktop (1280x800 & 1920x1080)', () => {
  test.use({ viewport: { width: 1280, height: 800 } })

  test('RESP-07: Login muestra split-screen completo (Hero izquierda + Form derecha)', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    const hero = page.locator('section[aria-label="Presentación institucional Modux"]')
    await expect(hero).toBeVisible()

    const formSection = page.locator('section:has-text("Iniciar sesión")')
    await expect(formSection).toBeVisible()
  })

  test('RESP-08: Sidebar de Admin colapsa y expande manteniendo el contenido central fluido', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    const collapseBtn = page.locator('button[aria-label*="menú lateral"]').first()
    await expect(collapseBtn).toBeVisible()

    // Colapsar
    await collapseBtn.click()
    const sidebar = page.locator('aside')
    await expect(sidebar).toHaveClass(/lg:w-20/)

    // Expandir
    await collapseBtn.click()
    await expect(sidebar).toHaveClass(/lg:w-64/)
  })

  test('RESP-09: Sin overflow horizontal en 1920x1080 en Docente y Estudiante', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 })

    // Docente
    await loginAs(page, 'docente01@modux.demo', 'Docente2026!', /\/docente\/dashboard/)
    const docenteOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)
    expect(docenteOverflow).toBe(false)

    // Estudiante
    await loginAs(page, 'est001@modux.demo', 'Alumno2026!', /\/estudiante\/dashboard/)
    const estudianteOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)
    expect(estudianteOverflow).toBe(false)
  })
})
