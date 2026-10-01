import { test, expect } from '@playwright/test'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

test('SUPERADMIN crea y edita los datos de un programa de estudio', async ({ page }) => {
  const suffix = Date.now()
  const codigo = `E2E-${String(suffix).slice(-8)}`
  const nombreInicial = `Programa E2E ${suffix}`
  const nombreEditado = `Programa Editado ${suffix}`

  await page.goto(`${BASE}/login`)
  await page.fill('[data-testid="email"]', 'superadmin@modux.demo')
  await page.fill('[data-testid="password"]', 'SuperAdmin2026!')
  await page.click('[data-testid="submit"]')
  await expect(page).toHaveURL(/\/admin\/dashboard/)

  await page.goto(`${BASE}/admin/programas`)
  await page.getByRole('button', { name: 'Nuevo Programa' }).click()
  await page.fill('[data-testid="programa-codigo-input"]', codigo)
  await page.fill('[data-testid="programa-nombre-input"]', nombreInicial)
  await page.click('[data-testid="guardar-programa-btn"]')

  const heading = page.getByRole('heading', { name: nombreInicial })
  await expect(heading).toBeVisible()
  const card = heading.locator('xpath=ancestor::div[contains(@class,"rounded-xl")][1]')
  const editButton = card.getByRole('button', { name: 'Editar' })
  const testId = await editButton.getAttribute('data-testid')
  const programaId = testId?.replace('editar-programa-', '')

  await editButton.click()
  await expect(page.getByRole('dialog', { name: 'Editar Programa de Estudio' })).toBeVisible()
  await page.fill('[data-testid="programa-nombre-input"]', nombreEditado)
  await page.selectOption('[data-testid="programa-estado-select"]', 'INACTIVO')
  await page.click('[data-testid="guardar-programa-btn"]')

  const editedHeading = page.getByRole('heading', { name: nombreEditado })
  await expect(editedHeading).toBeVisible()
  const editedCard = editedHeading.locator('xpath=ancestor::div[contains(@class,"rounded-xl")][1]')
  await expect(editedCard.getByText('INACTIVO', { exact: true })).toBeVisible()

  if (programaId) {
    const token = await page.evaluate(() => localStorage.getItem('modux_token'))
    await page.request.delete(`${BASE}/api/v1/admin/unidades-academicas/${programaId}?force=true`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    })
  }
})
