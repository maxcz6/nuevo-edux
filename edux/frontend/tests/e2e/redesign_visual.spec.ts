import { test, expect } from '@playwright/test'

/**
 * Verificación del rediseño visual: paleta institucional, foco por teclado,
 * densidad de datos, dark mode y ausencia de regresiones de accesibilidad.
 */

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

async function loginAs(page: import('@playwright/test').Page, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.fill('[data-testid="email"]', email)
  await page.fill('[data-testid="password"]', password)
  await page.click('[data-testid="submit"]')
  await page.waitForURL(/\/(admin|docente|estudiante|apoderado|coordinacion|secretaria|caja|mesa-partes)\//)
}

test.describe('Rediseño visual — paleta institucional', () => {

  test('VIS-01: el token primary es azul institucional (#0058be / #1e40af), no terracota', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')
    const hex = await page.evaluate(() => {
      const el = document.querySelector('[data-testid="submit"]')
      return el ? getComputedStyle(el).backgroundColor : ''
    })
    // EducaCampus primary = rgb(0, 88, 190) or Tailwind blue-600 = rgb(37, 99, 235)
    expect(['rgb(0, 88, 190)', 'rgb(37, 99, 235)']).toContain(hex)
  })

  test('VIS-02: el botón primario cumple contraste AA con texto blanco', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')
    const { fg, bg } = await page.evaluate(() => {
      const el = document.querySelector('[data-testid="submit"]')!
      const s = getComputedStyle(el)
      return { fg: s.color, bg: s.backgroundColor }
    })
    const lum = (rgb: string) => {
      const [r, g, b] = rgb.match(/\d+/g)!.map(Number).map((v) => {
        const c = v / 255
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4)
      })
      return 0.2126 * r + 0.7152 * g + 0.0722 * b
    }
    const l1 = lum(fg)
    const l2 = lum(bg)
    const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)
    expect(ratio).toBeGreaterThanOrEqual(4.5)
  })

  test('VIS-03: la tipografía institucional está cargada y aplicada en body', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(1200)
    const family = await page.evaluate(() => getComputedStyle(document.body).fontFamily)
    const hasFont = family.includes('Plus Jakarta Sans') || family.includes('Fira Sans') || family.includes('Inter')
    expect(hasFont).toBe(true)
  })

  test('VIS-04: el fondo usa el token de superficie claro', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')
    const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor)
    expect(['rgb(248, 249, 255)', 'rgb(248, 250, 252)']).toContain(bg)
  })

  test('VIS-05: el skip link es el primer elemento enfocable del layout', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!')
    await page.keyboard.press('Tab')
    const focused = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement
      return { text: el?.textContent?.trim() ?? '', href: el?.getAttribute('href') ?? '' }
    })
    expect(focused.text.toLowerCase()).toContain('contenido')
    expect(focused.href).toContain('#')
  })

  test('VIS-06: el foco se traslada al main al navegar (WCAG 2.4.3)', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!')
    await page.evaluate(() => (document.activeElement as HTMLElement)?.blur())
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(400)
    const inMain = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement
      return !!el && (el.tagName === 'MAIN' || !!el.closest('main'))
    })
    expect(inMain).toBe(true)
  })

  test('VIS-07: tablas usan cifras tabulares (estabilidad de columnas)', async ({ page }) => {
    await loginAs(page, 'admin@modux.demo', 'Admin2026!')
    await page.goto(`${BASE}/admin/usuarios`)
    await page.waitForLoadState('networkidle')
    const variant = await page.evaluate(() => {
      const table = document.querySelector('table')
      return table ? getComputedStyle(table).fontVariantNumeric : ''
    })
    expect(variant).toContain('tabular-nums')
  })

  test('VIS-08: dark mode mantiene contraste yokens surfaces', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.evaluate(() => document.documentElement.classList.add('dark'))
    await page.waitForTimeout(300)
    const colors = await page.evaluate(() => ({
      bg: getComputedStyle(document.body).backgroundColor,
      color: getComputedStyle(document.body).color,
    }))
    expect(['rgb(0, 9, 20)', 'rgb(11, 18, 32)']).toContain(colors.bg)
    const lum = (rgb: string) => {
      const [r, g, b] = rgb.match(/\d+/g)!.map(Number).map((v) => {
        const c = v / 255
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4)
      })
      return 0.2126 * r + 0.7152 * g + 0.0722 * b
    }
    const ratio = (Math.max(lum(colors.bg), lum(colors.color)) + 0.05) /
                  (Math.min(lum(colors.bg), lum(colors.color)) + 0.05)
    expect(ratio).toBeGreaterThanOrEqual(4.5)
  })

  test('VIS-09: sin overflow horizontal en 375px con la nueva paleta', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await loginAs(page, 'admin@modux.demo', 'Admin2026!')
    await page.waitForLoadState('networkidle')
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > window.innerWidth
    )
    expect(overflow).toBe(false)
  })

  test('VIS-10: los elementos clicables exponen cursor:pointer', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')
    const cursors = await page.evaluate(() => {
      const btn = document.querySelector('[data-testid="submit"]') as HTMLElement
      const link = document.querySelector('a[href]') as HTMLElement
      return { btn: getComputedStyle(btn).cursor, link: link ? getComputedStyle(link).cursor : '' }
    })
    expect(cursors.btn).toBe('pointer')
    expect(cursors.link).toBe('pointer')
  })

  test('VIS-11: axe sin violaciones críticas tras el rediseño (login)', async ({ page }) => {
    const AxeBuilder = (await import('@axe-core/playwright')).default
    await page.goto(`${BASE}/login`)
    await page.waitForLoadState('networkidle')
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze()
    const bad = results.violations.filter((v) => v.impact === 'critical' || v.impact === 'serious')
    expect(bad).toEqual([])
  })

})
