<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { notificacionesApi } from '@/api'
import type { Notificacion } from '@/types'

const isOpen = ref(false)
const notificaciones = ref<Notificacion[]>([])
const loading = ref(false)
const unreadCount = ref(0)
const selectedNotification = ref<Notificacion | null>(null)
const showDetail = ref(false)
let pollTimer: ReturnType<typeof setInterval> | null = null

async function fetchNotificaciones() {
  try {
    const res = await notificacionesApi.list({ per_page: 15 })
    notificaciones.value = res.data.data
    unreadCount.value = notificaciones.value.filter(n => !n.leido).length
  } catch (err) {
    console.error('Error al cargar notificaciones', err)
  }
}

async function toggleDropdown() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    await fetchNotificaciones()
  }
}

async function handleNotificationClick(n: Notificacion) {
  selectedNotification.value = n
  showDetail.value = true
  isOpen.value = false
  if (!n.leido) {
    try {
      await notificacionesApi.marcarLeida(n.id)
      n.leido = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch (err) {
      console.error('Error al marcar leída', err)
    }
  }
}

function closeDetail() {
  showDetail.value = false
  selectedNotification.value = null
}

async function handleMarkAllAsRead() {
  loading.value = true
  try {
    await notificacionesApi.marcarTodas()
    notificaciones.value.forEach(n => { n.leido = true })
    unreadCount.value = 0
  } catch (err) {
    console.error('Error al marcar todas leídas', err)
  } finally {
    loading.value = false
  }
}

function formatDate(isoStr?: string) {
  if (!isoStr) return ''
  const d = new Date(isoStr)
  return d.toLocaleDateString('es-PE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function formatFullDate(isoStr?: string) {
  if (!isoStr) return ''
  const d = new Date(isoStr)
  return d.toLocaleDateString('es-PE', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    timeZoneName: 'short',
  })
}

function getIconColor(tipo?: string) {
  if (tipo?.includes('APROBADA') || tipo?.includes('MATRICULA')) return 'text-emerald-500 bg-emerald-50 dark:bg-emerald-950/40'
  if (tipo?.includes('RECHAZADA')) return 'text-rose-500 bg-rose-50 dark:bg-rose-950/40'
  if (tipo?.includes('SOLICITUD')) return 'text-amber-500 bg-amber-50 dark:bg-amber-950/40'
  return 'text-blue-500 bg-blue-50 dark:bg-blue-950/40'
}

function getIcon(tipo?: string) {
  if (tipo?.includes('APROBADA') || tipo?.includes('MATRICULA')) return '✓'
  if (tipo?.includes('RECHAZADA')) return '✕'
  if (tipo?.includes('SOLICITUD')) return '→'
  return 'ℹ'
}

function getTipoLabel(tipo?: string) {
  if (!tipo) return 'General'
  if (tipo.includes('APROBADA')) return 'Aprobada'
  if (tipo.includes('RECHAZADA')) return 'Rechazada'
  if (tipo.includes('SOLICITUD')) return 'Solicitud'
  if (tipo.includes('MATRICULA')) return 'Matrícula'
  return tipo
}

onMounted(() => {
  fetchNotificaciones()
  pollTimer = setInterval(fetchNotificaciones, 30000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
  <div class="relative">
    <button
      @click="toggleDropdown"
      class="relative p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:text-slate-300 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500"
      aria-label="Abrir notificaciones"
      :aria-expanded="isOpen"
    >
      <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      <span
        v-if="unreadCount > 0"
        class="absolute -top-0.5 -right-0.5 h-4 w-4 rounded-full bg-rose-500 text-[10px] font-bold text-white flex items-center justify-center animate-pulse"
      >
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <div
      v-if="isOpen"
      @click="isOpen = false"
      class="fixed inset-0 z-40"
      aria-hidden="true"
    />

    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl z-50 overflow-hidden transition-all"
    >
      <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/40">
        <div class="flex items-center gap-2">
          <span class="font-bold text-sm text-slate-800 dark:text-slate-100 font-headline">Notificaciones</span>
          <span v-if="unreadCount > 0" class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
            {{ unreadCount }} nuevas
          </span>
        </div>
        <button
          v-if="unreadCount > 0"
          @click="handleMarkAllAsRead"
          :disabled="loading"
          class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium"
        >
          Marcar todas
        </button>
      </div>

      <div class="max-h-[380px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60">
        <div v-if="notificaciones.length === 0" class="py-10 text-center text-slate-400 text-xs">
          <svg class="h-8 w-8 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
          </svg>
          No tienes notificaciones por el momento
        </div>

        <div
          v-for="n in notificaciones"
          :key="n.id"
          @click="handleNotificationClick(n)"
          class="p-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors cursor-pointer flex gap-3 text-left relative"
          :class="{ 'bg-primary-50/20 dark:bg-primary-950/20': !n.leido }"
        >
          <div
            :class="getIconColor(n.tipo)"
            class="h-8 w-8 rounded-xl shrink-0 flex items-center justify-center text-sm font-bold border border-current/10"
          >
            {{ getIcon(n.tipo) }}
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-1 mb-0.5">
              <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate" :class="{ 'font-bold': !n.leido }">
                {{ n.titulo }}
              </h4>
              <span class="text-[10px] text-slate-400 shrink-0 whitespace-nowrap">
                {{ formatDate(n.created_at) }}
              </span>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-300 leading-snug line-clamp-2">
              {{ n.mensaje }}
            </p>
          </div>

          <span v-if="!n.leido" class="h-2 w-2 rounded-full bg-primary-500 shrink-0 self-center"></span>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
      <div
        v-if="showDetail && selectedNotification"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @click.self="closeDetail"
      >
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeDetail" />
        <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div
                :class="getIconColor(selectedNotification.tipo)"
                class="h-10 w-10 rounded-xl flex items-center justify-center text-lg font-bold border border-current/10"
              >
                {{ getIcon(selectedNotification.tipo) }}
              </div>
              <div>
                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                  {{ getTipoLabel(selectedNotification.tipo) }}
                </span>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 font-headline">
                  Notificación #{{ selectedNotification.id }}
                </h3>
              </div>
            </div>
            <button
              @click="closeDetail"
              class="p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              aria-label="Cerrar detalle"
            >
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 block">
                Título
              </label>
              <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                {{ selectedNotification.titulo }}
              </p>
            </div>

            <div v-if="selectedNotification.mensaje">
              <label class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 block">
                Mensaje
              </label>
              <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">
                {{ selectedNotification.mensaje }}
              </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 block">
                  Tipo
                </label>
                <span
                  :class="getIconColor(selectedNotification.tipo)"
                  class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border border-current/10"
                >
                  {{ getTipoLabel(selectedNotification.tipo) }}
                </span>
              </div>
              <div>
                <label class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 block">
                  Estado
                </label>
                <span
                  class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                  :class="selectedNotification.leido
                    ? 'text-slate-500 bg-slate-100 dark:bg-slate-800 dark:text-slate-400'
                    : 'text-primary-600 bg-primary-50 dark:bg-primary-950/40 dark:text-primary-300'"
                >
                  {{ selectedNotification.leido ? 'Leída' : 'No leída' }}
                </span>
              </div>
            </div>

            <div v-if="selectedNotification.referencia_tipo">
              <label class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 block">
                Referencia
              </label>
              <p class="text-sm text-slate-600 dark:text-slate-300">
                {{ selectedNotification.referencia_tipo }}
                <span v-if="selectedNotification.referencia_id" class="text-slate-400">
                  #{{ selectedNotification.referencia_id }}
                </span>
              </p>
            </div>

            <div>
              <label class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 block">
                Fecha y Hora
              </label>
              <p class="text-sm text-slate-600 dark:text-slate-300 capitalize">
                {{ formatFullDate(selectedNotification.created_at) }}
              </p>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 flex justify-end">
            <button
              @click="closeDetail"
              class="px-4 py-2 text-sm font-semibold rounded-lg bg-primary-600 hover:bg-primary-700 text-white transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>