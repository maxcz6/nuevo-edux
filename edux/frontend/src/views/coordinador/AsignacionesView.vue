<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { gruposApi, docentesApi } from '@/api'
import type { GrupoCurso, Docente } from '@/types'
import LoadingState from '@/components/ui/LoadingState.vue'
import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseModal from '@/components/ui/BaseModal.vue'

const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)

const grupos = ref<GrupoCurso[]>([])
const docentes = ref<Docente[]>([])

const showModal = ref(false)
const grupoSeleccionado = ref<GrupoCurso | null>(null)
const nuevoDocenteId = ref<number | ''>('')

async function fetchData() {
  loading.value = true
  error.value = null
  try {
    const [resGrupos, resDocentes] = await Promise.all([
      gruposApi.list(),
      docentesApi.list(),
    ])
    grupos.value = resGrupos.data.data
    docentes.value = resDocentes.data.data
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cargar las asignaciones'
  } finally {
    loading.value = false
  }
}

function abrirModalAsignacion(grupo: GrupoCurso) {
  grupoSeleccionado.value = grupo
  nuevoDocenteId.value = grupo.docente_id || ''
  showModal.value = true
}

async function guardarAsignacion() {
  if (!grupoSeleccionado.value || !nuevoDocenteId.value) return
  saving.value = true
  error.value = null
  try {
    await gruposApi.update(grupoSeleccionado.value.id, {
      ...grupoSeleccionado.value,
      docente_id: nuevoDocenteId.value,
    })
    successMessage.value = 'Docente asignado correctamente al grupo'
    showModal.value = false
    await fetchData()
    setTimeout(() => { successMessage.value = null }, 4000)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'No se pudo actualizar la asignación del docente'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="border-b border-slate-200 dark:border-slate-700 pb-5">
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Asignación de Docentes a Cursos</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        Distribución de carga académica y asignación de profesores a las unidades didácticas y grupos vigentes.
      </p>
    </div>

    <AlertMessage v-if="successMessage" type="success" :message="successMessage" />
    <AlertMessage v-if="error" type="error" :message="error" />
    <LoadingState v-if="loading" />

    <div v-else class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
          <thead class="bg-slate-50 dark:bg-slate-700/50 text-xs uppercase font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
            <tr>
              <th scope="col" class="px-6 py-3.5">Código Grupo</th>
              <th scope="col" class="px-6 py-3.5">Curso / Unidad Didáctica</th>
              <th scope="col" class="px-6 py-3.5">Turno / Aula</th>
              <th scope="col" class="px-6 py-3.5">Docente Asignado</th>
              <th scope="col" class="px-6 py-3.5 text-right">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
            <tr v-for="grupo in grupos" :key="grupo.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30">
              <td class="px-6 py-4 font-mono font-medium text-slate-800 dark:text-slate-200">
                {{ grupo.codigo_grupo || grupo.codigo }}
              </td>
              <td class="px-6 py-4">
                <span class="font-semibold text-slate-900 dark:text-white block">{{ grupo.curso?.nombre }}</span>
                <span class="text-xs text-slate-400 font-mono">{{ grupo.curso?.codigo }}</span>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                  {{ grupo.turno || 'Sin turno' }}
                </span>
                <span v-if="grupo.aula" class="ml-2 text-xs text-slate-500">{{ grupo.aula }}</span>
              </td>
              <td class="px-6 py-4">
                <div v-if="grupo.docente" class="flex items-center gap-2">
                  <span class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center font-bold text-xs">
                    {{ grupo.docente.usuario?.nombres?.charAt(0) || 'D' }}
                  </span>
                  <div>
                    <span class="font-medium text-slate-800 dark:text-slate-200 block">
                      {{ grupo.docente.usuario?.nombres }} {{ grupo.docente.usuario?.apellido_paterno }}
                    </span>
                    <span class="text-xs text-slate-400">{{ grupo.docente.especialidad || 'Docente' }}</span>
                  </div>
                </div>
                <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400">
                  Sin Asignar
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <button
                  type="button"
                  @click="abrirModalAsignacion(grupo)"
                  class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 dark:hover:bg-indigo-900/80 transition-colors"
                >
                  Asignar / Cambiar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Asignación -->
    <BaseModal
      v-if="showModal"
      title="Asignar Profesor al Grupo"
      @close="showModal = false"
    >
      <div class="space-y-4">
        <div>
          <p class="text-xs text-slate-400 uppercase font-semibold">Curso</p>
          <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ grupoSeleccionado?.curso?.nombre }}</p>
        </div>
        <div>
          <label for="select-docente" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            Seleccionar Docente
          </label>
          <select
            id="select-docente"
            v-model="nuevoDocenteId"
            class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white p-2.5 text-sm focus:ring-2 focus:ring-indigo-500"
          >
            <option value="" disabled>Seleccione un docente</option>
            <option v-for="d in docentes" :key="d.id" :value="d.id">
              {{ d.usuario?.nombres }} {{ d.usuario?.apellido_paterno }} — {{ d.especialidad || 'Especialidad N/D' }}
            </option>
          </select>
        </div>
        <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
          <button
            type="button"
            @click="showModal = false"
            class="px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg"
          >
            Cancelar
          </button>
          <button
            type="button"
            @click="guardarAsignacion"
            :disabled="saving || !nuevoDocenteId"
            class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50"
          >
            {{ saving ? 'Guardando...' : 'Confirmar Asignación' }}
          </button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
