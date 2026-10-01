<script setup lang="ts">
defineProps<{ type: 'success' | 'error' | 'warning' | 'info'; message: string }>()
</script>
<template>
  <div :class="[
    'rounded-md p-4 text-sm border',
    type === 'success' && 'bg-green-50 text-green-800 border-green-200',
    type === 'error' && 'bg-red-50 text-red-800 border-red-200',
    type === 'warning' && 'bg-yellow-50 text-yellow-800 border-yellow-200',
    type === 'info' && 'bg-blue-50 text-blue-800 border-blue-200',
  ]">{{ message }}</div>
</template>
