import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { UnidadAcademica } from '@/types'
import { programasApi } from '@/api'

export const useProgramaStore = defineStore('programa', () => {
  const programas = ref<UnidadAcademica[]>([])
  const selectedProgramaId = ref<number | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const selectedPrograma = computed(() => {
    if (!selectedProgramaId.value) return null
    return programas.value.find(p => p.id === selectedProgramaId.value) ?? null
  })

  async function fetchProgramas(force = false) {
    if (!force && programas.value.length > 0) return
    loading.value = true
    error.value = null
    try {
      const res = await programasApi.list({ tipo: 'PROGRAMA_ESTUDIOS', per_page: 50 })
      programas.value = res.data.data
    } catch {
      error.value = 'Error al cargar los programas de estudio.'
    } finally {
      loading.value = false
    }
  }

  function setPrograma(id: number | null) {
    selectedProgramaId.value = id
  }

  return {
    programas,
    selectedProgramaId,
    selectedPrograma,
    loading,
    error,
    fetchProgramas,
    setPrograma,
  }
})
