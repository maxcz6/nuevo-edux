<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'text' | 'title' | 'avatar' | 'card' | 'stat-card' | 'table' | 'button' | 'pixel-block'
    lines?: number
    width?: string
    height?: string
    rounded?: string
    animated?: boolean
  }>(),
  {
    variant: 'text',
    lines: 3,
    animated: true,
  }
)
</script>

<template>
  <div
    role="status"
    aria-busy="true"
    aria-label="Cargando contenido..."
    class="w-full select-none"
  >
    <!-- 1. Text Variant -->
    <div v-if="variant === 'text'" class="space-y-2.5">
      <div
        v-for="i in lines"
        :key="i"
        :class="[
          'h-3.5',
          animated ? 'skeleton-pixel' : 'bg-slate-200 dark:bg-slate-800',
          rounded || 'rounded-md',
          i === lines && lines > 1 ? 'w-4/6' : 'w-full'
        ]"
        :style="{ height: height || undefined }"
      />
    </div>

    <!-- 2. Title Variant -->
    <div v-else-if="variant === 'title'" class="space-y-2">
      <div
        :class="[
          'h-7 w-2/3',
          animated ? 'skeleton-pixel' : 'bg-slate-200 dark:bg-slate-800',
          rounded || 'rounded-lg'
        ]"
        :style="{ width: width || undefined, height: height || undefined }"
      />
      <div
        v-if="lines > 1"
        :class="[
          'h-4 w-1/2',
          animated ? 'skeleton-pixel' : 'bg-slate-200 dark:bg-slate-800',
          rounded || 'rounded-md'
        ]"
      />
    </div>

    <!-- 3. Avatar Variant -->
    <div
      v-else-if="variant === 'avatar'"
      :class="[
        'shrink-0',
        animated ? 'skeleton-pixel' : 'bg-slate-200 dark:bg-slate-800',
        rounded || 'rounded-full'
      ]"
      :style="{ width: width || '44px', height: height || '44px' }"
    />

    <!-- 4. Stat Card Variant (EducaCampus Dashboard style) -->
    <div
      v-else-if="variant === 'stat-card'"
      class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-3"
    >
      <div class="flex items-center justify-between">
        <div class="h-3 w-24 rounded-md skeleton-pixel" />
        <div class="h-9 w-9 rounded-xl skeleton-pixel" />
      </div>
      <div class="h-8 w-16 rounded-md skeleton-pixel" />
      <div class="h-2.5 w-32 rounded-md skeleton-pixel" />
    </div>

    <!-- 5. Card Variant -->
    <div
      v-else-if="variant === 'card'"
      class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 space-y-4"
    >
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl skeleton-pixel shrink-0" />
        <div class="space-y-1.5 flex-1">
          <div class="h-4 w-3/5 rounded-md skeleton-pixel" />
          <div class="h-3 w-2/5 rounded-md skeleton-pixel" />
        </div>
      </div>
      <div class="space-y-2 pt-2">
        <div class="h-3 w-full rounded-md skeleton-pixel" />
        <div class="h-3 w-5/6 rounded-md skeleton-pixel" />
        <div class="h-3 w-4/6 rounded-md skeleton-pixel" />
      </div>
    </div>

    <!-- 6. Table Variant -->
    <div
      v-else-if="variant === 'table'"
      class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden"
    >
      <!-- Header row -->
      <div class="h-12 bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200/80 dark:border-slate-800 flex items-center px-6 gap-4">
        <div class="h-4 w-1/4 rounded-md skeleton-pixel" />
        <div class="h-4 w-1/4 rounded-md skeleton-pixel" />
        <div class="h-4 w-1/6 rounded-md skeleton-pixel" />
        <div class="h-4 w-1/6 rounded-md skeleton-pixel ml-auto" />
      </div>
      <!-- Body rows -->
      <div
        v-for="r in lines"
        :key="r"
        class="h-14 border-b border-slate-100 dark:border-slate-800/60 flex items-center px-6 gap-4 last:border-0"
      >
        <div class="h-3.5 w-1/3 rounded-md skeleton-pixel" />
        <div class="h-3.5 w-1/4 rounded-md skeleton-pixel" />
        <div class="h-3.5 w-1/6 rounded-md skeleton-pixel" />
        <div class="h-3.5 w-16 rounded-md skeleton-pixel ml-auto" />
      </div>
    </div>

    <!-- 7. Button Variant -->
    <div
      v-else-if="variant === 'button'"
      :class="[
        'h-10 px-4 rounded-xl',
        animated ? 'skeleton-pixel' : 'bg-slate-200 dark:bg-slate-800'
      ]"
      :style="{ width: width || '120px' }"
    />

    <!-- 8. Generic Pixel Block -->
    <div
      v-else
      :class="[
        animated ? 'skeleton-pixel' : 'bg-slate-200 dark:bg-slate-800',
        rounded || 'rounded-xl'
      ]"
      :style="{ width: width || '100%', height: height || '100px' }"
    />

    <span class="sr-only">Cargando información…</span>
  </div>
</template>
