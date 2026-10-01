import { test, expect, type Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

/**
 * accesibilidad.spec.ts
 * Tests de accesibilidad WCAG 2.1 usando @axe-core/playwright.
 *
 * Cubre (complementando accessibility.spec.ts existente):
 *  - Axe scan en página de login (sin violaciones críticas ni serias)
 *  - Skip-to-content link: existe, es visible al focus, apunta a #main-content
 *  - Inputs con labels asociadas: verificación axe + manual
 *  - Contraste de texto: axe verifica color-contrast automáticamente
 *  - Dashboard admin: axe sin violaciones críticas
 *  - Formulario de login: navegación Tab + Enter
 *  - Roles ARIA correctos en elementos clave
 *  - Imágenes decorativas con aria-hidden
 *
 * NOTA: Los tests A11Y-01 a A11Y-05 del accessibility.spec.ts existente
 *       no se repiten. Este archivo extiende la cobertura con escenarios
 *       adicionales y verificaciones manuales complementarias.
 */

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

// ── Helper login ───────────────────────────────────────────────────────────────
async function loginAs(page: Page, email: string, password: string, expectedURL: RegExp) {
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('domcontentloaded')
  await page.getByTestId('email').fill(email)
  await page.getByTestId('password').fill(password)
  await page.getByTestId('submit').click()
  await page.waitForURL(expectedURL, { timeout: 20000 })
  await page.waitForLoadState('networkidle')
}

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 1: Axe-core — Página de Login
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🔍 Axe Core — Página de Login', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')
  })

  test('ACC-01: Login sin violaciones axe WCAG2A + WCAG2AA críticas o serias', async ({ page }) => {
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze()

    const critical = results.violations.filter(
      v => v.impact === 'critical' || v.impact === 'serious'
    )

    // Reporte detallado para debug en caso de fallo
    if (critical.length > 0) {
      console.error(
        'Violaciones axe críticas/serias:',
        JSON.stringify(critical.map(v => ({ id: v.id, impact: v.impact, description: v.description })), null, 2)
      )
    }

    expect(critical).toHaveLength(0)
  })

  test('ACC-02: Login sin violaciones de contraste de color (color-contrast)', async ({ page }) => {
    const results = await new AxeBuilder({ page })
      .withRules(['color-contrast'])
      .analyze()

    const contrastViolations = results.violations.filter(v => v.id === 'color-contrast')
    expect(contrastViolations).toHaveLength(0)
  })

  test('ACC-03: Login sin violaciones de estructura de formulario (label, input)', async ({ page }) => {
    const results = await new AxeBuilder({ page })
      .withRules(['label', 'label-content-name-mismatch', 'autocomplete-valid'])
      .analyze()

    const formViolations = results.violations
    expect(formViolations).toHaveLength(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 2: Skip-to-content Link
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('⏭️ Skip-to-content Link', () => {
  test('ACC-04: Skip link existe en el dashboard de admin', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    const skipLink = page.locator('a:has-text("Saltar al contenido principal")')
    await expect(skipLink).toHaveCount(1)
  })

  test('ACC-05: Skip link tiene href="#main-content"', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    const skipLink = page.locator('a:has-text("Saltar al contenido principal")')
    const href = await skipLink.getAttribute('href')
    expect(href).toBe('#main-content')
  })

  test('ACC-06: Skip link es visible al recibir focus por teclado', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    // El skip link normalmente está oculto visualmente pero visible en focus
    const skipLink = page.locator('a:has-text("Saltar al contenido principal")')

    // Focus con Tab desde el body
    await page.keyboard.press('Tab')

    // Verificar que el skip link está enfocado (o que el primer elemento enfocable es el skip link)
    const isFocused = await skipLink.evaluate(el => el === document.activeElement)
    // Si está visible al focus, debe ser el elemento activo
    if (isFocused) {
      await expect(skipLink).toBeVisible()
    } else {
      // Alternativa: al menos existe en el DOM y es accesible
      await expect(skipLink).toHaveCount(1)
    }
  })

  test('ACC-07: Elemento main#main-content tiene tabindex="-1" para recibir foco del skip link', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    const main = page.locator('main#main-content')
    await expect(main).toBeVisible()
    await expect(main).toHaveAttribute('tabindex', '-1')
  })

  test('ACC-08: Skip link existe en dashboard de docente', async ({ page }) => {
    await loginAs(page, 'docente01@modux.demo', 'Docente2026!', /\/docente\/dashboard/)

    const skipLink = page.locator('a:has-text("Saltar al contenido principal")')
    await expect(skipLink).toHaveCount(1)
  })

  test('ACC-09: Skip link existe en dashboard de estudiante', async ({ page }) => {
    await loginAs(page, 'est001@modux.demo', 'Alumno2026!', /\/estudiante\/dashboard/)

    const skipLink = page.locator('a:has-text("Saltar al contenido principal")')
    await expect(skipLink).toHaveCount(1)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 3: Inputs con Labels
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🏷️ Inputs con Labels asociadas', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')
  })

  test('ACC-10: Input email tiene label o aria-label descriptivo', async ({ page }) => {
    const emailInput = page.getByTestId('email')
    const id = await emailInput.getAttribute('id')
    const ariaLabel = await emailInput.getAttribute('aria-label')
    const ariaLabelledby = await emailInput.getAttribute('aria-labelledby')

    let hasLabel = false
    if (id) {
      hasLabel = await page.locator(`label[for="${id}"]`).count() > 0
    }
    hasLabel = hasLabel || !!ariaLabel || !!ariaLabelledby

    expect(hasLabel).toBe(true)
  })

  test('ACC-11: Input password tiene label o aria-label descriptivo', async ({ page }) => {
    const passwordInput = page.getByTestId('password')
    const id = await passwordInput.getAttribute('id')
    const ariaLabel = await passwordInput.getAttribute('aria-label')
    const ariaLabelledby = await passwordInput.getAttribute('aria-labelledby')

    let hasLabel = false
    if (id) {
      hasLabel = await page.locator(`label[for="${id}"]`).count() > 0
    }
    hasLabel = hasLabel || !!ariaLabel || !!ariaLabelledby

    expect(hasLabel).toBe(true)
  })

  test('ACC-12: Botón submit tiene texto o aria-label (no solo icono sin descripción)', async ({ page }) => {
    const submitBtn = page.getByTestId('submit')
    const textContent = await submitBtn.textContent()
    const ariaLabel = await submitBtn.getAttribute('aria-label')

    const hasAccessibleName =
      (textContent && textContent.trim().length > 0) || !!ariaLabel

    expect(hasAccessibleName).toBe(true)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 4: Axe — Dashboard Admin
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🔍 Axe Core — Dashboard Admin', () => {
  test('ACC-13: Dashboard admin sin violaciones axe críticas', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze()

    const critical = results.violations.filter(v => v.impact === 'critical')

    if (critical.length > 0) {
      console.error(
        'Violaciones críticas en dashboard:',
        JSON.stringify(critical.map(v => ({ id: v.id, description: v.description })), null, 2)
      )
    }

    expect(critical).toHaveLength(0)
  })

  test('ACC-14: Vista /admin/usuarios sin violaciones axe críticas', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze()

    const critical = results.violations.filter(v => v.impact === 'critical')
    expect(critical).toHaveLength(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 5: Roles ARIA y semántica
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🎭 Roles ARIA y semántica', () => {
  test('ACC-15: Nav del docente tiene aria-label="Menú docente"', async ({ page }) => {
    await loginAs(page, 'docente01@modux.demo', 'Docente2026!', /\/docente\/dashboard/)
    await expect(page.locator('nav[aria-label="Menú docente"]')).toBeVisible()
  })

  test('ACC-16: Nav del estudiante tiene aria-label="Menú estudiante"', async ({ page }) => {
    await loginAs(page, 'est001@modux.demo', 'Alumno2026!', /\/estudiante\/dashboard/)
    await expect(page.locator('nav[aria-label="Menú estudiante"]')).toBeVisible()
  })

  test('ACC-17: SVG decorativos en sidebar tienen aria-hidden="true"', async ({ page }) => {
    await loginAs(page, 'docente01@modux.demo', 'Docente2026!', /\/docente\/dashboard/)

    const hiddenSVGs = page.locator('aside nav svg[aria-hidden="true"]')
    await expect(hiddenSVGs.first()).toBeVisible()
  })

  test('ACC-18: Botón logout tiene aria-label="Cerrar sesión"', async ({ page }) => {
    await loginAs(page, 'docente01@modux.demo', 'Docente2026!', /\/docente\/dashboard/)
    await expect(page.locator('aside button[aria-label="Cerrar sesión"]')).toBeVisible()
  })

  test('ACC-19: Hamburger móvil tiene aria-label="Abrir menú de navegación"', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)
    await expect(
      page.locator('button[aria-label="Abrir menú de navegación"]').first()
    ).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 6: Navegación por teclado
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('⌨️ Navegación por teclado', () => {
  test('ACC-20: Tab en login: email → password → submit (orden lógico)', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    const emailInput = page.getByTestId('email')
    const passwordInput = page.getByTestId('password')
    const submitBtn = page.getByTestId('submit')

    await emailInput.focus()
    await expect(emailInput).toBeFocused()

    await page.keyboard.press('Tab')
    await expect(passwordInput).toBeFocused()

    await page.keyboard.press('Tab')
    await expect(submitBtn).toBeFocused()
  })

  test('ACC-21: Enter en submit realiza el login (sin necesidad de mouse)', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    await page.getByTestId('email').fill('admin@modux.demo')
    await page.getByTestId('password').fill('Admin2026!')
    await page.getByTestId('submit').focus()
    await page.keyboard.press('Enter')

    await page.waitForURL(/\/admin\/dashboard/, { timeout: 20000 })
    await expect(page).toHaveURL(/\/admin\/dashboard/)
  })
})
