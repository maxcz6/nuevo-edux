import { test, expect } from '@playwright/test'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

test.describe('Modux — Gestión de Usuarios y Asignación de Roles', () => {

  test('ADMINISTRADOR: Puede crear un nuevo usuario con rol asignado y cambiar su contraseña', async ({ page }) => {
    // 1. Iniciar sesión como ADMINISTRADOR
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)

    // 2. Navegar a Gestión de Usuarios
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/admin\/usuarios/)

    // 3. Abrir modal de Nuevo Usuario
    const nuevoUsuarioBtn = page.locator('[data-testid="nuevo-usuario-btn"]')
    await expect(nuevoUsuarioBtn).toBeVisible()
    await nuevoUsuarioBtn.click()

    // 4. Verificar que el modal y el selector de rol están presentes
    const modalTitle = page.locator('h2:has-text("Nuevo Usuario")')
    await expect(modalTitle).toBeVisible()

    const rolSelect = page.locator('[data-testid="rol-select"]')
    await expect(rolSelect).toBeVisible()

    // El ADMINISTRADOR no debe tener la opción de asignar SUPERADMIN
    const superadminOption = rolSelect.locator('option:has-text("SUPERADMIN")')
    expect(await superadminOption.count()).toBe(0)

    // 5. Completar datos del nuevo usuario
    const timestamp = Date.now()
    const testUsername = `profesor_${timestamp}`
    const testEmail = `docente_${timestamp}@modux.demo`

    await page.fill('[data-testid="nombres-input"]', 'Alejandro')
    await page.fill('[data-testid="apellido-paterno-input"]', 'Valdez')
    await page.fill('[data-testid="email-input"]', testEmail)
    await page.fill('[data-testid="username-input"]', testUsername)
    const docOption = rolSelect.locator('option:has-text("DOCENTE")')
    const docValue = await docOption.getAttribute('value')
    await rolSelect.selectOption(docValue!)
    await page.fill('[data-testid="password-input"]', 'Docente2026!*')

    // 6. Enviar formulario
    await page.click('[data-testid="submit-user-btn"]')

    // 7. Verificar que el modal se cerró y el usuario aparece en la tabla con el rol DOCENTE
    await expect(modalTitle).not.toBeVisible()
    await page.waitForLoadState('networkidle')

    const userRow = page.locator(`tr:has-text("${testEmail}")`)
    await expect(userRow).toBeVisible()
    await expect(userRow.locator('span:has-text("DOCENTE")')).toBeVisible()

    // 8. Abrir modal de cambio de contraseña administrativo
    const cambiarPasswordBtn = userRow.locator('[data-testid="cambiar-password-btn"]')
    await expect(cambiarPasswordBtn).toBeVisible()
    await cambiarPasswordBtn.click()

    const pwdModalTitle = page.locator('h3:has-text("Cambiar Contraseña")')
    await expect(pwdModalTitle).toBeVisible()
  })

  test('ADMINISTRADOR: No puede asignar el rol SUPERADMIN ni cambiar la contraseña de un SUPERADMIN', async ({ page }) => {
    // 1. Iniciar sesión como ADMINISTRADOR
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)

    // 2. Navegar a /admin/usuarios
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')

    // 3. Abrir modal de creación y verificar que SUPERADMIN no está en la lista de opciones
    await page.click('[data-testid="nuevo-usuario-btn"]')
    const rolSelect = page.locator('[data-testid="rol-select"]')
    const options = await rolSelect.locator('option').allInnerTexts()
    expect(options.some(opt => opt.includes('SUPERADMIN'))).toBe(false)
  })

})
