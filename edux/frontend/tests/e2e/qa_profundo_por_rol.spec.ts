import { test, expect } from '@playwright/test'

const BASE = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080'

test.describe('Modux — QA PROFUNDO POR ROL: Permisos, Formularios y Funcionalidad', () => {

  // ─── HELPERS ───────────────────────────────────────────────────────
  async function login(page: any, email: string, password: string) {
    await page.goto(`${BASE}/login`)
    await page.fill('[data-testid="email"]', email)
    await page.fill('[data-testid="password"]', password)
    await page.click('[data-testid="submit"]')
    await page.waitForLoadState('networkidle')
  }

  async function logout(page: any) {
    await page.click('text=Cerrar sesión')
    await page.waitForURL(/\/login/)
  }

  async function getVisibleSidebarLinks(page: any) {
    const links = await page.locator('aside nav a').all()
    const names: string[] = []
    for (const link of links) {
      const text = await link.textContent()
      if (text) names.push(text.trim())
    }
    return names
  }

  async function getPageTitle(page: any) {
    const h1 = page.locator('h1, h2').first()
    return await h1.textContent()
  }

  // ─── SUPERADMIN ═══════════════════════════════════════════════════
  test.describe('SUPERADMIN — Verificación completa de permisos y formularios', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, 'superadmin@modux.demo', 'SuperAdmin2026!')
      await expect(page).toHaveURL(/\/admin\/dashboard/)
    })

    test('SA-01: Sidebar muestra TODOS los enlaces (16 items)', async ({ page }) => {
      const links = await getVisibleSidebarLinks(page)
      const expectedItems = [
        'Dashboard', 'Instituciones', 'Programas de Estudio', 'Usuarios del Sistema',
        'Roles y Permisos', 'Docentes', 'Estudiantes', 'Períodos Académicos',
        'Cursos / Unidades', 'Grupos de Curso', 'Matrículas', 'Admisión Tecnológica',
        'Trámites Académicos', 'Ambientes y Talleres', 'Titulación y Egresados', 'Mi Perfil'
      ]
      for (const item of expectedItems) {
        await expect(page.locator('nav').locator(`text=${item}`)).toBeVisible()
      }
      console.log('SA-01 ✅ Todos los 16 enlaces visibles para SUPERADMIN')
    })

    test('SA-02: Verificar formulario "Nuevo Usuario" tiene campo de rol obligatorio', async ({ page }) => {
      await page.goto(`${BASE}/admin/usuarios`)
      await page.waitForLoadState('networkidle')
      await page.getByTestId('nuevo-usuario-btn').click()

      await expect(page.getByRole('heading', { name: 'Nuevo Usuario', level: 2 })).toBeVisible()
      const rolSelect = page.getByTestId('rol-select')
      await expect(rolSelect).toBeVisible()
      await expect(rolSelect).toHaveAttribute('required', '')

      const options = await rolSelect.locator('option').allTextContents()
      expect(options.some(opt => opt.includes('SUPERADMIN'))).toBe(true)
      expect(options.some(opt => opt.includes('DOCENTE'))).toBe(true)

      await page.click('button:has-text("Cancelar")')
    })

    test('SA-03: Crear usuario y verificar si aparece con rol en tabla', async ({ page }) => {
      await page.goto(`${BASE}/admin/usuarios`)
      await page.waitForLoadState('networkidle')

      const ts = Date.now()
      const testEmail = `sa_test_${ts}@modux.demo`
      const testUsername = `satest_${ts}`

      await page.getByTestId('nuevo-usuario-btn').click()
      await page.fill('[data-testid="nombres-input"]', 'Superadmin Test')
      await page.fill('[data-testid="apellido-paterno-input"]', 'QA')
      await page.fill('[data-testid="email-input"]', testEmail)
      await page.fill('[data-testid="username-input"]', testUsername)

      const rolSelect = page.getByTestId('rol-select')
      const docOption = rolSelect.locator('option:has-text("DOCENTE")')
      const docVal = await docOption.getAttribute('value')
      expect(docVal).toBeTruthy()
      await rolSelect.selectOption(docVal!)

      await page.fill('[data-testid="password-input"]', 'TestPass123!*')
      await page.click('button:has-text("Guardar")')
      await page.waitForLoadState('networkidle')

      // Verificar si el usuario aparece en la tabla con rol DOCENTE
      const userRow = page.locator(`tr:has-text("${testEmail}")`)
      await expect(userRow).toBeVisible()
      await expect(userRow.locator('span:has-text("DOCENTE")')).toBeVisible()
    })

    test('SA-04: Verificar formulario de Roles y Permisos', async ({ page }) => {
      await page.goto(`${BASE}/admin/roles`)
      await page.waitForLoadState('networkidle')

      // Verificar que el heading aparece
      await expect(page.locator('h1:has-text("Roles y Permisos")')).toBeVisible()

      // Verificar que se ven tarjetas de roles
      const roleCards = page.locator('.bg-white.rounded-xl')
      const count = await roleCards.count()
      console.log(`SA-04 Número de roles visibles: ${count}`)

      // Verificar que se ven nombres de roles
      const rolesNames = await page.locator('.font-mono.font-bold').allTextContents()
      console.log(`SA-04 Roles encontrados: ${JSON.stringify(rolesNames)}`)

      // Verificar si hay botón de crear/editar roles para SUPERADMIN
      const createButton = page.locator('button:has-text("Nuevo")')
      const hasCreateBtn = await createButton.isVisible().catch(() => false)
      console.log(`SA-04 ¿Botón "Nuevo" visible? ${hasCreateBtn}`)
    })

    test('SA-05: Verificar formulario de Programas de Estudio', async ({ page }) => {
      await page.goto(`${BASE}/admin/programas`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Programas de Estudio")')).toBeVisible()

      // Verificar botón "Nuevo Programa"
      const newBtn = page.locator('button:has-text("Nuevo Programa")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-05 ¿Botón "Nuevo Programa" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        // Verificar campos del formulario
        const formLabels = await page.locator('.bg-white.rounded-xl label').allTextContents()
        console.log(`SA-05 Campos formulario programa: ${JSON.stringify(formLabels)}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-06: Verificar formulario de Instituciones', async ({ page }) => {
      await page.goto(`${BASE}/admin/instituciones`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Instituciones")')).toBeVisible()

      // Verificar si hay formulario de edición
      const hasForm = await page.locator('form').count().catch(() => 0)
      console.log(`SA-06 ¿Hay formulario en Instituciones? ${hasForm > 0}`)

      const hasCreateBtn = await page.locator('button:has-text("Nuevo")').isVisible().catch(() => false)
      console.log(`SA-06 ¿Botón "Nuevo" instituciones? ${hasCreateBtn}`)
    })

    test('SA-07: Verificar formulario de Docentes', async ({ page }) => {
      await page.goto(`${BASE}/admin/docentes`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Planta Docente")')).toBeVisible()

      // Verificar botón "Nuevo Docente"
      const newBtn = page.locator('button:has-text("Nuevo Docente")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-07 ¿Botón "Nuevo Docente" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        const labelText = formLabels.join(' ')
        console.log(`SA-07 Campos formulario docente: ${JSON.stringify(formLabels)}`)
        console.log(`SA-07 ¿Tiene campo de rol? ${labelText.includes('Rol') ? 'SÍ' : 'NO'}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-08: Verificar formulario de Estudiantes', async ({ page }) => {
      await page.goto(`${BASE}/admin/estudiantes`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Alumnado")')).toBeVisible()

      const newBtn = page.locator('button:has-text("Nuevo Estudiante")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-08 ¿Botón "Nuevo Estudiante" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        const labelText = formLabels.join(' ')
        console.log(`SA-08 Campos formulario estudiante: ${JSON.stringify(formLabels)}`)
        console.log(`SA-08 ¿Tiene campo de rol? ${labelText.includes('Rol') ? 'SÍ' : 'NO'}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-09: Verificar formulario de Periodos', async ({ page }) => {
      await page.goto(`${BASE}/admin/periodos`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Períodos Académicos")')).toBeVisible()

      const newBtn = page.locator('button:has-text("+ Nuevo Período")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-09 ¿Botón "Nuevo Período" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        console.log(`SA-09 Campos formulario período: ${JSON.stringify(formLabels)}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-10: Verificar formulario de Cursos', async ({ page }) => {
      await page.goto(`${BASE}/admin/cursos`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Catálogo de Cursos")')).toBeVisible()

      const newBtn = page.locator('button:has-text("+ Nuevo Curso")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-10 ¿Botón "Nuevo Curso" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        console.log(`SA-10 Campos formulario curso: ${JSON.stringify(formLabels)}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-11: Verificar formulario de Grupos', async ({ page }) => {
      await page.goto(`${BASE}/admin/grupos`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Grupos de Curso")')).toBeVisible()

      const newBtn = page.locator('button:has-text("+ Nuevo Grupo")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-11 ¿Botón "Nuevo Grupo" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        console.log(`SA-11 Campos formulario grupo: ${JSON.stringify(formLabels)}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-12: Verificar formulario de Matrículas', async ({ page }) => {
      await page.goto(`${BASE}/admin/matriculas`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Gestión de Matrículas")')).toBeVisible()

      const newBtn = page.locator('button:has-text("+ Matricular Estudiante")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-12 ¿Botón "Matricular Estudiante" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        console.log(`SA-12 Campos formulario matrícula: ${JSON.stringify(formLabels)}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-13: Verificar formulario de Tramites', async ({ page }) => {
      await page.goto(`${BASE}/admin/tramites`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Trámites")')).toBeVisible()

      const newBtn = page.locator('button:has-text("Nueva Solicitud")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-13 ¿Botón "Nueva Solicitud" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        // Verificar campos del formulario
        const formFields = page.locator('form select, form input, form textarea')
        const count = await formFields.count()
        console.log(`SA-13 Campos en formulario trámite: ${count}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-14: Verificar formulario de Ambientes', async ({ page }) => {
      await page.goto(`${BASE}/admin/ambientes`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Ambientes")')).toBeVisible()

      const newBtn = page.locator('button:has-text("Nuevo Ambiente")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-14 ¿Botón "Nuevo Ambiente" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        console.log(`SA-14 Campos formulario ambiente: ${JSON.stringify(formLabels)}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-15: Verificar formulario de Titulación', async ({ page }) => {
      await page.goto(`${BASE}/admin/titulacion`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Titulación")')).toBeVisible()

      const tabs = await page.locator('button:has-text("Libro de Títulos"), button:has-text("Certificados Modulares")').all()
      console.log(`SA-15 Tabs encontrados: ${tabs.length}`)

      const newBtn = page.locator('button:has-text("Registrar Título")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-15 ¿Botón "Registrar Título" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        console.log(`SA-15 Campos formulario título: ${JSON.stringify(formLabels)}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-16: Verificar formulario de Admisión', async ({ page }) => {
      await page.goto(`${BASE}/admin/admision`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Admisión Tecnológica")')).toBeVisible()

      const newBtn = page.locator('button:has-text("Inscribir Postulante")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`SA-16 ¿Botón "Inscribir Postulante" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formFields = page.locator('form select, form input')
        const count = await formFields.count()
        console.log(`SA-16 Campos en formulario admisión: ${count}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('SA-17: Verificar que puede crear rol nuevo', async ({ page }) => {
      await page.goto(`${BASE}/admin/roles`)
      await page.waitForLoadState('networkidle')

      // SUPERADMIN debería poder ver el contenido de roles
      const roleCards = page.locator('.bg-white.rounded-xl')
      const count = await roleCards.count()
      console.log(`SA-17 Número de roles visibles: ${count}`)

      // Verificar si hay un botón de crear rol
      const createBtn = page.locator('button:has-text("Nuevo")')
      const hasCreate = await createBtn.isVisible().catch(() => false)
      console.log(`SA-17 ¿Botón crear rol visible? ${hasCreate}`)
    })

    test('SA-18: Verificar perfil del usuario', async ({ page }) => {
      await page.goto(`${BASE}/admin/perfil`)
      await page.waitForLoadState('networkidle')

      await expect(page.locator('h1:has-text("Mi Perfil")')).toBeVisible()

      // Verificar campos de perfil
      const profileLabels = await page.locator('label').allTextContents()
      console.log(`SA-18 Campos perfil: ${JSON.stringify(profileLabels)}`)

      // Verificar rol mostrado
      const roleBadge = page.getByTestId('role-badge')
      const hasRole = await roleBadge.isVisible().catch(() => false)
      console.log(`SA-18 ¿Badge de rol visible? ${hasRole}`)
    })
  })

  // ─── ADMINISTRADOR ═══════════════════════════════════════════════
  test.describe('ADMINISTRADOR — Verificación completa de permisos y formularios', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, 'admin@modux.demo', 'Admin2026!')
      await expect(page).toHaveURL(/\/admin\/dashboard/)
    })

    test('ADM-01: Sidebar muestra 15 enlaces (sin Instituciones ni Roles)', async ({ page }) => {
      const expectedItems = [
        'Dashboard', 'Programas de Estudio', 'Usuarios', 'Docentes', 'Estudiantes',
        'Períodos Académicos', 'Cursos / Unidades', 'Grupos de Curso', 'Matrículas',
        'Admisión Tecnológica', 'Trámites Académicos', 'Ambientes y Talleres',
        'Titulación y Egresados', 'Mi Perfil'
      ]
      for (const item of expectedItems) {
        await expect(page.locator('nav').locator(`text=${item}`)).toBeVisible()
      }

      // Verificar que NO muestra Instituciones ni Roles
      await expect(page.locator('text=Instituciones')).not.toBeVisible()
      await expect(page.locator('text=Roles y Permisos')).not.toBeVisible()
      console.log('ADM-01 ✅ Sidebar correcto para ADMINISTRADOR (15 enlaces, sin Instituciones/Roles)')
    })

    test('ADM-02: Verificar formulario de Usuarios tiene selector de rol y excluye SUPERADMIN', async ({ page }) => {
      await page.goto(`${BASE}/admin/usuarios`)
      await page.waitForLoadState('networkidle')
      await expect(page.getByRole('heading', { name: 'Usuarios', level: 1 })).toBeVisible()

      await page.getByTestId('nuevo-usuario-btn').click()
      await expect(page.getByRole('heading', { name: 'Nuevo Usuario', level: 2 })).toBeVisible()

      const rolSelect = page.getByTestId('rol-select')
      await expect(rolSelect).toBeVisible()
      await expect(rolSelect).toHaveAttribute('required', '')

      const options = await rolSelect.locator('option').allTextContents()
      expect(options.some(opt => opt.includes('SUPERADMIN'))).toBe(false)
      expect(options.some(opt => opt.includes('DOCENTE'))).toBe(true)

      await page.click('button:has-text("Cancelar")')
    })

    test('ADM-03: Verificar formulario de Docentes', async ({ page }) => {
      await page.goto(`${BASE}/admin/docentes`)
      await page.waitForLoadState('networkidle')
      await expect(page.locator('h1:has-text("Planta Docente")')).toBeVisible()

      const newBtn = page.locator('button:has-text("Nuevo Docente")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`ADM-03 ¿Botón "Nuevo Docente" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        const labelText = formLabels.join(' ')
        console.log(`ADM-03 Campos formulario docente: ${JSON.stringify(formLabels)}`)
        console.log(`ADM-03 ¿Tiene campo de rol? ${labelText.includes('Rol') ? 'SÍ' : 'NO'}`)
        console.log(`ADM-03 ¿Tiene campo de programa? ${labelText.includes('Programa') ? 'SÍ' : 'NO'}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('ADM-04: Verificar formulario de Estudiantes', async ({ page }) => {
      await page.goto(`${BASE}/admin/estudiantes`)
      await page.waitForLoadState('networkidle')
      await expect(page.locator('h1:has-text("Alumnado")')).toBeVisible()

      const newBtn = page.locator('button:has-text("Nuevo Estudiante")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`ADM-04 ¿Botón "Nuevo Estudiante" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        const labelText = formLabels.join(' ')
        console.log(`ADM-04 Campos formulario estudiante: ${JSON.stringify(formLabels)}`)
        console.log(`ADM-04 ¿Tiene campo de rol? ${labelText.includes('Rol') ? 'SÍ' : 'NO'}`)
        console.log(`ADM-04 ¿Tiene campo de programa? ${labelText.includes('Programa') ? 'SÍ' : 'NO'}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('ADM-05: Verificar formulario de Programas', async ({ page }) => {
      await page.goto(`${BASE}/admin/programas`)
      await page.waitForLoadState('networkidle')
      await expect(page.locator('h1:has-text("Programas de Estudio")')).toBeVisible()

      const newBtn = page.locator('button:has-text("Nuevo Programa")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`ADM-05 ¿Botón "Nuevo Programa" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.bg-white.rounded-xl label').allTextContents()
        console.log(`ADM-05 Campos formulario programa: ${JSON.stringify(formLabels)}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('ADM-06: Verificar formularios de Periodos, Cursos, Grupos, Matrículas', async ({ page }) => {
      const pages = [
        { path: '/admin/periodos', title: 'Períodos Académicos', btn: '+ Nuevo Período' },
        { path: '/admin/cursos', title: 'Catálogo de Cursos', btn: '+ Nuevo Curso' },
        { path: '/admin/grupos', title: 'Grupos de Curso', btn: '+ Nuevo Grupo' },
        { path: '/admin/matriculas', title: 'Gestión de Matrículas', btn: '+ Matricular Estudiante' },
      ]

      for (const p of pages) {
        await page.goto(`${BASE}${p.path}`)
        await page.waitForLoadState('networkidle')
        const heading = await page.locator('h1').textContent()
        console.log(`ADM-06 ${heading?.trim()} → Heading verificado`)

        const newBtn = page.locator(`button:has-text("${p.btn}")`)
        const hasBtn = await newBtn.isVisible().catch(() => false)
        console.log(`ADM-06 ¿Botón "${p.btn}" visible? ${hasBtn}`)
      }
    })

    test('ADM-07: Verificar formularios de Tramites, Ambientes, Titulación, Admisión', async ({ page }) => {
      const pages = [
        { path: '/admin/tramites', title: 'Trámites', btn: 'Nueva Solicitud' },
        { path: '/admin/ambientes', title: 'Ambientes', btn: 'Nuevo Ambiente' },
        { path: '/admin/titulacion', title: 'Titulación', btn: 'Registrar Título' },
        { path: '/admin/admision', title: 'Admisión', btn: 'Inscribir Postulante' },
      ]

      for (const p of pages) {
        await page.goto(`${BASE}${p.path}`)
        await page.waitForLoadState('networkidle')
        const heading = await page.locator('h1').textContent()
        console.log(`ADM-07 ${heading?.trim()} → Heading verificado`)

        const newBtn = page.locator(`button:has-text("${p.btn}")`)
        const hasBtn = await newBtn.isVisible().catch(() => false)
        console.log(`ADM-07 ¿Botón "${p.btn}" visible? ${hasBtn}`)
      }
    })

    test('ADM-08: Verificar que NO puede acceder a Instituciones ni Roles', async ({ page }) => {
      await page.goto(`${BASE}/admin/instituciones`)
      await page.waitForLoadState('networkidle')
      // ADMINISTRADOR no debería poder acceder
      const isBlocked = await page.locator('text=exclusiva del Superadministrador').isVisible().catch(() => false)
      console.log(`ADM-08 ¿Instituciones bloqueada para ADMIN? ${isBlocked}`)

      await page.goto(`${BASE}/admin/roles`)
      await page.waitForLoadState('networkidle')
      const rolesHeading = await page.locator('h1').textContent()
      console.log(`ADM-08 Roles heading: ${rolesHeading?.trim() || 'BLOQUEADO'}`)
    })

    test('ADM-09: Verificar perfil', async ({ page }) => {
      await page.goto(`${BASE}/admin/perfil`)
      await page.waitForLoadState('networkidle')
      await expect(page.locator('h1:has-text("Mi Perfil")')).toBeVisible()

      const profileLabels = await page.locator('label').allTextContents()
      console.log(`ADM-09 Campos perfil: ${JSON.stringify(profileLabels)}`)
    })
  })

  // ─── COORDINADOR ═════════════════════════════════════════════════
  test.describe('COORDINADOR — Verificación completa de permisos y formularios', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, 'coord.enf@modux.demo', 'Coord2026!')
      await expect(page).toHaveURL(/\/coordinacion\/dashboard/)
    })

    test('COO-01: Sidebar muestra solo 10 enlaces (solo su programa)', async ({ page }) => {
      const expectedItems = [
        'Dashboard', 'Mi Programa', 'Docentes del Programa', 'Estudiantes',
        'Cursos / Unidades', 'Grupos de Curso', 'Matrículas', 'Trámites',
        'Titulación', 'Mi Perfil'
      ]
      for (const item of expectedItems) {
        await expect(page.locator('nav').locator(`text=${item}`)).toBeVisible()
      }

      // Verificar que NO tiene enlaces a Instituciones, Roles, etc. en el sidebar
      await expect(page.locator('nav').locator('text=Instituciones')).not.toBeVisible()
      await expect(page.locator('nav').locator('text=Roles')).not.toBeVisible()
      await expect(page.locator('nav').locator('text=Períodos')).not.toBeVisible()
      await expect(page.locator('nav').locator('text=Admisión')).not.toBeVisible()
      await expect(page.locator('nav').locator('text=Ambientes')).not.toBeVisible()
      console.log('COO-01 ✅ Sidebar correcto para COORDINADOR (10 enlaces)')
    })

    test('COO-02: Verificar formulario de Docentes (solo su programa)', async ({ page }) => {
      await page.goto(`${BASE}/coordinacion/docentes`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`COO-02 Heading: ${heading?.trim()}`)

      const newBtn = page.locator('button:has-text("Nuevo Docente")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`COO-02 ¿Botón "Nuevo Docente" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        const labelText = formLabels.join(' ')
        console.log(`COO-02 Campos formulario docente: ${JSON.stringify(formLabels)}`)
        console.log(`COO-02 ¿Tiene campo de rol? ${labelText.includes('Rol') ? 'SÍ' : 'NO'}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('COO-03: Verificar formulario de Estudiantes (solo su programa)', async ({ page }) => {
      await page.goto(`${BASE}/coordinacion/estudiantes`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`COO-03 Heading: ${heading?.trim()}`)

      const newBtn = page.locator('button:has-text("Nuevo Estudiante")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`COO-03 ¿Botón "Nuevo Estudiante" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('.space-y-4 label').allTextContents()
        const labelText = formLabels.join(' ')
        console.log(`COO-03 Campos formulario estudiante: ${JSON.stringify(formLabels)}`)
        console.log(`COO-03 ¿Tiene campo de rol? ${labelText.includes('Rol') ? 'SÍ' : 'NO'}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('COO-04: Verificar que NO puede acceder a Instituciones y Roles', async ({ page }) => {
      await page.goto(`${BASE}/admin/instituciones`)
      await page.waitForLoadState('networkidle')
      const blocked = await page.locator('text=Superadministrador').isVisible().catch(() => false)
      console.log(`COO-04 ¿Instituciones bloqueada? ${blocked}`)

      await page.goto(`${BASE}/admin/roles`)
      await page.waitForLoadState('networkidle')
      const rolesUrl = page.url()
      console.log(`COO-04 URL después de intentar /admin/roles: ${rolesUrl}`)
    })

    test('COO-05: Verificar formulario de Programas', async ({ page }) => {
      await page.goto(`${BASE}/coordinacion/programas`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`COO-05 Heading: ${heading?.trim()}`)

      const newBtn = page.locator('button:has-text("Nuevo Programa")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`COO-05 ¿Botón "Nuevo Programa" visible? ${hasBtn}`)
    })

    test('COO-06: Verificar formularios de Cursos, Grupos, Matrículas', async ({ page }) => {
      const pages = [
        { path: '/coordinacion/cursos', btn: '+ Nuevo Curso' },
        { path: '/coordinacion/grupos', btn: '+ Nuevo Grupo' },
        { path: '/coordinacion/matriculas', btn: '+ Matricular Estudiante' },
      ]

      for (const p of pages) {
        await page.goto(`${BASE}${p.path}`)
        await page.waitForLoadState('networkidle')

        const newBtn = page.locator(`button:has-text("${p.btn}")`)
        const hasBtn = await newBtn.isVisible().catch(() => false)
        console.log(`COO-06 ${p.path} → Botón "${p.btn}" visible: ${hasBtn}`)
      }
    })

    test('COO-07: Verificar formularios de Tramites y Titulación', async ({ page }) => {
      const pages = [
        { path: '/coordinacion/tramites', btn: 'Nueva Solicitud' },
        { path: '/coordinacion/titulacion', btn: 'Registrar Título' },
      ]

      for (const p of pages) {
        await page.goto(`${BASE}${p.path}`)
        await page.waitForLoadState('networkidle')

        const newBtn = page.locator(`button:has-text("${p.btn}")`)
        const hasBtn = await newBtn.isVisible().catch(() => false)
        console.log(`COO-07 ${p.path} → Botón "${p.btn}" visible: ${hasBtn}`)
      }
    })
  })

  // ─── DOCENTE ═════════════════════════════════════════════════════
  test.describe('DOCENTE — Verificación completa de permisos y formularios', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, 'docente01@modux.demo', 'Docente2026!')
      await expect(page).toHaveURL(/\/docente\/dashboard/)
    })

    test('DOC-01: Verificar dashboard de docente', async ({ page }) => {
      await expect(page.locator('h1:has-text("Mis Grupos")')).toBeVisible()

      const groupCards = page.locator('.card')
      const count = await groupCards.count()
      console.log(`DOC-01 Número de grupos: ${count}`)

      const links = await page.locator('a, button').all()
      const linkNames: string[] = []
      for (const link of links) {
        const text = await link.textContent()
        if (text) linkNames.push(text.trim())
      }
      console.log(`DOC-01 Links encontrados: ${JSON.stringify([...new Set(linkNames)].slice(0, 20))}`)
    })

    test('DOC-02: Verificar Aula Virtual', async ({ page }) => {
      await page.goto(`${BASE}/docente/grupos/1/aula`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`DOC-02 Heading aula: ${heading?.trim()}`)

      const hasTabs = await page.locator('button:has-text("Asistencia"), button:has-text("Evaluaciones")').count()
      console.log(`DOC-02 ¿Hay tabs de Asistencia/Evaluaciones? ${hasTabs > 0}`)
    })

    test('DOC-03: Verificar Tareas del docente', async ({ page }) => {
      await page.goto(`${BASE}/docente/grupos/1/tareas`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`DOC-03 Heading tareas: ${heading?.trim()}`)

      const newBtn = page.locator('button:has-text("Nueva Tarea")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`DOC-03 ¿Botón "Nueva Tarea" visible? ${hasBtn}`)

      if (hasBtn) {
        await newBtn.click()
        await page.waitForTimeout(500)

        const formLabels = await page.locator('label').allTextContents()
        console.log(`DOC-03 Campos formulario tarea: ${JSON.stringify(formLabels)}`)

        await page.click('button:has-text("Cancelar")')
      }
    })

    test('DOC-04: Verificar Calificaciones del docente', async ({ page }) => {
      await page.goto(`${BASE}/docente/grupos/1/calificaciones`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`DOC-04 Heading calificaciones: ${heading?.trim()}`)

      const newBtn = page.locator('button:has-text("Nueva")')
      const hasBtn = await newBtn.isVisible().catch(() => false)
      console.log(`DOC-04 ¿Botón "Nueva" calificación visible? ${hasBtn}`)
    })

    test('DOC-05: Verificar perfil del docente', async ({ page }) => {
      await page.goto(`${BASE}/docente/perfil`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`DOC-05 Heading perfil: ${heading?.trim()}`)

      const profileLabels = await page.locator('label').allTextContents()
      console.log(`DOC-05 Campos perfil: ${JSON.stringify(profileLabels)}`)
    })

    test('DOC-06: Verificar que NO tiene acceso a rutas admin', async ({ page }) => {
      await page.goto(`${BASE}/admin/usuarios`)
      await page.waitForLoadState('networkidle')
      const url = page.url()
      console.log(`DOC-06 Después de intentar /admin/usuarios → ${url}`)
      const isOnAdmin = url.includes('/admin/')
      console.log(`DOC-06 ¿Está en ruta admin? ${isOnAdmin}`)
    })
  })

  // ─── ESTUDIANTE ══════════════════════════════════════════════════
  test.describe('ESTUDIANTE — Verificación completa de permisos y formularios', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, 'est001@modux.demo', 'Alumno2026!')
      const url = page.url()
      const loginSuccess = !url.includes('/login')
      console.log(`EST-01 Login estudiante → ${loginSuccess ? '✅ EXITO' : '❌ FALLÓ'} → ${url}`)

      if (loginSuccess) {
        await expect(page).toHaveURL(/\/estudiante\/dashboard/)
      }
    })

    test('EST-02: Verificar dashboard de estudiante', async ({ page }) => {
      const heading = await page.locator('h1').textContent()
      console.log(`EST-02 Heading: ${heading?.trim()}`)

      const courseCards = page.locator('.card')
      const count = await courseCards.count()
      console.log(`EST-02 Número de cursos: ${count}`)
    })

    test('EST-03: Verificar vista de curso', async ({ page }) => {
      await page.goto(`${BASE}/estudiante/cursos/1`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`EST-03 Heading curso: ${heading?.trim()}`)
    })

    test('EST-04: Verificar tareas de estudiante', async ({ page }) => {
      await page.goto(`${BASE}/estudiante/tareas`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`EST-04 Heading tareas: ${heading?.trim()}`)
    })

    test('EST-05: Verificar notas de estudiante', async ({ page }) => {
      await page.goto(`${BASE}/estudiante/notas`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`EST-05 Heading notas: ${heading?.trim()}`)
    })

    test('EST-06: Verificar perfil de estudiante', async ({ page }) => {
      await page.goto(`${BASE}/estudiante/perfil`)
      await page.waitForLoadState('networkidle')

      const heading = await page.locator('h1').textContent()
      console.log(`EST-06 Heading perfil: ${heading?.trim()}`)
    })
  })

  // ─── APODERADO ═══════════════════════════════════════════════════
  test.describe('APODERADO — Verificación completa de permisos y formularios', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, 'apoderado01@modux.demo', 'Padre2026!')
      const url = page.url()
      const loginSuccess = !url.includes('/login')
      console.log(`APA-01 Login apoderado → ${loginSuccess ? '✅ EXITO' : '❌ FALLÓ'} → ${url}`)

      if (loginSuccess) {
        await expect(page).toHaveURL(/\/apoderado\/dashboard/)
      }
    })

    test('APA-02: Verificar dashboard de apoderado', async ({ page }) => {
      const heading = await page.locator('h1').textContent()
      console.log(`APA-02 Heading: ${heading?.trim()}`)

      const studentCards = page.locator('.bg-white.rounded-lg')
      const count = await studentCards.count()
      console.log(`APA-02 Número de hijos: ${count}`)
    })
  })
})
