<script setup lang="ts">
import { ref } from 'vue'
import { authApi } from '@/api/auth'

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
  <main class="flex min-h-screen items-center justify-center bg-gray-100 px-4">
    <section class="card w-full max-w-md">
      <h1 class="text-xl font-bold text-gray-900">Recuperar contraseña</h1>
      <p class="mt-2 text-sm text-gray-600">Te enviaremos un enlace seguro a tu correo institucional.</p>
      <p v-if="message" class="mt-4 rounded bg-green-50 p-3 text-sm text-green-800">{{ message }}</p>
      <p v-if="error" class="mt-4 rounded bg-red-50 p-3 text-sm text-red-800">{{ error }}</p>
      <form v-if="!message" class="mt-5 space-y-4" @submit.prevent="submit">
        <input v-model="email" type="email" required autocomplete="email" class="input-field" placeholder="usuario@institucion.edu" />
        <button :disabled="loading" class="btn-primary w-full justify-center">{{ loading ? 'Enviando…' : 'Enviar enlace' }}</button>
      </form>
      <RouterLink to="/login" class="mt-5 block text-center text-sm text-primary-700">Volver a iniciar sesión</RouterLink>
    </section>
  </main>
</template>
