import { expect, test } from '@playwright/test'

test('admisión carga convocatorias autenticadas a través del gateway Docker', async ({ page }) => {
  await page.goto('/login')
  await page.getByTestId('email').fill('superadmin@modux.demo')
  await page.getByTestId('password').fill('SuperAdmin2026!')
  await page.getByTestId('submit').click()
  await expect(page).toHaveURL(/\/admin\/dashboard/)

  const responsePromise = page.waitForResponse((response) =>
    response.request().method() === 'GET'
      && response.url().includes('/api/v1/admin/admision/convocatorias')
  )

  await page.goto('/admin/admision')
  const response = await responsePromise
  const payload = await response.json()

  expect(response.status()).toBe(200)
  expect(Array.isArray(payload.data)).toBe(true)
  await expect(page.getByRole('heading', { name: 'Admisión Tecnológica' })).toBeVisible()
  await expect(page.getByTestId('admision-error')).toHaveCount(0)

  if (payload.data.length > 0) {
    await expect(page.getByTestId(`convocatoria-${payload.data[0].id}`)).toBeVisible()
  }
})
