import { test, expect, type Page } from '@playwright/test'
import * as path from 'path'
import * as fs from 'fs'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

async function login(page: Page, email: string, pass: string) {
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('networkidle')
  await page.fill('[data-testid="email"]', email)
  await page.fill('[data-testid="password"]', pass)
  await page.click('[data-testid="submit"]')
  await page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 15000 })
  await page.waitForLoadState('networkidle')
}

test.describe('Docente — Actividades con Archivos, Comentarios y Recursos', () => {
  test.describe.configure({ mode: 'serial' })
  const tmpFilePath = path.resolve('tests/e2e/guia_caso_clinico.docx')

  test.beforeAll(() => {
    fs.writeFileSync(tmpFilePath, 'Contenido de prueba para el archivo Word de la actividad académica docente.')
  })

  test.afterAll(() => {
    if (fs.existsSync(tmpFilePath)) {
      fs.unlinkSync(tmpFilePath)
    }
  })

  test('DOC-ACT-01: Docente puede crear actividad con archivo Word, comentarios, textos y titulo', async ({ page }) => {
    // 1. Iniciar sesión como Docente
    await login(page, 'docente01@modux.demo', 'Docente2026!')
    await expect(page).toHaveURL(/\/docente\/dashboard/)

    // 2. Navegar a las tareas del grupo 1
    await page.goto(`${BASE}/docente/grupos/1/tareas`)
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1')).toContainText('Actividades del Curso')

    // 3. Abrir modal "Nueva Actividad"
    await page.click('button:has-text("Nueva Actividad")')
    await expect(page.getByRole('heading', { name: /Nueva Actividad/ })).toBeVisible()

    // 4. Completar campos solicitados: título, descripción, comentarios del docente y adjunto
    const tituloUnico = `Actividad Práctica - Caso Clínico ${Date.now()}`
    await page.fill('input[placeholder*="Ej: Informe de Práctica"]', tituloUnico)
    await page.fill('textarea[placeholder*="Breve resumen"]', 'Análisis detallado de la historia clínica y plan de cuidados de enfermería.')
    await page.fill('textarea[placeholder*="Escriba aquí los criterios"]', 'Pautas: Entregar informe en formato formal con introducción, marco conceptual y conclusiones.')

    // Adjuntar archivo Word (.docx)
    const fileInput = page.locator('input[type="file"]')
    await fileInput.setInputFiles(tmpFilePath)

    // Verificar que el nombre del archivo seleccionado se muestra
    await expect(page.locator('form').getByText('guia_caso_clinico.docx')).toBeVisible()

    // 5. Guardar la actividad
    await page.click('button:has-text("Publicar Actividad")')

    // 6. Verificar que la actividad aparece en la lista con su título, comentarios y archivo adjunto
    await expect(page.locator(`text=${tituloUnico}`)).toBeVisible({ timeout: 15000 })
    await expect(page.locator('text=Indicaciones y Comentarios del Docente:').first()).toBeVisible()
    await expect(page.locator('text=guia_caso_clinico.docx').first()).toBeVisible()
    await expect(page.locator('button:has-text("Descargar")').first()).toBeVisible()
  })

  test('DOC-ACT-02: Estudiante puede visualizar las instrucciones docentes y descargar el archivo adjunto', async ({ page }) => {
    // 1. Iniciar sesión como Estudiante
    await login(page, 'est001@modux.demo', 'Alumno2026!')
    await expect(page).toHaveURL(/\/estudiante\/dashboard/)

    // 2. Ir al curso 1
    await page.goto(`${BASE}/estudiante/cursos/1`)
    await page.waitForLoadState('networkidle')

    // 3. Cambiar a la pestaña "Tareas"
    const btnTareas = page.locator('button', { hasText: 'Tareas' })
    await btnTareas.click()

    // 4. Verificar que se visualizan las indicaciones docentes y el botón de descarga del adjunto
    await expect(page.locator('text=Indicaciones y Comentarios del Docente:').first()).toBeVisible()
    const descargarBtn = page.locator('button:has-text("Descargar Guía de Trabajo")').first()
    await expect(descargarBtn).toBeVisible()
  })
})
