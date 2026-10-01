import { test, expect } from '@playwright/test'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

test.describe('Modux — SUPERADMIN (Global, Desactivar y Eliminar) vs ADMINISTRADOR (Solo Editar y Desactivar)', () => {

  test('ADMINISTRADOR: Solo puede editar y desactivar (no eliminar) en interfaz y API', async ({ page }) => {
    // 1. Login con ADMINISTRADOR
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)

    // 2. Módulo de Usuarios
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('Usuarios')

    // El Administrador DEBE ver botón "Desactivar" y NO debe ver botón "Eliminar"
    const desactivarBtn = page.locator('[data-testid="desactivar-btn"]').first()
    await expect(desactivarBtn).toBeVisible()
    await expect(desactivarBtn).toHaveText('Desactivar')
    await expect(page.locator('[data-testid="eliminar-btn"]')).toHaveCount(0)

    // Editar un usuario
    const editBtn = page.locator('button:has-text("Editar")').first()
    await editBtn.click()
    await expect(page.locator('text=Editar Usuario')).toBeVisible()
    await page.click('button:has-text("Cancelar")')

    // Desactivar un usuario activo
    page.once('dialog', async dialog => {
      expect(dialog.message()).toContain('desactivar')
      await dialog.accept()
    })
    await desactivarBtn.click()
    await page.waitForTimeout(500)

    // 3. Módulo de Cursos
    await page.goto(`${BASE}/admin/cursos`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('Catálogo de Cursos')

    // El Administrador en Cursos solo tiene "Desactivar"
    const desactivarCursoBtn = page.locator('[data-testid="desactivar-curso-btn"]').first()
    await expect(desactivarCursoBtn).toBeVisible()
    await expect(desactivarCursoBtn).toHaveText('Desactivar')
    await expect(page.locator('[data-testid="eliminar-curso-btn"]')).toHaveCount(0)

    // 4. Módulo de Docentes
    await page.goto(`${BASE}/admin/docentes`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('Planta Docente')

    const desactivarDocenteBtn = page.locator('[data-testid="desactivar-docente-btn"]').first()
    await expect(desactivarDocenteBtn).toBeVisible()
    await expect(desactivarDocenteBtn).toHaveText('Desactivar')
    await expect(page.locator('[data-testid="eliminar-docente-btn"]')).toHaveCount(0)

    // 5. Módulo de Estudiantes
    await page.goto(`${BASE}/admin/estudiantes`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('Alumnado')

    const desactivarEstudianteBtn = page.locator('[data-testid="desactivar-estudiante-btn"]').first()
    await expect(desactivarEstudianteBtn).toBeVisible()
    await expect(desactivarEstudianteBtn).toHaveText('Desactivar')
    await expect(page.locator('[data-testid="eliminar-estudiante-btn"]')).toHaveCount(0)

    // 6. Prueba de Seguridad API: Si ADMINISTRADOR intenta forzar un borrado permanente (?force=true), la API lo rechaza con 403 Forbidden
    const token = await page.evaluate(() => localStorage.getItem('modux_token'))
    const apiRes = await page.request.delete(`${BASE}/api/v1/admin/usuarios/2?force=true`, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    })
    expect(apiRes.status()).toBe(403)
    const errBody = await apiRes.json()
    expect(errBody.error).toBe('forbidden_action')
  })

  test('SUPERADMIN: Puede ver/editar cualquier información y tiene opciones de Desactivar y Eliminar', async ({ page }) => {
    // 1. Login con SUPERADMIN
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'superadmin@modux.demo')
    await page.fill('[data-testid="password"]', 'SuperAdmin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)
    await expect(page.locator('text=Superadmin')).toBeVisible()

    // 2. Módulo de Usuarios
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('Usuarios')

    // SUPERADMIN DEBE ver AMBOS botones: "Desactivar" Y "Eliminar"
    await expect(page.locator('[data-testid="desactivar-btn"]').first()).toBeVisible()
    await expect(page.locator('[data-testid="eliminar-btn"]').first()).toBeVisible()

    // Crear un usuario temporal para probar eliminación definitiva
    const randomUser = `temp_${Date.now()}`
    await page.click('[data-testid="nuevo-usuario-btn"]')
    await page.fill('[data-testid="nombres-input"]', 'Usuario Temporal')
    await page.fill('[data-testid="apellido-paterno-input"]', 'Playwright')
    await page.fill('[data-testid="email-input"]', `${randomUser}@modux.demo`)
    await page.fill('[data-testid="username-input"]', randomUser)
    await page.fill('[data-testid="password-input"]', 'TempPass2026!')

    // Esperar a que los roles carguen en el select
    const roleOptions = page.locator('[data-testid="rol-select"] option:not([disabled])')
    await expect(roleOptions).not.toHaveCount(0)
    const roleVal = await roleOptions.first().getAttribute('value')
    await page.selectOption('[data-testid="rol-select"]', roleVal!)

    await page.click('[data-testid="submit-user-btn"]')
    await page.waitForTimeout(500)

    // Verificar que el nuevo usuario aparece en la lista
    await expect(page.locator(`text=${randomUser}@modux.demo`)).toBeVisible()

    // Eliminar permanentemente el usuario creado como SUPERADMIN
    const newRow = page.locator('tr', { hasText: `${randomUser}@modux.demo` })

    // Desactivar y volver a activar desde la misma fila
    page.once('dialog', async dialog => {
      expect(dialog.message()).toContain('desactivar')
      await dialog.accept()
    })
    await newRow.locator('[data-testid="desactivar-btn"]').click()
    await expect(newRow.getByText('Inactivo', { exact: true })).toBeVisible()
    await expect(newRow.locator('[data-testid="activar-btn"]')).toBeVisible()

    page.once('dialog', async dialog => {
      expect(dialog.message()).toContain('activar nuevamente')
      await dialog.accept()
    })
    await newRow.locator('[data-testid="activar-btn"]').click()
    await expect(newRow.getByText('Activo', { exact: true })).toBeVisible()

    page.once('dialog', async dialog => {
      expect(dialog.message()).toContain('eliminar PERMANENTEMENTE')
      await dialog.accept()
    })
    await newRow.locator('[data-testid="eliminar-btn"]').click()
    await page.waitForTimeout(500)

    // Confirmar que el usuario ya NO existe en la tabla
    await expect(page.locator(`text=${randomUser}@modux.demo`)).toHaveCount(0)

    // 3. Módulo de Cursos
    await page.goto(`${BASE}/admin/cursos`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('Catálogo de Cursos')

    // SUPERADMIN tiene "Desactivar" Y "Eliminar"
    await expect(page.locator('[data-testid="desactivar-curso-btn"]').first()).toBeVisible()
    await expect(page.locator('[data-testid="eliminar-curso-btn"]').first()).toBeVisible()

    // Crear un curso temporal y eliminarlo permanentemente
    const randomCursoCode = `CUR-${Math.floor(Math.random() * 9000 + 1000)}`
    await page.click('button:has-text("+ Nuevo Curso")')
    await page.fill('input[placeholder="Ej: CUR-001"]', randomCursoCode)
    await page.fill('input[placeholder="Ej: Programación Web I"]', 'Curso Temporal E2E')
    await page.click('button[type="submit"]:has-text("Guardar")')
    await page.waitForTimeout(500)

    // Verificar que el curso existe
    await expect(page.locator(`text=${randomCursoCode}`)).toBeVisible()

    // Eliminar definitivamente el curso
    const cursoRow = page.locator('tr', { hasText: randomCursoCode })
    page.once('dialog', async dialog => {
      expect(dialog.message()).toContain('eliminar PERMANENTEMENTE')
      await dialog.accept()
    })
    await cursoRow.locator('[data-testid="eliminar-curso-btn"]').click()
    await page.waitForTimeout(500)

    // Confirmar que ya no existe
    await expect(page.locator(`text=${randomCursoCode}`)).toHaveCount(0)

    // 4. Módulo de Docentes y Estudiantes: SUPERADMIN tiene botones Desactivar y Eliminar
    await page.goto(`${BASE}/admin/docentes`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('[data-testid="desactivar-docente-btn"]').first()).toBeVisible()
    await expect(page.locator('[data-testid="eliminar-docente-btn"]').first()).toBeVisible()

    await page.goto(`${BASE}/admin/estudiantes`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('[data-testid="desactivar-estudiante-btn"]').first()).toBeVisible()
    await expect(page.locator('[data-testid="eliminar-estudiante-btn"]').first()).toBeVisible()
  })

})
