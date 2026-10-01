<script setup lang="ts">
import { ref } from 'vue'
import { authApi } from '@/api/auth'
import ProtectedInput from '@/components/ui/ProtectedInput.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

const email = ref('')
const loading = ref(false)
const message = ref<string | null>(null)
const error = ref<string | null>(null)

async function submit() {
  loading.value = true
  error.value = null
  try {
    const response = await authApi.forgotPassword(email.value)
    message.value = response.data.message
  } catch {
    error.value = 'No fue posible procesar la solicitud. Intenta nuevamente.'
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
          Recuperación
        </span>
      </div>
      <h1 class="text-2xl font-bold font-headline tracking-tight text-slate-900 dark:text-white">Recuperar contraseña</h1>
      <p class="mt-2 text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
        Te enviaremos un enlace seguro a tu correo institucional para restablecer tu acceso.
      </p>

      <div v-if="message" class="mt-4 rounded-xl border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 p-3.5 text-xs text-emerald-800 dark:text-emerald-200 flex items-start gap-2">
        <svg class="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span>{{ message }}</span>
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
          label="Correo electrónico institucional"
          autocomplete="email"
          required
          placeholder="usuario@institucion.edu"
        />
        <button :disabled="loading" class="btn-primary w-full py-2.5 min-h-[44px] rounded-xl text-sm font-semibold shadow-sm justify-center cursor-pointer disabled:cursor-not-allowed">
          <span v-if="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent mr-2" />
          {{ loading ? 'Enviando enlace…' : 'Enviar enlace' }}
        </button>
      </form>

      <RouterLink to="/login" class="mt-5 block text-center text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 hover:underline">
        ← Volver a iniciar sesión
      </RouterLink>
    </section>
  </main>
</template>
