<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps<{ open: boolean; title: string; size?: 'sm' | 'md' | 'lg' }>()
const emit = defineEmits<{ (e: 'close'): void }>()
const modalRef = ref<HTMLElement | null>(null)
let previousActiveElement: HTMLElement | null = null

function handleKeydown(e: KeyboardEvent) {
  if (!props.open) return
  if (e.key === 'Escape') {
    emit('close')
  } else if (e.key === 'Tab') {
    // Focus trap
    if (!modalRef.value) return
    const focusableElements = modalRef.value.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
    const firstElement = focusableElements[0] as HTMLElement
    const lastElement = focusableElements[focusableElements.length - 1] as HTMLElement

    if (e.shiftKey) {
      if (document.activeElement === firstElement) {
        lastElement.focus()
        e.preventDefault()
      }
    } else {
      if (document.activeElement === lastElement) {
        firstElement.focus()
        e.preventDefault()
      }
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
  if (props.open) {
    previousActiveElement = document.activeElement as HTMLElement
    nextTick(() => {
      if (modalRef.value) {
        const firstElement = modalRef.value.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])') as HTMLElement
        if (firstElement) firstElement.focus()
      }
    })
  }
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  if (previousActiveElement) {
    previousActiveElement.focus()
  }
})
</script>
<template>
  <Teleport to="body">
    <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title" ref="modalRef">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity" @click="emit('close')" />
        <div :class="['relative z-10 flex max-h-[calc(100dvh-2rem)] w-full flex-col overflow-hidden rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl ring-1 ring-black/5 dark:ring-white/5', size === 'lg' ? 'max-w-3xl' : size === 'sm' ? 'max-w-sm' : 'max-w-lg']">
          <div class="flex shrink-0 items-center justify-between border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/60 px-4 py-3.5 sm:px-6 sm:py-4">
            <h2 id="modal-title" class="text-base font-semibold text-slate-900 dark:text-white tracking-tight">{{ title }}</h2>
            <button
              type="button"
              @click="emit('close')"
              aria-label="Cerrar modal"
              class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="overflow-y-auto px-4 py-4 sm:px-6 sm:py-5 custom-scrollbar text-slate-800 dark:text-slate-200"><slot /></div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
