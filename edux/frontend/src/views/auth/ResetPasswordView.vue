<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { authApi } from '@/api/auth'

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
  if (password.value !== confirmation.value) { error.value = 'Las contraseñas no coinciden.'; return }
  loading.value = true; error.value = null
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
  } finally { loading.value = false }
}
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-gray-100 px-4"><section class="card w-full max-w-md">
    <h1 class="text-xl font-bold text-gray-900">Nueva contraseña</h1>
    <p v-if="message" class="mt-4 rounded bg-green-50 p-3 text-sm text-green-800">{{ message }} <RouterLink to="/login" class="font-bold underline">Ingresar</RouterLink></p>
    <form v-else class="mt-5 space-y-4" @submit.prevent="submit">
      <input v-model="email" type="email" required autocomplete="email" class="input-field" placeholder="Correo" />
      <input v-model="password" type="password" required minlength="8" autocomplete="new-password" class="input-field" placeholder="Nueva contraseña" />
      <input v-model="confirmation" type="password" required minlength="8" autocomplete="new-password" class="input-field" placeholder="Confirmar contraseña" />
      <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
      <button :disabled="loading" class="btn-primary w-full justify-center">{{ loading ? 'Guardando…' : 'Restablecer contraseña' }}</button>
    </form>
  </section></main>
</template>
