<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import http from '@/api/http'
import ProtectedInput from '@/components/ui/ProtectedInput.vue'
import FileDropzone from '@/components/ui/FileDropzone.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import type { Usuario } from '@/types'

const authStore = useAuthStore()

// ── Datos del perfil ─────────────────────────────────────────────────────────
const loadingPerfil = ref(false)
const savingPerfil  = ref(false)
const perfilMsg     = ref<{ type: 'success' | 'error'; text: string } | null>(null)
const fotoArchivo   = ref<File | null>(null)
const savingFoto    = ref(false)
const fotoMsg       = ref<{ type: 'success' | 'error'; text: string } | null>(null)

const perfilForm = ref({
  nombres:          '',
  apellido_paterno: '',
  apellido_materno: '',
  telefono:         '',
})

// ── Cambio de contraseña ─────────────────────────────────────────────────────
const savingPwd   = ref(false)
const pwdMsg      = ref<{ type: 'success' | 'error'; text: string } | null>(null)
const showPwdForm = ref(false)

const pwdForm = ref({
  password_actual:          '',
  password:                 '',
  password_confirmation:    '',
})
const pwdErrors = ref<Record<string, string>>({})

// ── Computed ─────────────────────────────────────────────────────────────────
const rolLabel = computed(() => {
  const roles = authStore.user?.roles?.map(r => r.nombre) ?? []
  const map: Record<string, string> = {
    SUPERADMIN:    'Superadministrador del Sistema',
    ADMINISTRADOR: 'Administrador Institucional',
    COORDINADOR:   'Coordinador de Programa',
    SECRETARIA:    'Secretaría Académica',
    CAJA:          'Caja y Cobranzas',
    MESA_DE_PARTES:'Mesa de Partes',
    DOCENTE:       'Docente',
    ESTUDIANTE:    'Estudiante',
    APODERADO:     'Apoderado / Padre de Familia',
  }
  return roles.map(r => map[r] ?? r).join(', ') || 'Usuario'
})

// ── Cargar perfil ─────────────────────────────────────────────────────────────
async function loadPerfil() {
  loadingPerfil.value = true
  try {
    const res = await http.get('/me/perfil')
    const u = res.data.data
    perfilForm.value = {
      nombres:          u.nombres          ?? '',
      apellido_paterno: u.apellido_paterno ?? '',
      apellido_materno: u.apellido_materno ?? '',
      telefono:         u.telefono         ?? '',
    }
  } catch {
    perfilMsg.value = { type: 'error', text: 'No se pudo cargar el perfil.' }
  } finally {
    loadingPerfil.value = false
  }
}

async function uploadFoto() {
  if (!fotoArchivo.value) return

  savingFoto.value = true
  fotoMsg.value = null
  const formData = new FormData()
  formData.append('foto', fotoArchivo.value)

  try {
    const res = await http.post<{ data: Usuario; message: string }>('/me/foto', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    authStore.user = res.data.data
    fotoArchivo.value = null
    fotoMsg.value = { type: 'success', text: res.data.message }
  } catch (error: unknown) {
    const response = (error as { response?: { data?: { message?: string; errors?: { foto?: string[] } } } }).response
    fotoMsg.value = {
      type: 'error',
      text: response?.data?.errors?.foto?.[0] ?? response?.data?.message ?? 'No se pudo actualizar la foto.',
    }
  } finally {
    savingFoto.value = false
  }
}

async function removeFoto() {
  savingFoto.value = true
  fotoMsg.value = null

  try {
    const res = await http.delete<{ data: Usuario; message: string }>('/me/foto')
    authStore.user = res.data.data
    fotoArchivo.value = null
    fotoMsg.value = { type: 'success', text: res.data.message }
  } catch {
    fotoMsg.value = { type: 'error', text: 'No se pudo eliminar la foto.' }
  } finally {
    savingFoto.value = false
  }
}

// ── Guardar perfil ────────────────────────────────────────────────────────────
async function savePerfil() {
  savingPerfil.value = true
  perfilMsg.value    = null
  try {
    const res = await http.patch('/me/perfil', perfilForm.value)
    authStore.user = res.data.data
    perfilMsg.value = { type: 'success', text: 'Perfil actualizado correctamente.' }
  } catch (e: any) {
    perfilMsg.value = { type: 'error', text: e?.response?.data?.message ?? 'Error al guardar el perfil.' }
  } finally {
    savingPerfil.value = false
  }
}

// ── Cambiar contraseña ────────────────────────────────────────────────────────
async function cambiarPassword() {
  savingPwd.value = true
  pwdMsg.value    = null
  pwdErrors.value = {}
  try {
    await http.post('/me/cambiar-password', pwdForm.value)
    pwdMsg.value = { type: 'success', text: 'Contraseña cambiada correctamente.' }
    pwdForm.value = { password_actual: '', password: '', password_confirmation: '' }
    showPwdForm.value = false
  } catch (e: any) {
    if (e?.response?.status === 422) {
      const errs = e.response.data?.errors ?? {}
      pwdErrors.value = Object.fromEntries(
        Object.entries(errs).map(([k, v]) => [k, (v as string[])[0]])
      )
      pwdMsg.value = { type: 'error', text: e.response.data?.message ?? 'Verifica los campos.' }
    } else {
      pwdMsg.value = { type: 'error', text: 'Error al cambiar la contraseña.' }
    }
  } finally {
    savingPwd.value = false
  }
}

onMounted(() => loadPerfil())
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-8">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Mi Perfil</h1>
      <p class="text-sm text-gray-500 mt-1">Gestiona tu información personal y contraseña de acceso.</p>
    </div>

    <!-- Avatar + rol -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-xs">
      <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
        <UserAvatar :user="authStore.user" size="xl" />
        <div class="min-w-0 flex-1">
        <div class="text-lg font-bold text-gray-900">
          {{ authStore.user?.nombres }} {{ authStore.user?.apellido_paterno }} {{ authStore.user?.apellido_materno }}
        </div>
        <div class="text-sm text-gray-500">{{ authStore.user?.email }}</div>
        <span data-testid="role-badge" class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-800">
          {{ rolLabel }}
        </span>
        </div>
      </div>

      <div class="mt-6 border-t border-gray-200 pt-5">
        <h2 class="text-sm font-semibold text-gray-900">Foto de perfil</h2>
        <p class="mb-4 mt-1 text-xs text-gray-500">
          Si no agregas una foto, mostraremos automáticamente las iniciales de tu primer nombre y apellido.
        </p>

        <div
          v-if="fotoMsg"
          role="alert"
          :class="[
            'mb-4 rounded-lg border p-3 text-sm font-medium',
            fotoMsg.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-700'
          ]"
        >
          {{ fotoMsg.text }}
        </div>

        <FileDropzone
          v-model="fotoArchivo"
          label="Nueva foto"
          accept=".jpg,.jpeg,.png,.webp"
          help="JPG, PNG o WebP. Recomendado: imagen cuadrada."
          :max-size-mb="5"
        />

        <div class="mt-4 flex flex-wrap justify-end gap-3">
          <button
            v-if="authStore.user?.tiene_foto"
            type="button"
            class="btn-secondary text-red-600"
            :disabled="savingFoto"
            @click="removeFoto"
          >
            Usar iniciales
          </button>
          <button
            type="button"
            class="btn-primary"
            :disabled="!fotoArchivo || savingFoto"
            @click="uploadFoto"
          >
            {{ savingFoto ? 'Guardando…' : 'Guardar foto' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Datos personales -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-xs">
      <h2 class="text-base font-semibold text-gray-900 mb-4">Datos Personales</h2>

      <div v-if="loadingPerfil" class="flex items-center justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <form v-else @submit.prevent="savePerfil" class="space-y-4">
        <!-- Alert -->
        <div
          v-if="perfilMsg"
          role="alert"
          :class="[
            'rounded-lg p-3 text-sm font-medium',
            perfilMsg.type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'
          ]"
        >
          {{ perfilMsg.text }}
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="nombres" class="block text-xs font-semibold text-gray-700 mb-1">Nombres *</label>
            <ProtectedInput
              id="nombres"
              v-model="perfilForm.nombres"
              type="text"
              :max-length="120"
              required
              class="input-field w-full"
              aria-label="Nombres"
            />
          </div>
          <div>
            <label for="apellido_paterno" class="block text-xs font-semibold text-gray-700 mb-1">Apellido Paterno *</label>
            <ProtectedInput
              id="apellido_paterno"
              v-model="perfilForm.apellido_paterno"
              type="text"
              :max-length="80"
              required
              class="input-field w-full"
              aria-label="Apellido paterno"
            />
          </div>
          <div>
            <label for="apellido_materno" class="block text-xs font-semibold text-gray-700 mb-1">Apellido Materno</label>
            <ProtectedInput
              id="apellido_materno"
              v-model="perfilForm.apellido_materno"
              type="text"
              :max-length="80"
              class="input-field w-full"
              aria-label="Apellido materno"
            />
          </div>
          <div>
            <label for="telefono" class="block text-xs font-semibold text-gray-700 mb-1">Teléfono</label>
            <ProtectedInput
              id="telefono"
              v-model="perfilForm.telefono"
              type="tel"
              :max-length="30"
              class="input-field w-full"
              aria-label="Teléfono de contacto"
            />
          </div>
        </div>

        <!-- Email (solo lectura) -->
        <div>
          <label class="block text-xs font-semibold text-gray-700 mb-1">Correo Electrónico</label>
          <input
            type="email"
            :value="authStore.user?.email"
            readonly
            class="input-field w-full bg-gray-50 text-gray-500 cursor-not-allowed"
            aria-label="Correo electrónico (no editable)"
          />
          <p class="text-xs text-gray-400 mt-1">El correo solo puede cambiarse contactando al administrador.</p>
        </div>

        <div class="flex justify-end pt-2">
          <button
            type="submit"
            :disabled="savingPerfil"
            class="btn-primary"
          >
            {{ savingPerfil ? 'Guardando...' : 'Guardar Cambios' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Seguridad / Contraseña -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-xs">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-base font-semibold text-gray-900">Seguridad</h2>
          <p class="text-xs text-gray-500 mt-0.5">Cambia tu contraseña de acceso al sistema.</p>
        </div>
        <button
          type="button"
          @click="showPwdForm = !showPwdForm"
          class="text-sm font-medium text-primary-600 hover:text-primary-800 focus:outline-none focus:underline"
        >
          {{ showPwdForm ? 'Cancelar' : 'Cambiar contraseña' }}
        </button>
      </div>

      <!-- Alert mensaje contraseña -->
      <div
        v-if="pwdMsg"
        role="alert"
        :class="[
          'rounded-lg p-3 text-sm font-medium mb-4',
          pwdMsg.type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'
        ]"
      >
        {{ pwdMsg.text }}
      </div>

      <form
        v-if="showPwdForm"
        @submit.prevent="cambiarPassword"
        class="space-y-4"
      >
        <div>
          <label for="password_actual" class="block text-xs font-semibold text-gray-700 mb-1">Contraseña Actual *</label>
          <ProtectedInput
            id="password_actual"
            v-model="pwdForm.password_actual"
            type="password"
            :max-length="255"
            required
            :error="pwdErrors['password_actual']"
            class="input-field w-full"
            aria-label="Contraseña actual"
          />
        </div>
        <div>
          <label for="password_nueva" class="block text-xs font-semibold text-gray-700 mb-1">Nueva Contraseña *</label>
          <ProtectedInput
            id="password_nueva"
            v-model="pwdForm.password"
            type="password"
            :max-length="255"
            required
            :error="pwdErrors['password']"
            class="input-field w-full"
            aria-label="Nueva contraseña (mínimo 8 caracteres)"
          />
          <p class="text-xs text-gray-400 mt-1">Mínimo 8 caracteres.</p>
        </div>
        <div>
          <label for="password_confirm" class="block text-xs font-semibold text-gray-700 mb-1">Confirmar Nueva Contraseña *</label>
          <ProtectedInput
            id="password_confirm"
            v-model="pwdForm.password_confirmation"
            type="password"
            :max-length="255"
            required
            :error="pwdErrors['password_confirmation']"
            class="input-field w-full"
            aria-label="Confirmar nueva contraseña"
          />
        </div>

        <div class="flex justify-end">
          <button
            type="submit"
            :disabled="savingPwd"
            class="btn-primary"
          >
            {{ savingPwd ? 'Cambiando...' : 'Confirmar Cambio' }}
          </button>
        </div>
      </form>

      <div v-else class="flex items-center gap-3 py-3 px-4 bg-gray-50 rounded-lg border border-gray-100">
        <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        <span class="text-sm text-gray-500">Tu contraseña está protegida. Haz clic en "Cambiar contraseña" para actualizarla.</span>
      </div>
    </div>
  </div>
</template>
