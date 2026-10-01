import { test } from '@playwright/test'
import * as path from 'path'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'
const OUT = path.join(process.cwd(), '..', 'anexos', 'capturas')

async function login(page: any, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('domcontentloaded')
  await page.fill('[data-testid="email"]', email)
  await page.fill('[data-testid="password"]', password)
  await page.click('[data-testid="submit"]')
  await page.waitForURL((url: any) => !url.pathname.includes('/login'), { timeout: 30000 })
  await page.waitForTimeout(1000)
}

test.describe('Docente UX/UI Audit Captures', () => {
  test('Capturar vistas de Docente en Light y Dark Mode', async ({ page }) => {
    test.setTimeout(60000)
    await login(page, 'docente01@modux.demo', 'Docente2026!')

    // 1. Dashboard Light
    await page.goto(`${BASE}/docente/dashboard`)
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1000)
    await page.screenshot({ path: path.join(OUT, 'docente-dashboard-light.png') })

    // 2. Aula Virtual Light
    await page.goto(`${BASE}/docente/grupos/1/aula`)
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1000)
    await page.screenshot({ path: path.join(OUT, 'docente-aula-light.png') })

    // 3. Tareas Light
    await page.goto(`${BASE}/docente/grupos/1/tareas`)
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1000)
    await page.screenshot({ path: path.join(OUT, 'docente-tareas-light.png') })

    // 4. Calificaciones Light
    await page.goto(`${BASE}/docente/grupos/1/calificaciones`)
    await page.waitForLoadState('domcontentloaded')
    await page.waitForTimeout(1000)
    await page.screenshot({ path: path.join(OUT, 'docente-calificaciones-light.png') })

    // Activar Dark Mode via clase HTML
    await page.evaluate(() => document.documentElement.classList.add('dark'))
    await page.waitForTimeout(500)

    // 5. Dashboard Dark
    await page.goto(`${BASE}/docente/dashboard`)
    await page.waitForLoadState('domcontentloaded')
    await page.evaluate(() => document.documentElement.classList.add('dark'))
    await page.waitForTimeout(1000)
    await page.screenshot({ path: path.join(OUT, 'docente-dashboard-dark.png') })

    // 6. Aula Virtual Dark
    await page.goto(`${BASE}/docente/grupos/1/aula`)
    await page.waitForLoadState('domcontentloaded')
    await page.evaluate(() => document.documentElement.classList.add('dark'))
    await page.waitForTimeout(1000)
    await page.screenshot({ path: path.join(OUT, 'docente-aula-dark.png') })

    // 7. Calificaciones Dark
    await page.goto(`${BASE}/docente/grupos/1/calificaciones`)
    await page.waitForLoadState('domcontentloaded')
    await page.evaluate(() => document.documentElement.classList.add('dark'))
    await page.waitForTimeout(1000)
    await page.screenshot({ path: path.join(OUT, 'docente-calificaciones-dark.png') })
  })
})
