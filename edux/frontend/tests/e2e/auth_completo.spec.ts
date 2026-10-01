import { test, expect, type Page } from '@playwright/test'

/**
 * auth_completo.spec.ts
 * Cobertura de autenticación completa para los 5 roles principales.
 *
 * Qué cubre (sin duplicar smoke.spec.ts ni roles_completo.spec.ts):
 *  - Login con waitForURL() explícito para cada rol
 *  - Verificación del dashboard correcto tras login (URL + h1/h2 visible)
 *  - Mensaje de error con credenciales inválidas (email válido, contraseña mala)
 *  - Logout desde el sidebar → redirige a /login
 *  - Ruta protegida sin auth → redirige a /login
 *  - Formulario vacío → no navega (validación client-side)
 */

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

// ── Credenciales ─────────────────────────────────────────────────────────────
const ROLES = [
  {
    name: 'superadmin',
    email: 'superadmin@modux.demo',
    password: 'SuperAdmin2026!',
    expectedURL: /\/admin\/dashboard/,
  },
  {
    name: 'admin',
    email: 'admin@modux.demo',
    password: 'Admin2026!',
    expectedURL: /\/admin\/dashboard/,
  },
  {
    name: 'coordinador',
    email: 'coord.enf@modux.demo',
    password: 'Coord2026!',
    expectedURL: /\/coordinador\/dashboard/,
  },
  {
    name: 'docente',
    email: 'docente01@modux.demo',
    password: 'Docente2026!',
    expectedURL: /\/docente\/dashboard/,
  },
  {
    name: 'estudiante',
    email: 'est001@modux.demo',
    password: 'Alumno2026!',
    expectedURL: /\/estudiante\/dashboard/,
  },
] as const

// ── Helper ────────────────────────────────────────────────────────────────────
async function loginAs(page: Page, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('domcontentloaded')
  await page.getByTestId('email').fill(email)
  await page.getByTestId('password').fill(password)
  await page.getByTestId('submit').click()
}

async function logout(page: Page) {
  // Abrir hamburger si el sidebar está oculto (móvil / colapsado)
  const hamburger = page.locator('button[aria-label="Abrir menú de navegación"]')
  if (await hamburger.isVisible()) {
    await hamburger.click()
    await page.waitForTimeout(300)
  }
  await page.locator('aside button:has-text("Cerrar sesión")').click()
  await page.waitForURL(/\/login/, { timeout: 15000 })
}

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 1: Login válido — un test por rol
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('✅ Login válido por rol', () => {
  for (const role of ROLES) {
    test(`LOGIN-${role.name.toUpperCase()}: login y redirección al dashboard correcto`, async ({ page }) => {
      await loginAs(page, role.email, role.password)

      // Esperar la URL esperada explícitamente
      await page.waitForURL(role.expectedURL, { timeout: 20000 })
      await expect(page).toHaveURL(role.expectedURL)

      // El dashboard debe mostrar al menos un heading visible
      await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })
    })
  }
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 2: Login inválido
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('❌ Login inválido', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('domcontentloaded')
  })

  test('AUTH-INV-01: Contraseña incorrecta muestra error-message y permanece en /login', async ({ page }) => {
    await page.getByTestId('email').fill('admin@modux.demo')
    await page.getByTestId('password').fill('ContraseñaIncorrecta999!')
    await page.getByTestId('submit').click()

    await expect(page.getByTestId('error-message')).toBeVisible({ timeout: 15000 })
    await expect(page).toHaveURL(/\/login/)
  })

  test('AUTH-INV-02: Email inexistente muestra error-message', async ({ page }) => {
    await page.getByTestId('email').fill('noexiste@fake.com')
    await page.getByTestId('password').fill('CualquierPassword1!')
    await page.getByTestId('submit').click()

    await expect(page.getByTestId('error-message')).toBeVisible({ timeout: 15000 })
    await expect(page).toHaveURL(/\/login/)
  })

  test('AUTH-INV-03: Formulario vacío no navega (validación HTML5 o JS)', async ({ page }) => {
    // No rellenar nada — verificar que seguimos en /login después del click
    await page.getByTestId('submit').click()

    // Tras click sin datos, debe permanecer en /login
    await expect(page).toHaveURL(/\/login/)
    // El campo email debe estar marcado como inválido (HTML5 :invalid)
    const emailValid = await page.getByTestId('email').evaluate(
      (el: HTMLInputElement) => el.validity.valid
    )
    expect(emailValid).toBe(false)
  })

  test('AUTH-INV-04: Email sin formato válido no envía el formulario', async ({ page }) => {
    await page.getByTestId('email').fill('esto-no-es-un-email')
    await page.getByTestId('password').fill('Admin2026!')
    await page.getByTestId('submit').click()

    await expect(page).toHaveURL(/\/login/)
    const emailValid = await page.getByTestId('email').evaluate(
      (el: HTMLInputElement) => el.validity.valid
    )
    expect(emailValid).toBe(false)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 3: Logout
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🚪 Logout', () => {
  test('AUTH-OUT-01: Logout como admin redirige a /login', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!')
    await page.waitForURL(/\/admin\/dashboard/, { timeout: 20000 })

    await logout(page)

    await expect(page).toHaveURL(/\/login/)
    // El formulario de login debe estar visible nuevamente
    await expect(page.getByTestId('email')).toBeVisible()
  })

  test('AUTH-OUT-02: Después del logout, acceso a dashboard redirige a /login (sesión limpia)', async ({ page }) => {
    await loginAs(page, 'docente01@modux.demo', 'Docente2026!')
    await page.waitForURL(/\/docente\/dashboard/, { timeout: 20000 })

    await logout(page)
    await expect(page).toHaveURL(/\/login/)

    // Intentar acceder a ruta protegida sin sesión
    await page.goto(`${BASE}/docente/dashboard`)
    await page.waitForURL(/\/login/, { timeout: 10000 })
    await expect(page).toHaveURL(/\/login/)
  })

  test('AUTH-OUT-03: Logout como estudiante limpia la sesión correctamente', async ({ page }) => {
    await loginAs(page, 'est001@modux.demo', 'Alumno2026!')
    await page.waitForURL(/\/estudiante\/dashboard/, { timeout: 20000 })

    await logout(page)
    await expect(page).toHaveURL(/\/login/)

    await page.goto(`${BASE}/estudiante/dashboard`)
    await page.waitForURL(/\/login/, { timeout: 10000 })
    await expect(page).toHaveURL(/\/login/)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 4: Rutas protegidas sin autenticación
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🔒 Rutas protegidas sin auth', () => {
  const protectedRoutes = [
    '/admin/dashboard',
    '/admin/usuarios',
    '/coordinacion/dashboard',
    '/docente/dashboard',
    '/estudiante/dashboard',
  ]

  for (const route of protectedRoutes) {
    test(`GUARD: "${route}" sin auth → redirige a /login`, async ({ page }) => {
      await page.goto(`${BASE}${route}`)
      await page.waitForURL(/\/login/, { timeout: 15000 })
      await expect(page).toHaveURL(/\/login/)
    })
  }

  test('GUARD-TOKEN: Token JWT falso en localStorage → redirige a /login', async ({ page }) => {
    // Insertar token malformado directamente
    await page.goto(`${BASE}/login`)
    await page.evaluate(() => {
      localStorage.setItem(
        'modux_token',
        'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIiwiZXhwIjoxfQ.INVALID_SIG'
      )
    })
    await page.goto(`${BASE}/admin/dashboard`)
    await page.waitForURL(/\/login/, { timeout: 15000 })
    await expect(page).toHaveURL(/\/login/)
  })
})
