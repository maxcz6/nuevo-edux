<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter, useRoute, RouterView, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import NotificationBell from '@/components/ui/NotificationBell.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const isSidebarOpen = ref(true)
const isMobileOpen = ref(false)

const navigation = [
  { name: 'Dashboard',         href: '/estudiante/dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { name: 'Tareas Pendientes', href: '/estudiante/tareas',    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
  { name: 'Mis Calificaciones',href: '/estudiante/notas',     icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { name: 'Mi Perfil',         href: '/estudiante/perfil',    icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
]

watch(() => route.path, () => {
  isMobileOpen.value = false
})

const logout = async () => {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="h-screen flex overflow-hidden bg-[#f8f9ff] dark:bg-[#000914]">
    <!-- Skip link -->
    <a 
      href="#main-content" 
      class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:p-3 focus:bg-white dark:focus:bg-slate-800 focus:text-primary-700 dark:focus:text-white focus:shadow-md focus:rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
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
        'fixed lg:static inset-y-0 left-0 z-50 w-64 flex flex-col bg-white dark:bg-slate-900 border-r border-slate-200/80 dark:border-slate-800 transition-all duration-200 ease-in-out shadow-xl lg:shadow-none'
      ]"
      aria-label="Navegación principal de estudiante"
    >
      <!-- Logo & Brand Header -->
      <div class="h-16 flex items-center justify-between px-4 sm:px-5 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-2.5">
          <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-primary-700 to-indigo-800 text-white flex items-center justify-center font-bold text-sm shadow-sm shadow-primary-900/20 shrink-0">E</div>
          <div v-if="isSidebarOpen || isMobileOpen">
            <span class="font-bold text-base font-headline tracking-tight text-slate-900 dark:text-slate-100 block">Aula Virtual</span>
            <span class="text-[11px] font-medium text-slate-400 dark:text-slate-500 block">IESTP SAM</span>
          </div>
        </div>
        <button 
          @click="isMobileOpen = false" 
          class="lg:hidden p-1.5 rounded-lg text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-500"
          aria-label="Cerrar menú de navegación"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Semester chip -->
      <div v-if="isSidebarOpen || isMobileOpen" class="px-4 pt-3 pb-1">
        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50/70 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-[11px] font-medium text-emerald-800 dark:text-emerald-300">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="truncate">Semestre 2026-I · Activo</span>
        </div>
      </div>

      <nav class="flex-1 px-3 py-3 space-y-0.5 overflow-y-auto custom-scrollbar" role="navigation" aria-label="Menú estudiante">
        <RouterLink 
          v-for="item in navigation" 
          :key="item.name" 
          :to="item.href"
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
        </RouterLink>
      </nav>

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
      <!-- Top header -->
      <header class="h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8 bg-white/85 dark:bg-slate-900/85 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800 z-10 transition-all">
        <div class="flex items-center gap-2.5">
          <!-- Mobile toggle -->
          <button 
            @click="isMobileOpen = true" 
            class="lg:hidden p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-500"
            aria-label="Abrir menú de navegación"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <!-- Desktop toggle -->
          <button 
            @click="isSidebarOpen = !isSidebarOpen" 
            class="hidden lg:flex items-center justify-center h-8 w-8 rounded-lg text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500"
            :aria-label="isSidebarOpen ? 'Colapsar menú lateral' : 'Expandir menú lateral'"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/60 border border-amber-200/60 dark:border-amber-800/60 text-amber-800 dark:text-amber-300">Estudiante</span>
        </div>

        <div class="flex items-center gap-3">
          <NotificationBell />
          <ThemeToggle />
          <div class="flex items-center gap-2.5">
            <span class="text-xs font-bold text-slate-800 dark:text-slate-100 hidden sm:inline">{{ authStore.user?.nombres }} {{ authStore.user?.apellido_paterno }}</span>
            <RouterLink
              to="/estudiante/perfil"
              class="rounded-full ring-2 ring-primary-100 hover:ring-primary-300 transition focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
              :title="`${authStore.user?.nombres} — Ver perfil`"
            >
              <UserAvatar :user="authStore.user" size="sm" />
            </RouterLink>
          </div>
        </div>
      </header>

      <!-- Main body -->
      <main id="main-content" class="flex-1 relative overflow-y-auto focus:outline-none p-4 sm:p-6 lg:p-8 custom-scrollbar" tabindex="-1">
        <RouterView />
      </main>
    </div>
  </div>
</template>

