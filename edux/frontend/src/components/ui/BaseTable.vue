<script setup lang="ts" generic="T extends { id: number; estado?: string | boolean }">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import EmptyState from '@/components/ui/EmptyState.vue'

const props = withDefaults(defineProps<{
  columns: { key: string; label: string; class?: string }[]
  rows: T[]
  loading?: boolean
  meta?: { current_page: number; last_page: number; per_page: number; total: number } | null
  emptyText?: string
  showEdit?: boolean
  showDelete?: boolean
}>(), {
  showEdit: true,
  showDelete: true,
})

const emit = defineEmits<{
  (e: 'page', page: number): void
  (e: 'delete', item: T): void
  (e: 'deactivate', item: T): void
  (e: 'activate', item: T): void
  (e: 'edit', item: T): void
}>()

const authStore = useAuthStore()
const isSuperAdmin = computed(() => authStore.hasRole('SUPERADMIN'))
</script>
<template>
  <div class="overflow-hidden rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm transition-colors">
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary-600 dark:border-primary-400 border-t-transparent" />
    </div>
    <template v-else>
      <EmptyState v-if="rows.length === 0" title="Sin registros" :description="emptyText ?? 'Todavía no hay información para mostrar.'" class="m-4" />
      <div v-else class="overflow-x-auto custom-scrollbar">
        <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
          <thead class="bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-800">
            <tr>
              <th v-for="col in columns" :key="col.key"
                class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ col.label }}
              </th>
              <th class="relative px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><span class="sr-only">Acciones</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-200">
            <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/60 transition-colors">
              <slot :row="row" />
              <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium">
                <div class="table-actions">
                  <slot name="extra-actions" :row="row" />
                  <button v-if="showEdit" @click="emit('edit', row)" class="text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-200 font-medium hover:underline">Editar</button>
                  <template v-if="showDelete">
                    <button
                      v-if="row.estado === 'INACTIVO' || row.estado === false"
                      type="button"
                      @click="emit('activate', row)"
                      data-testid="activar-btn"
                      class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-300 hover:underline font-medium"
                    >
                      Activar
                    </button>
                    <button
                      v-else
                      type="button"
                      @click="emit('deactivate', row)"
                      data-testid="desactivar-btn"
                      class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300 hover:underline font-medium"
                    >
                      Desactivar
                    </button>
                    <button
                      v-if="isSuperAdmin"
                      type="button"
                      @click="emit('delete', row)"
                      data-testid="eliminar-btn"
                      class="text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300 hover:underline font-medium"
                    >
                      Eliminar
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="meta && meta.last_page > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 px-4 py-3">
        <p class="text-xs text-slate-500 dark:text-slate-400">Página <span class="font-semibold text-slate-700 dark:text-slate-200 font-mono">{{ meta.current_page }}</span> de <span class="font-semibold text-slate-700 dark:text-slate-200 font-mono">{{ meta.last_page }}</span> — <span class="font-semibold text-slate-700 dark:text-slate-200 font-mono">{{ meta.total }}</span> registros</p>
        <div class="flex flex-wrap gap-1">
          <button v-for="page in meta.last_page" :key="page" @click="emit('page', page)"
            :class="['rounded-lg px-2.5 py-1 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500', page === meta.current_page ? 'bg-primary-600 text-white shadow-xs' : 'border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700']">
            {{ page }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
