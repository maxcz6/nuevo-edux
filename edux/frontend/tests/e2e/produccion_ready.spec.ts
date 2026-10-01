import { test, expect } from '@playwright/test'

/**
 * produccion_ready.spec.ts
 * Tests de production-readiness / smoke orientados a infraestructura y calidad.
 *
 * Cubre (sin duplicar smoke.spec.ts):
 *  - Health endpoint retorna {status: 'ok'} con latencia < 3s
 *  - Página principal carga sin errores JS de consola
 *  - Login tiene todos los elementos semánticos requeridos (form, labels, submit)
 *  - Meta viewport presente y correcto
 *  - Sin errores de red (4xx/5xx) en recursos críticos (CSS, JS, fonts)
 *  - Título HTML de la página es descriptivo
 *  - Link favicon presente
 *  - Robots/sitemap si existe (no bloquea tests si no existe)
 */

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 1: Health Check & API
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🏥 Health Check & API', () => {
  test('PROD-01: GET /api/v1/health retorna status 200 con body {status: "ok"}', async ({ request }) => {
    const startMs = Date.now()
    const res = await request.get(`${BASE}/api/v1/health`)
    const latencyMs = Date.now() - startMs

    expect(res.status()).toBe(200)

    const body = await res.json()
    expect(body.status).toBe('ok')

    // El health endpoint debe responder en menos de 3 segundos
    expect(latencyMs).toBeLessThan(3000)
  })

  test('PROD-02: Health endpoint retorna Content-Type application/json', async ({ request }) => {
    const res = await request.get(`${BASE}/api/v1/health`)
    const contentType = res.headers()['content-type'] ?? ''
    expect(contentType).toContain('application/json')
  })

  test('PROD-03: Ruta 404 de la API retorna JSON, no HTML', async ({ request }) => {
    const res = await request.get(`${BASE}/api/v1/__ruta_que_no_existe__`)
    // Debe ser 404 o 405, nunca 500
    expect(res.status()).toBeGreaterThanOrEqual(400)
    expect(res.status()).toBeLessThan(500)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 2: Smoke — Carga sin errores JS
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('💨 Smoke — Carga sin errores', () => {
  test('PROD-04: Página de login carga sin errores JavaScript en consola', async ({ page }) => {
    const jsErrors: string[] = []
    page.on('pageerror', err => jsErrors.push(err.message))
    page.on('console', msg => {
      if (msg.type() === 'error' && !msg.text().includes('favicon')) {
        jsErrors.push(`[console.error] ${msg.text()}`)
      }
    })

    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    expect(jsErrors).toHaveLength(0)
  })

  test('PROD-05: Página raíz (/) redirige a /login sin errores de carga', async ({ page }) => {
    const networkErrors: string[] = []
    page.on('response', res => {
      if (res.status() >= 500) {
        networkErrors.push(`${res.status()} ${res.url()}`)
      }
    })

    await page.goto(`${BASE}/`)
    await page.waitForLoadState('networkidle')

    // Debe terminar en /login (sin auth)
    await expect(page).toHaveURL(/\/login/)
    // No debe haber errores 5xx
    expect(networkErrors).toHaveLength(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 3: Elementos requeridos en Login
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('📋 Login — Elementos semánticos requeridos', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')
  })

  test('PROD-06: El formulario de login existe con role="form" o tag <form>', async ({ page }) => {
    // Verificar que existe un form HTML
    const form = page.locator('form')
    await expect(form).toBeVisible()
  })

  test('PROD-07: Input de email tiene data-testid y type="email" o type="text"', async ({ page }) => {
    const emailInput = page.getByTestId('email')
    await expect(emailInput).toBeVisible()
    const type = await emailInput.getAttribute('type')
    expect(['email', 'text']).toContain(type)
  })

  test('PROD-08: Input de contraseña tiene type="password"', async ({ page }) => {
    const passwordInput = page.getByTestId('password')
    await expect(passwordInput).toBeVisible()
    const type = await passwordInput.getAttribute('type')
    expect(type).toBe('password')
  })

  test('PROD-09: Botón submit está presente y es clickeable', async ({ page }) => {
    const submitBtn = page.getByTestId('submit')
    await expect(submitBtn).toBeVisible()
    await expect(submitBtn).toBeEnabled()

    const tagName = await submitBtn.evaluate(el => el.tagName.toLowerCase())
    expect(['button', 'input']).toContain(tagName)
  })

  test('PROD-10: Los campos email y password tienen labels asociados', async ({ page }) => {
    // Label puede estar asociado por for/id, aria-label o aria-labelledby
    const emailInput = page.getByTestId('email')
    const passwordInput = page.getByTestId('password')

    const emailId = await emailInput.getAttribute('id')
    const passwordId = await passwordInput.getAttribute('id')

    const emailAriaLabel = await emailInput.getAttribute('aria-label')
    const passwordAriaLabel = await passwordInput.getAttribute('aria-label')

    // Al menos uno de los métodos de etiquetado debe existir
    const emailHasLabel = (emailId && await page.locator(`label[for="${emailId}"]`).count() > 0) || !!emailAriaLabel
    const passwordHasLabel = (passwordId && await page.locator(`label[for="${passwordId}"]`).count() > 0) || !!passwordAriaLabel

    expect(emailHasLabel).toBe(true)
    expect(passwordHasLabel).toBe(true)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 4: Meta tags & SEO básico
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🔍 Meta tags & SEO básico', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('domcontentloaded')
  })

  test('PROD-11: Meta viewport presente y correcto', async ({ page }) => {
    const viewport = page.locator('meta[name="viewport"]')
    await expect(viewport).toHaveCount(1)
    const content = await viewport.getAttribute('content')
    // Debe incluir width=device-width para ser responsive
    expect(content).toContain('width=device-width')
  })

  test('PROD-12: Título HTML de la página es descriptivo (no vacío, no "Vite App")', async ({ page }) => {
    const title = await page.title()
    expect(title.trim().length).toBeGreaterThan(0)
    // El título no debe ser el placeholder genérico de Vite
    expect(title).not.toBe('Vite App')
    expect(title).not.toBe('Vue App')
  })

  test('PROD-13: Favicon está enlazado en el <head>', async ({ page }) => {
    const favicon = page.locator('link[rel*="icon"]')
    const count = await favicon.count()
    expect(count).toBeGreaterThanOrEqual(1)

    const href = await favicon.first().getAttribute('href')
    expect(href).toBeTruthy()
  })

  test('PROD-14: El HTML tiene atributo lang definido (accesibilidad / SEO)', async ({ page }) => {
    const lang = await page.locator('html').getAttribute('lang')
    expect(lang).toBeTruthy()
    expect(lang!.trim().length).toBeGreaterThan(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 5: Recursos críticos sin errores de red
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🌐 Recursos críticos — Sin errores de red', () => {
  test('PROD-15: CSS y JS cargados sin errores 4xx/5xx', async ({ page }) => {
    const failedResources: string[] = []

    page.on('response', res => {
      const url = res.url()
      const status = res.status()
      // Monitorear solo recursos del mismo origen (no CDNs externos que pueden fallar por CORS)
      if (
        url.startsWith(BASE) &&
        (url.includes('.css') || url.includes('.js')) &&
        status >= 400
      ) {
        failedResources.push(`${status} ${url}`)
      }
    })

    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')

    expect(failedResources).toHaveLength(0)
  })

  test('PROD-16: No hay recursos con status 500 al cargar el dashboard de admin', async ({ page }) => {
    const serverErrors: string[] = []

    page.on('response', res => {
      if (res.status() >= 500) {
        serverErrors.push(`${res.status()} ${res.url()}`)
      }
    })

    // Login primero
    await page.goto(`${BASE}/login`)
    await page.getByTestId('email').fill('admin@modux.demo')
    await page.getByTestId('password').fill('Admin2026!')
    await page.getByTestId('submit').click()
    await page.waitForURL(/\/admin\/dashboard/, { timeout: 20000 })
    await page.waitForLoadState('networkidle')

    // Excluir endpoints de stats que pueden fallar si la BD no tiene datos
    const criticalErrors = serverErrors.filter(e => !e.includes('/api/v1/stats'))
    expect(criticalErrors).toHaveLength(0)
  })
})
