<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import http from '@/api/http'
import type { Usuario } from '@/types'

const props = withDefaults(defineProps<{
  user: Usuario | null
  size?: 'sm' | 'md' | 'lg' | 'xl'
}>(), {
  size: 'md',
})

const photoUrl = ref<string | null>(null)
const imageFailed = ref(false)

const initials = computed(() => {
  const nombre = props.user?.nombres?.trim().split(/\s+/)[0] ?? ''
  const apellido = props.user?.apellido_paterno?.trim().split(/\s+/)[0] ?? ''
  return `${nombre.charAt(0)}${apellido.charAt(0)}`.toUpperCase() || 'US'
})

const fullName = computed(() => {
  if (!props.user) return 'Usuario'
  return `${props.user.nombres} ${props.user.apellido_paterno}`.trim()
})

const sizeClass = computed(() => ({
  sm: 'h-8 w-8 text-xs',
  md: 'h-10 w-10 text-sm',
  lg: 'h-16 w-16 text-xl',
  xl: 'h-24 w-24 text-3xl',
})[props.size])

function releasePhoto() {
  if (photoUrl.value) URL.revokeObjectURL(photoUrl.value)
  photoUrl.value = null
}

async function loadPhoto() {
  releasePhoto()
  imageFailed.value = false
  if (!props.user?.tiene_foto) return

  try {
    const response = await http.get<Blob>('/me/foto', { responseType: 'blob' })
    photoUrl.value = URL.createObjectURL(response.data)
  } catch {
    imageFailed.value = true
  }
}

watch(() => props.user, loadPhoto, { immediate: true })
onBeforeUnmount(releasePhoto)
</script>

<template>
  <div
    class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-primary-300 bg-primary-100 font-black text-primary-700 shadow-sm"
    :class="sizeClass"
    :aria-label="`Foto de perfil de ${fullName}`"
    role="img"
  >
    <img
      v-if="photoUrl && !imageFailed"
      :src="photoUrl"
      :alt="`Foto de perfil de ${fullName}`"
      class="h-full w-full object-cover"
      @error="imageFailed = true"
    />
    <span v-else aria-hidden="true">{{ initials }}</span>
  </div>
</template>
