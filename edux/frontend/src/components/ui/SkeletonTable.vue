<script setup lang="ts">
import SkeletonBase from './SkeletonBase.vue'

const props = withDefaults(
  defineProps<{
    columns?: number | { key: string; label?: string; width?: string }[]
    rows?: number
    showHeader?: boolean
    showActions?: boolean
    borderless?: boolean
  }>(),
  {
    columns: 5,
    rows: 5,
    showHeader: true,
    showActions: true,
    borderless: false,
  }
)

const columnCount = typeof props.columns === 'number' ? props.columns : props.columns.length

// Genera anchos variables y realistas para celdas de texto
const textWidths = ['w-3/4', 'w-1/2', 'w-4/5', 'w-2/3', 'w-11/12', 'w-3/5']
function getCellWidth(row: number, col: number) {
  return textWidths[(row * 3 + col) % textWidths.length]
}
</script>

<template>
  <div
    :class="borderless ? 'bg-white dark:bg-slate-900' : 'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900'"
    role="status"
    aria-label="Cargando tabla de datos..."
  >
    <div class="overflow-x-auto custom-scrollbar">
      <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
        <!-- Encabezados de columna -->
        <thead v-if="showHeader" class="border-b border-slate-100 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-800/50">
          <tr>
            <th
              v-for="c in columnCount"
              :key="`th-${c}`"
              class="px-4 py-3.5 text-left"
            >
              <SkeletonBase
                variant="rect"
                height="12px"
                :width="`${45 + ((c * 17) % 35)}%`"
                rounded="rounded"
              />
            </th>
            <th v-if="showActions" class="px-4 py-3.5 text-right">
              <div class="flex justify-end">
                <SkeletonBase variant="rect" height="12px" width="50px" rounded="rounded" />
              </div>
            </th>
          </tr>
        </thead>

        <!-- Filas de datos esqueleto -->
        <tbody class="divide-y divide-slate-100 bg-white text-sm dark:divide-slate-800 dark:bg-slate-900">
          <tr
            v-for="r in rows"
            :key="`tr-${r}`"
            class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
          >
            <!-- Primera celda suele tener avatar o identificador destacado -->
            <td class="whitespace-nowrap px-4 py-3.5">
              <div class="flex items-center gap-3">
                <SkeletonBase
                  v-if="r % 2 === 1"
                  variant="circle"
                  height="28px"
                  width="28px"
                />
                <div class="flex-1 space-y-1.5">
                  <SkeletonBase
                    variant="text"
                    height="14px"
                    :className="getCellWidth(r, 0)"
                  />
                  <SkeletonBase
                    v-if="r % 2 === 0"
                    variant="text"
                    height="10px"
                    width="40%"
                  />
                </div>
              </div>
            </td>

            <!-- Celdas intermedias -->
            <td
              v-for="c in (columnCount - 1)"
              :key="`td-${r}-${c}`"
              class="whitespace-nowrap px-4 py-3.5"
            >
              <!-- Celda tipo badge de estado aleatoria -->
              <div v-if="c === (columnCount - 1)" class="flex items-center">
                <SkeletonBase
                  variant="badge"
                  height="20px"
                  width="68px"
                  rounded="rounded-full"
                />
              </div>
              <div v-else class="space-y-1">
                <SkeletonBase
                  variant="text"
                  height="13px"
                  :className="getCellWidth(r, c)"
                />
              </div>
            </td>

            <!-- Celda de acciones a la derecha -->
            <td v-if="showActions" class="whitespace-nowrap px-4 py-3.5 text-right">
              <div class="flex items-center justify-end gap-2">
                <SkeletonBase variant="button" height="24px" width="48px" rounded="rounded-lg" />
                <SkeletonBase variant="button" height="24px" width="48px" rounded="rounded-lg" />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación Esqueleto -->
    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-800/30">
      <SkeletonBase variant="text" height="12px" width="160px" rounded="rounded" />
      <div class="flex items-center gap-1.5">
        <SkeletonBase variant="rect" height="28px" width="28px" rounded="rounded-lg" />
        <SkeletonBase variant="rect" height="28px" width="28px" rounded="rounded-lg" />
        <SkeletonBase variant="rect" height="28px" width="28px" rounded="rounded-lg" />
      </div>
    </div>
  </div>
</template>
