import { test, expect } from '@playwright/test'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

test.describe('Docentes View — Modal de Edición y Creación', () => {
  test('En "Editar Docente" no debe aparecer el selector de Programa de Estudios, pero en "Nuevo Docente" sí', async ({ page }) => {
    // 1. Iniciar sesión como ADMINISTRADOR
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', 'admin@modux.demo')
    await page.fill('[data-testid="password"]', 'Admin2026!')
    await page.click('[data-testid="submit"]')
    await expect(page).toHaveURL(/\/admin\/dashboard/)

    // 2. Ir a la vista de Docentes
    await page.goto(`${BASE}/admin/docentes`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('Planta Docente')

    // 3. Abrir modal de Editar Docente en el primer registro
    const editButtons = page.locator('button:has-text("Editar")')
    await expect(editButtons.first()).toBeVisible({ timeout: 5000 })
    await editButtons.first().click()

    // 4. Verificar que el título sea "Editar Docente"
    const modalTitle = page.locator('h3:has-text("Editar Docente")')
    await expect(modalTitle).toBeVisible()

    // Capturar screenshot del modal de edición
    await page.screenshot({ path: 'test-results/editar_docente_modal.png' })

    // 5. Verificar que el selector "Programa de Estudios (Carrera)" NO esté en el DOM
    const programaSelectInEdit = page.locator('label:has-text("Programa de Estudios (Carrera)")')
    await expect(programaSelectInEdit).toHaveCount(0)

    // 6. Cerrar modal de edición
    await page.click('button:has-text("Cancelar")')
    await expect(modalTitle).not.toBeVisible()

    // 7. Abrir modal de "Nuevo Docente"
    const newBtn = page.locator('button:has-text("Nuevo Docente")')
    await newBtn.click()

    // 8. Verificar que en "Nuevo Docente" SÍ aparezca el selector
    const createModalTitle = page.locator('h3:has-text("Nuevo Docente")')
    await expect(createModalTitle).toBeVisible()
    const programaSelectInCreate = page.locator('label:has-text("Programa de Estudios (Carrera)")')
    await expect(programaSelectInCreate).toBeVisible()
    const selectElem = page.locator('select:has-text("-- Seleccionar Programa --")')
    await expect(selectElem).toBeVisible()

    // 9. Cerrar modal
    await page.click('button:has-text("Cancelar")')
    await expect(createModalTitle).not.toBeVisible()
  })
})
