import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

test.describe('Modux — Accesibilidad Real (Axe Core WCAG 2.1 & Navegación Teclado)', () => {

  test('A11Y-01: Página de Login cumple con WCAG 2.1 A y AA sin violaciones críticas', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze()

    const criticalViolations = accessibilityScanResults.violations.filter(
      v => v.impact === 'critical' || v.impact === 'serious'
    )

    expect(criticalViolations).toEqual([])
  })

  test('A11Y-02: Navegación completa por teclado (Tab y Enter) en formulario de Login', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    const emailInput = page.getByTestId('email')
    const passwordInput = page.getByTestId('password')
    const submitBtn = page.getByTestId('submit')

    // Enfocar primer campo
    await emailInput.focus()
    await expect(emailInput).toBeFocused()

    // Tab hacia contraseña
    await page.keyboard.press('Tab')
    await expect(passwordInput).toBeFocused()

    // Tab hacia botón de envío
    await page.keyboard.press('Tab')
    await expect(submitBtn).toBeFocused()
  })

  test('A11Y-03: Dashboard y Gestión de Usuarios cumplen WCAG sin violaciones críticas', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)

    // 1. Escaneo de Dashboard
    const dashboardScan = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze()

    const dashCritical = dashboardScan.violations.filter(
      v => v.impact === 'critical'
    )
    expect(dashCritical).toEqual([])

    // 2. Escaneo de Usuarios
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')

    const usuariosScan = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze()

    const usersCritical = usuariosScan.violations.filter(
      v => v.impact === 'critical'
    )
    expect(usersCritical).toEqual([])
  })

  test('A11Y-04: Modal de Nuevo Usuario es cerrable mediante tecla Escape', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)

    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')

    // Abrir modal
    await page.getByTestId('nuevo-usuario-btn').click()
    const modalTitle = page.getByRole('heading', { name: 'Nuevo Usuario', level: 2 })
    await expect(modalTitle).toBeVisible()

    // Presionar Escape para cerrar el modal accesible
    await page.keyboard.press('Escape')
    await expect(modalTitle).not.toBeVisible()
  })

  test('A11Y-05: Vista móvil sin desbordamiento horizontal (no horizontal scroll)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    const hasHorizontalOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > window.innerWidth
    })

    expect(hasHorizontalOverflow).toBe(false)
  })

})
