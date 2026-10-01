import { test, expect, type Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

async function enableDarkMode(page: Page) {
  await page.evaluate(() => {
    document.documentElement.classList.add('dark')
    document.documentElement.style.colorScheme = 'dark'
    localStorage.setItem('modux-theme', 'dark')
  })
}

async function loginAs(page: Page, email: string, password: string, targetPath: RegExp) {
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('domcontentloaded')
  // Clear any existing session
  await page.evaluate(() => {
    localStorage.removeItem('modux-token')
    localStorage.removeItem('modux-user')
  })
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('networkidle')
  await page.getByTestId('email').fill(email)
  await page.getByTestId('password').fill(password)
  await page.getByTestId('submit').click()
  await page.waitForURL(targetPath, { timeout: 20000 })
  await page.waitForLoadState('networkidle')
}

test.describe('🌙 Dark Mode UX/UI & Playwright Verification', () => {

  test('DARK-01: Login view toggle functions cleanly without duplicates', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    const toggles = page.locator('button.theme-toggle')
    // Ensure only 1 single theme toggle exists on login page
    await expect(toggles).toHaveCount(1)
    await expect(toggles.first()).toBeVisible()

    // Toggle to Dark Mode
    await toggles.first().click()
    const html = page.locator('html')
    await expect(html).toHaveClass(/dark/)
    const stored = await page.evaluate(() => localStorage.getItem('modux-theme'))
    expect(stored).toBe('dark')

    // Accessibility check on Login in dark mode
    const axeResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze()
    const criticalAxe = axeResults.violations.filter(v => v.impact === 'critical' || v.impact === 'serious')
    expect(criticalAxe).toHaveLength(0)

    // Toggle back to Light Mode
    await toggles.first().click()
    await expect(html).not.toHaveClass(/dark/)
    expect(await page.evaluate(() => localStorage.getItem('modux-theme'))).toBe('light')
  })

  test('DARK-02: Public views (Forgot Password, Reset Password, Verification) in Dark Mode', async ({ page }) => {
    await page.goto(`${BASE}/forgot-password`)
    await enableDarkMode(page)
    await page.reload()
    await expect(page.locator('html')).toHaveClass(/dark/)
    await expect(page.getByRole('heading', { name: /Recuperar contraseña/i })).toBeVisible()

    await page.goto(`${BASE}/verificar-documento`)
    await expect(page.locator('html')).toHaveClass(/dark/)
    await expect(page.getByRole('heading', { name: /Portal de Consulta Pública/i })).toBeVisible()
  })

  test('DARK-03: Admin Dashboard renders in Dark Mode with high contrast', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await enableDarkMode(page)
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    await expect(page.locator('html')).toHaveClass(/dark/)
    await expect(page.getByTestId('user-name')).toBeVisible()

    // Verify dark background color computed style
    const bgColor = await page.evaluate(() => window.getComputedStyle(document.body).backgroundColor)
    expect(bgColor).toBe('rgb(0, 9, 20)')

    // Quick links visible and styled
    const statCards = page.locator('[data-testid="stat-card"]')
    await expect(statCards.first()).toBeVisible()
  })

  test('DARK-04: Admin Usuarios View & Modal in Dark Mode', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await enableDarkMode(page)
    await loginAs(page, 'admin@modux.demo', 'Admin2026!', /\/admin\/dashboard/)

    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')

    // Verify table in dark mode
    await expect(page.locator('table')).toBeVisible()

    // Open create user modal
    const nuevoBtn = page.getByTestId('nuevo-usuario-btn')
    await nuevoBtn.click()

    // Modal dialog is visible in dark mode
    const modalDialog = page.locator('[role="dialog"]')
    await expect(modalDialog).toBeVisible()
    await expect(page.getByTestId('nombres-input')).toBeVisible()

    // Close modal
    await page.keyboard.press('Escape')
    await expect(modalDialog).not.toBeVisible()
  })

  test('DARK-05: Docente Portal in Dark Mode', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await enableDarkMode(page)
    await loginAs(page, 'docente01@modux.demo', 'Docente2026!', /\/docente\/dashboard/)
    await expect(page.locator('html')).toHaveClass(/dark/)
    await expect(page.locator('aside')).toBeVisible()
  })

  test('DARK-06: Estudiante Portal in Dark Mode', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await enableDarkMode(page)
    await loginAs(page, 'est001@modux.demo', 'Alumno2026!', /\/estudiante\/dashboard/)
    await expect(page.locator('html')).toHaveClass(/dark/)
    await expect(page.locator('aside')).toBeVisible()
  })
})
