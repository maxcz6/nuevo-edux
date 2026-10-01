import { test, expect } from '@playwright/test'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

test.describe('Modux — Ciclo de Vida Completo de Usuario y Rol (CRUD E2E)', () => {

  test('Flujo E2E: Crear usuario con rol → Login con el nuevo usuario → Editar datos/rol → Desactivar usuario e impedir login', async ({ page }) => {
    const timestamp = Date.now()
    const testUsername = `user_crud_${timestamp}`
    const testEmail = `docente_crud_${timestamp}@modux.demo`
    const testPassword = 'DocenteCrud2026!*'

    // ── 1. Login como ADMINISTRADOR ─────────────────────────────────────────
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)

    // ── 2. Navegar a /admin/usuarios ─────────────────────────────────────────
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')
    await expect(page.getByRole('heading', { name: 'Usuarios', level: 1 })).toBeVisible()

    // ── 3. Abrir modal y validar que rol no está preseleccionado ────────────
    const nuevoBtn = page.getByTestId('nuevo-usuario-btn')
    await expect(nuevoBtn).toBeVisible()
    await nuevoBtn.click()

    const modalTitle = page.getByRole('heading', { name: 'Nuevo Usuario', level: 2 })
    await expect(modalTitle).toBeVisible()

    const rolSelect = page.getByTestId('rol-select')
    await expect(rolSelect).toBeVisible()

    // El ADMINISTRADOR no debe tener la opción de asignar SUPERADMIN
    const superadminOption = rolSelect.locator('option:has-text("SUPERADMIN")')
    expect(await superadminOption.count()).toBe(0)

    // ── 4. Rellenar formulario y asignar rol DOCENTE ─────────────────────────
    await page.fill('[data-testid="nombres-input"]', 'Docente')
    await page.fill('[data-testid="apellido-paterno-input"]', 'Automated')
    await page.fill('[data-testid="email-input"]', testEmail)
    await page.fill('[data-testid="username-input"]', testUsername)

    const docOption = rolSelect.locator('option:has-text("DOCENTE")')
    const docValue = await docOption.getAttribute('value')
    expect(docValue).toBeTruthy()
    await rolSelect.selectOption(docValue!)

    await page.fill('[data-testid="password-input"]', testPassword)
    await page.click('[data-testid="submit-user-btn"]')

    // El modal debe cerrarse y el nuevo usuario debe aparecer en la tabla
    await expect(modalTitle).not.toBeVisible()
    await page.waitForLoadState('networkidle')

    const userRow = page.locator(`tr:has-text("${testEmail}")`)
    await expect(userRow).toBeVisible()
    await expect(userRow.locator('span:has-text("DOCENTE")')).toBeVisible()
    await expect(userRow.locator('span:has-text("Activo")')).toBeVisible()

    const logout = async () => {
      const hamburger = page.locator('button[aria-label="Abrir menú de navegación"]').first()
      if (await hamburger.isVisible()) {
        await hamburger.click()
        await page.waitForTimeout(300)
      }
      await page.click('button:has-text("Cerrar sesión")')
      await expect(page).toHaveURL(/\/login/)
    }

    // ── 5. Cerrar sesión de ADMIN e iniciar sesión con el nuevo DOCENTE ──────
    await logout()

    await page.fill('[data-testid="email"]', testEmail)
    await page.fill('[data-testid="password"]', testPassword)
    await page.click('[data-testid="submit"]')

    // Debe ingresar directamente al panel de docente
    await expect(page).toHaveURL(/\/docente\/dashboard/)
    await expect(page.getByRole('heading', { name: 'Mis Grupos', level: 1 })).toBeVisible()

    // ── 6. Cerrar sesión de docente y volver como ADMIN para editar ──────────
    await logout()

    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)

    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')

    const userRowToEdit = page.locator(`tr:has-text("${testEmail}")`)
    await expect(userRowToEdit).toBeVisible()

    // Abrir modal de edición
    await userRowToEdit.locator('button:has-text("Editar")').click()
    const editModalTitle = page.getByRole('heading', { name: 'Editar Usuario', level: 2 })
    await expect(editModalTitle).toBeVisible()

    // Cambiar estado a INACTIVO
    await page.getByTestId('estado-select').selectOption('INACTIVO')
    await page.getByTestId('submit-user-btn').click()

    await expect(editModalTitle).not.toBeVisible()
    await page.waitForLoadState('networkidle')

    // Verificar que en la tabla ahora figura como Inactivo
    const updatedRow = page.locator(`tr:has-text("${testEmail}")`)
    await expect(updatedRow.locator('span:has-text("Inactivo")')).toBeVisible()

    // ── 7. Intentar login con el usuario desactivado y verificar rechazo ─────
    await logout()

    await page.fill('[data-testid="email"]', testEmail)
    await page.fill('[data-testid="password"]', testPassword)
    await page.click('[data-testid="submit"]')

    // El sistema debe rechazar el acceso y mantenerlo en /login con alerta
    await expect(page).toHaveURL(/\/login/)
    const alertMsg = page.getByTestId('error-message')
    await expect(alertMsg).toBeVisible()
  })

})
