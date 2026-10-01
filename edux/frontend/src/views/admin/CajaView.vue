<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { cajaApi } from '@/api'
import type { Pago, CajaKpis, NuevoPagoPayload, EstudianteCajaBusqueda } from '@/types'
import BaseModal from '@/components/ui/BaseModal.vue'
import SkeletonTable from '@/components/ui/SkeletonTable.vue'

// ── Estado principal ─────────────────────────────────────────────────────────
const pagos = ref<Pago[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const kpis = ref<CajaKpis>({
  total_hoy: 0,
  total_general: 0,
  cantidad_pagos: 0,
  cantidad_anulados: 0,
  promedio_ticket: 0,
})

// ── Filtros ──────────────────────────────────────────────────────────────────
const filters = reactive({
  search: '',
  metodo_pago: '',
  estado: '',
})

// ── Modales ──────────────────────────────────────────────────────────────────
const showRegistroModal = ref(false)
const showDetalleModal = ref(false)
const showAnularModal = ref(false)

const pagoSeleccionado = ref<Pago | null>(null)
const motivoAnulacion = ref('')
const submitting = ref(false)
const formError = ref<string | null>(null)

// ── Formulario de nuevo pago ────────────────────────────────────────────────
const nuevoPago = reactive<NuevoPagoPayload>({
  estudiante_id: null,
  tipo_comprobante: 'RECIBO',
  concepto: 'Matrícula Semestre 2026-I',
  monto: 120.00,
  metodo_pago: 'EFECTIVO',
  numero_operacion: '',
  observaciones: '',
})

// ── Autocompletado de estudiantes ───────────────────────────────────────────
const searchEstudianteText = ref('')
const estudiantesSugeridos = ref<EstudianteCajaBusqueda[]>([])
const searchingEstudiantes = ref(false)
const estudianteSeleccionado = ref<EstudianteCajaBusqueda | null>(null)

let debounceTimer: ReturnType<typeof setTimeout> | null = null
function onSearchEstudianteInput() {
  if (debounceTimer) clearTimeout(debounceTimer)
  if (!searchEstudianteText.value.trim()) {
    estudiantesSugeridos.value = []
    return
  }
  debounceTimer = setTimeout(async () => {
    searchingEstudiantes.value = true
    try {
      const res = await cajaApi.buscarEstudiantes(searchEstudianteText.value)
      estudiantesSugeridos.value = res.data.data ?? []
    } catch {
      estudiantesSugeridos.value = []
    } finally {
      searchingEstudiantes.value = false
    }
  }, 300)
}

function seleccionarEstudiante(est: EstudianteCajaBusqueda) {
  estudianteSeleccionado.value = est
  nuevoPago.estudiante_id = est.id
  searchEstudianteText.value = `${est.nombre_completo} (${est.codigo_estudiante})`
  estudiantesSugeridos.value = []
}

function limpiarEstudianteSeleccionado() {
  estudianteSeleccionado.value = null
  nuevoPago.estudiante_id = null
  searchEstudianteText.value = ''
  estudiantesSugeridos.value = []
}

// ── Carga de datos ──────────────────────────────────────────────────────────
async function loadPagos(page = 1) {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, unknown> = {
      page,
      per_page: meta.value.per_page,
    }
    if (filters.search.trim()) params.search = filters.search.trim()
    if (filters.metodo_pago) params.metodo_pago = filters.metodo_pago
    if (filters.estado) params.estado = filters.estado

    const res = await cajaApi.listPagos(params)
    pagos.value = res.data.data ?? []
    kpis.value = res.data.kpis ?? {
      total_hoy: 0,
      total_general: 0,
      cantidad_pagos: 0,
      cantidad_anulados: 0,
      promedio_ticket: 0,
    }
    meta.value = res.data.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0 }
  } catch (err: unknown) {
    error.value = 'No se pudo cargar el listado de pagos de caja.'
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.search = ''
  filters.metodo_pago = ''
  filters.estado = ''
  loadPagos(1)
}

// ── Apertura de modal de registro ───────────────────────────────────────────
function openRegistroModal() {
  formError.value = null
  nuevoPago.estudiante_id = null
  nuevoPago.tipo_comprobante = 'RECIBO'
  nuevoPago.concepto = 'Matrícula Semestre 2026-I'
  nuevoPago.monto = 120.00
  nuevoPago.metodo_pago = 'EFECTIVO'
  nuevoPago.numero_operacion = ''
  nuevoPago.observaciones = ''
  limpiarEstudianteSeleccionado()
  showRegistroModal.value = true
}

// ── Guardar nuevo pago ──────────────────────────────────────────────────────
async function submitPago() {
  if (!nuevoPago.concepto.trim()) {
    formError.value = 'El concepto de cobro es obligatorio.'
    return
  }
  if (!nuevoPago.monto || nuevoPago.monto <= 0) {
    formError.value = 'El monto debe ser mayor a 0.'
    return
  }
  if (['TRANSFERENCIA', 'YAPE', 'PLIN', 'DEPOSITO_BANCARIO'].includes(nuevoPago.metodo_pago) && !nuevoPago.numero_operacion?.trim()) {
    formError.value = 'El número de operación es obligatorio para pagos digitales o bancarios.'
    return
  }

  formError.value = null
  submitting.value = true
  try {
    await cajaApi.createPago({
      ...nuevoPago,
      monto: Number(nuevoPago.monto),
    })
    showRegistroModal.value = false
    await loadPagos(1)
  } catch (err: any) {
    formError.value = err?.response?.data?.message || 'Error al registrar el cobro en caja.'
  } finally {
    submitting.value = false
  }
}

// ── Ver Detalle de Pago / Recibo ────────────────────────────────────────────
function openDetalleModal(pago: Pago) {
  pagoSeleccionado.value = pago
  showDetalleModal.value = true
}

function imprimirRecibo() {
  window.print()
}

// ── Anular Pago ─────────────────────────────────────────────────────────────
function openAnularModal(pago: Pago) {
  pagoSeleccionado.value = pago
  motivoAnulacion.value = ''
  showAnularModal.value = true
}

async function submitAnularPago() {
  if (!pagoSeleccionado.value) return
  if (!motivoAnulacion.value.trim() || motivoAnulacion.value.trim().length < 5) {
    alert('Debe ingresar un motivo de anulación de al menos 5 caracteres.')
    return
  }

  submitting.value = true
  try {
    await cajaApi.anularPago(pagoSeleccionado.value.id, {
      motivo_anulacion: motivoAnulacion.value.trim(),
    })
    showAnularModal.value = false
    await loadPagos(meta.value.current_page)
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Error al anular el pago.')
  } finally {
    submitting.value = false
  }
}

// ── Formateo ────────────────────────────────────────────────────────────────
function formatMonto(val: number | string | undefined): string {
  const num = Number(val || 0)
  return `S/ ${num.toFixed(2)}`
}

function formatFecha(fechaStr: string | null | undefined): string {
  if (!fechaStr) return '—'
  try {
    return new Date(fechaStr).toLocaleString('es-PE', {
      dateStyle: 'medium',
      timeStyle: 'short',
    })
  } catch {
    return fechaStr
  }
}

const requiresOperacion = computed(() => {
  return ['TRANSFERENCIA', 'YAPE', 'PLIN', 'DEPOSITO_BANCARIO'].includes(nuevoPago.metodo_pago)
})

onMounted(() => {
  loadPagos(1)
})
</script>

<template>
  <div class="space-y-6">
    <!-- ── Encabezado Principal ── -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200 pb-5">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Caja y Registro de Pagos</h1>
        <p class="text-sm text-gray-500 mt-1">Gestión operativa de cobros, recaudación diaria, comprobantes de pago y conciliación.</p>
      </div>
      <div>
        <button
          type="button"
          @click="openRegistroModal"
          class="btn-primary inline-flex items-center gap-2 shadow-sm"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Registrar Cobro
        </button>
      </div>
    </div>

    <!-- ── Tarjetas de Indicadores (KPIs) ── -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <!-- Total Hoy -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Recaudación Hoy</span>
          <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ formatMonto(kpis.total_hoy) }}</p>
        <span class="text-xs text-gray-500">Cobros efectuados el día de hoy</span>
      </div>

      <!-- Total Acumulado -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total General</span>
          <div class="rounded-lg bg-primary-50 p-2 text-primary-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ formatMonto(kpis.total_general) }}</p>
        <span class="text-xs text-gray-500">Histórico de cobros válidos</span>
      </div>

      <!-- Pagos Válidos -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Comprobantes Válidos</span>
          <div class="rounded-lg bg-blue-50 p-2 text-blue-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ kpis.cantidad_pagos }}</p>
        <span class="text-xs text-gray-500">Transacciones activas registradas</span>
      </div>

      <!-- Ticket Promedio -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Ticket Promedio</span>
          <div class="rounded-lg bg-amber-50 p-2 text-amber-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
            </svg>
          </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ formatMonto(kpis.promedio_ticket) }}</p>
        <span class="text-xs text-gray-500">Por operación institucional</span>
      </div>
    </div>

    <!-- ── Barra de Búsqueda y Filtros ── -->
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Búsqueda -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Buscar comprobante o estudiante</label>
          <div class="relative">
            <input
              type="text"
              v-model="filters.search"
              @keyup.enter="loadPagos(1)"
              placeholder="N° comprobante, DNI, nombre..."
              class="input-field text-sm pr-8"
            />
            <button
              v-if="filters.search"
              type="button"
              @click="filters.search = ''; loadPagos(1)"
              class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-600"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- Método de Pago -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Método de Pago</label>
          <select v-model="filters.metodo_pago" @change="loadPagos(1)" class="input-field text-sm">
            <option value="">Todos los métodos</option>
            <option value="EFECTIVO">Efectivo</option>
            <option value="YAPE">Yape</option>
            <option value="PLIN">Plin</option>
            <option value="TRANSFERENCIA">Transferencia Bancaria</option>
            <option value="TARJETA_DEBITO">Tarjeta Débito</option>
            <option value="TARJETA_CREDITO">Tarjeta Crédito</option>
            <option value="DEPOSITO_BANCARIO">Depósito en Cuenta</option>
          </select>
        </div>

        <!-- Estado -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Estado</label>
          <select v-model="filters.estado" @change="loadPagos(1)" class="input-field text-sm">
            <option value="">Todos los estados</option>
            <option value="PAGADO">Pagado</option>
            <option value="ANULADO">Anulado</option>
          </select>
        </div>

        <!-- Acciones de Filtro -->
        <div class="flex items-end gap-2">
          <button
            type="button"
            @click="loadPagos(1)"
            class="btn-primary flex-1 text-sm py-2"
          >
            Filtrar
          </button>
          <button
            type="button"
            @click="resetFilters"
            class="btn-secondary text-sm py-2 px-3"
            title="Limpiar filtros"
          >
            Limpiar
          </button>
        </div>
      </div>
    </div>

    <!-- ── Indicador de Carga / Error ── -->
    <SkeletonTable v-if="loading" :columns="6" :rows="6" />

    <div v-else-if="error" class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- ── Tabla de Pagos ── -->
    <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
          <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
            <tr>
              <th scope="col" class="px-5 py-3.5">Comprobante</th>
              <th scope="col" class="px-5 py-3.5">Estudiante / Pagador</th>
              <th scope="col" class="px-5 py-3.5">Concepto</th>
              <th scope="col" class="px-5 py-3.5">Monto</th>
              <th scope="col" class="px-5 py-3.5">Método</th>
              <th scope="col" class="px-5 py-3.5">Fecha y Hora</th>
              <th scope="col" class="px-5 py-3.5">Estado</th>
              <th scope="col" class="px-5 py-3.5 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-if="pagos.length === 0">
              <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                <div class="flex flex-col items-center justify-center">
                  <svg class="h-10 w-10 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <p class="font-medium text-gray-700">No hay pagos registrados</p>
                  <p class="text-xs text-gray-400 mt-1">Haga clic en "+ Registrar Cobro" para ingresar un nuevo comprobante.</p>
                </div>
              </td>
            </tr>
            <tr v-for="pago in pagos" :key="pago.id" class="hover:bg-gray-50 transition-colors">
              <!-- Comprobante -->
              <td class="px-5 py-3.5 font-mono text-xs font-bold text-gray-900">
                <div>{{ pago.numero_comprobante }}</div>
                <span class="inline-block text-[10px] font-semibold uppercase text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded mt-0.5">
                  {{ pago.tipo_comprobante }}
                </span>
              </td>

              <!-- Estudiante -->
              <td class="px-5 py-3.5">
                <div v-if="pago.estudiante">
                  <p class="font-semibold text-gray-900 leading-tight">{{ pago.estudiante.nombre_completo }}</p>
                  <p class="text-xs text-gray-500">{{ pago.estudiante.codigo_estudiante }} · DNI: {{ pago.estudiante.numero_documento || '—' }}</p>
                </div>
                <div v-else class="text-xs italic text-gray-400">Público General</div>
              </td>

              <!-- Concepto -->
              <td class="px-5 py-3.5 text-gray-800">
                <div class="font-medium line-clamp-1">{{ pago.concepto }}</div>
                <div v-if="pago.numero_operacion" class="text-xs text-gray-400 font-mono">
                  Op: {{ pago.numero_operacion }}
                </div>
              </td>

              <!-- Monto -->
              <td class="px-5 py-3.5 font-bold text-gray-900 whitespace-nowrap">
                {{ formatMonto(pago.monto) }}
              </td>

              <!-- Método -->
              <td class="px-5 py-3.5 whitespace-nowrap">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="{
                    'bg-emerald-100 text-emerald-800': pago.metodo_pago === 'EFECTIVO',
                    'bg-purple-100 text-purple-800': ['YAPE', 'PLIN'].includes(pago.metodo_pago),
                    'bg-blue-100 text-blue-800': ['TRANSFERENCIA', 'DEPOSITO_BANCARIO'].includes(pago.metodo_pago),
                    'bg-amber-100 text-amber-800': ['TARJETA_DEBITO', 'TARJETA_CREDITO'].includes(pago.metodo_pago),
                  }"
                >
                  {{ pago.metodo_pago.replace('_', ' ') }}
                </span>
              </td>

              <!-- Fecha -->
              <td class="px-5 py-3.5 text-xs text-gray-600 whitespace-nowrap">
                {{ formatFecha(pago.fecha_pago) }}
              </td>

              <!-- Estado -->
              <td class="px-5 py-3.5 whitespace-nowrap">
                <span
                  v-if="pago.estado === 'PAGADO'"
                  class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800"
                >
                  <svg class="h-3 w-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  Pagado
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800"
                >
                  <svg class="h-3 w-3 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  Anulado
                </span>
              </td>

              <!-- Acciones -->
              <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-2">
                <button
                  type="button"
                  @click="openDetalleModal(pago)"
                  class="btn-secondary text-xs py-1 px-2.5 inline-flex items-center gap-1"
                  title="Ver Recibo de Pago"
                >
                  <svg class="h-3.5 w-3.5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  Recibo
                </button>
                <button
                  v-if="pago.estado === 'PAGADO'"
                  type="button"
                  @click="openAnularModal(pago)"
                  class="text-xs text-red-600 hover:text-red-800 hover:underline px-1 py-1 font-medium"
                  title="Anular Comprobante"
                >
                  Anular
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div v-if="meta.total > 0" class="flex flex-col sm:flex-row items-center justify-between border-t border-gray-200 px-5 py-3 bg-gray-50 text-xs text-gray-500 gap-2">
        <div>
          Mostrando página <span class="font-bold text-gray-700">{{ meta.current_page }}</span> de <span class="font-bold text-gray-700">{{ meta.last_page }}</span> ({{ meta.total }} registros en total).
        </div>
        <div class="flex items-center space-x-2">
          <button
            type="button"
            @click="loadPagos(meta.current_page - 1)"
            :disabled="meta.current_page <= 1"
            class="btn-secondary text-xs py-1 px-3 disabled:opacity-40"
          >
            Anterior
          </button>
          <button
            type="button"
            @click="loadPagos(meta.current_page + 1)"
            :disabled="meta.current_page >= meta.last_page"
            class="btn-secondary text-xs py-1 px-3 disabled:opacity-40"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>

    <!-- ── Modal: Registrar Cobro en Caja ── -->
    <BaseModal :open="showRegistroModal" @close="showRegistroModal = false" title="Registrar Cobro en Caja" size="lg">
      <form @submit.prevent="submitPago" class="space-y-4">
        <div v-if="formError" class="rounded-md bg-red-50 p-3 text-sm text-red-700 border border-red-200">
          {{ formError }}
        </div>

        <!-- Buscador de Estudiante -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Estudiante / Pagador</label>
          <div class="relative">
            <input
              type="text"
              v-model="searchEstudianteText"
              @input="onSearchEstudianteInput"
              placeholder="Escriba apellido, nombre, DNI o código..."
              class="input-field text-sm"
              :class="{ 'bg-emerald-50 border-emerald-300 font-medium': estudianteSeleccionado }"
            />
            <button
              v-if="estudianteSeleccionado"
              type="button"
              @click="limpiarEstudianteSeleccionado"
              class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-red-600 hover:text-red-800 font-medium"
            >
              Cambiar
            </button>
            <div v-if="searchingEstudiantes" class="absolute inset-y-0 right-3 flex items-center">
              <div class="h-4 w-4 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
            </div>
          </div>

          <!-- Sugerencias de Autocompletado -->
          <ul
            v-if="estudiantesSugeridos.length > 0"
            class="absolute z-50 mt-1 max-h-52 w-full max-w-lg overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg divide-y divide-gray-100"
          >
            <li
              v-for="est in estudiantesSugeridos"
              :key="est.id"
              @click="seleccionarEstudiante(est)"
              class="px-4 py-2 hover:bg-primary-50 cursor-pointer text-sm"
            >
              <div class="font-medium text-gray-900">{{ est.nombre_completo }}</div>
              <div class="text-xs text-gray-500">Código: {{ est.codigo_estudiante }} · DNI: {{ est.numero_documento || '—' }} · {{ est.programa || '' }}</div>
            </li>
          </ul>
          <p class="text-xs text-gray-400 mt-1">Opcional. Si se deja en blanco se registrará como cobro a público general.</p>
        </div>

        <!-- Fila: Tipo Comprobante & Concepto -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Comprobante *</label>
            <select v-model="nuevoPago.tipo_comprobante" data-testid="pago-tipo-comprobante" class="input-field text-sm" required>
              <option value="RECIBO">Recibo Interno</option>
              <option value="BOLETA">Boleta de Venta</option>
              <option value="FACTURA">Factura</option>
              <option value="TICKET">Ticket de Cobro</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Concepto de Cobro *</label>
            <input
              type="text"
              v-model="nuevoPago.concepto"
              data-testid="pago-concepto"
              list="conceptos-frecuentes"
              placeholder="Ej. Matrícula Semestre 2026-I"
              class="input-field text-sm"
              required
            />
            <datalist id="conceptos-frecuentes">
              <option value="Matrícula Semestre 2026-I" />
              <option value="Constancia de Matrícula" />
              <option value="Certificado de Estudios Oficial" />
              <option value="Trámite de Titulación Profesional" />
              <option value="Examen de Subsanación / Recuperación" />
              <option value="Carnet de Estudiante" />
              <option value="Derecho de Admisión Tecnológica" />
              <option value="Duplicado de Documento Académico" />
            </datalist>
          </div>
        </div>

        <!-- Fila: Monto & Método de Pago -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Monto a Cobrar (S/.) *</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-bold text-gray-500">S/.</span>
              <input
                type="number"
                step="0.10"
                min="0.10"
                v-model="nuevoPago.monto"
                data-testid="pago-monto"
                class="input-field text-sm pl-10 font-bold"
                required
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Método de Pago *</label>
            <select v-model="nuevoPago.metodo_pago" data-testid="pago-metodo-select" class="input-field text-sm" required>
              <option value="EFECTIVO">Efectivo en Ventanilla</option>
              <option value="YAPE">Yape Móvil</option>
              <option value="PLIN">Plin Móvil</option>
              <option value="TRANSFERENCIA">Transferencia Bancaria</option>
              <option value="TARJETA_DEBITO">Tarjeta Débito (POS)</option>
              <option value="TARJETA_CREDITO">Tarjeta Crédito (POS)</option>
              <option value="DEPOSITO_BANCARIO">Depósito en Cuenta Corriente</option>
            </select>
          </div>
        </div>

        <!-- N° de Operación (Condicional) -->
        <div v-if="requiresOperacion">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Número de Operación / Código de Aprobación *</label>
          <input
            type="text"
            v-model="nuevoPago.numero_operacion"
            placeholder="Ej. 12948576"
            class="input-field text-sm font-mono"
            required
          />
          <p class="text-xs text-gray-500 mt-1">Ingrese el código de transacción emitido por la app móvil o banco.</p>
        </div>

        <!-- Observaciones -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Observaciones / Trazabilidad</label>
          <textarea
            v-model="nuevoPago.observaciones"
            rows="2"
            placeholder="Detalles adicionales opcionales..."
            class="input-field text-sm"
          ></textarea>
        </div>

        <!-- Botones de Acción -->
        <div class="flex justify-end gap-3 pt-3 border-t border-gray-200">
          <button
            type="button"
            @click="showRegistroModal = false"
            class="btn-secondary"
            :disabled="submitting"
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="btn-primary inline-flex items-center gap-2"
            :disabled="submitting"
          >
            <svg v-if="submitting" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            {{ submitting ? 'Guardando...' : 'Confirmar y Emitir Recibo' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <!-- ── Modal: Recibo Oficial Imprimible ── -->
    <BaseModal :open="showDetalleModal" @close="showDetalleModal = false" title="Comprobante de Caja" size="md">
      <div v-if="pagoSeleccionado" class="space-y-5" id="recibo-impresion">
        <!-- Cabecera Institucional del Recibo -->
        <div class="border-b-2 border-dashed border-gray-300 pb-4 text-center">
          <h2 class="text-base font-extrabold uppercase text-gray-900 tracking-wider">IESTP SAM</h2>
          <p class="text-xs text-gray-500">Módulo Oficial de Caja y Recaudación Institucional</p>
          <div class="mt-3 inline-block rounded-md bg-gray-100 px-3 py-1 font-mono text-sm font-bold text-gray-800">
            {{ pagoSeleccionado.tipo_comprobante }} N° {{ pagoSeleccionado.numero_comprobante }}
          </div>
        </div>

        <!-- Datos del Recibo -->
        <div class="space-y-2 text-sm text-gray-700">
          <div class="flex justify-between py-1 border-b border-gray-100">
            <span class="text-gray-500 text-xs font-semibold uppercase">Fecha y Hora:</span>
            <span class="font-medium">{{ formatFecha(pagoSeleccionado.fecha_pago) }}</span>
          </div>

          <div class="flex justify-between py-1 border-b border-gray-100">
            <span class="text-gray-500 text-xs font-semibold uppercase">Estado:</span>
            <span class="font-bold" :class="pagoSeleccionado.estado === 'PAGADO' ? 'text-emerald-700' : 'text-red-700'">
              {{ pagoSeleccionado.estado }}
            </span>
          </div>

          <div v-if="pagoSeleccionado.estudiante" class="py-1 border-b border-gray-100">
            <span class="text-gray-500 text-xs font-semibold uppercase block">Estudiante:</span>
            <span class="font-bold text-gray-900">{{ pagoSeleccionado.estudiante.nombre_completo }}</span>
            <div class="text-xs text-gray-500">Código: {{ pagoSeleccionado.estudiante.codigo_estudiante }} · DNI: {{ pagoSeleccionado.estudiante.numero_documento || '—' }}</div>
          </div>

          <div class="py-1 border-b border-gray-100">
            <span class="text-gray-500 text-xs font-semibold uppercase block">Concepto:</span>
            <span class="font-medium text-gray-900">{{ pagoSeleccionado.concepto }}</span>
          </div>

          <div class="flex justify-between py-1 border-b border-gray-100">
            <span class="text-gray-500 text-xs font-semibold uppercase">Método de Pago:</span>
            <span class="font-medium">{{ pagoSeleccionado.metodo_pago.replace('_', ' ') }}</span>
          </div>

          <div v-if="pagoSeleccionado.numero_operacion" class="flex justify-between py-1 border-b border-gray-100">
            <span class="text-gray-500 text-xs font-semibold uppercase">N° Operación:</span>
            <span class="font-mono">{{ pagoSeleccionado.numero_operacion }}</span>
          </div>

          <div v-if="pagoSeleccionado.observaciones" class="py-1 border-b border-gray-100 text-xs text-gray-600">
            <span class="font-semibold block text-gray-500 uppercase">Observación:</span>
            {{ pagoSeleccionado.observaciones }}
          </div>

          <div v-if="pagoSeleccionado.motivo_anulacion" class="py-1 border-b border-red-100 bg-red-50 p-2 rounded text-xs text-red-700">
            <span class="font-bold block uppercase">Motivo de Anulación:</span>
            {{ pagoSeleccionado.motivo_anulacion }}
          </div>

          <!-- Total Destacado -->
          <div class="flex justify-between items-center pt-3 text-base">
            <span class="font-bold text-gray-900 uppercase">Total Pagado:</span>
            <span class="text-xl font-extrabold text-gray-900">{{ formatMonto(pagoSeleccionado.monto) }}</span>
          </div>
        </div>

        <!-- Pie de Firma y Botones -->
        <div class="pt-4 border-t border-gray-200 flex justify-between items-center">
          <div class="text-[11px] text-gray-400">
            Atendido por: {{ pagoSeleccionado.registrado_por?.nombres || 'Caja Central' }}
          </div>
          <div class="flex gap-2">
            <button
              type="button"
              @click="imprimirRecibo"
              class="btn-primary text-xs py-1.5 px-3 inline-flex items-center gap-1.5"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              Imprimir
            </button>
            <button
              type="button"
              @click="showDetalleModal = false"
              class="btn-secondary text-xs py-1.5 px-3"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </BaseModal>

    <!-- ── Modal: Anular Comprobante ── -->
    <BaseModal :open="showAnularModal" @close="showAnularModal = false" title="Anular Comprobante de Cobro" size="sm">
      <div v-if="pagoSeleccionado" class="space-y-4">
        <p class="text-sm text-gray-600">
          ¿Está seguro de anular el comprobante
          <strong class="font-mono text-gray-900">{{ pagoSeleccionado.numero_comprobante }}</strong>
          por el monto de
          <strong class="text-gray-900">{{ formatMonto(pagoSeleccionado.monto) }}</strong>?
        </p>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Motivo de Anulación *</label>
          <textarea
            v-model="motivoAnulacion"
            rows="3"
            placeholder="Indique la justificación (ej. error en monto, anulación por solicitud del alumno)..."
            class="input-field text-sm"
            required
          ></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t border-gray-200">
          <button
            type="button"
            @click="showAnularModal = false"
            class="btn-secondary text-xs"
            :disabled="submitting"
          >
            Cancelar
          </button>
          <button
            type="button"
            @click="submitAnularPago"
            class="btn-primary bg-red-600 hover:bg-red-700 text-xs text-white"
            :disabled="submitting"
          >
            {{ submitting ? 'Anulando...' : 'Confirmar Anulación' }}
          </button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
