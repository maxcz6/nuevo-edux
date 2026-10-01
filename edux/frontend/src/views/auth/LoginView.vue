<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ProtectedInput from '@/components/ui/ProtectedInput.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const panelLoaded = ref(false)
const selectedRole = ref<string | null>(null)

type DemoRole = 'superadmin' | 'admin' | 'coordinador' | 'docente' | 'estudiante'

interface DemoAccount {
  name: string
  label: string
  badge: string
  email: string
  password: string
  badgeClass: string
  activeClass: string
}

const DEMO_ACCOUNTS: Record<DemoRole, DemoAccount> = {
  superadmin: {
    name: 'superadmin',
    label: 'SuperAdmin',
    badge: 'Gobierno',
    email: 'superadmin@modux.demo',
    password: 'SuperAdmin2026!',
    badgeClass: 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800',
    activeClass: 'ring-2 ring-purple-500 bg-purple-50/80 dark:bg-purple-950/80',
  },
  admin: {
    name: 'admin',
    label: 'Administrador',
    badge: 'Sede Central',
    email: 'admin@modux.demo',
    password: 'Admin2026!',
    badgeClass: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800',
    activeClass: 'ring-2 ring-blue-500 bg-blue-50/80 dark:bg-blue-950/80',
  },
  coordinador: {
    name: 'coordinador',
    label: 'Coordinador',
    badge: 'Enfermería',
    email: 'coord.enf@modux.demo',
    password: 'Coord2026!',
    badgeClass: 'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/60 dark:text-teal-300 dark:border-teal-800',
    activeClass: 'ring-2 ring-teal-500 bg-teal-50/80 dark:bg-teal-950/80',
  },
  docente: {
    name: 'docente',
    label: 'Docente',
    badge: 'Cátedra',
    email: 'docente01@modux.demo',
    password: 'Docente2026!',
    badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
    activeClass: 'ring-2 ring-emerald-500 bg-emerald-50/80 dark:bg-emerald-950/80',
  },
  estudiante: {
    name: 'estudiante',
    label: 'Estudiante',
    badge: 'Matriculado',
    email: 'est001@modux.demo',
    password: 'Alumno2026!',
    badgeClass: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
    activeClass: 'ring-2 ring-amber-500 bg-amber-50/80 dark:bg-amber-950/80',
  },
}

onMounted(() => {
  setTimeout(() => { panelLoaded.value = true }, 50)
})

function setDemoAccount(role: DemoRole) {
  selectedRole.value = role
  const account = DEMO_ACCOUNTS[role]
  email.value = account.email
  password.value = account.password
  error.value = null
}

async function handleLogin() {
  error.value = null
  loading.value = true
  try {
    await authStore.login(email.value, password.value)
    
    // Enrutamiento seguro basado en los 5 roles del sistema
    if (authStore.hasRole('SUPERADMIN') || authStore.hasRole('ADMINISTRADOR')) {
      router.push('/admin/dashboard')
    } else if (authStore.hasRole('COORDINADOR')) {
      router.push('/coordinador/dashboard')
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
</script>

<template>
  <main class="min-h-screen bg-slate-50 dark:bg-[#000914] text-slate-900 dark:text-slate-100 lg:grid lg:grid-cols-[1.1fr_.9fr] transition-colors duration-200">
    <!-- Panel Izquierdo: Hero Institucional con estética académica moderna -->
    <section 
      :class="[
        'relative hidden overflow-hidden bg-gradient-to-br from-[#001229] via-[#081b33] to-[#020b17] px-12 py-10 lg:flex lg:flex-col lg:justify-between xl:px-20 text-white transition-all duration-700 ease-out transform',
        panelLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'
      ]"
      aria-label="Presentación institucional Modux"
    >
      <!-- Brand Top -->
      <div class="relative z-10 flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-indigo-600 text-xl font-black text-white shadow-lg shadow-primary-900/40">
          M
        </div>
        <div>
          <p class="text-xl font-bold tracking-tight font-headline text-white">Modux</p>
          <p class="text-xs text-slate-400">Plataforma Académica Institucional</p>
        </div>
      </div>

      <!-- Centro Hero -->
      <div class="relative z-10 max-w-xl py-12">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-xs font-semibold text-emerald-300 mb-6 shadow-xs">
          <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span>Semestre 2026-I · Entorno Conectado</span>
        </div>
        <h1 class="text-4xl font-bold leading-[1.15] tracking-tight font-headline text-white xl:text-5xl">
          Gestión académica moderna, conectada y segura<span class="text-primary-400">.</span>
        </h1>
        <p class="mt-5 max-w-lg text-sm leading-relaxed text-slate-300">
          Administración centralizada de expedientes, matrícula inteligente, aula virtual y calificaciones para instituciones de educación superior.
        </p>

        <!-- Feature Pills Glassmorphic -->
        <div class="mt-8 grid grid-cols-3 gap-3 max-w-md">
          <div class="rounded-xl bg-white/5 border border-white/10 p-3.5 text-center backdrop-blur-xs hover:bg-white/10 transition-colors">
            <p class="text-xs font-bold text-white font-headline">Multi-sede</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Aislamiento Seguro</p>
          </div>
          <div class="rounded-xl bg-white/5 border border-white/10 p-3.5 text-center backdrop-blur-xs hover:bg-white/10 transition-colors">
            <p class="text-xs font-bold text-white font-headline">5 Roles</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Control RBAC</p>
          </div>
          <div class="rounded-xl bg-white/5 border border-white/10 p-3.5 text-center backdrop-blur-xs hover:bg-white/10 transition-colors">
            <p class="text-xs font-bold text-white font-headline">Aula Virtual</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Tareas y Notas</p>
          </div>
        </div>
      </div>

      <!-- Footer Info -->
      <p class="relative z-10 text-xs text-slate-400">© 2026 Modux · IESTP SAM · Todos los derechos reservados</p>

      <!-- Luces ambientales -->
      <div class="absolute -right-20 top-20 h-96 w-96 rounded-full bg-primary-500/20 blur-3xl pointer-events-none" aria-hidden="true"></div>
      <div class="absolute -left-20 bottom-10 h-80 w-80 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none" aria-hidden="true"></div>
    </section>

    <!-- Panel Derecho: Formulario de Login y Selector de Rol -->
    <section class="flex min-h-screen flex-col justify-between px-4 py-8 sm:px-8 bg-slate-50 dark:bg-[#000914] transition-colors duration-200">
      <!-- Header Superior con Logo Móvil y ThemeToggle -->
      <div class="flex items-center justify-between w-full max-w-md mx-auto pt-2 pb-2">
        <div class="flex items-center gap-2.5 lg:invisible">
          <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary-700 to-primary-900 text-base font-black text-white shadow-xs">
            M
          </div>
          <span class="text-lg font-bold font-headline text-slate-900 dark:text-white">Modux</span>
        </div>
        <div class="flex items-center gap-2">
          <ThemeToggle />
        </div>
      </div>

      <!-- Card Principal -->
      <div class="w-full max-w-md mx-auto my-auto py-4">
        <div class="rounded-2xl border border-slate-200/90 dark:border-slate-800/90 bg-white dark:bg-slate-900 p-6 shadow-xl sm:p-8 transition-colors">
          <div class="mb-3">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 border border-primary-200/60 dark:border-primary-800/60">
              <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
              Portal de Acceso
            </span>
          </div>
          <h2 class="text-2xl font-bold font-headline tracking-tight text-slate-900 dark:text-white">
            Iniciar sesión
          </h2>
          <p class="mb-5 mt-1 text-xs text-slate-600 dark:text-slate-300">
            Ingresa con tus credenciales institucionales o selecciona un rol demo.
          </p>

          <!-- Quick Demo Switcher (5 Roles Oficiales Activos) -->
          <div class="mb-5 p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200/80 dark:border-slate-700/80">
            <div class="flex items-center justify-between mb-2.5">
              <span class="text-[11px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">
                Accesos demo institucionales:
              </span>
              <span class="text-[10px] text-slate-600 dark:text-slate-300 font-medium">Click para auto-completar</span>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              <button
                v-for="acc in Object.values(DEMO_ACCOUNTS)"
                :key="acc.name"
                type="button"
                @click="setDemoAccount(acc.name as DemoRole)"
                :class="[
                  'p-2 text-left rounded-lg border transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-primary-500 flex flex-col justify-between min-h-[48px]',
                  selectedRole === acc.name ? acc.activeClass : 'bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700/80 border-slate-200 dark:border-slate-700'
                ]"
              >
                <span class="text-xs font-bold text-slate-900 dark:text-slate-100 truncate block">
                  {{ acc.label }}
                </span>
                <span :class="['inline-block mt-1 text-[10px] font-semibold px-1.5 py-0.2 rounded border w-fit truncate', acc.badgeClass]">
                  {{ acc.badge }}
                </span>
              </button>
            </div>
          </div>

          <!-- Mensaje de Error -->
          <div 
            v-if="error" 
            data-testid="error-message" 
            role="alert" 
            class="mb-5 rounded-xl border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/40 p-3 text-xs text-rose-800 dark:text-rose-200 flex items-start gap-2"
          >
            <svg class="h-4 w-4 text-rose-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ error }}</span>
          </div>

          <!-- Formulario -->
          <form @submit.prevent="handleLogin" class="space-y-4">
            <ProtectedInput 
              id="email" 
              v-model="email" 
              type="email" 
              label="Correo electrónico" 
              autocomplete="email" 
              required 
              data-testid="email" 
              placeholder="usuario@institucion.edu" 
            />
            
            <ProtectedInput 
              id="password" 
              v-model="password" 
              type="password" 
              label="Contraseña" 
              autocomplete="current-password" 
              required 
              data-testid="password" 
              placeholder="••••••••" 
            />

            <button 
              type="submit" 
              data-testid="submit" 
              :disabled="loading" 
              class="btn-primary w-full py-2.5 min-h-[44px] rounded-xl text-sm font-semibold shadow-sm shadow-primary-900/10 cursor-pointer disabled:cursor-not-allowed"
            >
              <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent mr-2" />
              {{ loading ? 'Verificando acceso…' : 'Ingresar al sistema' }}
            </button>

            <RouterLink 
              to="/forgot-password" 
              class="block text-center text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 hover:underline pt-1"
            >
              ¿Olvidaste tu contraseña?
            </RouterLink>
          </form>
        </div>

        <p class="mt-4 text-center text-xs text-slate-600 dark:text-slate-400 font-medium">
          Acceso protegido · Cumplimiento de estándares de privacidad institucional
        </p>
      </div>

      <!-- Bottom Spacer -->
      <div class="text-center text-[11px] text-slate-600 dark:text-slate-400 pb-2 font-medium">
        Modux Academic Suite v2.6.0 · 2026
      </div>
    </section>
  </main>
</template>
