<script setup lang="ts">
import { computed, ref, useId } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: File | null
  accept?: string
  maxSizeMb?: number
  required?: boolean
  label?: string
  help?: string
}>(), {
  accept: '',
  maxSizeMb: 20,
  required: false,
  label: 'Archivo',
  help: 'Arrastra un archivo aquí o selecciónalo desde tu equipo.',
})

const emit = defineEmits<{
  'update:modelValue': [file: File | null]
}>()

const inputId = `file-${useId()}`
const input = ref<HTMLInputElement | null>(null)
const dragging = ref(false)
const error = ref('')

const formattedSize = computed(() => {
  if (!props.modelValue) return ''
  const bytes = props.modelValue.size
  return bytes < 1024 * 1024
    ? `${Math.max(1, Math.round(bytes / 1024))} KB`
    : `${(bytes / 1024 / 1024).toFixed(1)} MB`
})

function extensionAllowed(file: File) {
  if (!props.accept) return true
  const rules = props.accept.split(',').map((rule) => rule.trim().toLowerCase())
  const fileName = file.name.toLowerCase()
  return rules.some((rule) => {
    if (rule.startsWith('.')) return fileName.endsWith(rule)
    if (rule.endsWith('/*')) return file.type.startsWith(rule.slice(0, -1))
    return file.type.toLowerCase() === rule
  })
}

function selectFile(file?: File) {
  error.value = ''
  if (!file) return
  if (!extensionAllowed(file)) {
    error.value = 'Este tipo de archivo no está permitido.'
    return
  }
  if (file.size > props.maxSizeMb * 1024 * 1024) {
    error.value = `El archivo supera el límite de ${props.maxSizeMb} MB.`
    return
  }
  emit('update:modelValue', file)
}

function onInput(event: Event) {
  selectFile((event.target as HTMLInputElement).files?.[0])
}

function onDrop(event: DragEvent) {
  dragging.value = false
  selectFile(event.dataTransfer?.files?.[0])
}

function removeFile() {
  emit('update:modelValue', null)
  error.value = ''
  if (input.value) input.value.value = ''
}
</script>

<template>
  <div>
    <label :for="inputId" class="mb-1.5 block text-sm font-medium text-gray-700">
      {{ label }} <span v-if="required" class="text-red-600" aria-hidden="true">*</span>
    </label>

    <div
      class="file-dropzone"
      :class="{ 'file-dropzone--active': dragging, 'file-dropzone--selected': modelValue, 'file-dropzone--error': error }"
      @dragenter.prevent="dragging = true"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <input
        :id="inputId"
        ref="input"
        type="file"
        class="sr-only"
        :accept="accept"
        :required="required && !modelValue"
        @change="onInput"
      />

      <template v-if="modelValue">
        <div class="file-icon" aria-hidden="true">
          <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-width="2" stroke-linejoin="round" />
            <path d="M14 2v6h6" stroke-width="2" stroke-linejoin="round" />
          </svg>
        </div>
        <div class="min-w-0 flex-1 text-left">
          <p class="truncate text-sm font-semibold text-gray-900">{{ modelValue.name }}</p>
          <p class="text-xs text-gray-500">{{ formattedSize }} · Listo para subir</p>
        </div>
        <button type="button" class="file-remove" aria-label="Quitar archivo" @click="removeFile">Quitar</button>
      </template>

      <label v-else :for="inputId" class="flex w-full cursor-pointer flex-col items-center px-4 py-2 text-center">
        <svg class="mb-2 h-8 w-8 text-primary-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <path d="M12 16V4m0 0L7 9m5-5 5 5M5 20h14" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="text-sm font-semibold text-gray-900">Arrastra y suelta tu archivo</span>
        <span class="mt-1 text-xs text-gray-500">o <span class="font-semibold text-primary-600">explora tu equipo</span></span>
      </label>
    </div>

    <p v-if="error" class="mt-1.5 text-sm text-red-600" role="alert">{{ error }}</p>
    <p v-else class="mt-1.5 text-xs text-gray-500">{{ help }} Máximo {{ maxSizeMb }} MB.</p>
  </div>
</template>
