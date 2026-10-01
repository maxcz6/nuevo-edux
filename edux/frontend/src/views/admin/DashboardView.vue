<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { dashboardApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import PageHeader from '@/components/ui/PageHeader.vue'
import LoadingState from '@/components/ui/LoadingState.vue'

const authStore = useAuthStore()

interface DashboardStats {
  total_usuarios: number
  total_docentes: number
  total_estudiantes: number
  grupos_activos: number
  periodo_activo: { id: number; nombre: string; codigo: string } | null
}

const stats = ref<DashboardStats | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

// Prefijo de ruta base según rol activo
const basePath = computed(() => {
  if (authStore.hasRole('SUPERADMIN') || authStore.hasRole('ADMINISTRADOR')) return '/admin'
  if (authStore.hasRole('COORDINADOR')) return '/coordinacion'
  if (authStore.hasRole('SECRETARIA'))  return '/secretaria'
  if (authStore.hasRole('CAJA'))        return '/caja'
  if (authStore.hasRole('MESA_DE_PARTES')) return '/mesa-partes'
  return '/admin'
})

// Accesos rápidos filtrados por rol del usuario autenticado
const quickLinks = computed(() => {
  const p = basePath.value

  const all = [
    { to: `${p}/usuarios`,    label: 'Usuarios',          description: 'Cuentas, roles y acceso',   roles: ['SUPERADMIN', 'ADMINISTRADOR'] },
    { to: `${p}/docentes`,    label: 'Docentes',          description: 'Planta docente',             roles: ['SUPERADMIN', 'ADMINISTRADOR', 'COORDINADOR', 'SECRETARIA'] },
    { to: `${p}/estudiantes`, label: 'Estudiantes',       description: 'Expedientes académicos',     roles: ['SUPERADMIN', 'ADMINISTRADOR', 'COORDINADOR', 'SECRETARIA', 'CAJA', 'MESA_DE_PARTES'] },
    { to: `${p}/grupos`,      label: 'Grupos de Curso',   description: 'Oferta del período',         roles: ['SUPERADMIN', 'ADMINISTRADOR', 'COORDINADOR'] },
    { to: `${p}/periodos`,    label: 'Períodos',          description: 'Calendario académico',       roles: ['SUPERADMIN', 'ADMINISTRADOR', 'COORDINADOR'] },
    { to: `${p}/cursos`,      label: 'Cursos',            description: 'Catálogo curricular',        roles: ['SUPERADMIN', 'ADMINISTRADOR', 'COORDINADOR'] },
    { to: `${p}/matriculas`,  label: 'Matrículas',        description: 'Inscripción estudiantil',    roles: ['SUPERADMIN', 'ADMINISTRADOR', 'COORDINADOR', 'SECRETARIA', 'CAJA'] },
    { to: `${p}/caja`,        label: 'Caja y Cobranza',   description: 'Pagos y operaciones',        roles: ['SUPERADMIN', 'ADMINISTRADOR', 'CAJA'] },
    { to: `${p}/tramites`,    label: 'Trámites',          description: 'Solicitudes académicas',     roles: ['SUPERADMIN', 'ADMINISTRADOR', 'COORDINADOR', 'SECRETARIA', 'MESA_DE_PARTES'] },
    { to: `${p}/admision`,    label: 'Admisión',          description: 'Convocatorias e ingresantes',roles: ['SUPERADMIN', 'ADMINISTRADOR', 'COORDINADOR'] },
    { to: `${p}/programas`,   label: 'Programas',         description: 'Oferta académica',           roles: ['SUPERADMIN', 'ADMINISTRADOR', 'COORDINADOR', 'SECRETARIA'] },
  ]

  const userRoles = authStore.userRoles
  return all.filter(link => link.roles.some(r => userRoles.includes(r)))
})

onMounted(async () => {
  try {
    const res = await dashboardApi.stats()
    stats.value = res.data.data as unknown as DashboardStats
  } catch {
    error.value = 'Error al cargar las estadísticas.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page-shell space-y-6">
    <PageHeader eyebrow="Resumen institucional" title="Dashboard" description="Una vista rápida del estado académico y los accesos más utilizados." />

    <LoadingState v-if="loading" label="Preparando el resumen institucional…" />

    <div v-else-if="error" class="rounded-2xl bg-rose-50 border border-rose-200 p-4 text-rose-800 text-sm" role="alert">
      {{ error }}
    </div>

    <template v-else-if="stats">
      <!-- Hero Banner ambiental EducaCampus con período activo integrado -->
      <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-primary-950 to-slate-900 text-white p-6 sm:p-8 shadow-lg border border-slate-800">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm border border-white/15 text-xs font-medium text-slate-200 mb-3">
              <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
              <span>Portal de Gestión Institucional</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold font-headline tracking-tight text-white">
              Bienvenido, {{ authStore.user?.nombres }}
            </h2>
            <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-xl">
              Monitorea los indicadores clave del semestre y gestiona personas, cursos y expedientes.
            </p>
          </div>

          <div
            v-if="stats.periodo_activo"
            class="shrink-0 bg-white/10 backdrop-blur-md border border-white/20 rounded-xl p-3 sm:px-4 sm:py-3 text-left"
            role="status"
            aria-label="Período académico activo"
          >
            <p class="text-[11px] uppercase tracking-wider text-slate-300 font-semibold">Período activo</p>
            <p class="text-sm font-bold text-white mt-0.5 flex items-center gap-2">
              <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
              {{ stats.periodo_activo.nombre }} <span class="text-xs font-mono font-normal text-slate-300">({{ stats.periodo_activo.codigo }})</span>
            </p>
          </div>
          <div v-else class="shrink-0 bg-amber-500/20 border border-amber-400/30 rounded-xl p-3 text-xs text-amber-200" role="status">
            No hay un período académico activo configurado.
          </div>
        </div>

        <!-- Decoración ambiental sutil -->
        <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-primary-600/20 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-indigo-600/20 blur-3xl" aria-hidden="true"></div>
      </div>

      <!-- Stats ribbon: 4 cards estilo EducaCampus -->
      <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <!-- Usuarios -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Usuarios</p>
            <div class="h-9 w-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
              <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
          </div>
          <p class="mt-2 text-3xl font-bold font-headline text-slate-900 tracking-tight" aria-label="Total usuarios: {{ stats.total_usuarios }}">
            {{ stats.total_usuarios }}
          </p>
          <p class="mt-1 text-[11px] text-slate-400 font-medium">Cuentas registradas</p>
        </div>

        <!-- Docentes -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Docentes Activos</p>
            <div class="h-9 w-9 rounded-xl bg-primary-50 text-primary-700 flex items-center justify-center">
              <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
              </svg>
            </div>
          </div>
          <p class="mt-2 text-3xl font-bold font-headline text-primary-700 tracking-tight">{{ stats.total_docentes }}</p>
          <p class="mt-1 text-[11px] text-slate-400 font-medium">Planta docente habilitada</p>
        </div>

        <!-- Estudiantes -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Estudiantes Activos</p>
            <div class="h-9 w-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
              <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
              </svg>
            </div>
          </div>
          <p class="mt-2 text-3xl font-bold font-headline text-emerald-700 tracking-tight">{{ stats.total_estudiantes }}</p>
          <p class="mt-1 text-[11px] text-slate-400 font-medium">Alumnos con matrícula</p>
        </div>

        <!-- Grupos -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Grupos Activos</p>
            <div class="h-9 w-9 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center">
              <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
          </div>
          <p class="mt-2 text-3xl font-bold font-headline text-indigo-700 tracking-tight">{{ stats.grupos_activos }}</p>
          <p class="mt-1 text-[11px] text-slate-400 font-medium">Cursos en dictado</p>
        </div>
      </div>

      <!-- Accesos rápidos filtrados por rol -->
      <section class="mt-8 pt-2" aria-labelledby="accesos-rapidos-heading">
        <div class="mb-4">
          <h2 id="accesos-rapidos-heading" class="text-base font-bold font-headline text-slate-900 tracking-tight">Accesos rápidos</h2>
          <p class="text-xs text-slate-500">Continúa con las tareas habituales de gestión y consulta.</p>
        </div>
        <div v-if="quickLinks.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <RouterLink
            v-for="link in quickLinks"
            :key="link.to"
            :to="link.to"
            class="group rounded-2xl border border-slate-200/80 bg-white p-4.5 hover:border-primary-200 hover:shadow-md cursor-pointer transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-bold text-slate-900 group-hover:text-primary-700 transition-colors truncate">{{ link.label }}</p>
                <p class="mt-0.5 text-xs text-slate-500 truncate">{{ link.description }}</p>
              </div>
              <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-50 group-hover:bg-primary-50 text-slate-400 group-hover:text-primary-600 transition-colors" aria-hidden="true">
                <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
              </span>
            </div>
          </RouterLink>
        </div>
        <div v-else class="text-xs text-slate-400 italic">
          Sin accesos rápidos disponibles para tu rol.
        </div>
      </section>
    </template>
  </div>
</template>
