<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const sinRol = ref(false)

onMounted(async () => {
  if (!auth.token) {
    router.replace('/login')
    return
  }

  if (!auth.user) {
    try {
      await auth.fetchMe()
    } catch {
      auth.logout()
      router.replace('/login')
      return
    }
  }

  if (auth.hasRole('SUPERADMIN') || auth.hasRole('ADMINISTRADOR')) {
    router.replace('/admin/dashboard')
  } else if (auth.hasRole('COORDINADOR')) {
    router.replace('/coordinador/dashboard')
  } else if (auth.hasRole('DOCENTE')) {
    router.replace('/docente/dashboard')
  } else if (auth.hasRole('ESTUDIANTE')) {
    router.replace('/estudiante/dashboard')
  } else if (auth.hasRole('APODERADO')) {
    router.replace('/apoderado/dashboard')
  } else if (!auth.isAuthenticated) {
    router.replace('/login')
  } else {
    sinRol.value = true
  }
})

function handleLogout() {
  auth.logout()
  router.replace('/login')
}
</script>

<template>
  <div v-if="!sinRol" class="flex flex-col items-center justify-center min-h-[60vh] gap-3" role="status" aria-label="Redirigiendo...">
    <div class="h-9 w-9 animate-spin rounded-full border-3 border-indigo-600 border-t-transparent" aria-hidden="true" />
    <span class="text-sm font-medium text-slate-500 dark:text-slate-400">Cargando tu espacio…</span>
  </div>

  <div v-else class="flex flex-col items-center justify-center min-h-[60vh] p-6 text-center">
    <div class="p-4 rounded-full bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 mb-4">
      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
    </div>
    <h1 class="text-xl font-bold text-slate-900 dark:text-white">Sin rol asignado</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 max-w-md">
      Tu cuenta de usuario no tiene asignado un rol activo en este momento. Por favor contacta al administrador de tu institución.
    </p>
    <button
      type="button"
      @click="handleLogout"
      class="mt-6 px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors"
    >
      Cerrar Sesión
    </button>
  </div>
</template>
