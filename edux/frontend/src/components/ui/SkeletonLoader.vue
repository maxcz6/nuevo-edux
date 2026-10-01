<script setup lang="ts">
import SkeletonPixel from './SkeletonPixel.vue'

defineProps<{
  variant?: 'text' | 'title' | 'avatar' | 'card' | 'stat-card' | 'table' | 'button' | 'pixel-block'
  lines?: number
  width?: string
  height?: string
  rounded?: string
  animated?: boolean
}>()
</script>

<template>
  <SkeletonPixel v-bind="$props" />
</template>
