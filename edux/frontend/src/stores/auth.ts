import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Usuario, RoleName } from '@/types'
import { authApi } from '@/api/auth'
import { sanitizeInput, decodeTokenClaims, initCrossTabAuthSync } from '@/utils/tokenSecurity'

function normalizeRoleName(name: string): string {
  if (!name) return ''
  return name.trim().toUpperCase()
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<Usuario | null>(null)
  const token = ref<string | null>(localStorage.getItem('modux_token'))

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const userRoles = computed(() => user.value?.roles?.map(r => normalizeRoleName(r.nombre)) ?? [])
  
  // Helpers reactivos para los 5 roles esenciales
  const isSuperAdmin = computed(() => userRoles.value.includes('SUPERADMIN'))
  const isAdmin = computed(() => userRoles.value.includes('ADMINISTRADOR') || userRoles.value.includes('SUPERADMIN'))
  const isCoordinador = computed(() => userRoles.value.includes('COORDINADOR'))
  const isDocente = computed(() => userRoles.value.includes('DOCENTE'))
  const isEstudiante = computed(() => userRoles.value.includes('ESTUDIANTE'))

  // Programa de estudios / Unidad Académica del usuario
  const programaEstudioId = computed(() => user.value?.unidad_academica_id ?? null)
  const institucionId = computed(() => user.value?.institucion_id ?? null)

  const userPermisos = computed(() => {
    if (!user.value?.roles) return []
    return user.value.roles.flatMap(r => {
      if (!r.permisos) return []
      return (r.permisos as Array<string | { codigo?: string }>).map(p => {
        if (typeof p === 'string') return p
        return p?.codigo ?? ''
      }).filter((c): c is string => Boolean(c))
    })
  })

  const tokenClaims = computed(() => token.value ? decodeTokenClaims(token.value) : null)

  // Sincronización de cierre de sesión entre pestañas
  if (typeof window !== 'undefined') {
    initCrossTabAuthSync(() => {
      user.value = null
      token.value = null
    })
  }

  function hasRole(role: RoleName | string): boolean {
    return userRoles.value.includes(normalizeRoleName(role))
  }

  function hasPermission(permission: string): boolean {
    // Solo SUPERADMIN bypassa todos los permisos
    return userPermisos.value.includes(permission) || isSuperAdmin.value
  }

  async function login(email: string, password: string) {
    const cleanEmail = sanitizeInput(email, { removeNewlines: true, trim: true })
    const { data } = await authApi.login({ email: cleanEmail, password })
    user.value = data.data.user
    token.value = data.data.token
    localStorage.setItem('modux_token', data.data.token)
  }

  async function fetchMe() {
    const { data } = await authApi.me()
    user.value = data.data
  }

  function logout() {
    user.value = null
    token.value = null
    localStorage.removeItem('modux_token')
  }

  return {
    user,
    token,
    tokenClaims,
    isAuthenticated,
    userRoles,
    userPermisos,
    isSuperAdmin,
    isAdmin,
    isCoordinador,
    isDocente,
    isEstudiante,
    programaEstudioId,
    institucionId,
    hasRole,
    hasPermission,
    login,
    fetchMe,
    logout,
  }
})
