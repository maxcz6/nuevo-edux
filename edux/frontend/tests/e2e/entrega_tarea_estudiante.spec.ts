import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:8080'

test.describe('Estudiante — Entrega de Tareas y Reactividad en UI', () => {
  test('Envío de tarea actualiza reactivamente el estado a Entregada y muestra badges correctos', async ({ page }) => {
    // 1. Manejo automático de diálogos alert()
    page.on('console', msg => console.log('PAGE LOG:', msg.text()))
    page.on('response', async resp => {
      if (resp.url().includes('/aula/tareas/') && resp.status() >= 400) {
        console.log('API ERROR RESPONSE:', resp.status(), await resp.text())
      }
    })
    page.on('dialog', async (dialog) => {
      console.log('DIALOG DETECTED:', dialog.message())
      await dialog.accept()
    })

    // 2. Iniciar sesión como Estudiante
    await page.goto(`${BASE}/login`)
    await page.fill('input[type="email"]', 'est001@modux.demo')
    await page.fill('input[type="password"]', 'Alumno2026!')
    await page.click('button[type="submit"]')
    await page.waitForURL(/\/estudiante\/dashboard/, { timeout: 10000 })

    // 3. Ir al curso 1
    await page.goto(`${BASE}/estudiante/cursos/1`)
    await page.waitForLoadState('networkidle')

    // 4. Cambiar a la pestaña "Tareas"
    const tabTareas = page.locator('button', { hasText: 'Tareas' })
    if (await tabTareas.isVisible()) {
      await tabTareas.click()
    }

    // 5. Verificar si hay tareas en la lista
    const tareaCards = page.locator('.card')
    const count = await tareaCards.count()
    expect(count).toBeGreaterThan(0)

    // 6. Localizar el botón de acción ("Entregar Tarea" o "Actualizar Entrega")
    const actionBtn = page.locator('button', { hasText: /Entregar Tarea|Actualizar Entrega/ }).first()
    await expect(actionBtn).toBeVisible()

    const initialText = await actionBtn.textContent()

    // 7. Hacer clic para abrir el modal de entrega
    await actionBtn.click()

    // 8. Verificar que el modal se abrió
    const modalTitle = page.getByRole('heading', { name: 'Subir Entrega' })
    await expect(modalTitle).toBeVisible()

    // 9. Completar el comentario
    const comentariosInput = page.locator('textarea')
    await comentariosInput.fill('Trabajo académico entregado vía prueba automatizada Playwright.')

    // 10. Enviar la entrega
    const submitBtn = page.locator('button[type="submit"]', { hasText: /Enviar entrega/ })
    await submitBtn.click()

    // 11. Esperar a que el modal se cierre
    await expect(modalTitle).toBeHidden({ timeout: 10000 })

    // 12. Verificar que reactivamente (sin recargar F5) el estado se actualizó a "Entregada" o "Actualizar Entrega"
    const badgeEntregada = page.locator('span', { hasText: /Entregada|Calificada/ }).first()
    await expect(badgeEntregada).toBeVisible({ timeout: 10000 })

    const updatedBtn = page.locator('button', { hasText: /Actualizar Entrega|Tarea Calificada/ }).first()
    await expect(updatedBtn).toBeVisible()

    // 13. Verificar también la vista global de tareas
    await page.goto(`${BASE}/estudiante/tareas`)
    await page.waitForLoadState('networkidle')
    const badgeGlobal = page.locator('span', { hasText: /Entregada|Calificada/ }).first()
    await expect(badgeGlobal).toBeVisible()
  })
})
