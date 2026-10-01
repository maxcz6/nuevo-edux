import { test, expect } from '@playwright/test'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

test.describe('Filtros, Búsqueda y Activación de Usuarios y Estudiantes', () => {

  test('SUPERADMIN: Búsqueda y filtrado rápido de Estudiantes en /admin/usuarios', async ({ page }) => {
    // 1. Iniciar sesión como superadmin
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'superadmin@modux.demo')
    await page.fill('[data-testid="password"]', 'SuperAdmin2026!')
    await page.click('[data-testid="submit"]')
    await page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 15000 })

    // 2. Navegar a /admin/usuarios
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')

    // 3. Verificar que los elementos de búsqueda y filtros existen
    const searchInput = page.locator('[data-testid="usuario-search-input"]')
    const filtroEstudiantesBtn = page.locator('[data-testid="filtro-estudiantes-btn"]')
    const filtroRolSelect = page.locator('[data-testid="filtro-rol-select"]')
    const filtroEstadoSelect = page.locator('[data-testid="filtro-estado-select"]')

    await expect(searchInput).toBeVisible()
    await expect(filtroEstudiantesBtn).toBeVisible()
    await expect(filtroRolSelect).toBeVisible()
    await expect(filtroEstadoSelect).toBeVisible()

    // 4. Click en la píldora rápida de "Estudiantes"
    await filtroEstudiantesBtn.click()
    await page.waitForTimeout(500)
    await page.waitForLoadState('networkidle')

    // Verificar que el select de rol se actualizó a ESTUDIANTE
    await expect(filtroRolSelect).toHaveValue('ESTUDIANTE')

    // Debe mostrar badges de rol ESTUDIANTE en la tabla
    const badgeEstudiante = page.locator('span:has-text("ESTUDIANTE")').first()
    await expect(badgeEstudiante).toBeVisible()

    // 5. Probar búsqueda por nombre o email de estudiante
    await searchInput.fill('Lucia')
    await searchInput.press('Enter')
    await page.waitForTimeout(500)
    await page.waitForLoadState('networkidle')

    // La tabla debe contener al estudiante Lucia
    await expect(page.locator('table')).toContainText('Lucia')

    // 6. Probar filtro por estado
    await filtroEstadoSelect.selectOption('ACTIVO')
    await page.waitForTimeout(500)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('table')).toContainText('Activo')
  })

  test('ADMINISTRADOR: Puede activar y desactivar usuarios sin restricción de interfaz', async ({ page }) => {
    // 1. Iniciar sesión como administrador
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 15000 })

    // 2. Navegar a /admin/usuarios
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')

    // 3. Filtrar por Estudiantes
    await page.locator('[data-testid="filtro-estudiantes-btn"]').click()
    await page.waitForTimeout(500)
    await page.waitForLoadState('networkidle')

    // 4. Encontrar una fila de usuario y verificar que tiene botón Desactivar
    const deactivateBtn = page.locator('[data-testid="desactivar-btn"]').first()
    await expect(deactivateBtn).toBeVisible()

    // 5. Comprobar que NO tiene el botón de Eliminar permanente (reservado a SUPERADMIN)
    const deleteBtn = page.locator('[data-testid="eliminar-btn"]')
    await expect(deleteBtn).toHaveCount(0)
  })

  test('Roles y Estudiantes: Búsqueda reactiva y filtros de estado', async ({ page }) => {
    // 1. Login como admin
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 15000 })

    // 2. Vista de Roles
    await page.goto(`${BASE}/admin/roles`)
    await page.waitForLoadState('networkidle')

    const searchRoles = page.locator('input[placeholder*="Buscar rol"]')
    await expect(searchRoles).toBeVisible()
    await searchRoles.fill('ESTUDIANTE')
    await expect(page.locator('text=ESTUDIANTE').first()).toBeVisible()

    // 3. Vista de Estudiantes
    await page.goto(`${BASE}/admin/estudiantes`)
    await page.waitForLoadState('networkidle')

    const searchEstudiantes = page.locator('input[placeholder*="Buscar estudiante"]')
    await expect(searchEstudiantes).toBeVisible()

    // Selector de estado en Estudiantes
    const estadoSelect = page.locator('select:has-text("Todos los Estados")')
    await expect(estadoSelect).toBeVisible()

    // Buscar "Lucia"
    await searchEstudiantes.fill('Lucia')
    await page.click('button:has-text("Buscar")')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('table')).toContainText('Lucia')
  })

})
