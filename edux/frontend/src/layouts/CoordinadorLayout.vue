<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

const router = useRouter()
const authStore = useAuthStore()

const isSidebarOpen = ref(true)
const isMobileOpen = ref(false)

const programaNombre = computed(() => {
  return authStore.user?.unidad_academica?.nombre || 'Programa Asignado'
})

const ICONS = {
  dashboard:   'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  programas:   'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
  docentes:    'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
  estudiantes: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222',
  cursos:      'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
  grupos:      'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
  asignaciones:'M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
  actas:       'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
  matriculas:  'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
  tramites:    'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2',
  perfil:      'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
}

const navigation = [
  { name: 'Dashboard',             to: '/coordinador/dashboard',   icon: ICONS.dashboard },
  { name: 'Docentes del Programa', to: '/coordinador/docentes',    icon: ICONS.docentes },
  { name: 'Estudiantes',           to: '/coordinador/estudiantes', icon: ICONS.estudiantes },
  { name: 'Cursos / Malla',        to: '/coordinador/cursos',      icon: ICONS.cursos },
  { name: 'Grupos y Aulas',        to: '/coordinador/grupos',      icon: ICONS.grupos },
  { name: 'Asignación Docente',    to: '/coordinador/asignaciones',icon: ICONS.asignaciones },
  { name: 'Actas y Calificaciones',to: '/coordinador/actas',       icon: ICONS.actas },
  { name: 'Matrículas',            to: '/coordinador/matriculas',  icon: ICONS.matriculas },
  { name: 'Trámites',              to: '/coordinador/tramites',    icon: ICONS.tramites },
  { name: 'Mi Perfil',             to: '/coordinador/perfil',      icon: ICONS.perfil },
]

function handleLogout() {
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-900 flex flex-col font-sans transition-colors duration-200">
    <!-- Topbar -->
    <header class="h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 sm:px-6 z-20 sticky top-0 shadow-sm">
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="isSidebarOpen = !isSidebarOpen"
          class="hidden md:inline-flex p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          aria-label="Alternar barra lateral"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <button
          type="button"
          @click="isMobileOpen = !isMobileOpen"
          class="md:hidden p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700"
          aria-label="Abrir menú"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <div class="flex items-center gap-2">
          <span class="font-bold text-xl text-indigo-600 dark:text-indigo-400">EduX</span>
          <span class="hidden sm:inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
            Coordinación
          </span>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <div class="text-right hidden sm:block">
          <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Programa</p>
          <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate max-w-xs">{{ programaNombre }}</p>
        </div>
        <ThemeToggle />
        <div class="flex items-center gap-2 pl-2 border-l border-slate-200 dark:border-slate-700">
          <UserAvatar :user="authStore.user" size="sm" />
          <button
            type="button"
            @click="handleLogout"
            class="p-2 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
            title="Cerrar sesión"
            aria-label="Cerrar sesión"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <div class="flex-1 flex overflow-hidden">
      <!-- Sidebar Desktop -->
      <aside
        :class="[
          'bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 flex flex-col transition-all duration-300 z-10',
          isSidebarOpen ? 'w-64' : 'w-20'
        ]"
      >
        <div class="p-4 border-b border-slate-100 dark:border-slate-700/60" v-if="isSidebarOpen">
          <div class="bg-indigo-50 dark:bg-slate-700/40 p-3 rounded-xl border border-indigo-100 dark:border-slate-600/50">
            <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider block">Área Académica</span>
            <span class="text-sm font-bold text-slate-800 dark:text-slate-100 block truncate mt-0.5">{{ programaNombre }}</span>
          </div>
        </div>

        <nav class="flex-1 p-3 space-y-1 overflow-y-auto" aria-label="Menú de Coordinación">
          <RouterLink
            v-for="item in navigation"
            :key="item.to"
            :to="item.to"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="[
              $route.path.startsWith(item.to)
                ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-200 dark:shadow-none'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-white'
            ]"
            :title="!isSidebarOpen ? item.name : undefined"
          >
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
            </svg>
            <span v-if="isSidebarOpen" class="truncate">{{ item.name }}</span>
          </RouterLink>
        </nav>
      </aside>

      <!-- Main content -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 focus:outline-none" tabindex="-1">
        <div class="max-w-7xl mx-auto">
          <RouterView />
        </div>
      </main>
    </div>
  </div>
</template>
