<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
const authStore = useAuthStore()
const isSuperAdmin = authStore.hasRole('SUPERADMIN')
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Instituciones</h1>
      <p class="text-sm text-gray-500 mt-1">Gestión global de instituciones educativas registradas en Modux.</p>
    </div>

    <div v-if="!isSuperAdmin" class="p-6 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-medium" role="alert">
      Esta sección es exclusiva del Superadministrador del sistema.
    </div>

    <div v-else class="bg-white rounded-xl border border-gray-200 p-6 shadow-xs">
      <div class="flex items-center gap-3 text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm">
        <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>La gestión completa de instituciones se realiza a través de la API. Usa el panel de administración avanzado o contacta al equipo técnico de Modux para agregar, editar o eliminar instituciones.</span>
      </div>
    </div>
  </div>
</template>
