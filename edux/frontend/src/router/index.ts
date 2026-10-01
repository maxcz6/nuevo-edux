import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/auth/ForgotPasswordView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/auth/ResetPasswordView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/dashboard',
      name: 'dashboard-redirect',
      component: () => import('@/views/DashboardRedirectView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/verificar-documento/:codigo?',
      name: 'verificar-documento',
      component: () => import('@/views/public/VerificarDocumentoView.vue'),
      meta: { requiresAuth: false },
    },

    // ── SUPERADMIN & ADMINISTRADOR ─────────────────────────────────────────────
    {
      path: '/admin',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, roles: ['SUPERADMIN', 'ADMINISTRADOR'] },
      children: [
        { path: '', redirect: '/admin/dashboard' },
        { path: 'dashboard',    name: 'admin-dashboard',    component: () => import('@/views/admin/DashboardView.vue') },
        { path: 'perfil',       name: 'admin-perfil',       component: () => import('@/views/admin/PerfilView.vue') },
        { path: 'usuarios',     name: 'admin-usuarios',     component: () => import('@/views/admin/UsuariosView.vue') },
        { path: 'instituciones',name: 'admin-instituciones',component: () => import('@/views/admin/InstitucionesView.vue'), meta: { roles: ['SUPERADMIN'] } },
        { path: 'roles',        name: 'admin-roles',        component: () => import('@/views/admin/RolesView.vue') },
        { path: 'docentes',     name: 'admin-docentes',     component: () => import('@/views/admin/DocentesView.vue') },
        { path: 'estudiantes',  name: 'admin-estudiantes',  component: () => import('@/views/admin/EstudiantesView.vue') },
        { path: 'periodos',     name: 'admin-periodos',     component: () => import('@/views/admin/PeriodosView.vue') },
        { path: 'cursos',       name: 'admin-cursos',       component: () => import('@/views/admin/CursosView.vue') },
        { path: 'grupos',       name: 'admin-grupos',       component: () => import('@/views/admin/GruposView.vue') },
        { path: 'matriculas',   name: 'admin-matriculas',   component: () => import('@/views/admin/MatriculasView.vue') },
        { path: 'programas',    name: 'admin-programas',    component: () => import('@/views/admin/ProgramasView.vue') },
        { path: 'admision',     name: 'admin-admision',     component: () => import('@/views/admin/AdmisionView.vue') },
        { path: 'tramites',     name: 'admin-tramites',     component: () => import('@/views/admin/TramitesView.vue') },
        { path: 'ambientes',    name: 'admin-ambientes',    component: () => import('@/views/admin/AmbientesView.vue') },
        { path: 'titulacion',   name: 'admin-titulacion',   component: () => import('@/views/admin/TitulacionView.vue') },
      ],
    },

    // ── COORDINADOR DE PROGRAMA ───────────────────────────────────────────────
    {
      path: '/coordinador',
      component: () => import('@/layouts/CoordinadorLayout.vue'),
      meta: { requiresAuth: true, roles: ['COORDINADOR'] },
      children: [
        { path: '', redirect: '/coordinador/dashboard' },
        { path: 'dashboard',    name: 'coord-dashboard',    component: () => import('@/views/coordinador/DashboardView.vue') },
        { path: 'docentes',     name: 'coord-docentes',     component: () => import('@/views/admin/DocentesView.vue') },
        { path: 'estudiantes',  name: 'coord-estudiantes',  component: () => import('@/views/admin/EstudiantesView.vue') },
        { path: 'cursos',       name: 'coord-cursos',       component: () => import('@/views/admin/CursosView.vue') },
        { path: 'grupos',       name: 'coord-grupos',       component: () => import('@/views/admin/GruposView.vue') },
        { path: 'asignaciones', name: 'coord-asignaciones', component: () => import('@/views/coordinador/AsignacionesView.vue') },
        { path: 'actas',        name: 'coord-actas',        component: () => import('@/views/coordinador/ActasView.vue') },
        { path: 'matriculas',   name: 'coord-matriculas',   component: () => import('@/views/admin/MatriculasView.vue') },
        { path: 'tramites',     name: 'coord-tramites',     component: () => import('@/views/admin/TramitesView.vue') },
        { path: 'perfil',       name: 'coord-perfil',       component: () => import('@/views/admin/PerfilView.vue') },
      ],
    },

    // ── DOCENTE ───────────────────────────────────────────────────────────────
    {
      path: '/docente',
      component: () => import('@/layouts/DocenteLayout.vue'),
      meta: { requiresAuth: true, roles: ['DOCENTE'] },
      children: [
        { path: '', redirect: '/docente/dashboard' },
        { path: 'dashboard',                 name: 'docente-dashboard',      component: () => import('@/views/docente/DashboardView.vue') },
        { path: 'perfil',                    name: 'docente-perfil',         component: () => import('@/views/admin/PerfilView.vue') },
        { path: 'grupos/:id/aula',           name: 'docente-aula',           component: () => import('@/views/docente/AulaView.vue') },
        { path: 'grupos/:id/tareas',         name: 'docente-tareas',         component: () => import('@/views/docente/TareasView.vue') },
        { path: 'grupos/:id/calificaciones', name: 'docente-calificaciones',component: () => import('@/views/docente/CalificacionesView.vue') },
      ],
    },

    // ── ESTUDIANTE ────────────────────────────────────────────────────────────
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
      ],
    },

    // Fallback
    {
      path: '/:pathMatch(.*)*',
      redirect: '/dashboard',
    },
  ],
})

// ── Navigation Guard ──────────────────────────────────────────────────────────
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Si tiene token pero no los datos del usuario, cargarlos
  if (authStore.token && !authStore.user) {
    try {
      await authStore.fetchMe()
    } catch {
      authStore.logout()
      return next('/login')
    }
  }

  // Rutas que requieren estar no autenticado (login)
  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    return next('/dashboard')
  }

  // Rutas protegidas que requieren autenticación
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next('/login')
  }

  // Verificación estricta de rol
  if (to.meta.roles && Array.isArray(to.meta.roles)) {
    const allowedRoles = to.meta.roles as string[]
    const hasAllowedRole = allowedRoles.some(r => authStore.hasRole(r))

    if (!hasAllowedRole) {
      // Redirigir al dashboard de su propio rol
      return next('/dashboard')
    }
  }

  next()
})

export default router
