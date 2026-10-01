<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ProtectedInput from '@/components/ui/ProtectedInput.vue'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)


async function handleLogin() {
  error.value = null
  loading.value = true
  try {
    await authStore.login(email.value, password.value)
    // Redirect based on role
    if (authStore.hasRole('MESA_DE_PARTES')) {
      router.push('/mesa-partes/dashboard')
    } else if (authStore.hasRole('CAJA')) {
      router.push('/caja/dashboard')
    } else if (authStore.hasRole('SECRETARIA')) {
      router.push('/secretaria/dashboard')
    } else if (authStore.hasRole('COORDINADOR')) {
      router.push('/coordinacion/dashboard')
    } else if (authStore.hasRole('ADMINISTRADOR') || authStore.hasRole('SUPERADMIN')) {
      router.push('/admin/dashboard')
    } else if (authStore.hasRole('DOCENTE')) {
      router.push('/docente/dashboard')
    } else if (authStore.hasRole('ESTUDIANTE')) {
      router.push('/estudiante/dashboard')
    } else if (authStore.hasRole('APODERADO')) {
      router.push('/apoderado/dashboard')
    } else {
      router.push('/dashboard')
    }
  } catch (e: unknown) {
    const err = e as { response?: { status: number; data?: { message: string } } }
    if (err.response?.status === 401) {
      error.value = err.response.data?.message ?? 'Credenciales incorrectas.'
    } else if (err.response?.status === 429) {
      error.value = 'Demasiados intentos de inicio de sesión. Por favor espere un momento antes de volver a intentar.'
    } else {
      error.value = 'Error al conectar con el servidor.'
    }
  } finally {
    loading.value = false
  }
}

function setDemoAccount(role: 'superadmin' | 'admin' | 'coordinador' | 'secretaria' | 'caja' | 'mesa' | 'docente' | 'estudiante' | 'apoderado') {
  const accounts: Record<typeof role, { email: string; password: string }> = {
    superadmin: { email: 'superadmin@modux.demo', password: 'SuperAdmin2026!' },
    admin: { email: 'admin@modux.demo', password: 'Admin2026!' },
    coordinador: { email: 'coord.enf@modux.demo', password: 'Coord2026!' },
    secretaria: { email: 'secretaria@modux.demo', password: 'Secretaria2026!' },
    caja: { email: 'caja@modux.demo', password: 'Caja2026!' },
    mesa: { email: 'mesa.partes@modux.demo', password: 'Mesa2026!' },
    docente: { email: 'docente01@modux.demo', password: 'Docente2026!' },
    estudiante: { email: 'est001@modux.demo', password: 'Alumno2026!' },
    apoderado: { email: 'apoderado01@modux.demo', password: 'Padre2026!' },
  }

  const account = accounts[role]
  email.value = account.email
  password.value = account.password
}
</script>

<template>
  <main class="min-h-screen bg-[#f8f9ff] dark:bg-slate-950 lg:grid lg:grid-cols-[1.1fr_.9fr]">
    <!-- Panel Izquierdo: Hero Ambiental EducaCampus -->
    <section class="relative hidden overflow-hidden bg-gradient-to-br from-[#001229] via-[#091e38] to-[#040e1c] px-12 py-10 lg:flex lg:flex-col lg:justify-between xl:px-20 text-white">
      <div class="relative z-10 flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-indigo-600 text-xl font-black text-white shadow-lg shadow-primary-900/40">M</div>
        <div>
          <p class="text-xl font-bold tracking-tight font-headline text-white">Modux</p>
          <p class="text-xs text-slate-400">Plataforma Académica Institucional</p>
        </div>
      </div>

      <div class="relative z-10 max-w-xl py-12">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm border border-white/15 text-xs font-semibold text-emerald-300 mb-6">
          <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span>Semestre 2026-I · Conectado</span>
        </div>
        <h1 class="text-4xl font-bold leading-[1.1] tracking-tight font-headline text-white xl:text-5xl">
          Gestión académica moderna, conectada y segura<span class="text-primary-400">.</span>
        </h1>
        <p class="mt-5 max-w-lg text-sm leading-relaxed text-slate-300">
          Administración centralizada de expedientes, matrículas, aula virtual y caja para instituciones educativas de excelencia.
        </p>

        <div class="mt-8 grid grid-cols-3 gap-3 max-w-md">
          <div class="rounded-xl bg-white/5 border border-white/10 p-3 text-center backdrop-blur-xs">
            <p class="text-xs font-bold text-white font-headline">Multi-sede</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Aislamiento total</p>
          </div>
          <div class="rounded-xl bg-white/5 border border-white/10 p-3 text-center backdrop-blur-xs">
            <p class="text-xs font-bold text-white font-headline">9 Roles</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Control de acceso</p>
          </div>
          <div class="rounded-xl bg-white/5 border border-white/10 p-3 text-center backdrop-blur-xs">
            <p class="text-xs font-bold text-white font-headline">Aula Virtual</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Tareas y exámenes</p>
          </div>
        </div>
      </div>

      <p class="relative z-10 text-xs text-slate-500">© 2026 Modux · IESTP SAM · Todos los derechos reservados</p>

      <!-- Elementos de luz ambiental -->
      <div class="absolute -right-20 top-20 h-96 w-96 rounded-full bg-primary-500/20 blur-3xl pointer-events-none" aria-hidden="true"></div>
      <div class="absolute -left-20 bottom-10 h-80 w-80 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none" aria-hidden="true"></div>
    </section>

    <!-- Panel Derecho: Formulario de Login -->
    <section class="flex min-h-screen items-center justify-center px-4 py-12 sm:px-8 bg-[#f8f9ff] dark:bg-slate-950">
      <div class="w-full max-w-md">
        <div class="mb-8 lg:hidden">
          <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-600 text-lg font-black text-white shadow-sm">M</div>
            <span class="text-xl font-bold font-headline text-slate-900 dark:text-white">Modux</span>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-xl sm:p-8">
          <div class="mb-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 border border-primary-200/60">
              <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
              Portal de Acceso
            </span>
          </div>
          <h2 class="text-2xl font-bold font-headline tracking-tight text-slate-900 dark:text-white">Iniciar sesión</h2>
          <p class="mb-5 mt-1 text-xs text-slate-500 dark:text-slate-400">Usa las credenciales institucionales registradas.</p>

          <!-- Quick Demo Switcher -->
          <div class="mb-5 p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200/80 dark:border-slate-700">
            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-2">Acceso rápido demo (IESTP SAM):</span>
            <div class="grid grid-cols-3 gap-1.5">
              <button
                type="button"
                @click="setDemoAccount('superadmin')"
                class="px-2 py-1 text-[11px] font-semibold text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg border border-purple-200 transition text-center focus:outline-none focus:ring-2 focus:ring-purple-500"
              >
                SuperAdmin
              </button>
              <button
                type="button"
                @click="setDemoAccount('admin')"
                class="px-2 py-1 text-[11px] font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg border border-blue-200 transition text-center focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                Administrador
              </button>
              <button
                type="button"
                @click="setDemoAccount('coordinador')"
                class="px-2 py-1 text-[11px] font-semibold text-teal-700 bg-teal-50 hover:bg-teal-100 rounded-lg border border-teal-200 transition text-center focus:outline-none focus:ring-2 focus:ring-teal-500"
              >
                Coord. Enf.
              </button>
              <button
                type="button"
                @click="setDemoAccount('secretaria')"
                class="px-2 py-1 text-[11px] font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg border border-amber-200 transition text-center focus:outline-none focus:ring-2 focus:ring-amber-500"
              >
                Secretaría
              </button>
              <button
                type="button"
                @click="setDemoAccount('caja')"
                class="px-2 py-1 text-[11px] font-semibold text-yellow-700 bg-yellow-50 hover:bg-yellow-100 rounded-lg border border-yellow-200 transition text-center focus:outline-none focus:ring-2 focus:ring-yellow-500"
              >
                Caja
              </button>
              <button
                type="button"
                @click="setDemoAccount('mesa')"
                class="px-2 py-1 text-[11px] font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg border border-slate-300 transition text-center focus:outline-none focus:ring-2 focus:ring-slate-500"
              >
                Mesa Partes
              </button>
              <button
                type="button"
                @click="setDemoAccount('docente')"
                class="px-2 py-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition text-center focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                Docente
              </button>
              <button
                type="button"
                @click="setDemoAccount('estudiante')"
                class="px-2 py-1 text-[11px] font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-200 transition text-center focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                Estudiante
              </button>
              <button
                type="button"
                @click="setDemoAccount('apoderado')"
                class="px-2 py-1 text-[11px] font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-lg border border-rose-200 transition text-center focus:outline-none focus:ring-2 focus:ring-rose-500"
              >
                Apoderado
              </button>
            </div>
          </div>

          <div v-if="error" data-testid="error-message" role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
            {{ error }}
          </div>

          <form @submit.prevent="handleLogin" class="space-y-4">
            <ProtectedInput id="email" v-model="email" type="email" label="Correo electrónico" autocomplete="email" required data-testid="email" placeholder="usuario@institucion.edu" />
            <ProtectedInput id="password" v-model="password" type="password" label="Contraseña" autocomplete="current-password" required data-testid="password" />

            <button type="submit" data-testid="submit" :disabled="loading" class="btn-primary w-full py-2.5 rounded-xl text-sm font-semibold shadow-sm shadow-primary-900/10">
              <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent mr-2" />
              {{ loading ? 'Verificando acceso…' : 'Ingresar al sistema' }}
            </button>

            <RouterLink to="/forgot-password" class="block text-center text-xs font-semibold text-primary-600 hover:text-primary-800 hover:underline pt-1">
              ¿Olvidaste tu contraseña?
            </RouterLink>
          </form>
        </div>

        <p class="mt-5 text-center text-xs text-slate-600 dark:text-slate-400 font-medium">Acceso protegido · Tus datos permanecen dentro de tu institución</p>
      </div>
    </section>
  </main>
</template>
