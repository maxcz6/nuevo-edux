<script setup lang="ts">
import { computed } from 'vue'
import { RouterView } from 'vue-router'
import { useRoute } from 'vue-router'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

const route = useRoute()
const showPublicThemeToggle = computed(() => !route.matched.some((record) => record.meta.requiresAuth))
</script>

<template>
  <ThemeToggle v-if="showPublicThemeToggle" class="fixed right-4 top-4 z-50 sm:right-6 sm:top-6" />
  <RouterView />
</template>
