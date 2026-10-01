import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { requiresGuest: true }
    },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('@/views/auth/ForgotPasswordView.vue'), meta: { requiresGuest: true } },
    { path: '/reset-password', name: 'reset-password', component: () => import('@/views/auth/ResetPasswordView.vue'), meta: { requiresGuest: true } },
    {
      path: '/',
      redirect: '/dashboard'
    },
    {
      path: '/coordinacion',
      component: () => import('@/layouts/InstitutionalRoleLayout.vue'),
      meta: { requiresAuth: true, roles: ['COORDINADOR'] },
      children: [
        { path: '', redirect: '/coordinacion/dashboard' },
        { path: 'dashboard', component: () => import('@/views/admin/DashboardView.vue') },
        { path: 'programas', component: () => import('@/views/admin/ProgramasView.vue') },
        { path: 'docentes', component: () => import('@/views/admin/DocentesView.vue') },
        { path: 'estudiantes', component: () => import('@/views/admin/EstudiantesView.vue') },
        { path: 'cursos', component: () => import('@/views/admin/CursosView.vue') },
        { path: 'grupos', component: () => import('@/views/admin/GruposView.vue') },
        { path: 'matriculas', component: () => import('@/views/admin/MatriculasView.vue') },
        { path: 'tramites', component: () => import('@/views/admin/TramitesView.vue') },
        { path: 'periodos', component: () => import('@/views/admin/PeriodosView.vue') },
        { path: 'admision', component: () => import('@/views/admin/AdmisionView.vue') },
        { path: 'ambientes', component: () => import('@/views/admin/AmbientesView.vue') },
        { path: 'titulacion', component: () => import('@/views/admin/TitulacionView.vue') },
        { path: 'perfil', component: () => import('@/views/admin/PerfilView.vue') },
      ],
    },
    {
      path: '/secretaria',
      component: () => import('@/layouts/InstitutionalRoleLayout.vue'),
      meta: { requiresAuth: true, roles: ['SECRETARIA'] },
      children: [
        { path: '', redirect: '/secretaria/dashboard' },
        { path: 'dashboard', component: () => import('@/views/admin/DashboardView.vue') },
        { path: 'estudiantes', component: () => import('@/views/admin/EstudiantesView.vue') },
        { path: 'docentes', component: () => import('@/views/admin/DocentesView.vue') },
        { path: 'programas', component: () => import('@/views/admin/ProgramasView.vue') },
        { path: 'matriculas', component: () => import('@/views/admin/MatriculasView.vue') },
        { path: 'tramites', component: () => import('@/views/admin/TramitesView.vue') },
        { path: 'perfil', component: () => import('@/views/admin/PerfilView.vue') },
      ],
    },
    {
      path: '/caja',
      component: () => import('@/layouts/InstitutionalRoleLayout.vue'),
      meta: { requiresAuth: true, roles: ['CAJA'] },
      children: [
        { path: '', redirect: '/caja/dashboard' },
        { path: 'dashboard', component: () => import('@/views/admin/DashboardView.vue') },
        { path: 'caja', component: () => import('@/views/admin/CajaView.vue') },
        { path: 'matriculas', component: () => import('@/views/admin/MatriculasView.vue') },
        { path: 'estudiantes', component: () => import('@/views/admin/EstudiantesView.vue') },
        { path: 'perfil', component: () => import('@/views/admin/PerfilView.vue') },
      ],
    },
    {
      path: '/mesa-partes',
      component: () => import('@/layouts/InstitutionalRoleLayout.vue'),
      meta: { requiresAuth: true, roles: ['MESA_DE_PARTES'] },
      children: [
        { path: '', redirect: '/mesa-partes/dashboard' },
        { path: 'dashboard', component: () => import('@/views/admin/DashboardView.vue') },
        { path: 'tramites', component: () => import('@/views/admin/TramitesView.vue') },
        { path: 'estudiantes', component: () => import('@/views/admin/EstudiantesView.vue') },
        { path: 'perfil', component: () => import('@/views/admin/PerfilView.vue') },
      ],
    },
    {
      path: '/admin',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, roles: ['SUPERADMIN', 'ADMINISTRADOR'] },
      children: [
        { path: '', redirect: '/admin/dashboard' },
        { path: 'dashboard',    name: 'admin-dashboard',    component: () => import('@/views/admin/DashboardView.vue') },
        { path: 'perfil',       name: 'admin-perfil',       component: () => import('@/views/admin/PerfilView.vue') },
        { path: 'usuarios',     name: 'admin-usuarios',     component: () => import('@/views/admin/UsuariosView.vue'), meta: { roles: ['SUPERADMIN', 'ADMINISTRADOR'] } },
        { path: 'instituciones',name: 'admin-instituciones',component: () => import('@/views/admin/InstitucionesView.vue'), meta: { roles: ['SUPERADMIN'] } },
        { path: 'roles',        name: 'admin-roles',        component: () => import('@/views/admin/RolesView.vue'), meta: { roles: ['SUPERADMIN', 'ADMINISTRADOR'] } },
        { path: 'docentes',     name: 'admin-docentes',     component: () => import('@/views/admin/DocentesView.vue') },
        { path: 'estudiantes',  name: 'admin-estudiantes',  component: () => import('@/views/admin/EstudiantesView.vue') },
        { path: 'periodos',     name: 'admin-periodos',     component: () => import('@/views/admin/PeriodosView.vue') },
        { path: 'cursos',       name: 'admin-cursos',       component: () => import('@/views/admin/CursosView.vue') },
        { path: 'grupos',       name: 'admin-grupos',       component: () => import('@/views/admin/GruposView.vue') },
        { path: 'matriculas',   name: 'admin-matriculas',   component: () => import('@/views/admin/MatriculasView.vue') },
        { path: 'caja',         name: 'admin-caja',         component: () => import('@/views/admin/CajaView.vue') },
        { path: 'programas',    name: 'admin-programas',    component: () => import('@/views/admin/ProgramasView.vue') },
        { path: 'admision',     name: 'admin-admision',     component: () => import('@/views/admin/AdmisionView.vue') },
        { path: 'tramites',     name: 'admin-tramites',     component: () => import('@/views/admin/TramitesView.vue') },
        { path: 'mesa-partes',  name: 'admin-mesa-partes',  component: () => import('@/views/admin/TramitesView.vue') },
        { path: 'ambientes',    name: 'admin-ambientes',    component: () => import('@/views/admin/AmbientesView.vue') },
        { path: 'titulacion',   name: 'admin-titulacion',   component: () => import('@/views/admin/TitulacionView.vue') },
      ]
    },
    {
      path: '/verificar-documento/:codigo?',
      name: 'verificar-documento',
      component: () => import('@/views/public/VerificarDocumentoView.vue'),
      meta: { requiresAuth: false }
    },
    {
      path: '/docente',
      component: () => import('@/layouts/DocenteLayout.vue'),
      meta: { requiresAuth: true, roles: ['DOCENTE'] },
      children: [
        { path: '', redirect: '/docente/dashboard' },
        { path: 'dashboard',                  name: 'docente-dashboard',      component: () => import('@/views/docente/DashboardView.vue') },
        { path: 'perfil',                     name: 'docente-perfil',         component: () => import('@/views/admin/PerfilView.vue') },
        { path: 'grupos/:id/aula',            name: 'docente-aula',           component: () => import('@/views/docente/AulaView.vue') },
        { path: 'grupos/:id/tareas',          name: 'docente-tareas',         component: () => import('@/views/docente/TareasView.vue') },
        { path: 'grupos/:id/calificaciones',  name: 'docente-calificaciones', component: () => import('@/views/docente/CalificacionesView.vue') },
      ]
    },
    {
      path: '/estudiante',
      component: () => import('@/layouts/EstudianteLayout.vue'),
      meta: { requiresAuth: true, roles: ['ESTUDIANTE'] },
      children: [
        { path: '',          redirect: '/estudiante/dashboard' },
        { path: 'dashboard', name: 'estudiante-dashboard', component: () => import('@/views/estudiante/DashboardView.vue') },
        { path: 'perfil',    name: 'estudiante-perfil',    component: () => import('@/views/admin/PerfilView.vue') },
        { path: 'cursos/:id',name: 'estudiante-curso',     component: () => import('@/views/estudiante/CursoView.vue') },
        { path: 'tareas',    name: 'estudiante-tareas',    component: () => import('@/views/estudiante/TareasView.vue') },
        { path: 'notas',     name: 'estudiante-notas',     component: () => import('@/views/estudiante/NotasView.vue') },
      ]
    },
    {
      path: '/apoderado',
      component: () => import('@/layouts/ApoderadoLayout.vue'),
      meta: { requiresAuth: true, roles: ['APODERADO'] },
      children: [
        { path: '',          redirect: '/apoderado/dashboard' },
        { path: 'dashboard', name: 'apoderado-dashboard', component: () => import('@/views/apoderado/DashboardView.vue') },
        { path: 'perfil',    name: 'apoderado-perfil',    component: () => import('@/views/admin/PerfilView.vue') },
      ]
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/views/DashboardRedirectView.vue'),
      meta: { requiresAuth: true }
    }
  ]
})

router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()
  
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    if (authStore.token && !authStore.user) {
      try {
        await authStore.fetchMe()
      } catch {
        return next('/login')
      }
    } else if (!authStore.token) {
      return next('/login')
    }
  }
  
  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    return next('/dashboard')
  }
  
  const roleWorkspace: Record<string, string> = {
    COORDINADOR: '/coordinacion/dashboard',
    SECRETARIA: '/secretaria/dashboard',
    CAJA: '/caja/dashboard',
    MESA_DE_PARTES: '/mesa-partes/dashboard',
  }
  if (to.path.startsWith('/admin')) {
    const isAdmin = authStore.userRoles.some((role) => ['SUPERADMIN', 'ADMINISTRADOR'].includes(role))
    if (!isAdmin) {
      const workspace = authStore.userRoles.find((role) => roleWorkspace[role])
      return next(workspace ? roleWorkspace[workspace] : '/dashboard')
    }
  }

  if (to.meta.roles && authStore.user) {
    const allowedRoles = to.meta.roles as string[]
    const hasRole = authStore.userRoles.some(r => allowedRoles.includes(r))
    if (!hasRole) {
      return next('/dashboard')
    }
  }
  
  next()
})

/**
 * Tras cambiar de ruta, el foco se traslada al inicio del contenido para que
 * los lectores de pantalla y la navegación por teclado no queden anclados al
 * enlace que se acaba de pulsar (WCAG 2.4.3). Solo se mueve cuando el usuario
 * no tiene el foco dentro de la aplicación, para no interrumpir un flujo
 * interactivo en curso.
 */
router.afterEach((to) => {
  if (to.meta.requiresGuest) return

  requestAnimationFrame(() => {
    const active = document.activeElement
    if (active && active !== document.body && active !== document.documentElement) {
      return
    }
    const target = document.querySelector<HTMLElement>('main') ?? document.querySelector<HTMLElement>('#app')
    if (!target) return
    if (!target.hasAttribute('tabindex')) {
      target.setAttribute('tabindex', '-1')
    }
    target.focus({ preventScroll: true })
    window.scrollTo({ top: 0, behavior: 'auto' })
  })
})

export default router
