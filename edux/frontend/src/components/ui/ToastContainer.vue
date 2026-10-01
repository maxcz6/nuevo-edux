<script setup lang="ts">
import { useToast } from '@/composables/useToast'
const { toasts, removeToast } = useToast()

const getIcon = (type: string) => {
  switch (type) {
    case 'success': return 'M5 13l4 4L19 7'
    case 'error': return 'M6 18L18 6M6 6l12 12'
    case 'warning': return 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
    default: return 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
  }
}

const getColor = (type: string) => {
  switch (type) {
    case 'success': return 'bg-emerald-50 text-emerald-800 border-emerald-200'
    case 'error': return 'bg-rose-50 text-rose-800 border-rose-200'
    case 'warning': return 'bg-amber-50 text-amber-800 border-amber-200'
    default: return 'bg-blue-50 text-blue-800 border-blue-200'
  }
}
</script>
<template>
  <div aria-live="polite" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
    <TransitionGroup 
      enter-active-class="transition duration-300 ease-out" 
      enter-from-class="transform translate-y-2 opacity-0" 
      enter-to-class="transform translate-y-0 opacity-100" 
      leave-active-class="transition duration-200 ease-in" 
      leave-from-class="transform translate-y-0 opacity-100" 
      leave-to-class="transform translate-y-2 opacity-0">
      <div v-for="toast in toasts" :key="toast.id" 
           :class="['pointer-events-auto flex items-center gap-3 rounded-lg border p-4 shadow-lg min-w-[300px]', getColor(toast.type)]"
           role="alert">
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" :d="getIcon(toast.type)" />
        </svg>
        <p class="text-sm font-medium">{{ toast.message }}</p>
        <button @click="removeToast(toast.id)" class="ml-auto p-1 rounded-md hover:bg-black/5" aria-label="Cerrar notificación">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
