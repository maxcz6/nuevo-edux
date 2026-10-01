<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useProgramaStore } from '@/stores/programa'
import { useRouter, useRoute, RouterLink, RouterView } from 'vue-router'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'

const authStore = useAuthStore()
const programaStore = useProgramaStore()
const router = useRouter()
const route = useRoute()
const isMobileOpen = ref(false)
const isSidebarOpen = ref(true)

const isSuperAdmin  = computed(() => authStore.hasRole('SUPERADMIN'))
const isAdmin       = computed(() => authStore.hasRole('ADMINISTRADOR'))
const isCoordinador = computed(() => authStore.hasRole('COORDINADOR'))

// ── Iconos reutilizables ─────────────────────────────────────────────────────
const ICONS = {
  dashboard:   'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  institution: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
  programas:   'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
  usuarios:    'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
  roles:       'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
  docentes:    'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
  estudiantes: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222',
  periodos:    'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
  cursos:      'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
  grupos:      'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
  matriculas:  'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
  admision:    'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  tramites:    'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2',
  ambientes:   'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
  titulacion:  'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5',
  perfil:      'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
}

// ── Navegación diferenciada por rol ──────────────────────────────────────────
const navigation = computed(() => {
  if (isSuperAdmin.value) {
    // SUPERADMIN — acceso global completo
    return [
      { name: 'Dashboard',             to: '/admin/dashboard',   icon: ICONS.dashboard },
      { name: 'Instituciones',         to: '/admin/instituciones', icon: ICONS.institution, badge: 'Global' },
      { name: 'Programas de Estudio',  to: '/admin/programas',   icon: ICONS.programas },
      { name: 'Usuarios del Sistema',  to: '/admin/usuarios',    icon: ICONS.usuarios },
      { name: 'Roles y Permisos',      to: '/admin/roles',       icon: ICONS.roles },
      { name: 'Docentes',              to: '/admin/docentes',    icon: ICONS.docentes },
      { name: 'Estudiantes',           to: '/admin/estudiantes', icon: ICONS.estudiantes },
      { name: 'Períodos Académicos',   to: '/admin/periodos',    icon: ICONS.periodos },
      { name: 'Cursos / Unidades',     to: '/admin/cursos',      icon: ICONS.cursos },
      { name: 'Grupos de Curso',       to: '/admin/grupos',      icon: ICONS.grupos },
      { name: 'Matrículas',            to: '/admin/matriculas',  icon: ICONS.matriculas },
      { name: 'Admisión Tecnológica',  to: '/admin/admision',    icon: ICONS.admision },
      { name: 'Trámites Académicos',   to: '/admin/tramites',    icon: ICONS.tramites },
      { name: 'Ambientes y Talleres',  to: '/admin/ambientes',   icon: ICONS.ambientes },
      { name: 'Titulación y Egresados',to: '/admin/titulacion',  icon: ICONS.titulacion },
      { name: 'Mi Perfil',             to: '/admin/perfil',      icon: ICONS.perfil },
    ]
  }

  if (isAdmin.value) {
    // ADMINISTRADOR — gestión institucional, sin Instituciones ni CRUD de Roles
    return [
      { name: 'Dashboard',             to: '/admin/dashboard',   icon: ICONS.dashboard },
      { name: 'Programas de Estudio',  to: '/admin/programas',   icon: ICONS.programas },
      { name: 'Usuarios',              to: '/admin/usuarios',    icon: ICONS.usuarios },
      { name: 'Docentes',              to: '/admin/docentes',    icon: ICONS.docentes },
      { name: 'Estudiantes',           to: '/admin/estudiantes', icon: ICONS.estudiantes },
      { name: 'Períodos Académicos',   to: '/admin/periodos',    icon: ICONS.periodos },
      { name: 'Cursos / Unidades',     to: '/admin/cursos',      icon: ICONS.cursos },
      { name: 'Grupos de Curso',       to: '/admin/grupos',      icon: ICONS.grupos },
      { name: 'Matrículas',            to: '/admin/matriculas',  icon: ICONS.matriculas },
      { name: 'Admisión Tecnológica',  to: '/admin/admision',    icon: ICONS.admision },
      { name: 'Trámites Académicos',   to: '/admin/tramites',    icon: ICONS.tramites },
      { name: 'Ambientes y Talleres',  to: '/admin/ambientes',   icon: ICONS.ambientes },
      { name: 'Titulación y Egresados',to: '/admin/titulacion',  icon: ICONS.titulacion },
      { name: 'Mi Perfil',             to: '/admin/perfil',      icon: ICONS.perfil },
    ]
  }

  // Fallback para administradores
  return [
    { name: 'Dashboard', to: '/admin/dashboard', icon: ICONS.dashboard },
    { name: 'Mi Perfil', to: '/admin/perfil',    icon: ICONS.perfil },
  ]
})

// ── Etiqueta del rol para el badge ───────────────────────────────────────────
const rolLabel = computed(() => {
  if (isSuperAdmin.value)  return 'Superadmin · Global'
  if (isAdmin.value)       return 'Administrador'
  return 'Administración'
})

const rolBadgeClass = computed(() => {
  if (isSuperAdmin.value)  return 'bg-purple-100 text-purple-800 border-purple-200'
  if (isAdmin.value)       return 'bg-blue-100 text-blue-800 border-blue-200'
  return 'bg-gray-100 text-gray-800 border-gray-200'
})

onMounted(async () => {
  await programaStore.fetchProgramas()
  if (isCoordinador.value && authStore.user?.unidad_academica_id) {
    programaStore.setPrograma(authStore.user.unidad_academica_id)
  }
})

function onProgramaChange(event: Event) {
  const target = event.target as HTMLSelectElement
  const val = target.value ? Number(target.value) : null
  programaStore.setPrograma(val)
  if (route.path.includes('/admin/docentes') || route.path.includes('/admin/estudiantes')) {
    router.replace({ query: { ...route.query, programa_id: val ? String(val) : undefined } })
  }
}

watch(() => route.path, () => { isMobileOpen.value = false })

function logout() {
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-[#f8f9ff] dark:bg-[#000914] flex flex-col lg:flex-row">
    <!-- Skip to content -->
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:p-3 focus:bg-white focus:text-primary-700 focus:shadow-md focus:rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
    >
      Saltar al contenido principal
    </a>

    <!-- Mobile Backdrop -->
    <div
      v-if="isMobileOpen"
      class="fixed inset-0 bg-gray-900/50 z-40 lg:hidden backdrop-blur-xs transition-opacity"
      aria-hidden="true"
      @click="isMobileOpen = false"
    />

    <!-- Sidebar -->
    <aside
      :class="[
        isMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        isSidebarOpen ? 'lg:w-64' : 'lg:w-20',
        'fixed lg:static inset-y-0 left-0 z-50 w-64 bg-white dark:bg-slate-900 border-r border-slate-200/80 dark:border-slate-800 flex flex-col transition-all duration-200 ease-in-out shadow-xl lg:shadow-none'
      ]"
      :aria-label="`Navegación de ${rolLabel}`"
    >
      <!-- Logo & Brand Header -->
      <div class="h-16 flex items-center justify-between px-4 sm:px-5 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-2.5">
          <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-primary-700 to-primary-900 text-white flex items-center justify-center font-black text-lg shadow-sm shadow-primary-900/20 shrink-0">
            M
          </div>
          <div v-if="isSidebarOpen || isMobileOpen">
            <span class="text-base font-bold font-headline text-slate-900 dark:text-slate-100 tracking-tight leading-none block">Modux</span>
            <span class="text-[11px] font-medium text-slate-400 dark:text-slate-500 tracking-wide leading-none mt-0.5 block">IESTP SAM</span>
          </div>
        </div>
        <button
          @click="isMobileOpen = false"
          class="lg:hidden p-1.5 rounded-lg text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-500"
          aria-label="Cerrar menú de navegación"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Semester & Role chips inside sidebar -->
      <div v-if="isSidebarOpen || isMobileOpen" class="px-4 pt-3 pb-1 space-y-1.5">
        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50/70 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-[11px] font-medium text-emerald-800 dark:text-emerald-300">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="truncate">Semestre 2026-I · Activo</span>
        </div>
        <div>
          <span
            class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-0.5 rounded-md border"
            :class="rolBadgeClass"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
            {{ rolLabel }}
          </span>
        </div>
      </div>

      <!-- Nav links -->
      <nav class="flex-1 px-3 py-2 space-y-0.5 overflow-y-auto custom-scrollbar" role="navigation">
        <RouterLink
          v-for="item in navigation"
          :key="item.name"
          :to="item.to"
          :title="!isSidebarOpen && !isMobileOpen ? item.name : undefined"
          class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500"
          active-class="bg-primary-50 text-primary-700 font-semibold shadow-xs"
        >
          <svg
            class="h-5 w-5 shrink-0 text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-white group-[.router-link-active]:text-primary-600"
            :class="isSidebarOpen || isMobileOpen ? 'mr-3' : 'mx-auto'"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
          </svg>
          <span v-if="isSidebarOpen || isMobileOpen" class="truncate">{{ item.name }}</span>
          <span
            v-if="(isSidebarOpen || isMobileOpen) && item.badge"
            class="ml-auto text-[10px] font-bold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700"
          >{{ item.badge }}</span>
        </RouterLink>
      </nav>

      <!-- Logout -->
      <div class="p-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/40">
        <button
          @click="logout"
          class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold text-rose-600 dark:text-rose-300 bg-rose-50/60 dark:bg-rose-950/40 hover:bg-rose-100/80 dark:hover:bg-rose-900/40 border border-rose-200/60 dark:border-rose-800/40 transition focus:outline-none focus:ring-2 focus:ring-rose-500"
          aria-label="Cerrar sesión"
        >
          <svg class="h-4 w-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          <span v-if="isSidebarOpen || isMobileOpen">Cerrar sesión</span>
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-[#f8f9ff] dark:bg-[#000914]">
      <header class="h-16 bg-white/85 dark:bg-slate-900/85 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between px-4 sm:px-6 z-10 gap-4 transition-all">
        <!-- Left: Mobile hamburger & Desktop collapse toggle -->
        <div class="flex items-center gap-2.5">
          <button
            @click="isMobileOpen = true"
            class="lg:hidden p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-500"
            aria-label="Abrir menú de navegación"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <button
            @click="isSidebarOpen = !isSidebarOpen"
            class="hidden lg:flex items-center justify-center h-8 w-8 rounded-lg text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500"
            :aria-label="isSidebarOpen ? 'Colapsar menú lateral' : 'Expandir menú lateral'"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>

        <!-- Center: Program Switcher (hidden for coordinador = fixed to their program) -->
        <div v-if="!isCoordinador" class="flex items-center gap-2">
          <label for="programa-switcher" class="sr-only sm:not-sr-only text-xs font-semibold text-slate-500 whitespace-nowrap">
            Carrera:
          </label>
          <div class="relative">
            <select
              id="programa-switcher"
              :value="programaStore.selectedProgramaId ?? ''"
              @change="onProgramaChange"
              class="text-xs font-medium bg-slate-50 hover:bg-slate-100/80 border border-slate-200/90 text-slate-800 rounded-xl px-3 py-1.5 pr-8 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 max-w-[200px] sm:max-w-xs truncate cursor-pointer transition-colors"
              aria-label="Filtrar por Programa de Estudios"
            >
              <option value="">Todos los Programas</option>
              <option
                v-for="prog in programaStore.programas"
                :key="prog.id"
                :value="prog.id"
              >
                {{ prog.codigo }} - {{ prog.nombre }}
              </option>
            </select>
          </div>
        </div>
        <div v-else class="text-xs font-semibold text-teal-800 bg-teal-50 border border-teal-200/70 rounded-xl px-3 py-1.5 flex items-center gap-1.5">
          <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
          {{ programaStore.programas.find(p => p.id === programaStore.selectedProgramaId)?.nombre ?? 'Mi Programa' }}
        </div>

        <!-- Right: User info -->
        <div class="flex items-center gap-3">
          <ThemeToggle />
          <div class="text-right">
            <div class="text-xs font-bold text-slate-900 dark:text-slate-100 leading-tight" data-testid="user-name">
              <span>{{ authStore.user?.nombres }}</span> <span class="hidden sm:inline">{{ authStore.user?.apellido_paterno }}</span>
            </div>
            <div class="text-[11px] font-medium text-slate-400 leading-tight mt-0.5 hidden sm:block">{{ authStore.user?.institucion?.nombre ?? 'IESTP SAM • Palián' }}</div>
          </div>
          <RouterLink
            to="/admin/perfil"
            class="rounded-full ring-2 ring-primary-100 hover:ring-primary-300 transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            :title="`${authStore.user?.nombres} — Ver perfil`"
          >
            <UserAvatar :user="authStore.user" size="md" />
          </RouterLink>
        </div>
      </header>

      <main id="main-content" class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 focus:outline-none custom-scrollbar" tabindex="-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>
