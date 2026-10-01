<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { authApi } from '@/api/auth'
import ProtectedInput from '@/components/ui/ProtectedInput.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

const route = useRoute()
const email = ref(String(route.query.email ?? ''))
const token = ref(String(route.query.token ?? ''))
const institucionId = ref(route.query.institucion_id ? Number(route.query.institucion_id) : undefined)
const password = ref('')
const confirmation = ref('')
const loading = ref(false)
const message = ref<string | null>(null)
const error = ref<string | null>(null)

async function submit() {
  if (password.value !== confirmation.value) {
    error.value = 'Las contraseñas no coinciden.'
    return
  }
  loading.value = true
  error.value = null
  try {
    const response = await authApi.resetPassword({
      email: email.value,
      token: token.value,
      password: password.value,
      password_confirmation: confirmation.value,
      institucion_id: institucionId.value
    })
    message.value = response.data.message
  } catch (exception: unknown) {
    const response = (exception as { response?: { data?: { message?: string } } }).response
    error.value = response?.data?.message ?? 'No fue posible restablecer la contraseña.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-slate-50 dark:bg-[#000914] px-4 transition-colors duration-200">
    <!-- Top Action Bar -->
    <div class="fixed top-4 right-4 z-40">
      <ThemeToggle />
    </div>

    <section class="card w-full max-w-md rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-xl sm:p-8 transition-colors">
      <div class="mb-3">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 border border-primary-200/60 dark:border-primary-800/60">
          <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
          Seguridad
        </span>
      </div>
      <h1 class="text-2xl font-bold font-headline tracking-tight text-slate-900 dark:text-white">Nueva contraseña</h1>
      <p class="mt-2 text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
        Ingresa tu nueva contraseña para acceder a la plataforma institucional.
      </p>

      <div v-if="message" class="mt-4 rounded-xl border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 p-3.5 text-xs text-emerald-800 dark:text-emerald-200 flex flex-col gap-2">
        <div class="flex items-center gap-2">
          <svg class="h-4 w-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>{{ message }}</span>
        </div>
        <RouterLink to="/login" class="font-bold underline text-emerald-700 dark:text-emerald-300 hover:text-emerald-900">
          Ir al Inicio de Sesión →
        </RouterLink>
      </div>

      <div v-if="error" class="mt-4 rounded-xl border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/40 p-3.5 text-xs text-rose-800 dark:text-rose-200 flex items-start gap-2">
        <svg class="h-4 w-4 text-rose-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>{{ error }}</span>
      </div>

      <form v-if="!message" class="mt-5 space-y-4" @submit.prevent="submit">
        <ProtectedInput
          id="email"
          v-model="email"
          type="email"
          label="Correo electrónico"
          autocomplete="email"
          required
          placeholder="usuario@institucion.edu"
        />
        <ProtectedInput
          id="password"
          v-model="password"
          type="password"
          label="Nueva contraseña"
          autocomplete="new-password"
          required
          placeholder="Mínimo 8 caracteres"
        />
        <ProtectedInput
          id="confirmation"
          v-model="confirmation"
          type="password"
          label="Confirmar nueva contraseña"
          autocomplete="new-password"
          required
          placeholder="Repite la contraseña"
        />
        <button :disabled="loading" class="btn-primary w-full py-2.5 min-h-[44px] rounded-xl text-sm font-semibold shadow-sm justify-center cursor-pointer disabled:cursor-not-allowed">
          <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent mr-2" />
          {{ loading ? 'Guardando…' : 'Restablecer contraseña' }}
        </button>
      </form>
    </section>
  </main>
</template>
