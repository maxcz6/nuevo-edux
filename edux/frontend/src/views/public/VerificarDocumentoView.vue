<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

interface TituladoInfo {
  apellidos_nombres: string
  documento_tipo: string
  documento_numero: string | null
}

interface DatosRegistro {
  numero_titulo?: string
  numero_registro?: string
  numero_folio?: string
  numero_libro?: string
  resolucion_directoral?: string
  modalidad_titulacion?: string
  fecha_sustentacion?: string
  fecha_emision?: string
  codigo_minedu?: string
  codigo_verificacion?: string
}

interface DatosModulo {
  numero_modulo?: number
  nombre_modulo?: string
  resolucion_directoral?: string
  creditos?: number
  horas_teoria?: number
  horas_practica?: number
  horas_efsrt?: number
  fecha_emision?: string
  codigo_verificacion?: string
}

interface DocResult {
  tipo_documento: 'TITULO_PROFESIONAL_TECNICO' | 'CERTIFICADO_MODULAR'
  valido: boolean
  estado: string
  institucion: string
  codigo_modular_institucion: string | null
  programa_estudio: string
  titulado?: TituladoInfo
  estudiante?: TituladoInfo
  datos_registro?: DatosRegistro
  datos_modulo?: DatosModulo
  mensaje: string
}

const route = useRoute()
const searchCode = ref('')
const loading = ref(false)
const doc = ref<DocResult | null>(null)
const notFoundMessage = ref<string | null>(null)

const API_BASE = import.meta.env.VITE_API_URL || '/api/v1'

onMounted(() => {
  const codeParam = route.params.codigo as string
  if (codeParam) {
    searchCode.value = codeParam
    verifyCode(codeParam)
  }
})

async function onSearch() {
  if (!searchCode.value.trim()) return
  await verifyCode(searchCode.value.trim())
}

async function verifyCode(code: string) {
  loading.value = true
  doc.value = null
  notFoundMessage.value = null

  try {
    const res = await axios.get(`${API_BASE}/public/verificar-documento/${encodeURIComponent(code)}`)
    doc.value = res.data
  } catch (err: any) {
    notFoundMessage.value = err.response?.data?.mensaje || 'No se encontró ningún documento con este código de verificación.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-between text-gray-800">
    <!-- Header público -->
    <header class="bg-white border-b border-gray-200 shadow-xs">
      <div class="max-w-5xl mx-auto px-4 py-4 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-primary-700 text-white font-black text-xl flex items-center justify-center shadow-xs">
            M
          </div>
          <div>
            <h1 class="text-base font-bold text-gray-900 leading-tight">Portal de Consulta Pública de Documentos</h1>
            <p class="text-xs text-gray-500">IESTP "Santiago Antúnez de Mayolo" — Huancayo</p>
          </div>
        </div>

        <router-link to="/login" class="text-xs font-semibold text-primary-600 hover:text-primary-800 flex items-center gap-1">
          <span>Ingreso al Sistema</span>
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
          </svg>
        </router-link>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 py-8 space-y-6">
      <!-- Search Card -->
      <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-xs">
        <h2 class="text-lg font-bold text-gray-900 mb-1">Verificación de Autenticidad Institucional</h2>
        <p class="text-sm text-gray-500 mb-4">
          Ingrese el código único alfanumérico impreso en el Título Profesional Técnico o Certificado Modular emitido conforme a la Ley N° 30512.
        </p>

        <form @submit.prevent="onSearch" class="flex flex-col sm:flex-row gap-3">
          <input
            v-model="searchCode"
            type="text"
            placeholder="Ej: TIT-SAM-2026-AGRO-042 o MOD-SAM-..."
            required
            class="flex-1 px-4 py-3 rounded-xl border border-gray-300 text-base font-mono uppercase focus:ring-2 focus:ring-primary-500 focus:outline-none"
          />
          <button
            type="submit"
            :disabled="loading"
            class="px-6 py-3 rounded-xl bg-primary-600 hover:bg-primary-700 text-white font-semibold shadow-xs disabled:opacity-50 transition-colors"
          >
            {{ loading ? 'Verificando...' : 'Verificar Documento' }}
          </button>
        </form>
      </div>

      <!-- Not Found Alert -->
      <div v-if="notFoundMessage" class="bg-rose-50 border border-rose-200 rounded-2xl p-6 text-rose-800 space-y-2">
        <div class="flex items-center gap-2 font-bold text-base">
          <svg class="w-5 h-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Documento No Encontrado
        </div>
        <p class="text-sm">{{ notFoundMessage }}</p>
      </div>

      <!-- Result Card -->
      <div v-if="doc" class="bg-white rounded-2xl border border-gray-200 shadow-md overflow-hidden">
        <!-- Badge Header -->
        <div class="bg-primary-900 text-white p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 mb-2">
              <svg class="w-3.5 h-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              Documento Oficial Auténtico
            </div>
            <h3 class="text-xl font-black tracking-tight">
              {{ doc.tipo_documento === 'TITULO_PROFESIONAL_TECNICO' ? 'TÍTULO PROFESIONAL TÉCNICO' : 'CERTIFICADO MODULAR OFICIAL' }}
            </h3>
            <p class="text-xs text-primary-200 mt-0.5">{{ doc.institucion }}</p>
          </div>

          <div class="text-right">
            <div class="text-xs text-primary-200">Código de Verificación</div>
            <div class="font-mono text-sm font-bold text-white tracking-widest bg-primary-800/80 px-3 py-1 rounded-lg border border-primary-700">
              {{ doc.datos_registro?.codigo_verificacion || doc.datos_modulo?.codigo_verificacion }}
            </div>
          </div>
        </div>

        <!-- Details Grid -->
        <div class="p-6 space-y-6">
          <!-- Person Information -->
          <div class="border-b pb-4">
            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Titular del Documento</h4>
            <div class="text-xl font-extrabold text-gray-900">
              {{ (doc.titulado || doc.estudiante)?.apellidos_nombres }}
            </div>
            <div class="text-sm text-gray-500 mt-0.5">
              {{ (doc.titulado || doc.estudiante)?.documento_tipo }}: {{ (doc.titulado || doc.estudiante)?.documento_numero || 'Registrado' }}
            </div>
          </div>

          <!-- Academic Program -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 border-b pb-4">
            <div>
              <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Programa de Estudios</h4>
              <div class="text-base font-bold text-primary-800">{{ doc.programa_estudio }}</div>
              <div class="text-xs text-gray-500">Nivel Formativo: Profesional Técnico (3 años)</div>
            </div>

            <div v-if="doc.tipo_documento === 'TITULO_PROFESIONAL_TECNICO'">
              <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Modalidad de Titulación</h4>
              <div class="text-sm font-semibold text-gray-800">
                {{ doc.datos_registro?.modalidad_titulacion?.replace(/_/g, ' ') }}
              </div>
            </div>

            <div v-if="doc.tipo_documento === 'CERTIFICADO_MODULAR'">
              <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Módulo Formativo</h4>
              <div class="text-sm font-semibold text-gray-800">
                Módulo {{ doc.datos_modulo?.numero_modulo }}: {{ doc.datos_modulo?.nombre_modulo }}
              </div>
            </div>
          </div>

          <!-- Registry / Academic Details -->
          <div v-if="doc.tipo_documento === 'TITULO_PROFESIONAL_TECNICO'" class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-gray-50 p-4 rounded-xl text-xs">
            <div>
              <span class="text-gray-500 block">N° Libro</span>
              <strong class="text-sm text-gray-900 font-mono">{{ doc.datos_registro?.numero_libro }}</strong>
            </div>
            <div>
              <span class="text-gray-500 block">N° Folio</span>
              <strong class="text-sm text-gray-900 font-mono">{{ doc.datos_registro?.numero_folio }}</strong>
            </div>
            <div>
              <span class="text-gray-500 block">N° Registro</span>
              <strong class="text-sm text-gray-900 font-mono">{{ doc.datos_registro?.numero_registro }}</strong>
            </div>
            <div>
              <span class="text-gray-500 block">N° Título</span>
              <strong class="text-sm text-gray-900 font-mono">{{ doc.datos_registro?.numero_titulo }}</strong>
            </div>
          </div>

          <div v-if="doc.tipo_documento === 'CERTIFICADO_MODULAR'" class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-gray-50 p-4 rounded-xl text-xs">
            <div>
              <span class="text-gray-500 block">Créditos Académicos</span>
              <strong class="text-sm text-gray-900">{{ doc.datos_modulo?.creditos }} cr.</strong>
            </div>
            <div>
              <span class="text-gray-500 block">Horas Teoría / Práctica</span>
              <strong class="text-sm text-gray-900 font-mono">{{ doc.datos_modulo?.horas_teoria }}T / {{ doc.datos_modulo?.horas_practica }}P</strong>
            </div>
            <div class="col-span-2">
              <span class="text-gray-500 block">Horas EFSRT (Prácticas Pre-prof.)</span>
              <strong class="text-sm text-emerald-700 font-bold">{{ doc.datos_modulo?.horas_efsrt }} Horas Aprobadas</strong>
            </div>
          </div>

          <!-- Resolutions and Dates -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs text-gray-600">
            <div>
              <span class="text-gray-400 block font-medium">Resolución Directoral</span>
              <strong class="text-gray-900 font-mono text-xs">
                {{ doc.datos_registro?.resolucion_directoral || doc.datos_modulo?.resolucion_directoral || 'Conforme a norma' }}
              </strong>
            </div>
            <div>
              <span class="text-gray-400 block font-medium">Fecha de Emisión</span>
              <strong class="text-gray-900">
                {{ doc.datos_registro?.fecha_emision || doc.datos_modulo?.fecha_emision }}
              </strong>
            </div>
            <div>
              <span class="text-gray-400 block font-medium">Registro MINEDU / REGISTRA</span>
              <strong class="text-primary-700 font-mono">
                {{ doc.datos_registro?.codigo_minedu || 'Homologado Institucional' }}
              </strong>
            </div>
          </div>

          <!-- Institutional Footer Note -->
          <div class="p-3 rounded-lg bg-blue-50 border border-blue-100 text-xs text-blue-900 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <span>
              {{ doc.mensaje }} Documento expedido por el IESTP "Santiago Antúnez de Mayolo" (Palián, Huancayo).
            </span>
          </div>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200 bg-white py-4 text-center text-xs text-gray-500">
      IESTP "Santiago Antúnez de Mayolo" &copy; {{ new Date().getFullYear() }} — Sistema Integrado de Gestión Institucional Modux
    </footer>
  </div>
</template>
