<script setup lang="ts">
import SkeletonBase from './SkeletonBase.vue'

withDefaults(
  defineProps<{
    items?: number
    hasAvatar?: boolean
  }>(),
  {
    items: 4,
    hasAvatar: true,
  }
)
</script>

<template>
  <div
    class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900"
    role="status"
    aria-label="Cargando lista..."
  >
    <div
      v-for="i in items"
      :key="`list-skel-${i}`"
      class="flex items-center justify-between p-4 transition-colors"
    >
      <div class="flex items-center gap-3.5 flex-1 min-w-0 mr-4">
        <SkeletonBase
          v-if="hasAvatar"
          variant="circle"
          height="40px"
          width="40px"
        />
        <div class="space-y-1.5 flex-1 min-w-0">
          <SkeletonBase
            variant="text"
            height="15px"
            :width="`${45 + ((i * 19) % 40)}%`"
            rounded="rounded"
          />
          <SkeletonBase
            variant="text"
            height="12px"
            :width="`${30 + ((i * 13) % 30)}%`"
            rounded="rounded"
          />
        </div>
      </div>
      <div class="flex items-center gap-3 shrink-0">
        <SkeletonBase variant="badge" height="22px" width="75px" rounded="rounded-full" />
        <SkeletonBase variant="button" height="32px" width="80px" rounded="rounded-xl" />
      </div>
    </div>
  </div>
</template>
