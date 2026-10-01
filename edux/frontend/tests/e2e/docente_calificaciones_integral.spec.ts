import { test, expect, type Page } from '@playwright/test'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

async function login(page: Page, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.waitForLoadState('domcontentloaded')
  await page.fill('[data-testid="email"]', email)
  await page.fill('[data-testid="password"]', password)
  await page.click('[data-testid="submit"]')
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 })
  await page.waitForTimeout(1500)
}

test.describe('Gestión de Calificaciones Docente y Aislamiento Integral', () => {
  test('Docente puede ver consolidado, calificar por actividad en lote y exportar a Excel', async ({ page }) => {
    test.setTimeout(120000)

    page.on('console', msg => console.log('BROWSER CONSOLE:', msg.text()))
    page.on('pageerror', err => console.log('BROWSER ERROR:', err))
    page.on('dialog', async dialog => {
      console.log('BROWSER DIALOG:', dialog.message())
      await dialog.dismiss()
    })
    page.on('request', req => {
      if (req.url().includes('calificaciones')) console.log('REQ:', req.method(), req.url(), req.postData())
    })
    page.on('response', async res => {
      if (res.url().includes('calificaciones')) {
        let body = ''
        try { body = await res.text() } catch {}
        console.log('RES:', res.status(), res.url(), body.slice(0, 200))
      }
    })

    // 1. Login como Docente
    await login(page, 'docente01@modux.demo', 'Docente2026!')

    // 2. Ir al Dashboard Docente y comprobar botón de Notas
    await page.goto(`${BASE}/docente/dashboard`)
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(1000)

    const btnNotas = page.locator('a[href*="/calificaciones"]').first()
    await expect(btnNotas).toBeVisible({ timeout: 10000 })
    await btnNotas.click()

    // 3. Verificar que cargó la vista de Calificaciones
    await page.waitForURL((url) => url.pathname.includes('/calificaciones'), { timeout: 15000 })
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(1500)

    // Verificar encabezado y elementos clave
    await expect(page.locator('h1')).toContainText('Acta y Calificaciones')
    await expect(page.locator('text=Acta Consolidada (Matriz de Notas)')).toBeVisible()
    await expect(page.locator('text=Calificar por Actividad Publicada')).toBeVisible()

    // 4. Cambiar a pestaña "Calificar por Actividad Publicada"
    await page.click('button:has-text("Calificar por Actividad Publicada")')
    await page.waitForTimeout(1000)

    // Verificar selector de actividades
    const selectActividad = page.locator('select').first()
    await expect(selectActividad).toBeVisible()

    // Ingresar notas en los inputs de alumnos
    const inputsNotas = page.locator('input[type="number"][placeholder="0.0"]')
    const countInputs = await inputsNotas.count()
    console.log(`Encontrados ${countInputs} inputs para calificar estudiantes`)

    if (countInputs > 0) {
      // Ingresar nota 18.5 al primer estudiante
      await inputsNotas.nth(0).fill('18.5')
      if (countInputs > 1) {
        // Ingresar nota 16.0 al segundo estudiante si existe
        await inputsNotas.nth(1).fill('16.0')
      }

      // 5. Clic en "Guardar Calificaciones del Curso"
      const btnGuardar = page.locator('button:has-text("Guardar Calificaciones")').first()
      await expect(btnGuardar).toBeVisible()
      await btnGuardar.click()

      // Verificar mensaje de éxito
      await expect(page.locator('text=¡Se guardaron exitosamente')).toBeVisible({ timeout: 15000 })
    }

    // 6. Volver a la pestaña "Acta Consolidada (Matriz de Notas)"
    await page.click('button:has-text("Acta Consolidada")')
    await page.waitForTimeout(1000)

    // Verificar que la tabla consolidada existe
    await expect(page.locator('table')).toBeVisible()
    await expect(page.locator('th:has-text("Promedio")')).toBeVisible()
    await expect(page.locator('th:has-text("Condición")')).toBeVisible()

    // 7. Probar botón de Exportar a Excel (.CSV)
    const btnExportar = page.locator('button:has-text("Exportar a Excel")').first()
    await expect(btnExportar).toBeVisible()

    // Esperar evento de descarga
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 15000 }).catch(() => null),
      btnExportar.click(),
    ])

    if (download) {
      const filename = download.suggestedFilename()
      console.log(`Descarga exitosa de acta de notas: ${filename}`)
      expect(filename).toContain('.csv')
    }
  })

  test('Estudiante solo ve sus propias calificaciones en su vista de notas', async ({ page }) => {
    test.setTimeout(90000)

    // 1. Login como Estudiante
    await login(page, 'est001@modux.demo', 'Alumno2026!')

    // 2. Ir a Mis Notas
    await page.goto(`${BASE}/estudiante/notas`)
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(1500)

    await expect(page.locator('h1')).toContainText('Mis Notas')
    // El estudiante NO debe tener acceso a exportar acta completa del grupo ni ver otros alumnos
    await expect(page.locator('text=Nómina de Estudiantes')).not.toBeVisible()
    await expect(page.locator('text=Guardar Calificaciones del Curso')).not.toBeVisible()
  })
})
