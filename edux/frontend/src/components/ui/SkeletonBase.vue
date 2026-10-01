<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    variant?: 'text' | 'rect' | 'circle' | 'badge' | 'avatar' | 'button' | 'card'
    width?: string
    height?: string
    rounded?: string
    animated?: boolean
    className?: string
  }>(),
  {
    variant: 'rect',
    width: undefined,
    height: undefined,
    rounded: undefined,
    animated: true,
    className: '',
  }
)

const resolvedClasses = computed(() => {
  const classes: string[] = ['skeleton-base']

  if (props.animated) {
    classes.push('skeleton-shimmer')
  } else {
    classes.push('bg-slate-200 dark:bg-slate-800')
  }

  // Variant default geometry
  switch (props.variant) {
    case 'text':
      classes.push(props.rounded ?? 'rounded-md')
      if (!props.height) classes.push('h-4')
      if (!props.width) classes.push('w-full')
      break
    case 'circle':
    case 'avatar':
      classes.push(props.rounded ?? 'rounded-full shrink-0')
      if (!props.width && !props.height) {
        classes.push(props.variant === 'avatar' ? 'h-10 w-10' : 'h-8 w-8')
      }
      break
    case 'badge':
      classes.push(props.rounded ?? 'rounded-full')
      if (!props.height) classes.push('h-5')
      if (!props.width) classes.push('w-16')
      break
    case 'button':
      classes.push(props.rounded ?? 'rounded-xl')
      if (!props.height) classes.push('h-9')
      if (!props.width) classes.push('w-24')
      break
    case 'card':
      classes.push(props.rounded ?? 'rounded-2xl')
      if (!props.height) classes.push('h-36')
      if (!props.width) classes.push('w-full')
      break
    case 'rect':
    default:
      classes.push(props.rounded ?? 'rounded-lg')
      if (!props.height) classes.push('h-6')
      if (!props.width) classes.push('w-full')
      break
  }

  if (props.className) {
    classes.push(props.className)
  }

  return classes.join(' ')
})

const resolvedStyles = computed(() => {
  const styles: Record<string, string> = {}
  if (props.width) styles.width = props.width
  if (props.height) styles.height = props.height
  return styles
})
</script>

<template>
  <div
    :class="resolvedClasses"
    :style="resolvedStyles"
    aria-hidden="true"
    role="presentation"
  />
</template>
