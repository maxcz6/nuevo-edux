<script setup lang="ts">
import { ref, computed, useAttrs } from 'vue'
import { sanitizeInput } from '@/utils/tokenSecurity'

defineOptions({
  inheritAttrs: false
})

const attrs = useAttrs()
const filteredAttrs = computed(() => {
  const { class: _c, style: _s, 'data-testid': _dt, ...rest } = attrs
  return rest
})

const props = withDefaults(
  defineProps<{
    modelValue: string | number
    id?: string
    name?: string
    type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'search' | 'url'
    label?: string
    placeholder?: string
    required?: boolean
    disabled?: boolean
    error?: string | null
    hint?: string
    maxLength?: number
    autocomplete?: string
    dataTestid?: string
    allowNewlines?: boolean
  }>(),
  {
    type: 'text',
    required: false,
    disabled: false,
    error: null,
    allowNewlines: false,
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'blur', event: FocusEvent): void
  (e: 'focus', event: FocusEvent): void
}>()

const showPassword = ref(false)
const inputId = computed(() => props.id || `protected-input-${Math.random().toString(36).substring(2, 9)}`)
const effectiveTestId = computed(() => props.dataTestid || (attrs['data-testid'] as string) || props.id || undefined)
const effectiveType = computed(() => {
  if (props.type === 'password') {
    return showPassword.value ? 'text' : 'password'
  }
  return props.type
})

function handleInput(event: Event) {
  const target = event.target as HTMLInputElement
  const sanitized = sanitizeInput(target.value, {
    trim: false,
    removeNewlines: !props.allowNewlines,
    maxLength: props.maxLength
  })
  emit('update:modelValue', sanitized)
}

function handleBlur(event: FocusEvent) {
  const target = event.target as HTMLInputElement
  const sanitized = sanitizeInput(target.value, {
    trim: props.type !== 'password',
    removeNewlines: !props.allowNewlines,
    maxLength: props.maxLength
  })
  emit('update:modelValue', sanitized)
  emit('blur', event)
}
</script>

<template>
  <div class="space-y-1" :class="attrs.class" :style="(attrs.style as any)">
    <div class="flex justify-between items-center" v-if="label">
      <label :for="inputId" class="block text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-300">
        {{ label }}
        <span v-if="required" class="text-rose-500 font-bold" aria-hidden="true">*</span>
      </label>
      <span v-if="maxLength && String(modelValue).length > maxLength * 0.8" class="text-xs text-slate-400 font-mono">
        {{ String(modelValue).length }}/{{ maxLength }}
      </span>
    </div>

    <div class="relative rounded-lg shadow-xs">
      <input
        :id="inputId"
        :name="name"
        :type="effectiveType"
        :value="modelValue"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :maxlength="maxLength"
        :autocomplete="autocomplete"
        :data-testid="effectiveTestId"
        :aria-invalid="!!error"
        :aria-describedby="error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined"
        v-bind="filteredAttrs"
        @input="handleInput"
        @blur="handleBlur"
        @focus="$emit('focus', $event)"
        :class="[
          'block w-full rounded-xl text-sm transition-colors focus:outline-none focus:ring-2 disabled:bg-slate-100 dark:disabled:bg-slate-800/80 disabled:text-slate-400 dark:disabled:text-slate-500 disabled:cursor-not-allowed',
          type === 'password' ? 'pr-10' : 'px-3.5',
          'py-2.5',
          error
            ? 'border-rose-300 dark:border-rose-800 text-rose-900 dark:text-rose-100 placeholder-rose-300 dark:placeholder-rose-500/70 focus:border-rose-500 focus:ring-rose-500 bg-rose-50/20 dark:bg-rose-950/30'
            : 'border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-primary-500 focus:ring-primary-500 bg-white dark:bg-slate-800'
        ]"
      />

      <!-- Password toggle visibility button -->
      <button
        v-if="type === 'password'"
        type="button"
        @click="showPassword = !showPassword"
        :aria-label="showPassword ? 'Ocultar contraseña' : 'Ver contraseña'"
        tabindex="-1"
        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 focus:outline-none focus:text-primary-600 dark:focus:text-primary-400"
      >
        <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
        </svg>
      </button>
    </div>

    <!-- Error Message -->
    <p v-if="error" :id="`${inputId}-error`" role="alert" class="text-xs font-medium text-rose-600 dark:text-rose-400 mt-1 flex items-center gap-1">
      <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
      </svg>
      <span>{{ error }}</span>
    </p>

    <!-- Hint / Info -->
    <p v-else-if="hint" :id="`${inputId}-hint`" class="text-xs text-slate-500 dark:text-slate-400 mt-1">
      {{ hint }}
    </p>
  </div>
</template>
