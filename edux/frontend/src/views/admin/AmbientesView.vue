<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ambientesApi } from '@/api'

const loading = ref(true)
const error = ref<string | null>(null)
const successMsg = ref<string | null>(null)

const ambientes = ref<any[]>([])
const filterTipo = ref('')

const modalOpen = ref(false)
const saving = ref(false)
const form = ref({
  codigo: '',
  nombre: '',
  tipo: 'LABORATORIO',
  pabellon: 'Pabellón C',
  capacidad: 35,
  estado: 'ACTIVO',
})

onMounted(async () => {
  await loadAmbientes()
})

async function loadAmbientes() {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, any> = {}
    if (filterTipo.value) params.tipo = filterTipo.value
    const res = await ambientesApi.list(params)
    ambientes.value = res.data.data
  } catch {
    error.value = 'Error al cargar los ambientes y talleres.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  form.value = {
    codigo: '',
    nombre: '',
    tipo: 'LABORATORIO',
    pabellon: 'Pabellón C',
    capacidad: 35,
    estado: 'ACTIVO',
  }
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  error.value = null
  try {
    await ambientesApi.create(form.value)
    modalOpen.value = false
    successMsg.value = 'Ambiente registrado correctamente.'
    setTimeout(() => successMsg.value = null, 4000)
    await loadAmbientes()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al guardar ambiente.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
              Infraestructura y Talleres
            </span>
            <span class="text-xs text-gray-500 font-mono">Sede Central Palián</span>
          </div>
          <h1 class="text-2xl font-bold text-gray-900 mt-1">Ambientes, Talleres y Laboratorios</h1>
          <p class="text-sm text-gray-500 mt-0.5">
            Talleres de soldadura, laboratorios de cómputo y desarrollo web, laboratorios de farmacotecnia y parcelas agropecuarias.
          </p>
        </div>

        <button
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-lg shadow-sm transition-colors"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nuevo Ambiente
        </button>
      </div>
    </div>

    <!-- Alerts -->
    <div v-if="error" class="p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg text-sm text-red-700">
      {{ error }}
    </div>
    <div v-if="successMsg" class="p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg text-sm text-emerald-800">
      {{ successMsg }}
    </div>

    <!-- Filter toolbar -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-4">
      <select
        v-model="filterTipo"
        @change="loadAmbientes"
        class="text-sm border-gray-200 rounded-lg focus:ring-primary-500 focus:border-primary-500 py-2 px-3 border"
      >
        <option value="">Todos los Tipos de Espacio</option>
        <option value="LABORATORIO">Laboratorios de Especialidad</option>
        <option value="TALLER">Talleres de Mecánica y Producción</option>
        <option value="CAMPO">Campo Experimental Agropecuario</option>
        <option value="AULA">Aulas Teóricas y Auditorios</option>
      </select>
    </div>

    <!-- Cards Grid -->
    <div v-if="loading" class="p-12 text-center text-gray-500 bg-white rounded-xl">
      <svg class="w-8 h-8 animate-spin mx-auto text-primary-600 mb-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
      </svg>
      Cargando infraestructura...
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="a in ambientes"
        :key="a.id"
        class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-primary-300 transition-all"
      >
        <div class="flex items-start justify-between">
          <div>
            <span
              :class="[
                a.tipo === 'LABORATORIO' ? 'bg-blue-100 text-blue-800' :
                a.tipo === 'TALLER' ? 'bg-orange-100 text-orange-800' :
                a.tipo === 'CAMPO' ? 'bg-emerald-100 text-emerald-800' :
                'bg-purple-100 text-purple-800',
                'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold'
              ]"
            >
              {{ a.tipo }}
            </span>
            <h3 class="text-base font-bold text-gray-900 mt-2">{{ a.nombre }}</h3>
            <p class="text-xs font-mono text-gray-500">{{ a.codigo }} • {{ a.pabellon }}</p>
          </div>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
            {{ a.estado }}
          </span>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-600">
          <div>Capacidad Máxima: <span class="font-bold text-gray-900">{{ a.capacidad }} estudiantes</span></div>
          <div>Clases asignadas: <span class="font-bold text-primary-600">{{ a.horarios_count || 1 }}</span></div>
        </div>
      </div>
    </div>

    <!-- Modal Nuevo Ambiente -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" role="dialog" aria-modal="true">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
          <h3 class="text-lg font-bold text-gray-900">Registrar Espacio Formativo</h3>
          <button @click="modalOpen = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="handleSave" class="mt-4 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Código</label>
            <input v-model="form.codigo" required type="text" class="w-full border-gray-300 rounded-lg text-sm p-2 border" placeholder="LAB-WEB-02" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Nombre</label>
            <input v-model="form.nombre" required type="text" class="w-full border-gray-300 rounded-lg text-sm p-2 border" placeholder="Laboratorio de Servidores y Redes" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Tipo</label>
              <select v-model="form.tipo" required class="w-full border-gray-300 rounded-lg text-sm p-2 border">
                <option value="LABORATORIO">Laboratorio</option>
                <option value="TALLER">Taller</option>
                <option value="CAMPO">Campo Experimental</option>
                <option value="AULA">Aula Teórica</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Pabellón</label>
              <input v-model="form.pabellon" type="text" class="w-full border-gray-300 rounded-lg text-sm p-2 border" placeholder="Pabellón C" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Capacidad Estudiantes</label>
            <input v-model.number="form.capacidad" required type="number" min="1" class="w-full border-gray-300 rounded-lg text-sm p-2 border" />
          </div>

          <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
            <button type="button" @click="modalOpen = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
              Cancelar
            </button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-lg disabled:opacity-50">
              Guardar Ambiente
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
