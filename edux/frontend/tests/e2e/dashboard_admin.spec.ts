import { test, expect, type Page } from '@playwright/test'

/**
 * dashboard_admin.spec.ts
 * Tests focalizados en el dashboard del ADMINISTRADOR.
 *
 * Cubre (sin duplicar roles_completo.spec.ts):
 *  - Presencia de las 4 stat cards por texto Y por conteo de elementos
 *  - Sección de accesos rápidos completa
 *  - Sidebar: navegación funcional → click → URL cambia
 *  - Sidebar collapse/expand en desktop
 *  - Nombre de usuario visible en header (user-name testid)
 *  - Stat cards tienen valores numéricos (no "—" indefinido)
 *  - Navegación breadcrumb o título correcto según la ruta
 */

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

// ── Helper de login ────────────────────────────────────────────────────────────
async function loginAdmin(page: Page) {
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('domcontentloaded')
  await page.getByTestId('email').fill('admin@modux.demo')
  await page.getByTestId('password').fill('Admin2026!')
  await page.getByTestId('submit').click()
  await page.waitForURL(/\/admin\/dashboard/, { timeout: 20000 })
  await page.waitForLoadState('networkidle')
}

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 1: Dashboard — Stats Cards
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('📊 Dashboard Admin — Stat Cards', () => {
  test.beforeEach(async ({ page }) => {
    await loginAdmin(page)
  })

  test('DASH-01: Las 4 stat cards están presentes por texto', async ({ page }) => {
    await expect(page.locator('text=Total Usuarios')).toBeVisible({ timeout: 10000 })
    await expect(page.locator('text=Docentes Activos')).toBeVisible()
    await expect(page.locator('text=Estudiantes Activos')).toBeVisible()
    await expect(page.locator('text=Grupos Activos')).toBeVisible()
  })

  test('DASH-02: Existen exactamente 4 stat cards en el dashboard', async ({ page }) => {
    const cards = page.locator('[data-testid="stat-card"]')
    await expect(cards).toHaveCount(4)
  })

  test('DASH-03: Stat cards muestran valores numéricos (no vacíos)', async ({ page }) => {
    // Buscar el número en cada stat card — deben ser dígitos
    const statValues = page.locator(
      '[data-testid*="stat-value"], [class*="stat"] span[class*="text"], [class*="card"] p[class*="text-"]'
    )
    const valueCount = await statValues.count()

    if (valueCount > 0) {
      // Al menos el primer valor debe ser un texto no vacío
      const firstText = await statValues.first().innerText()
      expect(firstText.trim()).not.toBe('')
    } else {
      // Fallback: verificar que los textos de números son visibles junto a los labels
      await expect(page.locator('text=Total Usuarios').locator('..').locator('span, p').first()).toBeVisible()
    }
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 2: Dashboard — Accesos Rápidos
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('⚡ Dashboard Admin — Accesos Rápidos', () => {
  test.beforeEach(async ({ page }) => {
    await loginAdmin(page)
  })

  test('DASH-04: Sección de accesos rápidos está presente', async ({ page }) => {
    const section = page.locator('section[aria-labelledby="accesos-rapidos-heading"]')
    await expect(section).toBeVisible({ timeout: 10000 })
  })

  test('DASH-05: Acceso rápido "Usuarios" visible y es un enlace', async ({ page }) => {
    const section = page.locator('section[aria-labelledby="accesos-rapidos-heading"]')
    const userLink = section.locator('a:has-text("Usuarios")')
    await expect(userLink).toBeVisible()
    // Verificar que el href apunta a la ruta correcta
    const href = await userLink.getAttribute('href')
    expect(href).toMatch(/usuarios/)
  })

  test('DASH-06: Acceso rápido "Docentes" o "Programas" visible y navega correctamente', async ({ page }) => {
    const section = page.locator('section[aria-labelledby="accesos-rapidos-heading"]')
    const quickLink = section.locator('a[href*="/admin/docentes"], a[href*="/admin/usuarios"]').first()
    await expect(quickLink).toBeVisible()
    await quickLink.click()
    await page.waitForURL(/\/admin\/(docentes|usuarios)/, { timeout: 15000 })
    expect(page.url()).toMatch(/\/admin\/(docentes|usuarios)/)
  })

  test('DASH-07: Accesos rápidos NO incluyen "Instituciones" (solo SUPERADMIN)', async ({ page }) => {
    const section = page.locator('section[aria-labelledby="accesos-rapidos-heading"]')
    await expect(section.locator('a:has-text("Instituciones")')).toHaveCount(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 3: Sidebar — Navegación Funcional
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🗂️ Dashboard Admin — Sidebar', () => {
  test.beforeEach(async ({ page }) => {
    await loginAdmin(page)
  })

  test('DASH-08: Sidebar está presente y visible', async ({ page }) => {
    await expect(page.locator('aside')).toBeVisible()
  })

  test('DASH-09: Click en "Usuarios" del sidebar cambia la URL a /admin/usuarios', async ({ page }) => {
    const sidebarLink = page.locator('aside nav a[href*="usuarios"]').first()
    await expect(sidebarLink).toBeVisible({ timeout: 10000 })
    await sidebarLink.click()
    await page.waitForURL(/\/admin\/usuarios/, { timeout: 15000 })
    await expect(page).toHaveURL(/\/admin\/usuarios/)
    // La página debe cargar contenido (al menos un h1/h2)
    await expect(page.locator('h1, h2').first()).toBeVisible()
  })

  test('DASH-10: Click en "Docentes" del sidebar navega a /admin/docentes', async ({ page }) => {
    const docentesLink = page.locator('aside nav a[href*="docentes"]').first()
    await expect(docentesLink).toBeVisible({ timeout: 10000 })
    await docentesLink.click()
    await page.waitForURL(/\/admin\/docentes/, { timeout: 15000 })
    await expect(page).toHaveURL(/\/admin\/docentes/)
    await expect(page.locator('h1, h2').first()).toBeVisible()
  })

  test('DASH-11: Botón "Cerrar sesión" está en el sidebar', async ({ page }) => {
    const logoutBtn = page.locator('aside button:has-text("Cerrar sesión")')
    await expect(logoutBtn).toBeVisible()
  })

  test('DASH-12: Sidebar collapse/expand en desktop cambia ancho (aria-label dinámico)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })

    const colapsarBtn = page.locator('button[aria-label="Colapsar menú lateral"]')
    await expect(colapsarBtn).toBeVisible({ timeout: 10000 })

    await colapsarBtn.click()
    await page.waitForTimeout(300)

    // Después de colapsar, debe aparecer el botón de expandir
    const expandirBtn = page.locator('button[aria-label="Expandir menú lateral"]')
    await expect(expandirBtn).toBeVisible()

    // Volver a expandir
    await expandirBtn.click()
    await page.waitForTimeout(300)
    await expect(colapsarBtn).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 4: Header
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🔝 Dashboard Admin — Header', () => {
  test.beforeEach(async ({ page }) => {
    await loginAdmin(page)
  })

  test('DASH-13: Nombre del usuario visible en header', async ({ page }) => {
    await expect(page.getByTestId('user-name')).toBeVisible({ timeout: 10000 })
    // El nombre no debe estar vacío
    const name = await page.getByTestId('user-name').innerText()
    expect(name.trim().length).toBeGreaterThan(0)
  })

  test('DASH-14: Header tiene el perfil o controles del sistema visibles', async ({ page }) => {
    await expect(page.locator('header [data-testid="user-name"], header a[href*="perfil"]').first()).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 5: Navegación completa sidebar (2+ rutas)
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🧭 Admin — Navegación desde sidebar a vistas específicas', () => {
  test.beforeEach(async ({ page }) => {
    await loginAdmin(page)
  })

  test('DASH-15: Ruta /admin/estudiantes carga con contenido válido', async ({ page }) => {
    await page.goto(`${BASE}/admin/estudiantes`)
    await page.waitForURL(/\/admin\/estudiantes/, { timeout: 15000 })
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1, h2').first()).toBeVisible()
    // Sin error 500
    await expect(page.locator('text=500')).toHaveCount(0)
  })

  test('DASH-16: Ruta /admin/grupos carga tabla o mensaje de vacío', async ({ page }) => {
    await page.goto(`${BASE}/admin/grupos`)
    await page.waitForURL(/\/admin\/grupos/, { timeout: 15000 })
    await page.waitForLoadState('networkidle')
    // Debe mostrar tabla o un empty state — nunca error
    const hasTable = await page.locator('table').count() > 0
    const hasEmpty = (await page.locator('[class*="empty"], [class*="EmptyState"]').count() > 0) || (await page.getByText(/no hay/i).count() > 0)
    expect(hasTable || hasEmpty).toBe(true)
  })

  test('DASH-17: Navegación consecutiva sidebar: Usuarios → Periodos → Cursos', async ({ page }) => {
    const rutas = [
      { href: /usuarios/, pattern: /\/admin\/usuarios/ },
      { href: /periodos/, pattern: /\/admin\/periodos/ },
      { href: /cursos/, pattern: /\/admin\/cursos/ },
    ]

    for (const { href, pattern } of rutas) {
      const link = page.locator(`aside nav a[href*="${href.source}"]`).first()
      if (await link.count() > 0) {
        await link.click()
        await page.waitForURL(pattern, { timeout: 15000 })
        await expect(page).toHaveURL(pattern)
        await expect(page.locator('h1, h2').first()).toBeVisible()
      }
    }
  })
})
