<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { gruposApi } from '@/api'
import type { GrupoCurso } from '@/types'
import LoadingState from '@/components/ui/LoadingState.vue'
import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseModal from '@/components/ui/BaseModal.vue'

const loading = ref(true)
const closing = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)

const grupos = ref<GrupoCurso[]>([])
const showConfirmModal = ref(false)
const grupoParaCerrar = ref<GrupoCurso | null>(null)

async function fetchGrupos() {
  loading.value = true
  error.value = null
  try {
    const res = await gruposApi.list()
    grupos.value = res.data.data
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cargar los grupos para actas'
  } finally {
    loading.value = false
  }
}

function solicitarCierre(grupo: GrupoCurso) {
  grupoParaCerrar.value = grupo
  showConfirmModal.value = true
}

async function confirmarCierre() {
  if (!grupoParaCerrar.value) return
  closing.value = true
  error.value = null
  try {
    await gruposApi.cerrarActa(grupoParaCerrar.value.id)
    successMessage.value = `Acta del grupo ${grupoParaCerrar.value.codigo_grupo || grupoParaCerrar.value.codigo} cerrada satisfactoriamente`
    showConfirmModal.value = false
    await fetchGrupos()
    setTimeout(() => { successMessage.value = null }, 5000)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cerrar el acta'
  } finally {
    closing.value = false
  }
}

onMounted(() => {
  fetchGrupos()
})
</script>

<template>
  <div class="space-y-6">
    <div class="border-b border-slate-200 dark:border-slate-700 pb-5">
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Bandeja de Actas y Calificaciones Finales</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        Supervisión de evaluación continua, validación de promedios ponderados y aprobación de cierre formal de actas.
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
              <th scope="col" class="px-6 py-3.5">Grupo</th>
              <th scope="col" class="px-6 py-3.5">Curso / UD</th>
              <th scope="col" class="px-6 py-3.5">Profesor</th>
              <th scope="col" class="px-6 py-3.5">Estado del Acta</th>
              <th scope="col" class="px-6 py-3.5 text-right">Acciones</th>
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
                <span v-if="grupo.docente" class="text-slate-800 dark:text-slate-200 font-medium">
                  {{ grupo.docente.usuario?.nombres }} {{ grupo.docente.usuario?.apellido_paterno }}
                </span>
                <span v-else class="text-xs text-slate-400">Sin Docente</span>
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                  :class="[
                    grupo.estado === 'CERRADO'
                      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                      : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                  ]"
                >
                  {{ grupo.estado === 'CERRADO' ? 'Acta Cerrada' : 'En Curso / Abierta' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right space-x-2">
                <button
                  v-if="grupo.estado !== 'CERRADO'"
                  type="button"
                  @click="solicitarCierre(grupo)"
                  class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition-colors"
                >
                  Cerrar y Aprobar Acta
                </button>
                <span v-else class="text-xs text-slate-400 italic">
                  Acta Oficializada
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Confirm Modal -->
    <BaseModal
      v-if="showConfirmModal"
      title="Confirmar Cierre Definitivo de Acta"
      @close="showConfirmModal = false"
    >
      <div class="space-y-4">
        <p class="text-sm text-slate-600 dark:text-slate-300">
          ¿Estás seguro de cerrar el acta de calificaciones para el curso
          <strong class="text-slate-900 dark:text-white">{{ grupoParaCerrar?.curso?.nombre }}</strong>?
        </p>
        <p class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 p-3 rounded-lg border border-amber-200 dark:border-amber-800">
          Esta acción oficializa los promedios finales y bloquea modificaciones posteriores de notas para este grupo.
        </p>
        <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
          <button
            type="button"
            @click="showConfirmModal = false"
            class="px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg"
          >
            Cancelar
          </button>
          <button
            type="button"
            @click="confirmarCierre"
            :disabled="closing"
            class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg disabled:opacity-50"
          >
            {{ closing ? 'Cerrando...' : 'Confirmar Cierre' }}
          </button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
