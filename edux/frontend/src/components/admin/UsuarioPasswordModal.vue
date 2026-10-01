<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { usuariosApi } from '@/api'
import ProtectedInput from '@/components/ui/ProtectedInput.vue'
import type { Usuario } from '@/types'

interface Props {
  usuario: Usuario
  open: boolean
}

const props = defineProps<Props>()
const emit  = defineEmits<{ (e: 'close'): void; (e: 'done'): void }>()

const authStore = useAuthStore()
const saving    = ref(false)
const msg       = ref<{ type: 'success' | 'error'; text: string } | null>(null)
const errors    = ref<Record<string, string>>({})

const form = ref({
  password:              '',
  password_confirmation: '',
})

// ADMINISTRADOR no puede cambiar password de SUPERADMIN
const canChange = computed(() => {
  if (authStore.hasRole('SUPERADMIN')) return true
  const targetIsSuperAdmin = props.usuario.roles?.some(r => r.nombre === 'SUPERADMIN')
  return !targetIsSuperAdmin
})

const nombreCompleto = computed(() =>
  `${props.usuario.nombres ?? ''} ${props.usuario.apellido_paterno ?? ''}`.trim()
)

async function handleSubmit() {
  saving.value = true
  msg.value    = null
  errors.value = {}
  try {
    await usuariosApi.cambiarPassword(props.usuario.id, form.value)
    msg.value = { type: 'success', text: `Contraseña de ${nombreCompleto.value} actualizada correctamente.` }
    form.value = { password: '', password_confirmation: '' }
    setTimeout(() => emit('done'), 1200)
  } catch (e: any) {
    if (e?.response?.status === 422) {
      const errs = e.response.data?.errors ?? {}
      errors.value = Object.fromEntries(
        Object.entries(errs).map(([k, v]) => [k, (v as string[])[0]])
      )
      msg.value = { type: 'error', text: e.response.data?.message ?? 'Revisa los campos.' }
    } else if (e?.response?.status === 403) {
      msg.value = { type: 'error', text: e.response.data?.message ?? 'No tiene permisos para esta acción.' }
    } else {
      msg.value = { type: 'error', text: 'Error inesperado al cambiar la contraseña.' }
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-xs"
      role="dialog"
      aria-modal="true"
      :aria-label="`Cambiar contraseña de ${nombreCompleto}`"
      @keydown.esc="emit('close')"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-5">
        <!-- Header -->
        <div class="flex items-start justify-between">
          <div>
            <h3 class="text-lg font-bold text-gray-900">Cambiar Contraseña</h3>
            <p class="text-sm text-gray-500 mt-0.5">
              Usuario: <span class="font-semibold text-gray-700">{{ nombreCompleto }}</span>
            </p>
            <div class="flex flex-wrap gap-1 mt-1">
              <span
                v-for="rol in usuario.roles"
                :key="rol.id"
                class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-700"
              >
                {{ rol.nombre }}
              </span>
            </div>
          </div>
          <button
            type="button"
            @click="emit('close')"
            class="p-1.5 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400"
            aria-label="Cerrar modal"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- No permitido si el target es SUPERADMIN y el actor es ADMIN -->
        <div
          v-if="!canChange"
          class="rounded-lg p-4 bg-red-50 border border-red-200 text-sm text-red-700"
          role="alert"
        >
          <strong>Acción no permitida.</strong> Un Administrador no puede cambiar la contraseña de un Superadministrador.
        </div>

        <form v-else @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Alert -->
          <div
            v-if="msg"
            role="alert"
            :class="[
              'rounded-lg p-3 text-sm font-medium',
              msg.type === 'success'
                ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                : 'bg-red-50 text-red-700 border border-red-200'
            ]"
          >
            {{ msg.text }}
          </div>

          <div>
            <label for="pwd-nueva" class="block text-xs font-semibold text-gray-700 mb-1">
              Nueva Contraseña *
            </label>
            <ProtectedInput
              id="pwd-nueva"
              v-model="form.password"
              type="password"
              :max-length="255"
              required
              :error="errors['password']"
              class="input-field w-full"
              aria-label="Nueva contraseña"
            />
            <p class="text-xs text-gray-400 mt-1">Mínimo 8 caracteres.</p>
          </div>

          <div>
            <label for="pwd-confirm" class="block text-xs font-semibold text-gray-700 mb-1">
              Confirmar Nueva Contraseña *
            </label>
            <ProtectedInput
              id="pwd-confirm"
              v-model="form.password_confirmation"
              type="password"
              :max-length="255"
              required
              :error="errors['password_confirmation']"
              class="input-field w-full"
              aria-label="Confirmar nueva contraseña"
            />
          </div>

          <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
            <button
              type="button"
              @click="emit('close')"
              class="btn-secondary"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="btn-primary"
            >
              {{ saving ? 'Actualizando...' : 'Cambiar Contraseña' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
