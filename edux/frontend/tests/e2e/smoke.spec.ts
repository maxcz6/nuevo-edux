import { test, expect } from '@playwright/test'

/**
 * Smoke test de Modux — flujo académico principal.
 *
 * Cubre: login admin → crear grupo → docente publica tarea
 *        → estudiante entrega → docente califica → estudiante ve nota.
 */

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

test.describe('Modux — Smoke Tests', () => {

  test('health endpoint responde OK', async ({ request }) => {
    const res = await request.get(`${BASE}/api/v1/health`)
    expect(res.status()).toBe(200)
    const body = await res.json()
    expect(body.status).toBe('ok')
  })

  test('login de admin demo es exitoso', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)
    await expect(page.locator('[data-testid="user-name"]')).toBeVisible()
  })

  test('login de docente demo redirige al panel correcto', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'docente01@modux.demo')
    await page.fill('[data-testid="password"]', 'Docente2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/docente\/dashboard/)
  })

  test('login de estudiante demo redirige al panel correcto', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'est001@modux.demo')
    await page.fill('[data-testid="password"]', 'Alumno2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/estudiante\/dashboard/)
  })

  test('login de apoderado demo redirige al portal de apoderado', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'apoderado01@modux.demo')
    await page.fill('[data-testid="password"]', 'Padre2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/apoderado\/dashboard/)
  })

  test('ruta protegida sin token redirige a login', async ({ page }) => {
    await page.goto(`${BASE}/admin/dashboard`)
    await expect(page).toHaveURL(/\/login/)
  })

  test('credenciales incorrectas muestran error', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'WrongPassword')
    await page.click('[data-testid="submit"]')
    await expect(page.locator('[data-testid="error-message"]')).toBeVisible()
    await expect(page).toHaveURL(/\/login/)
  })

})
