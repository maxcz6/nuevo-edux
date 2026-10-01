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
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
      role="dialog"
      aria-modal="true"
      :aria-label="`Cambiar contraseña de ${nombreCompleto}`"
      @keydown.esc="emit('close')"
    >
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-2xl max-w-md w-full p-6 space-y-5 text-slate-900 dark:text-slate-100 transition-colors">
        <!-- Header -->
        <div class="flex items-start justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h3 class="text-lg font-bold font-headline text-slate-900 dark:text-white">Cambiar Contraseña</h3>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
              Usuario: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ nombreCompleto }}</span>
            </p>
            <div class="flex flex-wrap gap-1 mt-1.5">
              <span
                v-for="rol in usuario.roles"
                :key="rol.id"
                class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700"
              >
                {{ rol.nombre }}
              </span>
            </div>
          </div>
          <button
            type="button"
            @click="emit('close')"
            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary-500"
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
          class="rounded-xl p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-xs sm:text-sm text-rose-800 dark:text-rose-200"
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
              'rounded-xl p-3 text-xs sm:text-sm font-medium',
              msg.type === 'success'
                ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/60'
                : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-900/60'
            ]"
          >
            {{ msg.text }}
          </div>

          <ProtectedInput
            id="pwd-nueva"
            v-model="form.password"
            type="password"
            label="Nueva Contraseña"
            :max-length="255"
            required
            :error="errors['password']"
            hint="Mínimo 8 caracteres."
            aria-label="Nueva contraseña"
          />

          <ProtectedInput
            id="pwd-confirm"
            v-model="form.password_confirmation"
            type="password"
            label="Confirmar Nueva Contraseña"
            :max-length="255"
            required
            :error="errors['password_confirmation']"
            aria-label="Confirmar nueva contraseña"
          />

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
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
