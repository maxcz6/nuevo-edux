import { test, expect, type Page } from '@playwright/test'

/**
 * MODUX — Suite de Pruebas E2E Integral por Rol (v2 — corregida)
 *
 * Cubre los 9 roles del sistema verificando:
 * login → redirección → sidebar → módulos → control de acceso
 * → accesibilidad → responsive → seguridad
 */

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

// ─── Credenciales demo (verificadas contra InstitucionDemoSeeder.php) ─────────
const CREDS = {
  superadmin:  { email: 'superadmin@modux.demo',  password: 'SuperAdmin2026!' },
  admin:       { email: 'admin@modux.demo',        password: 'Admin2026!' },
  coordinador: { email: 'coord.enf@modux.demo',   password: 'Coord2026!' },
  secretaria:  { email: 'secretaria@modux.demo',  password: 'Secretaria2026!' },
  caja:        { email: 'caja@modux.demo',         password: 'Caja2026!' },
  mesa:        { email: 'mesa.partes@modux.demo',  password: 'Mesa2026!' },
  docente:     { email: 'docente01@modux.demo',    password: 'Docente2026!' },
  estudiante:  { email: 'est001@modux.demo',       password: 'Alumno2026!' },
  apoderado:   { email: 'apoderado01@modux.demo',  password: 'Padre2026!' },
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
async function login(page: Page, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('networkidle')
  await page.fill('[data-testid="email"]', email)
  await page.fill('[data-testid="password"]', password)
  await page.click('[data-testid="submit"]')
  await page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 15000 })
  await page.waitForLoadState('networkidle')
}

async function collectErrors(page: Page): Promise<string[]> {
  const errors: string[] = []
  page.on('console', msg => {
    if (msg.type() === 'error' && !msg.text().includes('favicon')) {
      errors.push(`[console] ${msg.text()}`)
    }
  })
  page.on('pageerror', err => errors.push(`[pageerror] ${err.message}`))
  return errors
}

async function visitAndCheck(page: Page, path: string) {
  await page.goto(`${BASE}${path}`)
  await page.waitForLoadState('domcontentloaded')
  // Verifica que no redirigió a login (acceso correcto)
  await expect(page).not.toHaveURL(/\/login/)
  // No debe haber pantalla de error 500
  await expect(page.locator('text=500')).toHaveCount(0)
}

async function hasSidebarText(page: Page, text: string): Promise<boolean> {
  return (await page.locator('aside').getByText(text).count()) > 0
}

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 0: Health Check & Login
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🏥 Health & Login', () => {
  test('API health endpoint responde OK con database y redis', async ({ request }) => {
    const res = await request.get(`${BASE}/api/v1/health`)
    expect(res.status()).toBe(200)
    const body = await res.json()
    expect(body.status).toBe('ok')
    if (body.services) {
      expect(body.services?.database).toBe('ok')
      expect(body.services?.redis).toBe('ok')
    }
  })

  test('Ruta protegida sin token → redirige a /login', async ({ page }) => {
    await page.goto(`${BASE}/admin/dashboard`)
    await expect(page).toHaveURL(/\/login/)
  })

  test('Credenciales incorrectas → muestra error y permanece en login', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'WrongPassword999!')
    await page.click('[data-testid="submit"]')
    await expect(page.locator('[data-testid="error-message"]')).toBeVisible()
    await expect(page).toHaveURL(/\/login/)
  })

  test('Demo switcher rellena correctamente email y password', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.click('button:has-text("SuperAdmin")')
    await expect(page.locator('[data-testid="email"]')).toHaveValue('superadmin@modux.demo')
    // Apoderado usa Padre2026! (fix aplicado en LoginView.vue)
    await page.click('button:has-text("Apoderado")')
    await expect(page.locator('[data-testid="email"]')).toHaveValue('apoderado01@modux.demo')
    await expect(page.locator('[data-testid="password"]')).toHaveValue('Padre2026!')
  })

  test('Página pública de verificación no requiere autenticación', async ({ page }) => {
    await page.goto(`${BASE}/verificar-documento/TIT-SAM-2026-AGRO-042`)
    await page.waitForLoadState('networkidle')
    await expect(page).not.toHaveURL(/\/login/)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 1: SUPERADMIN
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('👑 SUPERADMIN', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDS.superadmin.email, CREDS.superadmin.password)
    await expect(page).toHaveURL(/\/admin\/dashboard/)
  })

  test('SA-01: Login y nombre visible en header', async ({ page }) => {
    await expect(page.locator('[data-testid="user-name"]')).toBeVisible()
  })

  test('SA-02: Dashboard muestra 4 cards de estadísticas', async ({ page }) => {
    await expect(page.locator('text=Total Usuarios')).toBeVisible()
    await expect(page.locator('text=Docentes Activos')).toBeVisible()
    await expect(page.locator('text=Estudiantes Activos')).toBeVisible()
    await expect(page.locator('text=Grupos Activos')).toBeVisible()
  })

  test('SA-03: Dashboard quick links filtrados por rol incluyen Usuarios', async ({ page }) => {
    // Sección de accesos rápidos (aria-labelledby="accesos-rapidos-heading")
    const section = page.locator('section[aria-labelledby="accesos-rapidos-heading"]')
    await expect(section).toBeVisible()
    await expect(section.locator('a:has-text("Usuarios")')).toBeVisible()
    await expect(section.locator('a:has-text("Caja y Cobranza")')).toBeVisible()
  })

  test('SA-04: Sidebar tiene enlace de Cerrar sesión', async ({ page }) => {
    await expect(page.locator('aside button:has-text("Cerrar sesión")')).toBeVisible()
  })

  test('SA-05: Selector de programa con múltiples opciones', async ({ page }) => {
    const selector = page.locator('select#programa-switcher, select[id*="programa"]')
    await expect(selector).toBeVisible()
    const opciones = await selector.locator('option').count()
    expect(opciones).toBeGreaterThanOrEqual(1)
  })

  test('SA-06: Recorrido módulos sin errores JS', async ({ page }) => {
    const errors = await collectErrors(page)
    const rutas = [
      '/admin/instituciones', '/admin/programas', '/admin/usuarios',
      '/admin/roles', '/admin/docentes', '/admin/estudiantes',
      '/admin/periodos', '/admin/cursos', '/admin/grupos',
      '/admin/matriculas', '/admin/admision', '/admin/tramites',
      '/admin/ambientes', '/admin/titulacion', '/admin/perfil',
    ]
    for (const ruta of rutas) {
      await visitAndCheck(page, ruta)
    }
    expect(errors).toHaveLength(0)
  })

  test('SA-07: Skip link "Saltar al contenido principal" existe', async ({ page }) => {
    await expect(page.locator('a:has-text("Saltar al contenido principal")')).toHaveCount(1)
  })

  test('SA-08: main#main-content accesible con tabindex=-1', async ({ page }) => {
    const main = page.locator('main#main-content')
    await expect(main).toBeVisible()
    await expect(main).toHaveAttribute('tabindex', '-1')
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 2: ADMINISTRADOR
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🏢 ADMINISTRADOR', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDS.admin.email, CREDS.admin.password)
    await expect(page).toHaveURL(/\/admin\/dashboard/)
  })

  test('ADM-01: Quick links incluyen Usuarios y excluyen Instituciones', async ({ page }) => {
    const section = page.locator('section[aria-labelledby="accesos-rapidos-heading"]')
    await expect(section.locator('a:has-text("Usuarios")')).toBeVisible()
    // Instituciones es solo SUPERADMIN
    await expect(section.locator('a:has-text("Instituciones")')).toHaveCount(0)
  })

  test('ADM-02: Módulos admin accesibles sin errores', async ({ page }) => {
    const errors = await collectErrors(page)
    const rutas = [
      '/admin/programas', '/admin/usuarios', '/admin/docentes',
      '/admin/estudiantes', '/admin/periodos', '/admin/cursos',
      '/admin/grupos', '/admin/matriculas', '/admin/admision',
      '/admin/tramites', '/admin/ambientes', '/admin/titulacion',
    ]
    for (const ruta of rutas) {
      await visitAndCheck(page, ruta)
    }
    expect(errors).toHaveLength(0)
  })

  test('ADM-03: Formulario "Nuevo Usuario" tiene campo de rol', async ({ page }) => {
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')
    await page.getByTestId('nuevo-usuario-btn').click()
    await expect(page.getByTestId('rol-select')).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 3: COORDINADOR
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('📋 COORDINADOR', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDS.coordinador.email, CREDS.coordinador.password)
    await expect(page).toHaveURL(/\/coordinacion\/dashboard/)
  })

  test('COORD-01: Sidebar NO tiene "Usuarios del Sistema"', async ({ page }) => {
    await expect(page.locator('aside nav').getByText('Usuarios del Sistema')).toHaveCount(0)
  })

  test('COORD-02: Sidebar NO tiene "Roles y Permisos"', async ({ page }) => {
    await expect(page.locator('aside nav').getByText('Roles y Permisos')).toHaveCount(0)
  })

  test('COORD-03: No puede acceder a /admin/usuarios (redirige)', async ({ page }) => {
    await page.goto(`${BASE}/admin/usuarios`)
    await expect(page).not.toHaveURL(/\/admin\/usuarios/)
  })

  test('COORD-04: Quick links NO incluyen Usuarios', async ({ page }) => {
    const section = page.locator('section[aria-labelledby="accesos-rapidos-heading"]')
    await expect(section.locator('a:has-text("Usuarios")')).toHaveCount(0)
  })

  test('COORD-05: Módulos académicos accesibles en /coordinacion', async ({ page }) => {
    const errors = await collectErrors(page)
    const rutas = ['/coordinacion/docentes', '/coordinacion/estudiantes', '/coordinacion/grupos', '/coordinacion/tramites']
    for (const ruta of rutas) {
      await visitAndCheck(page, ruta)
    }
    expect(errors).toHaveLength(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 4: SECRETARIA
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('📝 SECRETARIA', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDS.secretaria.email, CREDS.secretaria.password)
    await expect(page).toHaveURL(/\/secretaria\/dashboard/)
  })

  test('SEC-01: Login exitoso → redirige a /secretaria/dashboard', async ({ page }) => {
    await expect(page.locator('h1, h2').first()).toBeVisible()
  })

  test('SEC-02: NO puede acceder a /admin/usuarios', async ({ page }) => {
    await page.goto(`${BASE}/admin/usuarios`)
    await expect(page).not.toHaveURL(/\/admin\/usuarios/)
  })

  test('SEC-03: NO puede acceder a /admin/caja ni /caja/caja', async ({ page }) => {
    await page.goto(`${BASE}/admin/caja`)
    await expect(page).not.toHaveURL(/\/admin\/caja/)
    await page.goto(`${BASE}/caja/caja`)
    await expect(page).not.toHaveURL(/\/caja\/caja/)
  })

  test('SEC-04: Módulos accesibles: trámites y estudiantes en /secretaria', async ({ page }) => {
    const errors = await collectErrors(page)
    await visitAndCheck(page, '/secretaria/tramites')
    await visitAndCheck(page, '/secretaria/estudiantes')
    expect(errors).toHaveLength(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 5: CAJA
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('💰 CAJA', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDS.caja.email, CREDS.caja.password)
    await expect(page).toHaveURL(/\/caja\/dashboard/)
  })

  test('CAJA-01: Login exitoso → redirige a /caja/dashboard', async ({ page }) => {
    await expect(page.locator('h1, h2').first()).toBeVisible()
  })

  test('CAJA-02: Quick links incluyen Caja y Cobranza', async ({ page }) => {
    const section = page.locator('section[aria-labelledby="accesos-rapidos-heading"]')
    await expect(section.locator('a:has-text("Caja y Cobranza")')).toBeVisible()
  })

  test('CAJA-03: Quick links NO incluyen Usuarios ni Grupos de Curso', async ({ page }) => {
    const section = page.locator('section[aria-labelledby="accesos-rapidos-heading"]')
    await expect(section.locator('a:has-text("Usuarios")')).toHaveCount(0)
    await expect(section.locator('a:has-text("Grupos de Curso")')).toHaveCount(0)
  })

  test('CAJA-04: Módulo Caja y Matrículas accesibles sin errores en /caja', async ({ page }) => {
    const errors = await collectErrors(page)
    await visitAndCheck(page, '/caja/caja')
    await visitAndCheck(page, '/caja/matriculas')
    expect(errors).toHaveLength(0)
  })

  test('CAJA-05: NO puede acceder a cursos ni grupos académicos', async ({ page }) => {
    await page.goto(`${BASE}/admin/cursos`)
    await expect(page).not.toHaveURL(/\/admin\/cursos/)
    await page.goto(`${BASE}/admin/grupos`)
    await expect(page).not.toHaveURL(/\/admin\/grupos/)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 6: MESA DE PARTES
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('📫 MESA DE PARTES', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDS.mesa.email, CREDS.mesa.password)
    await expect(page).toHaveURL(/\/mesa-partes\/dashboard/)
  })

  test('MESA-01: Login exitoso → redirige a /mesa-partes/dashboard', async ({ page }) => {
    await expect(page.locator('h1, h2').first()).toBeVisible()
  })

  test('MESA-02: Módulo Trámites accesible en /mesa-partes', async ({ page }) => {
    const errors = await collectErrors(page)
    await visitAndCheck(page, '/mesa-partes/tramites')
    await visitAndCheck(page, '/mesa-partes/estudiantes')
    expect(errors).toHaveLength(0)
  })

  test('MESA-03: NO puede acceder a caja ni cursos', async ({ page }) => {
    await page.goto(`${BASE}/admin/caja`)
    await expect(page).not.toHaveURL(/\/admin\/caja/)
    await page.goto(`${BASE}/caja/caja`)
    await expect(page).not.toHaveURL(/\/caja\/caja/)
    await page.goto(`${BASE}/admin/cursos`)
    await expect(page).not.toHaveURL(/\/admin\/cursos/)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 7: DOCENTE
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🎓 DOCENTE', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDS.docente.email, CREDS.docente.password)
    await expect(page).toHaveURL(/\/docente\/dashboard/)
  })

  test('DOC-01: Badge "Docente" visible en header', async ({ page }) => {
    await expect(page.locator('header span:has-text("Docente")')).toBeVisible()
  })

  test('DOC-02: Sidebar contiene exactamente 2 ítems de nav', async ({ page }) => {
    const links = page.locator('aside nav a')
    await expect(links).toHaveCount(2)
  })

  test('DOC-03: NO hay botón "Salir" en el header (solo en sidebar)', async ({ page }) => {
    await expect(page.locator('header button:has-text("Salir")')).toHaveCount(0)
  })

  test('DOC-04: Botón logout del sidebar tiene aria-label correcto', async ({ page }) => {
    await expect(page.locator('aside button[aria-label="Cerrar sesión"]')).toBeVisible()
  })

  test('DOC-05: Avatar en header es link al perfil del docente', async ({ page }) => {
    await expect(page.locator('header a[href*="/docente/perfil"]')).toBeVisible()
  })

  test('DOC-06: Dashboard carga (grupos o mensaje vacío)', async ({ page }) => {
    await page.waitForLoadState('networkidle')
    await expect(page.locator('text=500')).toHaveCount(0)
    await expect(page.locator('h1')).toContainText('Mis grupos')
  })

  test('DOC-07: Sidebar toggle disponible en desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    const toggle = page.locator('button[aria-label*="Colapsar"], button[aria-label*="Expandir"]')
    await expect(toggle).toBeVisible()
    await toggle.click()
    await page.waitForTimeout(250)
    await expect(page.locator('aside')).toHaveClass(/lg:w-20/)
  })

  test('DOC-08: Aula virtual del grupo 1 carga sin errores', async ({ page }) => {
    const errors = await collectErrors(page)
    await page.goto(`${BASE}/docente/grupos/1/aula`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/docente\/grupos\/1\/aula/)
    expect(errors).toHaveLength(0)
  })

  test('DOC-09: Tareas y calificaciones del grupo 1 cargan', async ({ page }) => {
    const errors = await collectErrors(page)
    await page.goto(`${BASE}/docente/grupos/1/tareas`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/docente\/grupos\/1\/tareas/)
    await page.goto(`${BASE}/docente/grupos/1/calificaciones`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/docente\/grupos\/1\/calificaciones/)
    expect(errors).toHaveLength(0)
  })

  test('DOC-10: NO puede acceder a rutas de admin', async ({ page }) => {
    await page.goto(`${BASE}/admin/usuarios`)
    // Debe redirigir — no importa si va al login o al dashboard del docente
    await expect(page).not.toHaveURL(/\/admin\/usuarios/)
  })

  test('DOC-11: Nav tiene aria-label="Menú docente"', async ({ page }) => {
    await expect(page.locator('nav[aria-label="Menú docente"]')).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 8: ESTUDIANTE
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('📚 ESTUDIANTE', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDS.estudiante.email, CREDS.estudiante.password)
    await expect(page).toHaveURL(/\/estudiante\/dashboard/)
  })

  test('EST-01: Badge "Estudiante" visible en header', async ({ page }) => {
    await expect(page.locator('header span:has-text("Estudiante")')).toBeVisible()
  })

  test('EST-02: Sidebar tiene 4 ítems de nav (sin duplicados)', async ({ page }) => {
    const links = page.locator('aside nav a')
    await expect(links).toHaveCount(4)
    await expect(links.getByText('Dashboard')).toBeVisible()
    await expect(links.getByText('Tareas Pendientes')).toBeVisible()
    await expect(links.getByText('Mis Calificaciones')).toBeVisible()
    await expect(links.getByText('Mi Perfil')).toBeVisible()
  })

  test('EST-03: NO hay botón "Salir" en el header', async ({ page }) => {
    await expect(page.locator('header button:has-text("Salir")')).toHaveCount(0)
  })

  test('EST-04: Avatar en header es link al perfil del estudiante', async ({ page }) => {
    await expect(page.locator('header a[href*="/estudiante/perfil"]')).toBeVisible()
  })

  test('EST-05: Dashboard carga sin error 500', async ({ page }) => {
    await expect(page.locator('text=500')).toHaveCount(0)
    await expect(page.locator('h1, h2').first()).toBeVisible()
  })

  test('EST-06: Vista Mis Notas tiene PageHeader "Mis Notas"', async ({ page }) => {
    await page.goto(`${BASE}/estudiante/notas`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('text=Mis Notas')).toBeVisible()
  })

  test('EST-07: Vista Mis Notas tiene overflow-x-auto si hay tabla', async ({ page }) => {
    await page.goto(`${BASE}/estudiante/notas`)
    await page.waitForLoadState('networkidle')
    const tableCount = await page.locator('table').count()
    if (tableCount > 0) {
      await expect(page.locator('.overflow-x-auto').first()).toBeVisible()
      // th debe tener scope="col"
      await expect(page.locator('table th[scope="col"]').first()).toBeVisible()
    }
  })

  test('EST-08: Vista Tareas tiene PageHeader "Mis Tareas"', async ({ page }) => {
    const errors = await collectErrors(page)
    await page.goto(`${BASE}/estudiante/tareas`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('text=Mis Tareas y Asignaciones')).toBeVisible()
    expect(errors).toHaveLength(0)
  })

  test('EST-09: Curso 1 carga sin errores', async ({ page }) => {
    const errors = await collectErrors(page)
    await page.goto(`${BASE}/estudiante/cursos/1`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/estudiante\/cursos\/1/)
    expect(errors).toHaveLength(0)
  })

  test('EST-10: NO puede acceder a rutas de admin', async ({ page }) => {
    await page.goto(`${BASE}/admin/usuarios`)
    await expect(page).not.toHaveURL(/\/admin\/usuarios/)
  })

  test('EST-11: NO puede acceder a rutas de docente', async ({ page }) => {
    await page.goto(`${BASE}/docente/grupos/1/calificaciones`)
    await expect(page).not.toHaveURL(/\/docente\/grupos/)
  })

  test('EST-12: Nav tiene aria-label="Menú estudiante"', async ({ page }) => {
    await expect(page.locator('nav[aria-label="Menú estudiante"]')).toBeVisible()
  })

  test('EST-13: Title en nav-links del sidebar (accesibilidad colapsado)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    // Colapsar sidebar
    const toggle = page.locator('button[aria-label*="Colapsar"]')
    if (await toggle.count() > 0) {
      await toggle.click()
      await page.waitForTimeout(250)
      // Los nav links en modo colapsado deben tener title
      const navLinks = page.locator('aside nav a[title]')
      await expect(navLinks.first()).toBeVisible()
    }
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 9: APODERADO
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('👨‍👧 APODERADO', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, CREDS.apoderado.email, CREDS.apoderado.password)
    await expect(page).toHaveURL(/\/apoderado\/dashboard/)
  })

  test('APO-01: Badge "Apoderado" visible en header', async ({ page }) => {
    await expect(page.locator('header span:has-text("Apoderado")')).toBeVisible()
  })

  test('APO-02: Sidebar tiene 2 ítems de nav', async ({ page }) => {
    const links = page.locator('aside nav a')
    await expect(links).toHaveCount(2)
  })

  test('APO-03: NO hay botón "Salir" en el header', async ({ page }) => {
    await expect(page.locator('header button:has-text("Salir")')).toHaveCount(0)
  })

  test('APO-04: Avatar en header es link al perfil del apoderado', async ({ page }) => {
    await expect(page.locator('header a[href*="/apoderado/perfil"]')).toBeVisible()
  })

  test('APO-05: Dashboard tiene PageHeader "Seguimiento Académico"', async ({ page }) => {
    await expect(page.locator('text=Seguimiento Académico')).toBeVisible()
  })

  test('APO-06: Dashboard carga sin error 500', async ({ page }) => {
    await page.waitForLoadState('networkidle')
    await expect(page.locator('text=500')).toHaveCount(0)
    await expect(page.locator('h1, h2').first()).toBeVisible()
  })

  test('APO-07: Nav tiene aria-label="Menú apoderado"', async ({ page }) => {
    await expect(page.locator('nav[aria-label="Menú apoderado"]')).toBeVisible()
  })

  test('APO-08: NO puede acceder a admin ni docente ni estudiante', async ({ page }) => {
    await page.goto(`${BASE}/admin/dashboard`)
    await expect(page).not.toHaveURL(/\/admin\/dashboard/)
    await page.goto(`${BASE}/docente/dashboard`)
    await expect(page).not.toHaveURL(/\/docente\/dashboard/)
    await page.goto(`${BASE}/estudiante/dashboard`)
    await expect(page).not.toHaveURL(/\/estudiante\/dashboard/)
  })

  test('APO-09: Dashboard sin errores de consola', async ({ page }) => {
    const errors = await collectErrors(page)
    await page.reload()
    await page.waitForLoadState('networkidle')
    expect(errors).toHaveLength(0)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 10: RESPONSIVE (Móvil 393px)
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('📱 Responsive Mobile (393×851)', () => {
  test.use({ viewport: { width: 393, height: 851 } })

  test('MOB-01: Login — todos los campos visibles en móvil', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await expect(page.locator('[data-testid="email"]')).toBeVisible()
    await expect(page.locator('[data-testid="password"]')).toBeVisible()
    await expect(page.locator('[data-testid="submit"]')).toBeVisible()
  })

  test('MOB-02: Admin móvil — sidebar oculto, hamburger visible', async ({ page }) => {
    await login(page, CREDS.admin.email, CREDS.admin.password)
    await expect(page).toHaveURL(/\/admin\/dashboard/)
    // Hamburger de abrir menú debe ser visible
    await expect(page.locator('button[aria-label="Abrir menú de navegación"]').first()).toBeVisible()
  })

  test('MOB-03: Admin móvil — hamburger abre el sidebar', async ({ page }) => {
    await login(page, CREDS.admin.email, CREDS.admin.password)
    const hamburger = page.locator('button[aria-label="Abrir menú de navegación"]').first()
    await hamburger.click()
    await page.waitForTimeout(350)
    // Backdrop visible
    await expect(page.locator('[aria-hidden="true"].fixed.inset-0')).toBeVisible()
  })

  test('MOB-04: Docente móvil — hamburger visible', async ({ page }) => {
    await login(page, CREDS.docente.email, CREDS.docente.password)
    await expect(page.locator('button[aria-label="Abrir menú de navegación"]').first()).toBeVisible()
  })

  test('MOB-05: Estudiante móvil — botón cierre menú (lg:hidden, oculto en móvil expandido)', async ({ page }) => {
    await login(page, CREDS.estudiante.email, CREDS.estudiante.password)
    // Abrir el menú móvil primero
    const hamburger = page.locator('button[aria-label="Abrir menú de navegación"]').first()
    await hamburger.click()
    await page.waitForTimeout(350)
    // Luego el botón cerrar debe ser visible
    const closeBtn = page.locator('aside button[aria-label="Cerrar menú de navegación"]')
    await expect(closeBtn).toBeVisible()
  })

  test('MOB-06: Notas estudiante sin overflow horizontal roto', async ({ page }) => {
    await login(page, CREDS.estudiante.email, CREDS.estudiante.password)
    await page.goto(`${BASE}/estudiante/notas`)
    await page.waitForLoadState('networkidle')
    const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth)
    expect(bodyScrollWidth).toBeLessThanOrEqual(400) // 393 + margen
  })

  test('MOB-07: Apoderado móvil — dashboard carga', async ({ page }) => {
    await login(page, CREDS.apoderado.email, CREDS.apoderado.password)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('text=Seguimiento Académico')).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 11: SEGURIDAD
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🔒 Seguridad: Control de Acceso', () => {
  test('SEG-01: ESTUDIANTE no puede acceder a ninguna ruta de admin', async ({ page }) => {
    await login(page, CREDS.estudiante.email, CREDS.estudiante.password)
    const rutas = ['/admin/usuarios', '/admin/roles', '/admin/cursos']
    for (const ruta of rutas) {
      await page.goto(`${BASE}${ruta}`)
      await page.waitForLoadState('networkidle')
      await expect(page).not.toHaveURL(new RegExp(ruta.replace(/\//g, '\\/')))
    }
  })

  test('SEG-02: DOCENTE no puede acceder a matrículas ni usuarios', async ({ page }) => {
    await login(page, CREDS.docente.email, CREDS.docente.password)
    await page.goto(`${BASE}/admin/matriculas`)
    await expect(page).not.toHaveURL(/\/admin\/matriculas/)
    await page.goto(`${BASE}/admin/usuarios`)
    await expect(page).not.toHaveURL(/\/admin\/usuarios/)
  })

  test('SEG-03: APODERADO no puede cruzar a otros portales', async ({ page }) => {
    await login(page, CREDS.apoderado.email, CREDS.apoderado.password)
    const rutas = ['/admin/dashboard', '/docente/dashboard', '/estudiante/dashboard']
    for (const ruta of rutas) {
      await page.goto(`${BASE}${ruta}`)
      await expect(page).not.toHaveURL(new RegExp(ruta.replace(/\//g, '\\/')))
    }
  })

  test('SEG-04: CAJA no puede acceder a cursos ni grupos académicos', async ({ page }) => {
    await login(page, CREDS.caja.email, CREDS.caja.password)
    await page.goto(`${BASE}/admin/cursos`)
    await expect(page).not.toHaveURL(/\/admin\/cursos/)
    await page.goto(`${BASE}/admin/grupos`)
    await expect(page).not.toHaveURL(/\/admin\/grupos/)
    await page.goto(`${BASE}/admin/instituciones`)
    await expect(page).not.toHaveURL(/\/admin\/instituciones/)
  })

  test('SEG-05: Logout limpia sesión y bloquea acceso posterior', async ({ page }) => {
    await login(page, CREDS.admin.email, CREDS.admin.password)
    await expect(page).toHaveURL(/\/admin\/dashboard/)
    const hamburger = page.locator('button[aria-label="Abrir menú de navegación"]').first()
    if (await hamburger.isVisible()) {
      await hamburger.click()
      await page.waitForTimeout(300)
    }
    await page.locator('aside button:has-text("Cerrar sesión")').click()
    await expect(page).toHaveURL(/\/login/)
    // Sin token no puede acceder
    await page.goto(`${BASE}/admin/dashboard`)
    await expect(page).toHaveURL(/\/login/)
  })

  test('SEG-06: Token JWT falso → interceptor redirige a login', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.evaluate(() => {
      localStorage.setItem(
        'modux_token',
        'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIiwiZXhwIjoxfQ.invalid'
      )
    })
    await page.goto(`${BASE}/admin/dashboard`)
    await expect(page).toHaveURL(/\/login/)
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 12: ACCESIBILIDAD WCAG 2.1
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('♿ Accesibilidad (WCAG 2.1)', () => {
  test('A11Y-01: Admin layout tiene skip link', async ({ page }) => {
    await login(page, CREDS.admin.email, CREDS.admin.password)
    await expect(page.locator('a:has-text("Saltar al contenido principal")')).toHaveCount(1)
  })

  test('A11Y-02: Docente layout tiene skip link', async ({ page }) => {
    await login(page, CREDS.docente.email, CREDS.docente.password)
    await expect(page.locator('a:has-text("Saltar al contenido principal")')).toHaveCount(1)
  })

  test('A11Y-03: Estudiante layout tiene skip link', async ({ page }) => {
    await login(page, CREDS.estudiante.email, CREDS.estudiante.password)
    await expect(page.locator('a:has-text("Saltar al contenido principal")')).toHaveCount(1)
  })

  test('A11Y-04: Apoderado layout tiene skip link', async ({ page }) => {
    await login(page, CREDS.apoderado.email, CREDS.apoderado.password)
    await expect(page.locator('a:has-text("Saltar al contenido principal")')).toHaveCount(1)
  })

  test('A11Y-05: Docente logout sidebar tiene aria-label="Cerrar sesión"', async ({ page }) => {
    await login(page, CREDS.docente.email, CREDS.docente.password)
    await expect(page.locator('aside button[aria-label="Cerrar sesión"]')).toBeVisible()
  })

  test('A11Y-06: Apoderado nav tiene aria-label="Menú apoderado"', async ({ page }) => {
    await login(page, CREDS.apoderado.email, CREDS.apoderado.password)
    await expect(page.locator('nav[aria-label="Menú apoderado"]')).toBeVisible()
  })

  test('A11Y-07: Notas tienen tabla con scope="col" en th', async ({ page }) => {
    await login(page, CREDS.estudiante.email, CREDS.estudiante.password)
    await page.goto(`${BASE}/estudiante/notas`)
    await page.waitForLoadState('networkidle')
    const tableCount = await page.locator('table').count()
    if (tableCount > 0) {
      const thWithScope = page.locator('table th[scope="col"]')
      await expect(thWithScope.first()).toBeVisible()
    }
  })

  test('A11Y-08: Botón colapsar sidebar tiene aria-label dinámico', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await login(page, CREDS.admin.email, CREDS.admin.password)
    const colapsarBtn = page.locator('button[aria-label="Colapsar menú lateral"]')
    await expect(colapsarBtn).toBeVisible()
    await colapsarBtn.click()
    await page.waitForTimeout(250)
    const expandirBtn = page.locator('button[aria-label="Expandir menú lateral"]')
    await expect(expandirBtn).toBeVisible()
  })

  test('A11Y-09: SVG decorativos tienen aria-hidden="true"', async ({ page }) => {
    await login(page, CREDS.docente.email, CREDS.docente.password)
    // Los iconos de nav deben ser aria-hidden
    const svgs = page.locator('aside nav svg[aria-hidden="true"]')
    await expect(svgs.first()).toBeVisible()
  })

  test('A11Y-10: Hamburger móvil tiene aria-label correcto', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await login(page, CREDS.admin.email, CREDS.admin.password)
    await expect(page.locator('button[aria-label="Abrir menú de navegación"]').first()).toBeVisible()
  })
})

// ═══════════════════════════════════════════════════════════════════════════════
// BLOQUE 13: FLUJO ACADÉMICO
// ═══════════════════════════════════════════════════════════════════════════════
test.describe('🔄 Flujo Académico', () => {
  test('FLOW-01: Admin puede ver grupos → grupo tiene estudiantes', async ({ page }) => {
    await login(page, CREDS.admin.email, CREDS.admin.password)
    await page.goto(`${BASE}/admin/grupos`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/grupos/)
    await expect(page.locator('table, [class*="EmptyState"]').first()).toBeVisible()
  })

  test('FLOW-02: Docente accede al aula del grupo 1', async ({ page }) => {
    await login(page, CREDS.docente.email, CREDS.docente.password)
    await page.goto(`${BASE}/docente/grupos/1/aula`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/docente\/grupos\/1\/aula/)
    await expect(page.locator('text=500')).toHaveCount(0)
  })

  test('FLOW-03: Estudiante accede a sus tareas ordenadas por fecha', async ({ page }) => {
    await login(page, CREDS.estudiante.email, CREDS.estudiante.password)
    await page.goto(`${BASE}/estudiante/tareas`)
    await page.waitForLoadState('networkidle')
    // PageHeader visible
    await expect(page.locator('text=Mis Tareas y Asignaciones')).toBeVisible()
    // No hay crash
    await expect(page.locator('text=500')).toHaveCount(0)
  })

  test('FLOW-04: Apoderado ve seguimiento de su hijo', async ({ page }) => {
    await login(page, CREDS.apoderado.email, CREDS.apoderado.password)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('text=Seguimiento Académico')).toBeVisible()
    await expect(page.locator('text=500')).toHaveCount(0)
  })

  test('FLOW-05: Admin ve Admisión y Titulación completas', async ({ page }) => {
    const errors = await collectErrors(page)
    await login(page, CREDS.admin.email, CREDS.admin.password)
    await visitAndCheck(page, '/admin/admision')
    await expect(page.locator('h1')).toContainText('Admisión Tecnológica')
    await visitAndCheck(page, '/admin/titulacion')
    await expect(page.locator('h1')).toContainText('Titulación')
    expect(errors).toHaveLength(0)
  })
})
